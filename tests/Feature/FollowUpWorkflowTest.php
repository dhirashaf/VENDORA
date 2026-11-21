<?php

use App\Enums\CycleStatus;
use App\Enums\Decision;
use App\Enums\FollowupStatus;
use App\Enums\NonconformanceCategory;
use App\Enums\VendorStatus;
use App\Models\FollowUp;
use App\Support\EvaluationWorkflow;
use App\Support\FollowUpWorkflow;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    Mail::fake();
    $this->actors = seedEvaluationActors();
    $this->followUps = app(FollowUpWorkflow::class);

    $workflow = app(EvaluationWorkflow::class);
    $cycle = submitAllScores(createOpenCycle($this->actors), fn () => 2);
    $workflow->submitForApproval($cycle, $this->actors['vm']);
    $workflow->approve($cycle->refresh(), $this->actors['manager']);

    $this->followUp = FollowUp::query()->firstOrFail();
});

/**
 * Bawa kasus sampai tahap Monitoring dengan seluruh syarat SOP terpenuhi.
 */
function progressToMonitoring(FollowUp $followUp, array $actors): FollowUp
{
    $workflow = app(FollowUpWorkflow::class);
    $vm = $actors['vm'];

    $workflow->advance($followUp, $vm);
    $workflow->recordEthicsReview($followUp, ['findings' => 'Tidak ada indikasi fraud'], $vm);
    $workflow->advance($followUp->refresh(), $vm);
    $meeting = $workflow->scheduleMeeting($followUp, ['scheduled_at' => now()->addDay()->toDateTimeString()], $vm);
    $workflow->advance($followUp->refresh(), $vm);
    $workflow->recordMinutes($meeting, ['held_at' => now()->toDateTimeString(), 'minutes' => 'Vendor menyanggupi perbaikan'], $vm);
    $workflow->updateForm($followUp->refresh(), [
        'nc_category' => NonconformanceCategory::Delivery,
        'nc_description' => 'Keterlambatan pengiriman',
        'root_cause' => 'Kapasitas gudang',
    ], $vm);
    $workflow->advance($followUp->refresh(), $vm);
    $workflow->updateForm($followUp->refresh(), [
        'requested_corrective_action' => 'Tambah kapasitas',
        'vendor_corrective_action' => 'Sewa gudang transit',
        'target_completion_date' => now()->addDays(30)->toDateString(),
    ], $vm);
    $workflow->advance($followUp->refresh(), $vm);

    return $followUp->refresh();
}

test('a follow-up cannot advance while SOP requirements are missing', function () {
    $this->followUps->advance($this->followUp, $this->actors['vm']);

    expect($this->followUp->refresh()->status)->toBe(FollowupStatus::EthicsReview)
        ->and(fn () => $this->followUps->advance($this->followUp, $this->actors['vm']))
        ->toThrow(ValidationException::class, 'review kode etik');
});

test('the follow-up walks every SOP stage with timestamped history', function () {
    $followUp = progressToMonitoring($this->followUp, $this->actors);

    expect($followUp->status)->toBe(FollowupStatus::Monitoring)
        ->and($followUp->statusHistory->pluck('to_status')->all())->toBe([
            FollowupStatus::Open, FollowupStatus::EthicsReview, FollowupStatus::MeetingInvited,
            FollowupStatus::Clarification, FollowupStatus::CorrectiveActionSubmitted, FollowupStatus::Monitoring,
        ])
        ->and($followUp->statusHistory->every(fn ($history) => $history->changed_at !== null && $history->changed_by !== null))->toBeTrue();
});

test('the decision is only available after monitoring', function () {
    expect(fn () => $this->followUps->decide($this->followUp, ['decision' => Decision::Recommended->value, 'reason' => 'x'], $this->actors['vm']))
        ->toThrow(ValidationException::class);
});

test('a fraud indication allows an immediate block decision', function () {
    $this->followUps->advance($this->followUp, $this->actors['vm']);
    $this->followUps->recordEthicsReview($this->followUp, ['fraud_indication' => true, 'findings' => 'Dokumen palsu'], $this->actors['vm']);

    expect($this->followUps->canDecide($this->followUp->refresh()))->toBeTrue();
});

test('an approved decision updates the vendor status and locks the case', function (Decision $decision, VendorStatus $vendorStatus) {
    $followUp = progressToMonitoring($this->followUp, $this->actors);

    $this->followUps->decide($followUp, ['decision' => $decision->value, 'reason' => 'Hasil monitoring'], $this->actors['vm']);
    expect($followUp->vendor->refresh()->status)->toBe(VendorStatus::Active);

    $this->followUps->approveDecision($followUp->refresh(), $this->actors['manager']);

    $followUp->refresh();
    expect($followUp->status)->toBe(FollowupStatus::Closed)
        ->and($followUp->vendor->status)->toBe($vendorStatus)
        ->and($followUp->statusDecision->approved_by)->toBe($this->actors['manager']->id)
        ->and($followUp->evaluation->cycle->status)->toBe(CycleStatus::Closed)
        ->and(fn () => $this->followUps->updateForm($followUp, ['root_cause' => 'ubah'], $this->actors['vm']))
        ->toThrow(ValidationException::class, 'hanya dapat dibaca');
})->with([
    [Decision::Recommended, VendorStatus::Active],
    [Decision::NotRecommendedSuspend, VendorStatus::Suspended],
    [Decision::NotRecommendedBlockId, VendorStatus::Blocked],
    [Decision::ReDevelopment, VendorStatus::NotRecommended],
]);

test('a rejected decision returns the case to monitoring', function () {
    $followUp = progressToMonitoring($this->followUp, $this->actors);
    $this->followUps->decide($followUp, ['decision' => Decision::NotRecommendedSuspend->value, 'reason' => 'x'], $this->actors['vm']);

    $this->actingAs($this->actors['manager'])
        ->post(route('follow-ups.decision.reject', $followUp), ['notes' => 'Perlu monitoring tambahan'])
        ->assertRedirect();

    expect($followUp->refresh()->status)->toBe(FollowupStatus::Monitoring)
        ->and($followUp->statusDecision)->toBeNull();
});

test('only the PVM manager can approve a decision', function () {
    $followUp = progressToMonitoring($this->followUp, $this->actors);
    $this->followUps->decide($followUp, ['decision' => Decision::Recommended->value, 'reason' => 'x'], $this->actors['vm']);

    $this->actingAs($this->actors['vm'])->post(route('follow-ups.decision.approve', $followUp))->assertForbidden();
    $this->actingAs($this->actors['manager'])->post(route('follow-ups.decision.approve', $followUp))->assertRedirect();
});

test('evidence attachments are stored privately and downloadable by authorised roles', function () {
    Storage::fake();

    $this->actingAs($this->actors['vm'])
        ->post(route('follow-ups.attachments.store', $this->followUp), ['file' => UploadedFile::fake()->create('bukti.pdf', 100, 'application/pdf')])
        ->assertRedirect();

    $attachment = $this->followUp->attachments()->firstOrFail();
    Storage::assertExists($attachment->file_url);

    $this->actingAs($this->actors['manager'])->get(route('follow-ups.attachments.download', [$this->followUp, $attachment]))->assertOk();
    $this->actingAs($this->actors['assessors']['Finance'])->get(route('follow-ups.attachments.download', [$this->followUp, $attachment]))->assertForbidden();
});

test('the follow-up page renders the Deviation & Corrective Action form', function () {
    $this->actingAs($this->actors['vm'])
        ->get(route('follow-ups.show', $this->followUp))
        ->assertOk()
        ->assertSee('Deviation &amp; Corrective Action Form', false)
        ->assertSee('Preventive action')
        ->assertSee('Impact assessment');
});
