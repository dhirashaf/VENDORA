<?php

namespace Database\Seeders;

use App\Enums\CycleType;
use App\Enums\Decision;
use App\Enums\Department;
use App\Enums\EvaluationFrequency;
use App\Enums\NonconformanceCategory;
use App\Enums\RoleCode;
use App\Enums\VendorCategory;
use App\Enums\VendorStatus;
use App\Enums\VendorType;
use App\Models\EvaluationAssignment;
use App\Models\EvaluationCycle;
use App\Models\FollowUp;
use App\Models\Role;
use App\Models\User;
use App\Models\Vendor;
use App\Support\EvaluationWorkflow;
use App\Support\FollowUpWorkflow;
use App\Support\ReminderDispatcher;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;

/**
 * Data uji sintetis/anonim (Batasan 10): 66 vendor (39 Trade, 27 Non-Trade), riwayat tiga periode,
 * evaluasi berjalan, dan kasus tindak lanjut. Seluruh data dibuat melalui workflow sistem
 * agar konsisten dengan aturan bisnis.
 */
class DemoDataSeeder extends Seeder
{
    private EvaluationWorkflow $workflow;

    private FollowUpWorkflow $followUps;

    private User $vm;

    private User $manager;

    /**
     * @var array<string, int>
     */
    private array $assessors = [];

    public function run(EvaluationWorkflow $workflow, FollowUpWorkflow $followUps, ReminderDispatcher $reminders): void
    {
        config(['mail.default' => 'array']);
        mt_srand(2026);

        $this->workflow = $workflow;
        $this->followUps = $followUps;
        $this->seedUsers();
        $vendors = $this->seedVendors();
        $today = Carbon::today();

        // Riwayat 2025-S2 dan 2026-S1 (semua approved) untuk 20 vendor strategic pertama.
        foreach ($vendors->take(20)->values() as $index => $vendor) {
            if ($index < 12) {
                $this->runCycle($vendor, '2025-S2', Carbon::parse('2025-10-06'), $this->profileScore($vendor) - 0.2, 'approved');
            }

            $isSuspendCase = $vendor->name === 'PT Karya Alat Industri';
            $cycle = $this->runCycle($vendor, '2026-S1', Carbon::parse('2026-04-06'), $isSuspendCase ? 1.9 : $this->profileScore($vendor) - 0.1, 'approved');

            // Kasus tindak lanjut 2026-S1 yang sudah diputuskan: suspend sementara.
            if ($isSuspendCase) {
                $this->completeFollowUp($cycle);
            }
        }

        // Periode berjalan 2026-S2 untuk seluruh strategic vendor.
        $currentStart = $today->copy()->subDays(14);
        $inProgress = 0;

        foreach ($vendors->where('is_strategic', true)->values() as $index => $vendor) {
            if ($vendor->status !== VendorStatus::Active) {
                continue;
            }

            $period = $vendor->evaluation_frequency === EvaluationFrequency::Semester ? '2026-S2' : '2026-ANNUAL';
            [$stage, $start, $skip] = match (true) {
                $vendor->name === 'PT Suryatama' => ['collecting', $today->copy()->subDays(11), [Department::Finance]],
                $vendor->name === 'PT Logistindo' => ['collecting', $today->copy()->subDays(20), [Department::Engineering]],
                $vendor->name === 'CV Anugerah' => ['pending', $today->copy()->subDays(16), []],
                $vendor->name === 'PT Baja Perkasa' => ['approved', $today->copy()->subDays(25), []],
                $inProgress < 18 && $index % 2 === 1 => ['collecting', $currentStart->copy()->addDays($index % 9), [Department::cases()[1 + $index % 3]]],
                $index % 7 === 0 => ['pending', $currentStart, []],
                default => ['approved', $currentStart->copy()->subDays(5), []],
            };

            if ($stage === 'collecting') {
                $inProgress++;
            }

            $score = match ($vendor->name) {
                'CV Anugerah' => 3.4,
                'PT Baja Perkasa' => 2.1,
                'PT Megah Jasa Teknik' => 2.3,
                default => $this->profileScore($vendor),
            };

            $cycle = $this->runCycle($vendor, $period, $start, $score, $stage, $skip);

            if ($vendor->name === 'PT Baja Perkasa') {
                $this->progressFollowUpToClarification($cycle);
            }
        }

        // Satu evaluasi ad-hoc menjelang perpanjangan kontrak.
        $adHocVendor = $vendors->firstWhere('name', 'PT Citra Mesin Nusantara');
        $this->runCycle($adHocVendor, $today->format('Y-m').'-ADHOC', $today->copy()->subDays(2), 3.0, 'collecting', [Department::Finance, Department::Engineering],
            cycleType: CycleType::AdHoc, adhocReason: 'Menjelang perpanjangan kontrak strategis (berakhir '.$adHocVendor->contract_end_date->format('d M Y').')');

        // Jalankan reminder 10 hari terakhir agar log reminder & eskalasi terisi.
        for ($day = $today->copy()->subDays(10); $day->lte($today); $day->addDay()) {
            Carbon::setTestNow($day->copy()->setTime(8, 0));
            $reminders->run($day->copy());
        }

        Carbon::setTestNow();
    }

    private function seedUsers(): void
    {
        $roles = Role::query()->pluck('id', 'code');
        $people = [
            ['Ayu Pratama', 'ayu@chakra.com', RoleCode::VmSpecialist, Department::VendorManagement, 'Vendor Management Specialist'],
            ['Satria Wibowo', 'satria@chakra.com', RoleCode::PvmManager, Department::VendorManagement, 'Procurement & Vendor Management Manager'],
            ['Dimas Saputra', 'dimas@chakra.com', RoleCode::Assessor, Department::Finance, 'Finance Supervisor'],
            ['Rina Kartika', 'rina@chakra.com', RoleCode::Assessor, Department::Engineering, 'Service Engineer'],
            ['Budi Santoso', 'budi@chakra.com', RoleCode::Assessor, Department::Procurement, 'Procurement Officer'],
            ['Laras Wulandari', 'laras@chakra.com', RoleCode::Assessor, Department::Finance, 'Finance Officer'],
            ['Guest User', 'guest@chakra.com', RoleCode::User, Department::Other, 'Operasional'],
        ];

        foreach ($people as [$name, $email, $role, $department, $title]) {
            User::query()->updateOrCreate(['work_email' => $email], [
                'role_id' => $roles[$role->value],
                'full_name' => $name,
                'password_hash' => Hash::make('password'),
                'department' => $department,
                'job_title' => $title,
                'is_active' => true,
            ]);
        }

        $this->vm = User::query()->where('work_email', 'ayu@chakra.com')->firstOrFail();
        $this->manager = User::query()->where('work_email', 'satria@chakra.com')->firstOrFail();
        $this->assessors = [
            Department::Finance->value => User::query()->where('work_email', 'dimas@chakra.com')->value('id'),
            Department::Engineering->value => User::query()->where('work_email', 'rina@chakra.com')->value('id'),
            Department::Procurement->value => User::query()->where('work_email', 'budi@chakra.com')->value('id'),
        ];
    }

    /**
     * @return Collection<int, Vendor>
     */
    private function seedVendors(): Collection
    {
        $named = [
            ['PT Suryatama', VendorType::Trade, VendorCategory::Production],
            ['CV Anugerah', VendorType::NonTrade, VendorCategory::Service],
            ['PT Baja Perkasa', VendorType::Trade, VendorCategory::Production],
            ['PT Logistindo', VendorType::NonTrade, VendorCategory::Service],
            ['PT Bumi Karya Mandiri', VendorType::Trade, VendorCategory::Production],
            ['PT Citra Mesin Nusantara', VendorType::Trade, VendorCategory::Production],
            ['PT Megah Jasa Teknik', VendorType::Trade, VendorCategory::Service],
            ['PT Delta Komponen Indo', VendorType::Trade, VendorCategory::Production],
            ['PT Karya Alat Industri', VendorType::Trade, VendorCategory::Production],
            ['PT Panca Pelayanan Mandiri', VendorType::NonTrade, VendorCategory::General],
        ];

        $prefixes = ['Sinar', 'Mitra', 'Prima', 'Sentosa', 'Nusa', 'Jaya', 'Cahaya', 'Mega', 'Graha', 'Abadi', 'Global', 'Indo', 'Surya', 'Bintang', 'Samudra', 'Persada'];
        $suffixes = ['Teknik', 'Logistik', 'Diesel', 'Komponen', 'Hidrolik', 'Pratama', 'Sejahtera', 'Utama', 'Energi', 'Ban', 'Lestari', 'Perkasa', 'Solusi', 'Mandiri'];
        $tradeCount = collect($named)->where(1, VendorType::Trade)->count();
        $vendors = collect();

        for ($index = 0; $index < 66; $index++) {
            if (isset($named[$index])) {
                [$name, $type, $category] = $named[$index];
            } else {
                $type = $tradeCount < 39 ? VendorType::Trade : VendorType::NonTrade;
                $tradeCount += $type === VendorType::Trade ? 1 : 0;
                $name = ($index % 4 === 0 ? 'CV ' : 'PT ').$prefixes[$index % count($prefixes)].' '.$suffixes[($index * 5 + intdiv($index, count($prefixes))) % count($suffixes)];
                $category = $type === VendorType::Trade ? VendorCategory::Production : [VendorCategory::Service, VendorCategory::General][$index % 2];
            }

            $vendors->push(Vendor::query()->updateOrCreate(['vendor_code' => 'VND-'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT)], [
                'sap_vendor_id' => $index % 3 === 0 ? (string) (1000100 + $index) : null,
                'name' => $name,
                'vendor_type' => $type,
                'vendor_category' => $category,
                'is_strategic' => $index < 48,
                'evaluation_frequency' => $index % 6 === 5 ? EvaluationFrequency::Annual : EvaluationFrequency::Semester,
                'contract_end_date' => Carbon::parse('2026-12-01')->addMonths(($index * 5) % 24),
                'contact_person' => 'Kontak '.($index + 1),
                'contact_email' => 'vendor'.($index + 1).'@example.com',
                'status' => $name === 'PT Panca Pelayanan Mandiri' ? VendorStatus::Blocked : VendorStatus::Active,
            ]));
        }

        return $vendors;
    }

    /**
     * @param  list<Department>  $skipDepartments  Fungsi yang belum submit (untuk tahap collecting)
     */
    private function runCycle(
        Vendor $vendor,
        string $period,
        Carbon $start,
        float $targetScore,
        string $stage,
        array $skipDepartments = [],
        CycleType $cycleType = CycleType::Semester,
        ?string $adhocReason = null,
    ): EvaluationCycle {
        $existing = EvaluationCycle::query()->where('vendor_id', $vendor->id)->where('period_label', $period)->first();

        if ($existing) {
            return $existing;
        }

        Carbon::setTestNow($start->copy()->setTime(8, 0));
        $cycleType = str_ends_with($period, 'ANNUAL') ? CycleType::Annual : $cycleType;

        $cycle = $this->workflow->createCycle($vendor, [
            'cycle_type' => $cycleType->value,
            'period_label' => $period,
            'start_date' => $start->toDateString(),
            'sla_days' => 14,
            'adhoc_reason' => $adhocReason,
        ], $this->assessors, $this->vm);

        foreach ($cycle->assignments()->get() as $offset => $assignment) {
            $skipped = in_array($assignment->department, $skipDepartments, true);
            $submitAt = $start->copy()->addDays(min(13, 3 + $offset * 3 + mt_rand(0, 4)))->setTime(10 + $offset, 15);

            if ($submitAt->isFuture()) {
                $submitAt = Carbon::now()->subHours(2);
            }

            Carbon::setTestNow($submitAt);
            $scores = $this->scoresFor($assignment, $targetScore, $skipped);
            $this->workflow->saveScores($assignment, $scores, ! $skipped, User::query()->findOrFail($assignment->assessor_id));
        }

        if (in_array($stage, ['pending', 'approved'], true)) {
            Carbon::setTestNow($start->copy()->addDays(14)->setTime(14, 0)->min(Carbon::now()));
            $this->workflow->submitForApproval($cycle->refresh(), $this->vm);
        }

        if ($stage === 'approved') {
            Carbon::setTestNow($start->copy()->addDays(16)->setTime(9, 30)->min(Carbon::now()));
            $this->workflow->approve($cycle->refresh(), $this->manager, 'Disetujui sesuai rekap otomatis.');
        }

        Carbon::setTestNow();

        return $cycle->refresh();
    }

    /**
     * @return array<int, array{score: int|null, notes: string|null}>
     */
    private function scoresFor(EvaluationAssignment $assignment, float $target, bool $partial): array
    {
        $items = $this->workflow->itemsFor($assignment);
        $scores = [];

        foreach ($items as $position => $item) {
            if ($partial && $position >= intdiv($items->count(), 2)) {
                break;
            }

            $score = (int) max(1, min(4, round($target + (mt_rand(-50, 50) / 100))));
            $scores[$item->id] = [
                'score' => $score,
                'notes' => $score <= 2 ? 'Terdapat temuan berulang pada periode ini.' : null,
            ];
        }

        return $scores;
    }

    private function profileScore(Vendor $vendor): float
    {
        return 2.7 + (crc32($vendor->vendor_code) % 120) / 100;
    }

    private function progressFollowUpToClarification(EvaluationCycle $cycle): FollowUp
    {
        $followUp = $cycle->evaluation->followUps()->firstOrFail();
        $opened = $followUp->opened_at->copy();

        Carbon::setTestNow($opened->copy()->addDays(1)->setTime(9, 30));
        $this->followUps->advance($followUp, $this->vm);
        $this->followUps->recordEthicsReview($followUp, ['findings' => 'History PO dan hasil audit diperiksa; tidak ada indikasi fraud.'], $this->vm);
        $this->followUps->advance($followUp->refresh(), $this->vm);

        Carbon::setTestNow($opened->copy()->addDays(4)->setTime(9, 30)->min(Carbon::now()));
        $this->followUps->scheduleMeeting($followUp, ['scheduled_at' => $opened->copy()->addDays(6)->setTime(10, 0)->toDateTimeString(), 'location_or_link' => 'Ruang Meeting Procurement / Teams'], $this->vm);
        $this->followUps->advance($followUp->refresh(), $this->vm);
        $this->followUps->updateForm($followUp->refresh(), [
            'nc_category' => NonconformanceCategory::Delivery,
            'nc_description' => 'Keterlambatan pengiriman berulang pada 3 PO terakhir.',
            'root_cause' => 'Kapasitas gudang vendor tidak memadai.',
            'requested_corrective_action' => 'Penambahan kapasitas gudang, target 30 hari.',
        ], $this->vm);

        Carbon::setTestNow();

        return $followUp;
    }

    private function completeFollowUp(EvaluationCycle $cycle): void
    {
        $followUp = $this->progressFollowUpToClarification($cycle);
        $opened = $followUp->opened_at->copy();
        $meeting = $followUp->meetings()->firstOrFail();

        Carbon::setTestNow($opened->copy()->addDays(6)->setTime(11, 0));
        $this->followUps->recordMinutes($meeting, ['held_at' => $meeting->scheduled_at->toDateTimeString(), 'minutes' => 'Vendor mengakui keterlambatan dan menyanggupi rencana perbaikan.'], $this->vm);
        $this->followUps->advance($followUp->refresh(), $this->vm);

        Carbon::setTestNow($opened->copy()->addDays(10)->setTime(9, 0));
        $this->followUps->updateForm($followUp->refresh(), [
            'vendor_explanation' => 'Keterbatasan armada pada musim puncak.',
            'vendor_corrective_action' => 'Menambah 2 unit armada dan gudang transit.',
            'target_completion_date' => $opened->copy()->addDays(30)->toDateString(),
            'preventive_action' => 'Laporan kapasitas bulanan kepada Procurement.',
        ], $this->vm);
        $this->followUps->advance($followUp->refresh(), $this->vm);

        Carbon::setTestNow($opened->copy()->addDays(40)->setTime(9, 0));
        $this->followUps->decide($followUp->refresh(), [
            'decision' => Decision::NotRecommendedSuspend->value,
            'reason' => 'Corrective action tidak tercapai sesuai target; vendor di-suspend sementara.',
            'effective_date' => $opened->copy()->addDays(40)->toDateString(),
        ], $this->vm);

        Carbon::setTestNow($opened->copy()->addDays(41)->setTime(10, 0));
        $this->followUps->approveDecision($followUp->refresh(), $this->manager);
        Carbon::setTestNow();
    }
}
