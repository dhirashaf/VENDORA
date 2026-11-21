<?php

use App\Enums\Department;
use App\Models\User;
use App\Models\Vendor;
use App\Support\EvaluationWorkflow;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
    $this->actors = seedEvaluationActors();
});

test('every application page requires authentication', function (string $route) {
    $this->get(route($route))->assertRedirect(route('login'));
})->with(['dashboard', 'cycles.index', 'assessments.index', 'recap.index', 'follow-ups.index', 'vendors.index', 'audit-logs.index']);

test('a user can log in with a work email and the login is audited', function () {
    $user = $this->actors['vm'];

    $this->post(route('auth.authenticate'), ['work_email' => $user->work_email, 'password' => 'password'])
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
    $this->assertDatabaseHas('audit_logs', ['action' => 'LOGIN', 'user_id' => $user->id]);
});

test('inactive accounts cannot log in', function () {
    $user = User::factory()->vmSpecialist()->inactive()->create();

    $this->post(route('auth.authenticate'), ['work_email' => $user->work_email, 'password' => 'password'])
        ->assertSessionHasErrors('work_email');

    $this->assertGuest();
});

test('passwords are stored as bcrypt hashes', function () {
    expect($this->actors['vm']->password_hash)->toStartWith('$2y$')
        ->and(password_verify('password', $this->actors['vm']->password_hash))->toBeTrue();
});

test('master data is restricted to the VM specialist', function (string $actor, int $status) {
    $this->actingAs($actor === 'assessor' ? $this->actors['assessors']['Finance'] : $this->actors[$actor])
        ->get(route('vendors.index'))
        ->assertStatus($status);
})->with([['vm', 200], ['manager', 403], ['assessor', 403], ['user', 403]]);

test('only the PVM manager can approve evaluations', function () {
    $cycle = submitAllScores(createOpenCycle($this->actors), fn () => 3);
    app(EvaluationWorkflow::class)->submitForApproval($cycle, $this->actors['vm']);

    $this->actingAs($this->actors['vm'])->post(route('cycles.approve', $cycle))->assertForbidden();
    $this->actingAs($this->actors['assessors']['Finance'])->post(route('cycles.approve', $cycle))->assertForbidden();
    $this->actingAs($this->actors['manager'])->post(route('cycles.approve', $cycle))->assertRedirect();
});

test('an assessor cannot open or fill another assessor form', function () {
    $cycle = createOpenCycle($this->actors);
    $engineering = $cycle->assignments->firstWhere('department', Department::Engineering);
    $finance = $this->actors['assessors']['Finance'];

    $this->actingAs($finance)->get(route('assessments.edit', $engineering))->assertForbidden();
    $this->actingAs($finance)->put(route('assessments.update', $engineering), ['action' => 'draft'])->assertForbidden();
    $this->actingAs($this->actors['vm'])->put(route('assessments.update', $engineering), ['action' => 'draft'])->assertForbidden();
});

test('assessors only see cycles assigned to them on the dashboard', function () {
    $mine = createOpenCycle($this->actors);
    $otherAssessor = User::factory()->assessor(Department::Finance)->create();
    $others = app(EvaluationWorkflow::class)->createCycle(
        Vendor::factory()->create(['name' => 'PT Bukan Milik Saya']),
        ['cycle_type' => 'Semester', 'period_label' => '2026-S2', 'start_date' => now()->toDateString()],
        ['Finance' => $otherAssessor->id, 'Engineering' => $this->actors['assessors']['Engineering']->id, 'Procurement' => $this->actors['assessors']['Procurement']->id],
        $this->actors['vm'],
    );

    $this->actingAs($this->actors['assessors']['Finance'])
        ->get(route('dashboard', ['period' => '2026-S2']))
        ->assertOk()
        ->assertSee($mine->vendor->name)
        ->assertDontSee('PT Bukan Milik Saya');

    $this->actingAs($this->actors['assessors']['Finance'])->get(route('cycles.show', $others))->assertForbidden();
});

test('the consulted user role can view monitoring and follow-ups but not export', function () {
    $user = $this->actors['user'];

    $this->actingAs($user)->get(route('dashboard'))->assertOk();
    $this->actingAs($user)->get(route('follow-ups.index'))->assertOk();
    $this->actingAs($user)->get(route('dashboard.export', 'xlsx'))->assertForbidden();
});

test('login attempts are rate limited', function () {
    foreach (range(1, 5) as $attempt) {
        $this->post(route('auth.authenticate'), ['work_email' => 'x@chakra.com', 'password' => 'wrong']);
    }

    $this->post(route('auth.authenticate'), ['work_email' => 'x@chakra.com', 'password' => 'wrong'])->assertTooManyRequests();
});
