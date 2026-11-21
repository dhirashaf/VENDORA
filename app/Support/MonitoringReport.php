<?php

namespace App\Support;

use App\Enums\AssignmentStatus;
use App\Enums\CycleStatus;
use App\Enums\VendorType;
use App\Models\EvaluationAssignment;
use App\Models\EvaluationCycle;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Data monitoring status evaluasi per vendor untuk dashboard dan ekspor (FR-02, FR-10, FR-17).
 */
class MonitoringReport
{
    public const STATUS_OVERDUE = 'overdue';

    public const STATUS_INCOMPLETE = 'incomplete';

    /**
     * @return Collection<int, string>
     */
    public static function periods(?User $user = null): Collection
    {
        return self::scopedQuery($user)
            ->orderByDesc('start_date')
            ->pluck('period_label')
            ->unique()
            ->values();
    }

    /**
     * Filter semu "periode berjalan": siklus yang dimulai pada semester kalender ini
     * (tahunan, semesteran, maupun ad-hoc) ditambah siklus lama yang belum selesai.
     */
    public const CURRENT_PERIOD = 'current';

    public static function defaultPeriod(): string
    {
        return self::CURRENT_PERIOD;
    }

    public static function periodLabel(?string $period): string
    {
        return match ($period) {
            null, '' => 'semua periode',
            self::CURRENT_PERIOD => 'periode berjalan',
            default => $period,
        };
    }

    /**
     * @param  array{period?: string|null, vendor_type?: string|null, status?: string|null, search?: string|null}  $filters
     * @return Collection<int, EvaluationCycle>
     */
    public static function cycles(array $filters, ?User $user = null): Collection
    {
        $today = Carbon::today();

        return self::scopedQuery($user)
            ->with(['vendor', 'evaluation.followUps', 'assignments.assessor'])
            ->when($filters['period'] ?? null, fn (Builder $query, string $period) => $period === self::CURRENT_PERIOD
                ? $query->where(fn (Builder $current) => $current
                    ->whereDate('start_date', '>=', $today->copy()->month($today->month <= 6 ? 1 : 7)->startOfMonth()->toDateString())
                    ->orWhereIn('status', [CycleStatus::Scheduled->value, CycleStatus::Open->value, CycleStatus::InProgress->value, CycleStatus::Compiled->value, CycleStatus::PendingApproval->value]))
                : $query->where('period_label', $period))
            ->when($filters['vendor_type'] ?? null, fn (Builder $query, string $type) => $query->whereHas('vendor', fn (Builder $vendor) => $vendor->where('vendor_type', $type)))
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->whereHas('vendor', fn (Builder $vendor) => $vendor
                ->where(fn (Builder $match) => $match
                    ->where('name', 'like', '%'.$search.'%')
                    ->orWhere('vendor_code', 'like', '%'.$search.'%'))))
            ->when($filters['status'] ?? null, function (Builder $query, string $status) use ($today): void {
                match ($status) {
                    self::STATUS_OVERDUE => $query->whereIn('status', [CycleStatus::Open->value, CycleStatus::InProgress->value])
                        ->whereDate('due_date', '<', $today->toDateString()),
                    self::STATUS_INCOMPLETE => $query->whereIn('status', [CycleStatus::Open->value, CycleStatus::InProgress->value]),
                    default => $query->where('status', $status),
                };
            })
            ->orderBy('due_date')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  Collection<int, EvaluationCycle>  $cycles
     * @return array<string, int>
     */
    public static function summary(Collection $cycles): array
    {
        $active = $cycles->reject(fn (EvaluationCycle $cycle): bool => $cycle->status === CycleStatus::Cancelled);
        $completed = $active->filter(fn (EvaluationCycle $cycle): bool => in_array($cycle->status, [CycleStatus::Approved, CycleStatus::Closed], true))->count();

        return [
            'total' => $active->count(),
            'completed' => $completed,
            'completed_pct' => $active->count() ? (int) round($completed / $active->count() * 100) : 0,
            'incomplete' => $active->filter->isCollecting()->count(),
            'overdue' => $active->filter->isOverdue()->count(),
            'pending_approval' => $active->where('status', CycleStatus::PendingApproval)->count(),
            'on_time_pct' => self::onTimePct($active),
        ];
    }

    /**
     * Persentase evaluasi yang seluruh penilainya submit dalam tenggat (indikator NFR-02).
     *
     * @param  Collection<int, EvaluationCycle>  $cycles
     */
    public static function onTimePct(Collection $cycles): int
    {
        $finished = $cycles->filter(fn (EvaluationCycle $cycle): bool => $cycle->assignments->isNotEmpty()
            && $cycle->assignments->every(fn (EvaluationAssignment $assignment): bool => $assignment->submitted_at !== null));

        if ($finished->isEmpty()) {
            return 0;
        }

        $onTime = $finished->filter(fn (EvaluationCycle $cycle): bool => $cycle->assignments
            ->every(fn (EvaluationAssignment $assignment): bool => $assignment->submitted_at->lte($cycle->due_date->copy()->endOfDay())));

        return (int) round($onTime->count() / $finished->count() * 100);
    }

    /**
     * Fungsi penilai yang belum submit pada siklus ini.
     */
    public static function pendingDepartments(EvaluationCycle $cycle): string
    {
        return $cycle->assignments
            ->reject(fn (EvaluationAssignment $assignment): bool => $assignment->status === AssignmentStatus::Submitted)
            ->map(fn (EvaluationAssignment $assignment): string => $assignment->department->label())
            ->join(', ');
    }

    /**
     * Baris ekspor Excel/PDF.
     *
     * @param  Collection<int, EvaluationCycle>  $cycles
     * @return list<list<string|int|float|null>>
     */
    public static function exportRows(Collection $cycles): array
    {
        return $cycles->map(fn (EvaluationCycle $cycle): array => [
            $cycle->cycle_code,
            $cycle->vendor->vendor_code,
            $cycle->vendor->name,
            $cycle->vendor->vendor_type === VendorType::Trade ? 'Trade' : 'Non-Trade',
            $cycle->vendor->vendor_category->label(),
            $cycle->period_label,
            $cycle->cycle_type->label(),
            $cycle->status->label(),
            $cycle->due_date->toDateString(),
            $cycle->agingDays(),
            self::pendingDepartments($cycle) ?: '-',
            $cycle->evaluation?->total_weighted_score !== null ? (float) $cycle->evaluation->total_weighted_score : null,
            $cycle->evaluation?->achievement_pct !== null ? (float) $cycle->evaluation->achievement_pct : null,
            $cycle->evaluation?->recommendation?->label(),
            $cycle->vendor->status->label(),
        ])->values()->all();
    }

    /**
     * @return list<string>
     */
    public static function exportHeadings(): array
    {
        return ['Kode Siklus', 'Kode Vendor', 'Vendor', 'Jenis', 'Kategori', 'Periode', 'Tipe Siklus', 'Status',
            'Tenggat', 'Aging (hari)', 'Penilai Belum Submit', 'Total Weighted Score', 'Pencapaian (%)', 'Rekomendasi', 'Status Vendor'];
    }

    /**
     * Penilai hanya melihat siklus yang ditugaskan kepadanya (Tabel 3.5).
     *
     * @return Builder<EvaluationCycle>
     */
    private static function scopedQuery(?User $user): Builder
    {
        return EvaluationCycle::query()
            ->when($user && $user->cannot('view-all-evaluations'), fn (Builder $query) => $query
                ->whereHas('assignments', fn (Builder $assignment) => $assignment->where('assessor_id', $user->id)));
    }
}
