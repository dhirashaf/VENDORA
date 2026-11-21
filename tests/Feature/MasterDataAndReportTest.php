<?php

use App\Enums\CycleStatus;
use App\Enums\VendorStatus;
use App\Models\AuditLog;
use App\Models\EvaluationCycle;
use App\Models\Vendor;
use App\Support\CycleScheduler;
use App\Support\EvaluationWorkflow;
use App\Support\Settings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
    $this->actors = seedEvaluationActors();
});

function vendorPayload(array $overrides = []): array
{
    return array_merge([
        'vendor_code' => 'VND-1001',
        'name' => 'PT Uji Coba',
        'vendor_type' => 'Trade',
        'vendor_category' => 'Production',
        'is_strategic' => '1',
        'evaluation_frequency' => 'Semester',
        'status' => 'Active',
    ], $overrides);
}

test('a vendor can be registered with an SAP-mappable unique code', function () {
    $this->actingAs($this->actors['vm'])->post(route('vendors.store'), vendorPayload(['sap_vendor_id' => '1000123']))
        ->assertRedirect(route('vendors.index'));

    $vendor = Vendor::query()->where('vendor_code', 'VND-1001')->firstOrFail();
    expect($vendor->sap_vendor_id)->toBe('1000123')->and($vendor->is_strategic)->toBeTrue();
    $this->assertDatabaseHas('audit_logs', ['action' => 'CREATE', 'entity_name' => 'vendors', 'entity_id' => $vendor->id]);
});

test('duplicate vendor codes are rejected', function () {
    Vendor::factory()->create(['vendor_code' => 'VND-1001']);

    $this->actingAs($this->actors['vm'])->post(route('vendors.store'), vendorPayload())
        ->assertSessionHasErrors(['vendor_code' => 'Kode vendor sudah terdaftar. Gunakan kode vendor yang unik.']);
});

test('vendor updates are recorded in the audit log with old and new values', function () {
    $vendor = Vendor::factory()->create(['status' => VendorStatus::Active]);

    $this->actingAs($this->actors['vm'])
        ->put(route('vendors.update', $vendor), vendorPayload(['vendor_code' => $vendor->vendor_code, 'status' => 'Suspended']))
        ->assertRedirect();

    $log = AuditLog::query()->where('entity_name', 'vendors')->where('action', 'UPDATE')->latest('id')->firstOrFail();
    expect($log->old_value)->toContain('Active')->and($log->new_value)->toContain('Suspended');
});

test('routine cycles are generated once per period for active strategic vendors', function () {
    $semester = Vendor::factory()->create();
    $annual = Vendor::factory()->annual()->create();
    Vendor::factory()->nonStrategic()->create();
    Vendor::factory()->create(['status' => VendorStatus::Blocked]);

    $scheduler = app(CycleScheduler::class);
    $first = $scheduler->generate($this->actors['vm'], Carbon::parse('2026-07-01'));
    $second = $scheduler->generate($this->actors['vm'], Carbon::parse('2026-07-01'));

    expect($first)->toBe(['created' => 2, 'skipped' => 0])
        ->and($second)->toBe(['created' => 0, 'skipped' => 2])
        ->and(EvaluationCycle::query()->where('vendor_id', $semester->id)->value('period_label'))->toBe('2026-S2')
        ->and(EvaluationCycle::query()->where('vendor_id', $annual->id)->value('period_label'))->toBe('2026-ANNUAL');
});

test('an ad-hoc cycle requires a reason and uses the configured SLA', function () {
    $vendor = Vendor::factory()->create();
    Settings::set(Settings::DEFAULT_SLA_DAYS, '10');
    $assessors = collect($this->actors['assessors'])->map->id->all();

    $this->actingAs($this->actors['vm'])->post(route('cycles.store'), [
        'vendor_id' => $vendor->id, 'cycle_type' => 'Ad_hoc', 'start_date' => '2026-11-21', 'sla_days' => 10, 'assessors' => $assessors,
    ])->assertSessionHasErrors('adhoc_reason');

    $this->actingAs($this->actors['vm'])->post(route('cycles.store'), [
        'vendor_id' => $vendor->id, 'cycle_type' => 'Ad_hoc', 'start_date' => '2026-11-21', 'sla_days' => 10,
        'adhoc_reason' => 'Masalah kualitas berulang', 'assessors' => $assessors, 'send_now' => '1',
    ])->assertRedirect();

    $cycle = EvaluationCycle::query()->where('vendor_id', $vendor->id)->firstOrFail();
    expect($cycle->period_label)->toBe('2026-11-ADHOC')
        ->and($cycle->due_date->toDateString())->toBe('2026-12-01')
        ->and($cycle->status)->toBe(CycleStatus::Open);
});

test('the monitoring export produces a real xlsx workbook', function () {
    createOpenCycle($this->actors, Vendor::factory()->create(['name' => 'PT Ekspor Excel']));

    $response = $this->actingAs($this->actors['vm'])->get(route('dashboard.export', ['format' => 'xlsx', 'period' => '2026-S2']));

    $response->assertOk()->assertDownload('monitoring-evaluasi-2026-S2.xlsx');

    $zip = new ZipArchive;
    expect($zip->open($response->getFile()->getPathname()))->toBeTrue();
    $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();

    expect($sheet)->toContain('PT Ekspor Excel')->toContain('Total Weighted Score');
});

test('the recap print view lists evaluations for PDF export', function () {
    createOpenCycle($this->actors, Vendor::factory()->create(['name' => 'PT Cetak PDF']));

    $this->actingAs($this->actors['manager'])
        ->get(route('recap.export', ['format' => 'pdf', 'period' => '2026-S2']))
        ->assertOk()
        ->assertSee('PT Cetak PDF')
        ->assertSee('window.print', false);
});

test('vendor history shows scores across periods', function () {
    $vendor = Vendor::factory()->create();
    $workflow = app(EvaluationWorkflow::class);

    foreach (['2026-S1' => '2026-04-01', '2026-S2' => '2026-10-01'] as $period => $start) {
        $cycle = $workflow->createCycle($vendor, ['cycle_type' => 'Semester', 'period_label' => $period, 'start_date' => $start], collect($this->actors['assessors'])->map->id->all(), $this->actors['vm']);
        submitAllScores($cycle, fn () => $period === '2026-S1' ? 3 : 4);
    }

    $this->actingAs($this->actors['manager'])
        ->get(route('recap.vendor', $vendor))
        ->assertOk()
        ->assertSeeInOrder(['2026-S2', '4,00', '2026-S1', '3,00']);
});

test('settings validate that the notes threshold does not exceed the recommended threshold', function () {
    $this->actingAs($this->actors['vm'])->put(route('settings.update'), [
        'default_sla_days' => 14, 'followup_due_days' => 45, 'recommended_min_score' => 3, 'recommended_with_notes_min_score' => 3.5,
    ])->assertSessionHasErrors('recommended_with_notes_min_score');

    $this->actingAs($this->actors['vm'])->put(route('settings.update'), [
        'default_sla_days' => 10, 'followup_due_days' => 30, 'recommended_min_score' => 3.2, 'recommended_with_notes_min_score' => 2.6, 'enforce_dependency' => '1',
    ])->assertRedirect();

    expect(Settings::integer(Settings::DEFAULT_SLA_DAYS))->toBe(10)
        ->and(Settings::decimal(Settings::RECOMMENDED_WITH_NOTES_MIN_SCORE))->toBe(2.6)
        ->and(Settings::boolean(Settings::REMINDERS_ENABLED))->toBeFalse();
});
