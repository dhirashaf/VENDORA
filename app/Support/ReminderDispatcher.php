<?php

namespace App\Support;

use App\Enums\AssignmentStatus;
use App\Enums\Channel;
use App\Enums\CycleStatus;
use App\Enums\ReminderTrigger;
use App\Enums\SendStatus;
use App\Mail\ReminderMessage;
use App\Models\EvaluationAssignment;
use App\Models\EvaluationCycle;
use App\Models\ReminderLog;
use App\Models\ReminderRule;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Pengiriman form, reminder terjadwal, reminder lanjutan, dan eskalasi
 * berdasarkan aturan yang tersimpan sebagai data (FR-06, FR-07, FR-08, NFR-03).
 */
class ReminderDispatcher
{
    public const TEMPLATE_FORM_ASSIGNED = 'FORM_ASSIGNED';

    public const TEMPLATE_MANUAL = 'REMINDER_MANUAL';

    /**
     * Proses harian: tandai penugasan lewat tenggat lalu kirim reminder sesuai aturan aktif.
     *
     * @return array{overdue: int, sent: int, failed: int, skipped: bool}
     */
    public function run(?Carbon $today = null): array
    {
        $today = ($today ?? Carbon::today())->copy()->startOfDay();
        $summary = ['overdue' => 0, 'sent' => 0, 'failed' => 0, 'skipped' => false];

        $pending = EvaluationAssignment::query()
            ->with(['assessor', 'cycle.vendor'])
            ->where('status', '!=', AssignmentStatus::Submitted->value)
            ->whereHas('cycle', fn ($query) => $query->whereIn('status', [CycleStatus::Open->value, CycleStatus::InProgress->value]))
            ->get();

        foreach ($pending as $assignment) {
            if ($assignment->cycle->due_date->lt($today) && $assignment->status !== AssignmentStatus::Overdue) {
                $assignment->update(['status' => AssignmentStatus::Overdue]);
                $summary['overdue']++;
            }
        }

        if (! Settings::boolean(Settings::REMINDERS_ENABLED)) {
            $summary['skipped'] = true;

            return $summary;
        }

        $rules = ReminderRule::query()->with(['template', 'escalateToRole'])->where('is_active', true)->get();

        foreach ($pending as $assignment) {
            foreach ($rules as $rule) {
                if (! $rule->isDueOn($assignment->cycle->due_date, $today)) {
                    continue;
                }

                foreach ($this->recipientsFor($rule, $assignment) as $recipient) {
                    if ($this->alreadySent($assignment, $rule, $recipient, $today)) {
                        continue;
                    }

                    foreach ($this->deliver($rule, $assignment, $recipient, $today) as $log) {
                        $summary[$log->status === SendStatus::Sent ? 'sent' : 'failed']++;
                    }
                }
            }
        }

        return $summary;
    }

    /**
     * Buka siklus terjadwal yang tanggal mulainya sudah tiba dan kirim formnya.
     */
    public function openScheduledCycles(EvaluationWorkflow $workflow, ?Carbon $today = null): int
    {
        $cycles = EvaluationCycle::query()
            ->where('status', CycleStatus::Scheduled->value)
            ->whereDate('start_date', '<=', ($today ?? Carbon::today())->toDateString())
            ->get();

        $cycles->each(fn (EvaluationCycle $cycle) => $workflow->sendForms($cycle));

        return $cycles->count();
    }

    public function sendFormAssigned(EvaluationAssignment $assignment): void
    {
        $rule = $this->ruleForTemplate(self::TEMPLATE_FORM_ASSIGNED);

        if ($rule) {
            $this->deliver($rule, $assignment, $assignment->assessor, Carbon::today());
        }
    }

    /**
     * Tombol "Ingatkan" oleh Vendor Management.
     */
    public function sendManual(EvaluationAssignment $assignment, User $actor): void
    {
        $rule = $this->ruleForTemplate(self::TEMPLATE_MANUAL);

        if ($rule) {
            $this->deliver($rule, $assignment, $assignment->assessor, Carbon::today());
            AuditLogger::record(AuditLogger::REMINDER, $assignment, null, ['recipient' => $assignment->assessor->full_name], $actor->id);
        }
    }

    /**
     * Kirim pesan melalui email dan notifikasi dalam aplikasi, masing-masing tercatat di reminder_logs.
     *
     * @return list<ReminderLog>
     */
    private function deliver(ReminderRule $rule, EvaluationAssignment $assignment, User $recipient, Carbon $today): array
    {
        $isEscalation = $rule->trigger_type === ReminderTrigger::Escalation;
        $message = $rule->template->render($this->replacements($assignment, $recipient, $today));
        $logs = [];

        foreach ([Channel::Email, Channel::InApp] as $channel) {
            $log = ReminderLog::query()->create([
                'assignment_id' => $assignment->id,
                'rule_id' => $rule->id,
                'recipient_id' => $recipient->id,
                'channel' => $channel,
                'scheduled_at' => $today->copy()->setTimeFrom(now()),
                'status' => SendStatus::Scheduled,
                'is_escalation' => $isEscalation,
            ]);

            try {
                if ($channel === Channel::Email) {
                    Mail::to($recipient->work_email)->send(new ReminderMessage($message['subject'], $message['body']));
                }

                $log->update(['status' => SendStatus::Sent, 'sent_at' => now()]);
            } catch (Throwable $exception) {
                report($exception);
                $log->update(['status' => SendStatus::Failed, 'error_message' => $exception->getMessage()]);
            }

            $logs[] = $log;
        }

        return $logs;
    }

    /**
     * @return Collection<int, User>
     */
    private function recipientsFor(ReminderRule $rule, EvaluationAssignment $assignment): Collection
    {
        if ($rule->trigger_type === ReminderTrigger::Escalation && $rule->escalateToRole) {
            return User::query()->active()->where('role_id', $rule->escalate_to_role_id)->get();
        }

        return collect([$assignment->assessor]);
    }

    private function alreadySent(EvaluationAssignment $assignment, ReminderRule $rule, User $recipient, Carbon $today): bool
    {
        return ReminderLog::query()
            ->where('assignment_id', $assignment->id)
            ->where('rule_id', $rule->id)
            ->where('recipient_id', $recipient->id)
            ->whereDate('scheduled_at', $today->toDateString())
            ->exists();
    }

    private function ruleForTemplate(string $templateCode): ?ReminderRule
    {
        return ReminderRule::query()
            ->with('template')
            ->whereHas('template', fn ($query) => $query->where('code', $templateCode))
            ->first();
    }

    /**
     * @return array<string, string>
     */
    private function replacements(EvaluationAssignment $assignment, User $recipient, Carbon $today): array
    {
        $cycle = $assignment->cycle;
        $daysLeft = (int) $today->diffInDays($cycle->due_date, false);

        return [
            'recipient_name' => $recipient->full_name,
            'assessor_name' => $assignment->assessor->full_name,
            'department' => $assignment->department->label(),
            'vendor_name' => $cycle->vendor->name,
            'period' => $cycle->period_label,
            'cycle_code' => $cycle->cycle_code,
            'due_date' => $cycle->due_date->translatedFormat('d F Y'),
            'days_left' => (string) max(0, $daysLeft),
            'days_overdue' => (string) max(0, -$daysLeft),
            'progress' => number_format((float) $assignment->progress_pct, 0).'%',
            'form_url' => route('assessments.edit', $assignment),
        ];
    }

    /**
     * Placeholder yang dapat dipakai pada template reminder.
     *
     * @return list<string>
     */
    public static function placeholders(): array
    {
        return ['recipient_name', 'assessor_name', 'department', 'vendor_name', 'period', 'cycle_code', 'due_date', 'days_left', 'days_overdue', 'progress', 'form_url'];
    }

    /**
     * Template yang dipakai sistem (bukan aturan terjadwal) dan tidak boleh dihapus.
     *
     * @return list<string>
     */
    public static function systemTemplates(): array
    {
        return [self::TEMPLATE_FORM_ASSIGNED, self::TEMPLATE_MANUAL];
    }
}
