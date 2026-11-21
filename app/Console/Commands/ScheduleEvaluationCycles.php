<?php

namespace App\Console\Commands;

use App\Enums\RoleCode;
use App\Models\User;
use App\Support\CycleScheduler;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

#[Signature('evaluations:schedule {--date= : Tanggal mulai siklus (Y-m-d)}')]
#[Description('Buat siklus evaluasi rutin tahunan/semesteran untuk strategic vendor aktif (FR-05)')]
class ScheduleEvaluationCycles extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(CycleScheduler $scheduler): int
    {
        $creator = User::query()->active()->withRole(RoleCode::VmSpecialist)->orderBy('id')->first();

        if (! $creator) {
            $this->error('Tidak ada Vendor Management Specialist aktif sebagai pembuat siklus.');

            return self::FAILURE;
        }

        $date = $this->option('date') ? Carbon::parse($this->option('date')) : Carbon::today();
        $result = $scheduler->generate($creator, $date);

        $this->info("{$result['created']} siklus dibuat, {$result['skipped']} vendor dilewati (sudah memiliki siklus periode ini).");

        return self::SUCCESS;
    }
}
