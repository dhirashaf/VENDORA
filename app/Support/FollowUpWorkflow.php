<?php

namespace App\Support;

use App\Enums\CycleStatus;
use App\Enums\Decision;
use App\Enums\FollowupStatus;
use App\Enums\NonconformanceCategory;
use App\Enums\RoleCode;
use App\Enums\VendorStatus;
use App\Models\Evaluation;
use App\Models\FollowUp;
use App\Models\FollowupAttachment;
use App\Models\FollowupMeeting;
use App\Models\FormTemplate;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Alur tindak lanjut pascaevaluasi sesuai SOP Vendor Evaluation & Development
 * dan struktur Deviation & Corrective Action Form (FR-18 s.d. FR-21, ISO 9001 kl. 10.2).
 */
class FollowUpWorkflow
{
    /**
     * Buka kasus tindak lanjut otomatis dari evaluasi yang disetujui dengan skor di bawah standar.
     */
    public function openFromEvaluation(Evaluation $evaluation, User $actor): FollowUp
    {
        $existing = $evaluation->followUps()->first();

        if ($existing) {
            return $existing;
        }

        $cycle = $evaluation->cycle()->with('vendor')->firstOrFail();
        $pic = $evaluation->compiler
            ?? User::query()->active()->withRole(RoleCode::VmSpecialist)->orderBy('id')->first()
            ?? $actor;

        return DB::transaction(function () use ($evaluation, $cycle, $pic, $actor): FollowUp {
            $followUp = FollowUp::query()->create([
                'followup_no' => $this->nextNumber(),
                'evaluation_id' => $evaluation->id,
                'vendor_id' => $cycle->vendor_id,
                'status' => FollowupStatus::Open,
                'pic_user_id' => $pic->id,
                'due_date' => Carbon::today()->addDays(Settings::integer(Settings::FOLLOWUP_DUE_DAYS)),
                'nc_category' => $this->weakestCategory($evaluation),
                'nc_description' => sprintf(
                    'Skor evaluasi %s periode %s = %s (di bawah standar %s).',
                    $cycle->vendor->name,
                    $cycle->period_label,
                    number_format((float) $evaluation->total_weighted_score, 2, ',', '.'),
                    number_format(Settings::decimal(Settings::RECOMMENDED_WITH_NOTES_MIN_SCORE), 2, ',', '.'),
                ),
                'opened_at' => now(),
            ]);

            $this->recordHistory($followUp, null, FollowupStatus::Open, $actor, 'Kasus dibuka otomatis dari evaluasi '.$cycle->cycle_code);
            AuditLogger::record(AuditLogger::CREATE, $followUp, null, ['followup_no' => $followUp->followup_no, 'evaluation_id' => $evaluation->id], $actor->id);

            return $followUp;
        });
    }

    /**
     * Perbarui isian Deviation & Corrective Action Form (FR-18).
     *
     * @param  array<string, mixed>  $data
     */
    public function updateForm(FollowUp $followUp, array $data, User $actor): void
    {
        $this->ensureEditable($followUp);

        $followUp->fill($data)->save();
        AuditLogger::recordChanges($followUp);
    }

    /**
     * Syarat isian sebelum kasus dapat maju ke tahap berikutnya.
     *
     * @return list<string>
     */
    public function missingRequirements(FollowUp $followUp): array
    {
        return match ($followUp->status) {
            FollowupStatus::EthicsReview => $followUp->ethicsReviews()->exists() ? [] : ['Hasil review kode etik / due diligence'],
            FollowupStatus::MeetingInvited => $followUp->meetings()->exists() ? [] : ['Jadwal undangan meeting klarifikasi'],
            FollowupStatus::Clarification => array_values(array_filter([
                $followUp->nc_category ? null : 'Kategori non-conformance',
                filled($followUp->nc_description) ? null : 'Deskripsi ketidaksesuaian',
                filled($followUp->root_cause) ? null : 'Root cause',
                $followUp->meetings()->whereNotNull('held_at')->exists() ? null : 'Notulen meeting klarifikasi (tandai meeting telah dilaksanakan)',
            ])),
            FollowupStatus::CorrectiveActionSubmitted => array_values(array_filter([
                filled($followUp->requested_corrective_action) ? null : 'Requested corrective action',
                filled($followUp->vendor_corrective_action) ? null : 'Corrective action dari vendor',
                $followUp->target_completion_date ? null : 'Target completion date',
            ])),
            default => [],
        };
    }

    /**
     * Majukan status ke tahap berikutnya sesuai alur SOP (FR-19).
     */
    public function advance(FollowUp $followUp, User $actor, ?string $notes = null): void
    {
        $this->ensureEditable($followUp);

        if ($followUp->status === FollowupStatus::Monitoring) {
            throw ValidationException::withMessages(['status' => 'Tahap monitoring diakhiri dengan mencatat keputusan akhir.']);
        }

        $missing = $this->missingRequirements($followUp);

        if ($missing !== []) {
            throw ValidationException::withMessages(['status' => 'Belum dapat lanjut. Lengkapi: '.implode('; ', $missing).'.']);
        }

        $this->changeStatus($followUp, $followUp->status->next(), $actor, $notes);
    }

    /**
     * @param  array{fraud_indication?: bool, due_diligence_required?: bool, findings?: string|null}  $data
     */
    public function recordEthicsReview(FollowUp $followUp, array $data, User $actor): void
    {
        $this->ensureEditable($followUp);

        $review = $followUp->ethicsReviews()->create([
            'reviewed_by' => $actor->id,
            'fraud_indication' => (bool) ($data['fraud_indication'] ?? false),
            'due_diligence_required' => (bool) ($data['due_diligence_required'] ?? false),
            'findings' => $data['findings'] ?? null,
            'reviewed_at' => now(),
        ]);

        AuditLogger::record(AuditLogger::CREATE, $review, null, $review->only(['fraud_indication', 'due_diligence_required']), $actor->id);
    }

    /**
     * @param  array{scheduled_at: string, location_or_link?: string|null}  $data
     */
    public function scheduleMeeting(FollowUp $followUp, array $data, User $actor): FollowupMeeting
    {
        $this->ensureEditable($followUp);

        $meeting = $followUp->meetings()->create([
            'scheduled_at' => $data['scheduled_at'],
            'location_or_link' => $data['location_or_link'] ?? null,
            'organized_by' => $actor->id,
        ]);

        AuditLogger::record(AuditLogger::CREATE, $meeting, null, $meeting->only(['scheduled_at', 'location_or_link']), $actor->id);

        return $meeting;
    }

    /**
     * @param  array{minutes: string, held_at: string}  $data
     */
    public function recordMinutes(FollowupMeeting $meeting, array $data, User $actor): void
    {
        $this->ensureEditable($meeting->followUp);

        $meeting->update(['minutes' => $data['minutes'], 'held_at' => $data['held_at']]);
        AuditLogger::recordChanges($meeting);
    }

    public function addAttachment(FollowUp $followUp, UploadedFile $file, User $actor): FollowupAttachment
    {
        $this->ensureEditable($followUp);

        $attachment = $followUp->attachments()->create([
            'file_name' => $file->getClientOriginalName(),
            'file_url' => $file->store('followups/'.$followUp->id),
            'uploaded_by' => $actor->id,
            'uploaded_at' => now(),
        ]);

        AuditLogger::record(AuditLogger::CREATE, $attachment, null, ['file_name' => $attachment->file_name], $actor->id);

        return $attachment;
    }

    /**
     * Keputusan akhir dicatat Vendor Management (FR-20). Diperbolehkan setelah monitoring
     * selesai, atau lebih awal bila review kode etik menemukan indikasi fraud (SOP).
     *
     * @param  array{decision: string, reason?: string|null, effective_date?: string|null, result_recommendation?: string|null}  $data
     */
    public function decide(FollowUp $followUp, array $data, User $actor): void
    {
        $this->ensureEditable($followUp);

        if (! $this->canDecide($followUp)) {
            throw ValidationException::withMessages(['decision' => 'Keputusan akhir tersedia setelah tahap monitoring, atau bila ada indikasi fraud pada review kode etik.']);
        }

        DB::transaction(function () use ($followUp, $data, $actor): void {
            $decision = $followUp->statusDecision()->create([
                'vendor_id' => $followUp->vendor_id,
                'decision' => $data['decision'],
                'reason' => $data['reason'] ?? null,
                'decided_by' => $actor->id,
                'effective_date' => $data['effective_date'] ?? Carbon::today(),
                'decided_at' => now(),
            ]);

            $followUp->update(['result_recommendation' => $data['result_recommendation'] ?? $data['reason'] ?? null]);
            AuditLogger::record(AuditLogger::CREATE, $decision, null, ['decision' => $decision->decision->value], $actor->id);
            $this->changeStatus($followUp, FollowupStatus::DecisionMade, $actor, 'Keputusan: '.$decision->decision->label());
        });
    }

    public function canDecide(FollowUp $followUp): bool
    {
        if ($followUp->isLocked()) {
            return false;
        }

        return $followUp->status === FollowupStatus::Monitoring
            || $followUp->ethicsReviews()->where('fraud_indication', true)->exists();
    }

    /**
     * PVM Manager menyetujui keputusan; status vendor diperbarui dan kasus ditutup.
     */
    public function approveDecision(FollowUp $followUp, User $actor): void
    {
        $decision = $followUp->statusDecision;

        if ($followUp->status !== FollowupStatus::DecisionMade || ! $decision || $decision->isApproved()) {
            throw ValidationException::withMessages(['decision' => 'Tidak ada keputusan yang menunggu approval.']);
        }

        DB::transaction(function () use ($followUp, $decision, $actor): void {
            $decision->update(['approved_by' => $actor->id]);

            $vendor = $followUp->vendor;
            $previousStatus = $vendor->status;
            $vendor->update(['status' => $this->vendorStatusFor($decision->decision)]);
            AuditLogger::record(AuditLogger::STATUS_CHANGE, $vendor, ['status' => $previousStatus->value], ['status' => $vendor->status->value], $actor->id);

            $followUp->update(['closed_at' => now()]);
            $this->changeStatus($followUp, FollowupStatus::Closed, $actor, 'Keputusan disetujui PVM Manager');

            $cycle = $followUp->evaluation->cycle;

            if ($cycle->status === CycleStatus::Approved) {
                $cycle->update(['status' => CycleStatus::Closed]);
            }
        });
    }

    public function rejectDecision(FollowUp $followUp, User $actor, string $notes): void
    {
        $decision = $followUp->statusDecision;

        if ($followUp->status !== FollowupStatus::DecisionMade || ! $decision || $decision->isApproved()) {
            throw ValidationException::withMessages(['decision' => 'Tidak ada keputusan yang menunggu approval.']);
        }

        DB::transaction(function () use ($followUp, $decision, $actor, $notes): void {
            AuditLogger::record(AuditLogger::STATUS_CHANGE, $decision, ['decision' => $decision->decision->value], 'Ditolak: '.$notes, $actor->id);
            $decision->delete();
            $this->changeStatus($followUp, FollowupStatus::Monitoring, $actor, 'Keputusan dikembalikan: '.$notes);
        });
    }

    public function vendorStatusFor(Decision $decision): VendorStatus
    {
        return match ($decision) {
            Decision::Recommended => VendorStatus::Active,
            Decision::NotRecommendedSuspend => VendorStatus::Suspended,
            Decision::NotRecommendedBlockId => VendorStatus::Blocked,
            Decision::ReDevelopment => VendorStatus::NotRecommended,
        };
    }

    private function changeStatus(FollowUp $followUp, FollowupStatus $to, User $actor, ?string $notes = null): void
    {
        $from = $followUp->status;
        $followUp->update(['status' => $to]);
        $this->recordHistory($followUp, $from, $to, $actor, $notes);
        AuditLogger::record(AuditLogger::STATUS_CHANGE, $followUp, ['status' => $from->value], ['status' => $to->value], $actor->id);
    }

    private function recordHistory(FollowUp $followUp, ?FollowupStatus $from, FollowupStatus $to, User $actor, ?string $notes): void
    {
        $followUp->statusHistory()->create([
            'from_status' => $from,
            'to_status' => $to,
            'changed_by' => $actor->id,
            'changed_at' => now(),
            'notes' => $notes,
        ]);
    }

    private function ensureEditable(FollowUp $followUp): void
    {
        if ($followUp->isLocked()) {
            throw ValidationException::withMessages(['status' => 'Kasus sudah diputuskan dan hanya dapat dibaca.']);
        }
    }

    /**
     * Kategori non-conformance awal ditebak dari kategori form dengan skor terendah.
     */
    private function weakestCategory(Evaluation $evaluation): NonconformanceCategory
    {
        $formTemplate = FormTemplate::query()->with('categories.items')->find($evaluation->cycle->form_template_id);
        $scores = collect(ScoreCalculator::categoryScores($evaluation->load('scores'), $formTemplate->categories))->filter(fn (?float $score): bool => $score !== null);

        return match ($scores->sort()->keys()->first()) {
            'A' => NonconformanceCategory::Quality,
            'B' => NonconformanceCategory::Delivery,
            'C' => NonconformanceCategory::Cost,
            'D', 'E' => NonconformanceCategory::Service,
            'F' => NonconformanceCategory::Compliance,
            default => NonconformanceCategory::Other,
        };
    }

    private function nextNumber(): string
    {
        $prefix = 'FU-'.now()->format('Y').'-';
        $last = FollowUp::query()->where('followup_no', 'like', $prefix.'%')->orderByDesc('followup_no')->value('followup_no');
        $sequence = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}
