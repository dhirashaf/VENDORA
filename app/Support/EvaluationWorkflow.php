<?php

namespace App\Support;

use App\Enums\AssignmentStatus;
use App\Enums\CycleStatus;
use App\Enums\CycleType;
use App\Enums\Department;
use App\Enums\Recommendation;
use App\Models\AssessmentItem;
use App\Models\Evaluation;
use App\Models\EvaluationAssignment;
use App\Models\EvaluationCycle;
use App\Models\FormTemplate;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Alur siklus evaluasi: penjadwalan, pengiriman form, pengisian, kompilasi, dan approval.
 */
class EvaluationWorkflow
{
    public function __construct(
        private ReminderDispatcher $reminders,
        private FollowUpWorkflow $followUps,
    ) {}

    /**
     * Fungsi penilai yang dibutuhkan form (dari kolom assessor_department).
     *
     * @return Collection<int, Department>
     */
    public function requiredDepartments(FormTemplate $formTemplate): Collection
    {
        return $formTemplate->items()
            ->where('assessment_items.is_active', true)
            ->get()
            ->pluck('assessor_department')
            ->unique()
            ->sortBy(fn (Department $department): int => array_search($department, Department::cases(), true))
            ->values();
    }

    /**
     * @param  array{cycle_type: string, period_label: string, start_date: string, sla_days?: int|null, adhoc_reason?: string|null}  $data
     * @param  array<string, int>  $assessorIds  departemen => id pengguna penilai
     */
    public function createCycle(Vendor $vendor, array $data, array $assessorIds, User $creator, bool $sendNow = true): EvaluationCycle
    {
        $formTemplate = FormTemplate::active() ?? throw ValidationException::withMessages([
            'vendor_id' => 'Belum ada form evaluasi aktif.',
        ]);

        $departments = $this->requiredDepartments($formTemplate);
        $missing = $departments->reject(fn (Department $department): bool => isset($assessorIds[$department->value]));

        if ($missing->isNotEmpty()) {
            throw ValidationException::withMessages([
                'assessors' => 'Penilai belum ditetapkan untuk fungsi: '.$missing->map->label()->join(', ').'.',
            ]);
        }

        $cycle = DB::transaction(function () use ($vendor, $data, $departments, $assessorIds, $creator, $formTemplate): EvaluationCycle {
            $startDate = Carbon::parse($data['start_date'])->startOfDay();
            $slaDays = (int) ($data['sla_days'] ?? Settings::integer(Settings::DEFAULT_SLA_DAYS));

            $cycle = EvaluationCycle::query()->create([
                'cycle_code' => $this->nextCycleCode($data['period_label']),
                'vendor_id' => $vendor->id,
                'form_template_id' => $formTemplate->id,
                'period_label' => $data['period_label'],
                'cycle_type' => $data['cycle_type'],
                'adhoc_reason' => $data['cycle_type'] === CycleType::AdHoc->value ? ($data['adhoc_reason'] ?? null) : null,
                'start_date' => $startDate,
                'sla_days' => $slaDays,
                'due_date' => $startDate->copy()->addDays($slaDays),
                'status' => CycleStatus::Scheduled,
                'created_by' => $creator->id,
            ]);

            $cycle->evaluation()->create([
                'is_complete' => false,
                'recommendation' => Recommendation::CannotBeCalculated,
            ]);

            foreach ($departments as $department) {
                $cycle->assignments()->create([
                    'assessor_id' => $assessorIds[$department->value],
                    'department' => $department,
                    'status' => AssignmentStatus::NotStarted,
                    'progress_pct' => 0,
                ]);
            }

            AuditLogger::record(AuditLogger::CREATE, $cycle, null, [
                'vendor' => $vendor->name,
                'period_label' => $cycle->period_label,
                'cycle_type' => $cycle->cycle_type->value,
                'due_date' => $cycle->due_date->toDateString(),
            ], $creator->id);

            return $cycle;
        });

        if ($sendNow && $cycle->start_date->lte(Carbon::today())) {
            $this->sendForms($cycle, $creator);
        }

        return $cycle->refresh();
    }

    /**
     * Kirim form ke seluruh penilai yang ditugaskan (FR-06).
     */
    public function sendForms(EvaluationCycle $cycle, ?User $actor = null): void
    {
        if ($cycle->status !== CycleStatus::Scheduled) {
            throw ValidationException::withMessages(['cycle' => 'Form siklus ini sudah dikirim.']);
        }

        $cycle->update(['status' => CycleStatus::Open]);

        foreach ($cycle->assignments()->with(['assessor', 'cycle.vendor'])->get() as $assignment) {
            $assignment->update(['sent_at' => now()]);
            $this->reminders->sendFormAssigned($assignment);
        }

        AuditLogger::record(AuditLogger::STATUS_CHANGE, $cycle, ['status' => CycleStatus::Scheduled->value], ['status' => CycleStatus::Open->value], $actor?->id);
    }

    /**
     * Simpan skor penilai sebagai draf atau submit (FR-12, FR-14). Submit ditolak bila ada item kosong (NFR-04).
     *
     * @param  array<int|string, array{score?: int|string|null, notes?: string|null, evidence_url?: string|null}>  $input
     */
    public function saveScores(EvaluationAssignment $assignment, array $input, bool $submit, User $actor): EvaluationAssignment
    {
        $cycle = $assignment->cycle;

        if (! $cycle->isCollecting() || $assignment->isSubmitted()) {
            throw ValidationException::withMessages(['scores' => 'Form ini sudah terkunci dan tidak dapat diubah.']);
        }

        $items = $this->itemsFor($assignment);
        $evaluation = $cycle->evaluation;

        DB::transaction(function () use ($assignment, $input, $items, $evaluation, $actor): void {
            $existing = $assignment->scores()->get()->keyBy('item_id');

            foreach ($items as $item) {
                $row = $input[$item->id] ?? [];
                $score = isset($row['score']) && $row['score'] !== '' ? (int) $row['score'] : null;
                $current = $existing->get($item->id);

                if ($score === null) {
                    $current?->delete();

                    continue;
                }

                $attributes = [
                    'assignment_id' => $assignment->id,
                    'score' => $score,
                    'weight_pct' => $item->weight_pct,
                    'weighted_score' => ScoreCalculator::weightedScore($score, (float) $item->weight_pct),
                    'notes' => $row['notes'] ?? null,
                    'evidence_url' => $row['evidence_url'] ?? null,
                    'scored_at' => now(),
                ];

                if ($current) {
                    $current->fill($attributes);

                    if ($current->isDirty(['score', 'notes', 'evidence_url'])) {
                        $previousScore = $current->getOriginal('score');
                        $current->save();
                        AuditLogger::record(AuditLogger::UPDATE, $current, ['score' => $previousScore], ['score' => $score, 'item_no' => $item->item_no], $actor->id);
                    }
                } else {
                    $created = $evaluation->scores()->create($attributes + ['item_id' => $item->id]);
                    AuditLogger::record(AuditLogger::CREATE, $created, null, ['score' => $score, 'item_no' => $item->item_no], $actor->id);
                }
            }

            $filled = $assignment->scores()->count();
            $assignment->update([
                'progress_pct' => $items->isEmpty() ? 100 : round($filled / $items->count() * 100, 2),
                'last_saved_at' => now(),
                'status' => $assignment->status === AssignmentStatus::Overdue ? AssignmentStatus::Overdue : AssignmentStatus::Draft,
            ]);
        });

        if ($assignment->cycle->status === CycleStatus::Open) {
            $assignment->cycle->update(['status' => CycleStatus::InProgress]);
        }

        if ($submit) {
            $this->submitAssignment($assignment, $items, $actor);
        }

        $this->refreshEvaluation($cycle->refresh());

        return $assignment->refresh();
    }

    /**
     * Item penilaian yang menjadi tanggung jawab fungsi penilai pada penugasan ini.
     *
     * @return Collection<int, AssessmentItem>
     */
    public function itemsFor(EvaluationAssignment $assignment): Collection
    {
        return $assignment->cycle->formTemplate->items()
            ->with('category')
            ->where('assessment_items.is_active', true)
            ->where('assessor_department', $assignment->department->value)
            ->orderBy('assessment_items.sort_order')
            ->get();
    }

    /**
     * @param  Collection<int, AssessmentItem>  $items
     */
    private function submitAssignment(EvaluationAssignment $assignment, Collection $items, User $actor): void
    {
        $scoredItemIds = $assignment->scores()->pluck('item_id');
        $emptyItems = $items->reject(fn (AssessmentItem $item): bool => $scoredItemIds->contains($item->id));

        if ($emptyItems->isNotEmpty()) {
            $categories = $emptyItems->map(fn (AssessmentItem $item): string => $item->category->code)->unique()->sort()->join(', ');

            throw ValidationException::withMessages([
                'scores' => "Field wajib belum lengkap. Kategori {$categories} belum lengkap. Submit ditolak: {$emptyItems->count()} item masih kosong.",
            ])->errorBag('default');
        }

        $assignment->update([
            'status' => AssignmentStatus::Submitted,
            'submitted_at' => now(),
            'progress_pct' => 100,
        ]);

        AuditLogger::record(AuditLogger::SUBMIT, $assignment, null, [
            'department' => $assignment->department->value,
            'items' => $items->count(),
        ], $actor->id);
    }

    /**
     * Buka kembali form yang sudah disubmit untuk revisi, selama hasil belum diajukan/disetujui.
     */
    public function reopenAssignment(EvaluationAssignment $assignment, User $actor): void
    {
        $cycle = $assignment->cycle;

        if (! in_array($cycle->status, [CycleStatus::Open, CycleStatus::InProgress, CycleStatus::Compiled], true)) {
            throw ValidationException::withMessages(['assignment' => 'Form tidak dapat dibuka kembali setelah diajukan untuk approval.']);
        }

        $assignment->update([
            'status' => $cycle->due_date->lt(Carbon::today()) ? AssignmentStatus::Overdue : AssignmentStatus::Draft,
            'submitted_at' => null,
        ]);
        $cycle->update(['status' => CycleStatus::InProgress]);
        $this->refreshEvaluation($cycle->refresh());

        AuditLogger::record(AuditLogger::STATUS_CHANGE, $assignment, ['status' => AssignmentStatus::Submitted->value], ['status' => $assignment->status->value], $actor->id);
    }

    /**
     * Rekap otomatis (FR-15, FR-16). Selama ada penilai/item kosong, status "belum dapat dihitung".
     */
    public function refreshEvaluation(EvaluationCycle $cycle): Evaluation
    {
        $evaluation = $cycle->evaluation()->with('scores')->firstOrFail();
        $assignments = $cycle->assignments()->get();
        $itemCount = $cycle->formTemplate->items()->where('assessment_items.is_active', true)->count();

        $isComplete = $assignments->isNotEmpty()
            && $assignments->every(fn (EvaluationAssignment $assignment): bool => $assignment->isSubmitted())
            && $evaluation->scores->count() === $itemCount;

        if ($isComplete) {
            $total = ScoreCalculator::total($evaluation->scores);
            $evaluation->update([
                'is_complete' => true,
                'total_weighted_score' => $total,
                'achievement_pct' => ScoreCalculator::achievementPct($total),
                'recommendation' => ScoreCalculator::recommendationFor($total),
            ]);

            if ($cycle->isCollecting()) {
                $cycle->update(['status' => CycleStatus::Compiled]);
            }
        } else {
            $evaluation->update([
                'is_complete' => false,
                'total_weighted_score' => null,
                'achievement_pct' => null,
                'recommendation' => Recommendation::CannotBeCalculated,
            ]);
        }

        return $evaluation;
    }

    /**
     * Vendor Management Specialist mengompilasi dan mengajukan hasil untuk approval.
     * Dependency (FR-09): bila aktif, pengajuan ditahan selama masih ada penilai yang belum submit.
     */
    public function submitForApproval(EvaluationCycle $cycle, User $actor): void
    {
        $evaluation = $this->refreshEvaluation($cycle);
        $allowedStatuses = Settings::boolean(Settings::ENFORCE_DEPENDENCY)
            ? [CycleStatus::Compiled]
            : [CycleStatus::Compiled, CycleStatus::Open, CycleStatus::InProgress];

        if (! in_array($cycle->status, $allowedStatuses, true)) {
            throw ValidationException::withMessages([
                'cycle' => 'Evaluasi belum dapat diajukan: masih ada penilai yang belum submit (dependency aktif).',
            ]);
        }

        $evaluation->update(['compiled_by' => $actor->id, 'compiled_at' => now()]);
        $this->transition($cycle, CycleStatus::PendingApproval, $actor);
    }

    /**
     * PVM Manager menyetujui hasil. Skor di bawah standar otomatis membuka tindak lanjut (FR-21).
     */
    public function approve(EvaluationCycle $cycle, User $actor, ?string $notes = null): void
    {
        if ($cycle->status !== CycleStatus::PendingApproval) {
            throw ValidationException::withMessages(['cycle' => 'Hanya evaluasi yang menunggu approval yang dapat disetujui.']);
        }

        $evaluation = $cycle->evaluation;
        $evaluation->update([
            'approved_by' => $actor->id,
            'approved_at' => now(),
            'approval_notes' => $notes,
        ]);
        $cycle->update(['completed_at' => now()]);
        $this->transition($cycle, CycleStatus::Approved, $actor, AuditLogger::APPROVE);

        if ($evaluation->recommendation === Recommendation::NotRecommended) {
            $this->followUps->openFromEvaluation($evaluation, $actor);
        }
    }

    /**
     * PVM Manager mengembalikan hasil ke Vendor Management untuk diperbaiki.
     */
    public function returnForRevision(EvaluationCycle $cycle, User $actor, string $notes): void
    {
        if ($cycle->status !== CycleStatus::PendingApproval) {
            throw ValidationException::withMessages(['cycle' => 'Hanya evaluasi yang menunggu approval yang dapat dikembalikan.']);
        }

        $cycle->evaluation->update(['approval_notes' => $notes]);
        $target = $cycle->evaluation->is_complete ? CycleStatus::Compiled : CycleStatus::InProgress;
        $this->transition($cycle, $target, $actor);
    }

    public function cancel(EvaluationCycle $cycle, User $actor, string $reason): void
    {
        if (in_array($cycle->status, [CycleStatus::Approved, CycleStatus::Closed, CycleStatus::Cancelled], true)) {
            throw ValidationException::withMessages(['cycle' => 'Siklus yang sudah selesai tidak dapat dibatalkan.']);
        }

        $cycle->evaluation?->update(['approval_notes' => 'Dibatalkan: '.$reason]);
        $this->transition($cycle, CycleStatus::Cancelled, $actor);
    }

    private function transition(EvaluationCycle $cycle, CycleStatus $to, User $actor, string $action = AuditLogger::STATUS_CHANGE): void
    {
        $from = $cycle->status;
        $cycle->update(['status' => $to]);

        AuditLogger::record($action, $cycle, ['status' => $from->value], ['status' => $to->value], $actor->id);
    }

    private function nextCycleCode(string $periodLabel): string
    {
        $prefix = 'EV-'.strtoupper($periodLabel).'-';
        $last = EvaluationCycle::query()->where('cycle_code', 'like', $prefix.'%')->orderByDesc('cycle_code')->value('cycle_code');
        $sequence = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);
    }
}
