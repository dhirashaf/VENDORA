<?php

namespace App\Console\Commands;

use App\Support\EvaluationWorkflow;
use App\Support\ReminderDispatcher;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

#[Signature('reminders:dispatch {--date= : Tanggal acuan (Y-m-d) untuk simulasi}')]
#[Description('Buka siklus terjadwal, tandai penugasan lewat tenggat, dan kirim reminder sesuai aturan aktif (FR-06 s.d. FR-08)')]
class DispatchReminders extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(ReminderDispatcher $dispatcher, EvaluationWorkflow $workflow): int
    {
        $date = $this->option('date') ? Carbon::parse($this->option('date')) : Carbon::today();

        $opened = $dispatcher->openScheduledCycles($workflow, $date);
        $result = $dispatcher->run($date);

        if ($result['skipped']) {
            $this->warn('Reminder terjadwal dinonaktifkan pada pengaturan sistem.');
        }

        $this->info("Tanggal acuan {$date->toDateString()}: {$opened} siklus dibuka, {$result['overdue']} penugasan lewat tenggat, {$result['sent']} pesan terkirim, {$result['failed']} gagal.");

        return self::SUCCESS;
    }
}
