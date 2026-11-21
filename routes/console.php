<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Reminder harian: buka siklus terjadwal, tandai lewat tenggat, kirim H-7/H-3/H-1, tiap 2 hari, eskalasi H+3 (FR-07, FR-08).
Schedule::command('reminders:dispatch')->dailyAt('08:00')->withoutOverlapping()->onOneServer();

// Siklus rutin semesteran/tahunan dibuat pada awal semester (FR-05).
Schedule::command('evaluations:schedule')->cron('0 7 1 1,7 *')->withoutOverlapping()->onOneServer();
