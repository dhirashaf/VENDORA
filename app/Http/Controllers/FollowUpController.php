<?php

namespace App\Http\Controllers;

use App\Enums\Decision;
use App\Enums\FollowupStatus;
use App\Enums\NonconformanceCategory;
use App\Enums\RoleCode;
use App\Models\FollowUp;
use App\Models\FollowupAttachment;
use App\Models\FollowupMeeting;
use App\Models\User;
use App\Support\FollowUpWorkflow;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Pencatatan dan tracking tindak lanjut vendor (FR-18 s.d. FR-21).
 */
class FollowUpController extends Controller
{
    public function __construct(private FollowUpWorkflow $workflow) {}

    public function index(Request $request): View
    {
        $followUps = FollowUp::query()
            ->with(['vendor', 'pic', 'evaluation.cycle', 'statusDecision'])
            ->when($request->string('status')->toString(), fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($request->string('search')->toString(), fn (Builder $query, string $search) => $query->where(fn (Builder $match) => $match
                ->where('followup_no', 'like', '%'.$search.'%')
                ->orWhereHas('vendor', fn (Builder $vendor) => $vendor->where('name', 'like', '%'.$search.'%'))))
            ->when($request->boolean('active'), fn (Builder $query) => $query->whereNotIn('status', [FollowupStatus::Closed->value]))
            ->orderByRaw('CASE WHEN status = ? THEN 1 ELSE 0 END', [FollowupStatus::Closed->value])
            ->orderBy('due_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('follow-ups.index', [
            'followUps' => $followUps,
            'filters' => $request->only(['status', 'search']),
        ]);
    }

    public function show(FollowUp $followUp): View
    {
        $followUp->load(['vendor', 'pic', 'evaluation.cycle', 'statusHistory.changer', 'ethicsReviews.reviewer',
            'meetings.organizer', 'attachments.uploader', 'statusDecision.decider', 'statusDecision.approver']);

        return view('follow-ups.show', [
            'followUp' => $followUp,
            'missingRequirements' => $this->workflow->missingRequirements($followUp),
            'canDecide' => $this->workflow->canDecide($followUp),
            'picOptions' => User::query()->active()->withRole(RoleCode::VmSpecialist)->orderBy('full_name')->get(),
            'archive' => FollowUp::query()->where('vendor_id', $followUp->vendor_id)->whereKeyNot($followUp->id)->latest('opened_at')->get(),
        ]);
    }

    public function update(Request $request, FollowUp $followUp): RedirectResponse
    {
        $data = $request->validate([
            'pic_user_id' => ['required', 'exists:users,id'],
            'due_date' => ['nullable', 'date'],
            'nc_category' => ['nullable', Rule::enum(NonconformanceCategory::class)],
            'nc_description' => ['nullable', 'string', 'max:5000'],
            'root_cause' => ['nullable', 'string', 'max:5000'],
            'requested_corrective_action' => ['nullable', 'string', 'max:5000'],
            'preventive_action' => ['nullable', 'string', 'max:5000'],
            'impact_assessment' => ['nullable', 'string', 'max:5000'],
            'vendor_explanation' => ['nullable', 'string', 'max:5000'],
            'vendor_corrective_action' => ['nullable', 'string', 'max:5000'],
            'target_completion_date' => ['nullable', 'date'],
            'result_recommendation' => ['nullable', 'string', 'max:5000'],
        ]);

        $this->workflow->updateForm($followUp, $data, $request->user());

        return back()->with('success', 'Deviation & Corrective Action Form tersimpan.');
    }

    public function advance(Request $request, FollowUp $followUp): RedirectResponse
    {
        $data = $request->validate(['notes' => ['nullable', 'string', 'max:1000']]);
        $previous = $followUp->status;
        $this->workflow->advance($followUp, $request->user(), $data['notes'] ?? null);

        return back()->with('success', "Tahap {$previous->label()} selesai. Status sekarang: {$followUp->status->label()}.");
    }

    public function storeEthicsReview(Request $request, FollowUp $followUp): RedirectResponse
    {
        $data = $request->validate([
            'fraud_indication' => ['boolean'],
            'due_diligence_required' => ['boolean'],
            'findings' => ['required', 'string', 'max:5000'],
        ]);

        $this->workflow->recordEthicsReview($followUp, $data, $request->user());

        return back()->with('success', 'Hasil review kode etik tersimpan.');
    }

    public function storeMeeting(Request $request, FollowUp $followUp): RedirectResponse
    {
        $data = $request->validate([
            'scheduled_at' => ['required', 'date'],
            'location_or_link' => ['nullable', 'string', 'max:300'],
        ]);

        $this->workflow->scheduleMeeting($followUp, $data, $request->user());

        return back()->with('success', 'Undangan meeting klarifikasi tercatat.');
    }

    public function updateMeeting(Request $request, FollowUp $followUp, FollowupMeeting $meeting): RedirectResponse
    {
        abort_unless($meeting->followup_id === $followUp->id, 404);

        $data = $request->validate([
            'held_at' => ['required', 'date'],
            'minutes' => ['required', 'string', 'max:5000'],
        ]);

        $this->workflow->recordMinutes($meeting, $data, $request->user());

        return back()->with('success', 'Notulen meeting tersimpan.');
    }

    public function storeAttachment(Request $request, FollowUp $followUp): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx'],
        ]);

        $this->workflow->addAttachment($followUp, $request->file('file'), $request->user());

        return back()->with('success', 'Lampiran bukti diunggah.');
    }

    public function downloadAttachment(FollowUp $followUp, FollowupAttachment $attachment): StreamedResponse
    {
        abort_unless($attachment->followup_id === $followUp->id && Storage::exists($attachment->file_url), 404);

        return Storage::download($attachment->file_url, $attachment->file_name);
    }

    public function decide(Request $request, FollowUp $followUp): RedirectResponse
    {
        $data = $request->validate([
            'decision' => ['required', Rule::enum(Decision::class)],
            'reason' => ['required', 'string', 'max:5000'],
            'effective_date' => ['nullable', 'date'],
            'result_recommendation' => ['nullable', 'string', 'max:5000'],
        ]);

        $this->workflow->decide($followUp, $data, $request->user());

        return back()->with('success', 'Keputusan akhir dicatat dan menunggu approval PVM Manager.');
    }

    public function approveDecision(Request $request, FollowUp $followUp): RedirectResponse
    {
        $this->workflow->approveDecision($followUp, $request->user());

        return back()->with('success', 'Keputusan disetujui. Status vendor diperbarui menjadi '.$followUp->vendor->refresh()->status->label().'.');
    }

    public function rejectDecision(Request $request, FollowUp $followUp): RedirectResponse
    {
        $data = $request->validate(['notes' => ['required', 'string', 'max:1000']]);
        $this->workflow->rejectDecision($followUp, $request->user(), $data['notes']);

        return back()->with('success', 'Keputusan dikembalikan ke tahap monitoring.');
    }
}
