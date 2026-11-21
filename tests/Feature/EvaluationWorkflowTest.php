<?php

use App\Enums\AssignmentStatus;
use App\Enums\CycleStatus;
use App\Enums\Department;
use App\Enums\Recommendation;
use App\Models\AssessmentItem;
use App\Models\FormTemplate;
use App\Support\EvaluationWorkflow;
use App\Support\ScoreCalculator;
use App\Support\Settings;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    Mail::fake();
    $this->actors = seedEvaluationActors();
    $this->workflow = app(EvaluationWorkflow::class);
});

test('form template follows the official structure of 7 categories, 14 items and 100 percent weight', function () {
    $form = FormTemplate::active()->load('categories.items');

    expect($form->categories)->toHaveCount(7)
        ->and($form->categories->pluck('code')->all())->toBe(['A', 'B', 'C', 'D', 'E', 'F', 'G'])
        ->and($form->categories->flatMap->items)->toHaveCount(14)
        ->and((float) $form->categories->flatMap->items->sum('weight_pct'))->toBe(100.0)
        ->and($form->categories->map(fn ($category) => (int) $category->items->sum('weight_pct'))->all())->toBe([18, 18, 14, 12, 14, 13, 11]);
});

test('creating a cycle assigns one assessor per department and sends the forms', function () {
    $cycle = createOpenCycle($this->actors);

    expect($cycle->status)->toBe(CycleStatus::Open)
        ->and($cycle->due_date->toDateString())->toBe(now()->addDays(14)->toDateString())
        ->and($cycle->assignments)->toHaveCount(3)
        ->and($cycle->assignments->every(fn ($assignment) => $assignment->sent_at !== null))->toBeTrue()
        ->and($cycle->evaluation->recommendation)->toBe(Recommendation::CannotBeCalculated);

    Mail::assertSentCount(3);
});

test('a cycle starting in the future stays scheduled until its start date', function () {
    $cycle = createOpenCycle($this->actors, startDate: now()->addDays(5)->toDateString());

    expect($cycle->status)->toBe(CycleStatus::Scheduled);
    Mail::assertNothingSent();
});

test('submit is rejected while items are empty but the draft is kept', function (int $filledItems) {
    $cycle = createOpenCycle($this->actors);
    $assignment = $cycle->assignments->firstWhere('department', Department::Engineering);
    $items = $this->workflow->itemsFor($assignment);
    $scores = $items->take($filledItems)->mapWithKeys(fn ($item) => [$item->id => ['score' => 3]])->all();

    expect(fn () => $this->workflow->saveScores($assignment, $scores, true, $assignment->assessor))
        ->toThrow(ValidationException::class, 'Field wajib belum lengkap');

    $assignment->refresh();
    expect($assignment->status)->toBe(AssignmentStatus::Draft)
        ->and($assignment->scores()->count())->toBe($filledItems)
        ->and((float) $assignment->progress_pct)->toBe(round($filledItems / $items->count() * 100, 2));
})->with([0, 1, 4]);

test('assessor items are limited to their own department', function () {
    $cycle = createOpenCycle($this->actors);
    $finance = $cycle->assignments->firstWhere('department', Department::Finance);
    $engineeringItem = AssessmentItem::query()->where('assessor_department', Department::Engineering->value)->first();

    $this->workflow->saveScores($finance, [$engineeringItem->id => ['score' => 4]], false, $finance->assessor);

    expect($finance->scores()->count())->toBe(0);
});

test('evaluation stays "belum dapat dihitung" until every assessor submits', function () {
    $cycle = createOpenCycle($this->actors);
    $finance = $cycle->assignments->firstWhere('department', Department::Finance);
    $scores = $this->workflow->itemsFor($finance)->mapWithKeys(fn ($item) => [$item->id => ['score' => 4]])->all();

    $this->workflow->saveScores($finance, $scores, true, $finance->assessor);

    expect($cycle->refresh()->status)->toBe(CycleStatus::InProgress)
        ->and($cycle->evaluation->is_complete)->toBeFalse()
        ->and($cycle->evaluation->total_weighted_score)->toBeNull();
});

test('weighted total equals an independent manual calculation once all assessors submit', function () {
    $cycle = submitAllScores(createOpenCycle($this->actors), fn (AssessmentItem $item) => 1 + ($item->item_no % 4));

    $manual = AssessmentItem::query()->get()->sum(fn ($item) => (1 + ($item->item_no % 4)) * (float) $item->weight_pct) / 100;
    $evaluation = $cycle->evaluation;

    expect($cycle->status)->toBe(CycleStatus::Compiled)
        ->and($evaluation->is_complete)->toBeTrue()
        ->and((float) $evaluation->total_weighted_score)->toBe(round($manual, 2))
        ->and((float) $evaluation->achievement_pct)->toBe(round($manual / 4 * 100, 2))
        ->and($evaluation->scores)->toHaveCount(14);
});

test('uniform scores produce boundary totals', function (int $score, float $total) {
    $cycle = submitAllScores(createOpenCycle($this->actors), fn () => $score);

    expect((float) $cycle->evaluation->total_weighted_score)->toBe($total);
})->with([[1, 1.0], [3, 3.0], [4, 4.0]]);

test('recommendation thresholds follow the configured boundaries', function (float $score, Recommendation $expected) {
    expect(ScoreCalculator::recommendationFor($score))->toBe($expected);
})->with([
    [2.49, Recommendation::NotRecommended],
    [2.50, Recommendation::RecommendedWithNotes],
    [2.51, Recommendation::RecommendedWithNotes],
    [2.99, Recommendation::RecommendedWithNotes],
    [3.00, Recommendation::Recommended],
]);

test('dependency blocks submission for approval while assessors are pending', function () {
    $cycle = createOpenCycle($this->actors);

    expect(fn () => $this->workflow->submitForApproval($cycle, $this->actors['vm']))
        ->toThrow(ValidationException::class, 'dependency aktif');

    Settings::set(Settings::ENFORCE_DEPENDENCY, '0');
    $this->workflow->submitForApproval($cycle->refresh(), $this->actors['vm']);

    expect($cycle->refresh()->status)->toBe(CycleStatus::PendingApproval)
        ->and($cycle->evaluation->recommendation)->toBe(Recommendation::CannotBeCalculated);
});

test('approving a score below 2.50 opens a follow-up case automatically', function () {
    $cycle = submitAllScores(createOpenCycle($this->actors), fn () => 2);
    $this->workflow->submitForApproval($cycle, $this->actors['vm']);
    $this->workflow->approve($cycle->refresh(), $this->actors['manager']);

    $followUp = $cycle->evaluation->followUps()->first();

    expect($cycle->refresh()->status)->toBe(CycleStatus::Approved)
        ->and($cycle->evaluation->approved_by)->toBe($this->actors['manager']->id)
        ->and($followUp)->not->toBeNull()
        ->and($followUp->statusHistory)->toHaveCount(1)
        ->and($followUp->due_date->toDateString())->toBe(now()->addDays(45)->toDateString());
});

test('approving a score of 2.50 or more does not open a follow-up', function () {
    $cycle = submitAllScores(createOpenCycle($this->actors), fn (AssessmentItem $item) => $item->item_no % 2 === 1 ? 3 : 2);
    $this->workflow->submitForApproval($cycle, $this->actors['vm']);
    $this->workflow->approve($cycle->refresh(), $this->actors['manager']);

    expect((float) $cycle->evaluation->total_weighted_score)->toBeGreaterThanOrEqual(2.5)
        ->and($cycle->evaluation->followUps()->exists())->toBeFalse();
});

test('a submitted form can be reopened before approval and locks the result again', function () {
    $cycle = submitAllScores(createOpenCycle($this->actors), fn () => 3);
    $assignment = $cycle->assignments->first();

    $this->workflow->reopenAssignment($assignment, $assignment->assessor);

    expect($assignment->refresh()->status)->toBe(AssignmentStatus::Draft)
        ->and($cycle->refresh()->status)->toBe(CycleStatus::InProgress)
        ->and($cycle->evaluation->is_complete)->toBeFalse();
});

test('assessor submits through the form endpoint and score changes are audited', function () {
    $cycle = createOpenCycle($this->actors);
    $assignment = $cycle->assignments->firstWhere('department', Department::Finance);
    $scores = $this->workflow->itemsFor($assignment)->mapWithKeys(fn ($item) => [$item->id => ['score' => 3, 'notes' => 'OK']])->all();

    $this->actingAs($assignment->assessor)
        ->put(route('assessments.update', $assignment), ['scores' => $scores, 'action' => 'submit'])
        ->assertRedirect(route('assessments.index'));

    expect($assignment->refresh()->status)->toBe(AssignmentStatus::Submitted);
    $this->assertDatabaseHas('audit_logs', ['action' => 'SUBMIT', 'entity_name' => 'evaluation_assignments', 'entity_id' => $assignment->id]);
    $this->assertDatabaseHas('audit_logs', ['action' => 'CREATE', 'entity_name' => 'evaluation_scores']);
});

test('the form endpoint rejects scores outside the 1-4 scale', function () {
    $cycle = createOpenCycle($this->actors);
    $assignment = $cycle->assignments->firstWhere('department', Department::Finance);
    $item = $this->workflow->itemsFor($assignment)->first();

    $this->actingAs($assignment->assessor)
        ->put(route('assessments.update', $assignment), ['scores' => [$item->id => ['score' => 5]], 'action' => 'draft'])
        ->assertSessionHasErrors('scores.'.$item->id.'.score');
});
