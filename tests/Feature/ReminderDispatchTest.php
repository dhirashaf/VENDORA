<?php

use App\Enums\AssignmentStatus;
use App\Enums\Channel;
use App\Enums\CycleStatus;
use App\Enums\Department;
use App\Mail\ReminderMessage;
use App\Models\ReminderLog;
use App\Models\ReminderRule;
use App\Support\EvaluationWorkflow;
use App\Support\ReminderDispatcher;
use App\Support\Settings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
    $this->actors = seedEvaluationActors();
    $this->cycle = createOpenCycle($this->actors, startDate: '2026-11-01');
    $this->dispatcher = app(ReminderDispatcher::class);
    $this->dueDate = $this->cycle->due_date->copy();

    // Abaikan log pengiriman form awal; test ini fokus pada reminder terjadwal.
    ReminderLog::query()->delete();
    Mail::fake();
});

/**
 * @return Collection<int, ReminderLog>
 */
function emailLogs(): Collection
{
    return ReminderLog::query()->with('rule')->where('channel', Channel::Email->value)->get();
}

test('reminders fire exactly on H-7, H-3 and H-1 for every pending assessor', function (int $daysBefore, bool $expected) {
    $this->dispatcher->run($this->dueDate->copy()->subDays($daysBefore));

    expect(emailLogs()->count())->toBe($expected ? 3 : 0);

    if ($expected) {
        expect(emailLogs()->first()->rule->name)->toBe('Reminder H-'.$daysBefore);
        Mail::assertSent(ReminderMessage::class, 3);
    }
})->with([[8, false], [7, true], [4, false], [3, true], [2, false], [1, true], [0, false]]);

test('every reminder is delivered by email and in-app notification', function () {
    $this->dispatcher->run($this->dueDate->copy()->subDays(3));

    expect(ReminderLog::query()->where('channel', Channel::InApp->value)->count())->toBe(3)
        ->and(ReminderLog::query()->where('status', 'Sent')->count())->toBe(6);
});

test('submitted assessors no longer receive reminders', function () {
    $finance = $this->cycle->assignments->firstWhere('department', Department::Finance);
    $finance->update(['status' => AssignmentStatus::Submitted, 'submitted_at' => now()]);

    $this->dispatcher->run($this->dueDate->copy()->subDay());

    expect(emailLogs()->pluck('recipient_id')->all())->not->toContain($finance->assessor_id)
        ->and(emailLogs())->toHaveCount(2);
});

test('after the due date reminders repeat every 2 days and escalate to vendor management on H+3', function () {
    $schedule = collect(range(1, 6))->mapWithKeys(function (int $daysAfter) {
        $before = emailLogs()->count();
        $this->dispatcher->run($this->dueDate->copy()->addDays($daysAfter));

        return [$daysAfter => emailLogs()->skip($before)->map(fn ($log) => $log->rule->name.($log->is_escalation ? ':esc' : ''))->unique()->values()->all()];
    });

    expect($schedule[1])->toBe([])
        ->and($schedule[2])->toBe(['Reminder lanjutan tiap 2 hari setelah tenggat'])
        ->and($schedule[3])->toBe(['Eskalasi H+3 ke Vendor Management:esc'])
        ->and($schedule[4])->toBe(['Reminder lanjutan tiap 2 hari setelah tenggat'])
        ->and($schedule[5])->toBe([])
        ->and($schedule[6])->toBe(['Reminder lanjutan tiap 2 hari setelah tenggat']);

    $escalations = emailLogs()->where('is_escalation', true);
    expect($escalations->pluck('recipient_id')->unique()->all())->toBe([$this->actors['vm']->id]);
});

test('overdue assignments are flagged and reminders are not duplicated on the same day', function () {
    $day = $this->dueDate->copy()->addDays(2);

    $this->dispatcher->run($day);
    $this->dispatcher->run($day);

    expect(emailLogs())->toHaveCount(3)
        ->and($this->cycle->assignments()->pluck('status')->unique()->all())->toBe([AssignmentStatus::Overdue]);
});

test('disabling reminders in settings stops dispatch without code changes', function () {
    Settings::set(Settings::REMINDERS_ENABLED, '0');

    $result = $this->dispatcher->run($this->dueDate->copy()->subDays(3));

    expect($result['skipped'])->toBeTrue()->and(emailLogs())->toHaveCount(0);
});

test('reminder rules are configurable from the admin screen', function () {
    $rule = ReminderRule::query()->where('name', 'Reminder H-7')->firstOrFail();

    $this->actingAs($this->actors['vm'])->put(route('reminders.rules.update', $rule), [
        'name' => 'Reminder H-5',
        'trigger_type' => 'Before_Due',
        'offset_days' => 5,
        'template_id' => $rule->template_id,
        'is_active' => '1',
    ])->assertRedirect();

    $this->dispatcher->run($this->dueDate->copy()->subDays(5));

    expect(emailLogs()->first()?->rule->name)->toBe('Reminder H-5');
});

test('the artisan command processes reminders for a given date', function () {
    $this->artisan('reminders:dispatch', ['--date' => $this->dueDate->copy()->subDays(7)->toDateString()])
        ->assertSuccessful()
        ->expectsOutputToContain('6 pesan terkirim');
});

test('manual reminder button sends to the pending assessor and is audited', function () {
    $assignment = $this->cycle->assignments->first();

    $this->actingAs($this->actors['vm'])->post(route('assignments.remind', $assignment))->assertRedirect();

    expect(emailLogs())->toHaveCount(1);
    $this->assertDatabaseHas('audit_logs', ['action' => 'REMINDER', 'entity_id' => $assignment->id]);
});

test('scheduled cycles are opened on their start date', function () {
    $scheduled = createOpenCycle($this->actors, startDate: Carbon::today()->addDays(3)->toDateString());

    $opened = $this->dispatcher->openScheduledCycles(app(EvaluationWorkflow::class), Carbon::today()->addDays(3));

    expect($opened)->toBe(1)->and($scheduled->refresh()->status)->toBe(CycleStatus::Open);
});
