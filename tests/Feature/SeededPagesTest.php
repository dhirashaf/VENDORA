<?php

use App\Enums\VendorType;
use App\Models\EvaluationAssignment;
use App\Models\EvaluationCycle;
use App\Models\FollowUp;
use App\Models\User;
use App\Models\Vendor;

beforeEach(function () {
    $this->seed();
});

test('demo data matches the aggregate data received from the company', function () {
    expect(Vendor::query()->count())->toBe(66)
        ->and(Vendor::query()->where('vendor_type', VendorType::Trade->value)->count())->toBe(39)
        ->and(Vendor::query()->where('vendor_type', VendorType::NonTrade->value)->count())->toBe(27)
        ->and(EvaluationCycle::query()->whereIn('status', ['Open', 'In_Progress'])->count())->toBeGreaterThanOrEqual(19)
        ->and(FollowUp::query()->where('status', 'Closed')->exists())->toBeTrue()
        ->and(FollowUp::query()->where('status', 'Clarification')->exists())->toBeTrue();
});

test('every page renders for each role without errors', function (string $email, array $routes) {
    $user = User::query()->where('work_email', $email)->firstOrFail();
    $cycle = EvaluationCycle::query()->whereHas('assignments', fn ($query) => $query->where('assessor_id', $user->id))->first()
        ?? EvaluationCycle::query()->firstOrFail();

    $parameters = [
        'cycles.show' => $cycle,
        'assessments.edit' => EvaluationAssignment::query()->where('assessor_id', $user->id)->first() ?? EvaluationAssignment::query()->first(),
        'recap.vendor' => $cycle->vendor,
        'follow-ups.show' => FollowUp::query()->first(),
        'vendors.edit' => Vendor::query()->first(),
        'users.edit' => $user,
    ];

    foreach ($routes as $route) {
        $this->actingAs($user)->get(route($route, $parameters[$route] ?? []))->assertOk();
    }
})->with([
    'VM Specialist' => ['ayu@chakra.com', ['dashboard', 'cycles.index', 'cycles.create', 'cycles.show', 'assessments.index', 'assessments.edit', 'recap.index', 'recap.vendor',
        'follow-ups.index', 'follow-ups.show', 'vendors.index', 'vendors.create', 'vendors.edit', 'users.index', 'users.create', 'users.edit',
        'reminders.index', 'settings.edit', 'audit-logs.index', 'notifications.index']],
    'PVM Manager' => ['satria@chakra.com', ['dashboard', 'cycles.index', 'cycles.show', 'recap.index', 'recap.vendor', 'follow-ups.index', 'follow-ups.show', 'audit-logs.index']],
    'Penilai' => ['dimas@chakra.com', ['dashboard', 'assessments.index', 'assessments.edit', 'cycles.index', 'cycles.show', 'recap.vendor', 'notifications.index']],
    'User' => ['guest@chakra.com', ['dashboard', 'cycles.index', 'recap.index', 'follow-ups.index', 'follow-ups.show']],
]);

test('the dashboard flags overdue and incomplete evaluations from the report scenario', function () {
    $vm = User::query()->where('work_email', 'ayu@chakra.com')->firstOrFail();

    $this->actingAs($vm)->get(route('dashboard', ['period' => '2026-S2', 'status' => 'overdue']))
        ->assertOk()
        ->assertSee('PT Logistindo')
        ->assertSee('Engineering (belum)')
        ->assertDontSee('PT Suryatama');
});
