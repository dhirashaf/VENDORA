<?php

namespace App\Support;

use App\Enums\CycleStatus;
use App\Enums\CycleType;
use App\Enums\EvaluationFrequency;
use App\Enums\RoleCode;
use App\Enums\VendorStatus;
use App\Models\EvaluationCycle;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Support\Carbon;

/**
 * Penjadwalan siklus evaluasi rutin tahunan/semesteran per vendor (FR-05).
 */
class CycleScheduler
{
    public function __construct(private EvaluationWorkflow $workflow) {}

    public static function periodLabel(EvaluationFrequency $frequency, Carbon $date): string
    {
        return $frequency === EvaluationFrequency::Semester
            ? $date->year.'-S'.($date->month <= 6 ? 1 : 2)
            : $date->year.'-ANNUAL';
    }

    public static function adHocPeriodLabel(Carbon $date): string
    {
        return $date->format('Y-m').'-ADHOC';
    }

    /**
     * Penilai bawaan per fungsi: pengguna aktif pertama berperan Penilai pada fungsi tersebut.
     *
     * @return array<string, int>
     */
    public static function defaultAssessors(): array
    {
        return User::query()
            ->active()
            ->withRole(RoleCode::Assessor)
            ->orderBy('id')
            ->get()
            ->groupBy(fn (User $user): string => $user->department->value)
            ->map(fn ($users): int => $users->first()->id)
            ->all();
    }

    /**
     * Buat siklus rutin periode berjalan bagi strategic vendor aktif yang belum memilikinya.
     *
     * @return array{created: int, skipped: int}
     */
    public function generate(User $creator, ?Carbon $date = null, bool $sendNow = true): array
    {
        $date = ($date ?? Carbon::today())->copy()->startOfDay();
        $assessors = self::defaultAssessors();
        $result = ['created' => 0, 'skipped' => 0];

        $vendors = Vendor::query()
            ->where('is_strategic', true)
            ->where('status', VendorStatus::Active->value)
            ->orderBy('vendor_code')
            ->get();

        foreach ($vendors as $vendor) {
            $periodLabel = self::periodLabel($vendor->evaluation_frequency, $date);
            $exists = EvaluationCycle::query()
                ->where('vendor_id', $vendor->id)
                ->where('period_label', $periodLabel)
                ->where('status', '!=', CycleStatus::Cancelled->value)
                ->exists();

            if ($exists) {
                $result['skipped']++;

                continue;
            }

            $this->workflow->createCycle($vendor, [
                'cycle_type' => $vendor->evaluation_frequency === EvaluationFrequency::Semester ? CycleType::Semester->value : CycleType::Annual->value,
                'period_label' => $periodLabel,
                'start_date' => $date->toDateString(),
            ], $assessors, $creator, $sendNow);

            $result['created']++;
        }

        return $result;
    }
}
