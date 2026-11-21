<?php

use App\Enums\Department;
use App\Models\AssessmentItem;
use App\Models\EvaluationCycle;
use App\Models\User;
use App\Models\Vendor;
use App\Support\EvaluationWorkflow;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Siapkan data referensi (peran, form 7 kategori/14 item, skala, reminder, pengaturan)
 * beserta aktor utama dan satu penilai per fungsi.
 *
 * @return array{vm: User, manager: User, user: User, assessors: array<string, User>}
 */
function seedEvaluationActors(): array
{
    test()->seed(ReferenceDataSeeder::class);

    $assessors = [];

    foreach ([Department::Finance, Department::Engineering, Department::Procurement] as $department) {
        $assessors[$department->value] = User::factory()->assessor($department)->create();
    }

    return [
        'vm' => User::factory()->vmSpecialist()->create(),
        'manager' => User::factory()->pvmManager()->create(),
        'user' => User::factory()->create(),
        'assessors' => $assessors,
    ];
}

/**
 * Buat siklus evaluasi yang formnya sudah dikirim ke penilai.
 *
 * @param  array{vm: User, assessors: array<string, User>}  $actors
 */
function createOpenCycle(array $actors, ?Vendor $vendor = null, ?string $startDate = null): EvaluationCycle
{
    return app(EvaluationWorkflow::class)->createCycle(
        $vendor ?? Vendor::factory()->create(),
        ['cycle_type' => 'Semester', 'period_label' => '2026-S2', 'start_date' => $startDate ?? now()->toDateString(), 'sla_days' => 14],
        collect($actors['assessors'])->map->id->all(),
        $actors['vm'],
    );
}

/**
 * Isi dan submit seluruh item untuk setiap penilai dengan skor tetap per item.
 *
 * @param  callable(AssessmentItem): int  $scoreFor
 */
function submitAllScores(EvaluationCycle $cycle, callable $scoreFor): EvaluationCycle
{
    $workflow = app(EvaluationWorkflow::class);

    foreach ($cycle->assignments()->with('assessor')->get() as $assignment) {
        $scores = $workflow->itemsFor($assignment)->mapWithKeys(fn ($item) => [$item->id => ['score' => $scoreFor($item)]])->all();
        $workflow->saveScores($assignment, $scores, true, $assignment->assessor);
    }

    return $cycle->refresh();
}
