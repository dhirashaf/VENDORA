<?php

namespace App\Http\Controllers;

use App\Enums\AssignmentStatus;
use App\Enums\CycleStatus;
use App\Models\EvaluationAssignment;
use App\Models\ScoreScale;
use App\Support\EvaluationWorkflow;
use App\Support\ScoreCalculator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Form evaluasi digital per fungsi penilai dengan validasi, progres, dan draf (FR-11 s.d. FR-14, FR-16).
 */
class AssessmentController extends Controller
{
    public function __construct(private EvaluationWorkflow $workflow) {}

    public function index(Request $request): View
    {
        $user = $request->user();

        $assignments = EvaluationAssignment::query()
            ->with(['cycle.vendor', 'assessor'])
            ->when(! $user->can('view-all-evaluations'), fn (Builder $query) => $query->where('assessor_id', $user->id))
            ->when($request->string('status')->toString(), fn (Builder $query, string $status) => $query->where('evaluation_assignments.status', $status), fn (Builder $query) => $query
                ->where('evaluation_assignments.status', '!=', AssignmentStatus::Submitted->value))
            ->whereHas('cycle', fn (Builder $cycle) => $cycle->where('status', '!=', CycleStatus::Cancelled->value))
            ->join('evaluation_cycles', 'evaluation_cycles.id', '=', 'evaluation_assignments.cycle_id')
            ->orderBy('evaluation_cycles.due_date')
            ->select('evaluation_assignments.*')
            ->paginate(25)
            ->withQueryString();

        return view('assessments.index', [
            'assignments' => $assignments,
            'status' => $request->string('status')->toString(),
        ]);
    }

    public function edit(EvaluationAssignment $assignment): View
    {
        Gate::authorize('view-assignment', $assignment);

        $assignment->load(['cycle.vendor', 'cycle.evaluation.scores', 'cycle.formTemplate.categories.items', 'cycle.assignments.assessor', 'assessor']);
        $cycle = $assignment->cycle;
        $ownItemIds = $this->workflow->itemsFor($assignment)->pluck('id');
        $scoresByItem = $cycle->evaluation->scores->keyBy('item_id');
        $canEdit = Gate::allows('fill-assignment', $assignment) && $cycle->isCollecting() && ! $assignment->isSubmitted();

        return view('assessments.edit', [
            'assignment' => $assignment,
            'cycle' => $cycle,
            'categories' => $cycle->formTemplate->categories,
            'ownItemIds' => $ownItemIds,
            'scoresByItem' => $scoresByItem,
            'scales' => ScoreScale::query()->orderByDesc('score')->get(),
            'canEdit' => $canEdit,
            'canReopen' => $assignment->isSubmitted()
                && in_array($cycle->status, [CycleStatus::InProgress, CycleStatus::Compiled], true)
                && Gate::allows('reopen-assignment', $assignment),
            'filledCount' => $scoresByItem->only($ownItemIds->all())->count(),
            'categoryScores' => ScoreCalculator::categoryScores($cycle->evaluation, $cycle->formTemplate->categories),
        ]);
    }

    public function update(Request $request, EvaluationAssignment $assignment): RedirectResponse
    {
        Gate::authorize('fill-assignment', $assignment);

        $data = $request->validate([
            'scores' => ['array'],
            'scores.*.score' => ['nullable', 'integer', 'exists:score_scales,score'],
            'scores.*.notes' => ['nullable', 'string', 'max:2000'],
            'scores.*.evidence_url' => ['nullable', 'url', 'max:500'],
            'action' => ['required', 'in:draft,submit'],
        ], [
            'scores.*.score.exists' => 'Skor harus bernilai 1 sampai 4.',
            'scores.*.evidence_url.url' => 'Tautan bukti harus berupa URL yang valid.',
        ]);

        $submit = $data['action'] === 'submit';
        $this->workflow->saveScores($assignment, $data['scores'] ?? [], $submit, $request->user());

        return $submit
            ? redirect()->route('assessments.index')->with('success', 'Penilaian berhasil disubmit. Terima kasih.')
            : redirect()->route('assessments.edit', $assignment)->with('success', 'Draf penilaian tersimpan.');
    }
}
