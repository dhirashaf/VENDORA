<?php

use App\Http\Controllers\AssessmentController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CycleController;
use App\Http\Controllers\FollowUpController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\RecapController;
use App\Http\Controllers\ReminderController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VendorController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('/', [AuthController::class, 'login'])->name('login');
    Route::get('/login', [AuthController::class, 'login'])->name('auth.login');
    Route::post('/login', [AuthController::class, 'authenticate'])->middleware('throttle:login')->name('auth.authenticate');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Modul 1 & 2: Dashboard monitoring
    Route::get('/dashboard', [PageController::class, 'dashboard'])->name('dashboard');
    Route::get('/dashboard/export/{format}', [PageController::class, 'exportDashboard'])
        ->whereIn('format', ['xlsx', 'pdf'])->middleware('permission:export-reports')->name('dashboard.export');
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');

    // Master data (VM Specialist)
    Route::middleware('permission:manage-master')->group(function (): void {
        Route::resource('vendors', VendorController::class)->except(['show', 'destroy']);
        Route::resource('users', UserController::class)->except(['show', 'destroy']);
        Route::get('/settings', [SettingController::class, 'edit'])->name('settings.edit');
        Route::put('/settings', [SettingController::class, 'update'])->name('settings.update');

        Route::get('/reminders', [ReminderController::class, 'index'])->name('reminders.index');
        Route::post('/reminders/run', [ReminderController::class, 'run'])->name('reminders.run');
        Route::post('/reminders/rules', [ReminderController::class, 'storeRule'])->name('reminders.rules.store');
        Route::put('/reminders/rules/{rule}', [ReminderController::class, 'updateRule'])->name('reminders.rules.update');
        Route::post('/reminders/templates', [ReminderController::class, 'storeTemplate'])->name('reminders.templates.store');
        Route::put('/reminders/templates/{template}', [ReminderController::class, 'updateTemplate'])->name('reminders.templates.update');
    });

    // Siklus evaluasi
    Route::get('/cycles', [CycleController::class, 'index'])->name('cycles.index');
    Route::middleware('permission:manage-cycles')->group(function (): void {
        Route::get('/cycles/create', [CycleController::class, 'create'])->name('cycles.create');
        Route::post('/cycles', [CycleController::class, 'store'])->name('cycles.store');
        Route::post('/cycles/generate', [CycleController::class, 'generate'])->name('cycles.generate');
        Route::post('/cycles/{cycle}/send', [CycleController::class, 'send'])->name('cycles.send');
        Route::post('/cycles/{cycle}/submit-approval', [CycleController::class, 'submitForApproval'])->name('cycles.submit-approval');
        Route::post('/cycles/{cycle}/cancel', [CycleController::class, 'cancel'])->name('cycles.cancel');
        Route::post('/assignments/{assignment}/remind', [CycleController::class, 'remind'])->name('assignments.remind');
    });
    Route::middleware('permission:approve-evaluation')->group(function (): void {
        Route::post('/cycles/{cycle}/approve', [CycleController::class, 'approve'])->name('cycles.approve');
        Route::post('/cycles/{cycle}/return', [CycleController::class, 'returnForRevision'])->name('cycles.return');
    });
    Route::get('/cycles/{cycle}', [CycleController::class, 'show'])->name('cycles.show');
    Route::post('/assignments/{assignment}/reopen', [CycleController::class, 'reopen'])->name('assignments.reopen');

    // Modul 3: Form evaluasi digital
    Route::get('/assessments', [AssessmentController::class, 'index'])->name('assessments.index');
    Route::get('/assessments/{assignment}', [AssessmentController::class, 'edit'])->name('assessments.edit');
    Route::put('/assessments/{assignment}', [AssessmentController::class, 'update'])->name('assessments.update');

    // Modul 1 & 4: Rekapitulasi & riwayat
    Route::get('/recap', [RecapController::class, 'index'])->middleware('permission:view-all-evaluations')->name('recap.index');
    Route::get('/recap/export/{format}', [RecapController::class, 'export'])
        ->whereIn('format', ['xlsx', 'pdf'])->middleware('permission:export-reports')->name('recap.export');
    Route::get('/recap/vendors/{vendor}', [RecapController::class, 'vendor'])->name('recap.vendor');
    Route::get('/recap/vendors/{vendor}/export/{format}', [RecapController::class, 'exportVendor'])
        ->whereIn('format', ['xlsx', 'pdf'])->middleware('permission:export-reports')->name('recap.vendor.export');

    // Modul 5: Tindak lanjut
    Route::middleware('permission:view-followups')->group(function (): void {
        Route::get('/follow-ups', [FollowUpController::class, 'index'])->name('follow-ups.index');
        Route::get('/follow-ups/{followUp}', [FollowUpController::class, 'show'])->name('follow-ups.show');
        Route::get('/follow-ups/{followUp}/attachments/{attachment}', [FollowUpController::class, 'downloadAttachment'])->name('follow-ups.attachments.download');
    });
    Route::middleware('permission:manage-followups')->group(function (): void {
        Route::put('/follow-ups/{followUp}', [FollowUpController::class, 'update'])->name('follow-ups.update');
        Route::post('/follow-ups/{followUp}/advance', [FollowUpController::class, 'advance'])->name('follow-ups.advance');
        Route::post('/follow-ups/{followUp}/ethics-reviews', [FollowUpController::class, 'storeEthicsReview'])->name('follow-ups.ethics-reviews.store');
        Route::post('/follow-ups/{followUp}/meetings', [FollowUpController::class, 'storeMeeting'])->name('follow-ups.meetings.store');
        Route::put('/follow-ups/{followUp}/meetings/{meeting}', [FollowUpController::class, 'updateMeeting'])->name('follow-ups.meetings.update');
        Route::post('/follow-ups/{followUp}/attachments', [FollowUpController::class, 'storeAttachment'])->name('follow-ups.attachments.store');
        Route::post('/follow-ups/{followUp}/decision', [FollowUpController::class, 'decide'])->name('follow-ups.decide');
    });
    Route::middleware('permission:approve-decision')->group(function (): void {
        Route::post('/follow-ups/{followUp}/decision/approve', [FollowUpController::class, 'approveDecision'])->name('follow-ups.decision.approve');
        Route::post('/follow-ups/{followUp}/decision/reject', [FollowUpController::class, 'rejectDecision'])->name('follow-ups.decision.reject');
    });

    Route::get('/audit-logs', [AuditLogController::class, 'index'])->middleware('permission:view-audit-logs')->name('audit-logs.index');
});
