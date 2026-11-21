<?php

namespace App\Http\Controllers;

use App\Enums\CycleStatus;
use App\Enums\Recommendation;
use App\Models\EvaluationCycle;
use App\Models\FormTemplate;
use App\Models\Vendor;
use App\Support\MonitoringReport;
use App\Support\ScoreCalculator;
use App\Support\Settings;
use App\Support\XlsxWriter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Rekapitulasi otomatis per periode dan riwayat skor vendor antarperiode (FR-03, FR-04, FR-15, FR-17).
 */
class RecapController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $periods = MonitoringReport::periods($user);
        $period = $request->string('period')->toString() ?: MonitoringReport::defaultPeriod();
        $cycles = MonitoringReport::cycles(['period' => $period, 'vendor_type' => $request->string('vendor_type')->toString() ?: null], $user)
            ->reject(fn (EvaluationCycle $cycle): bool => $cycle->status === CycleStatus::Cancelled)
            ->sortByDesc(fn (EvaluationCycle $cycle): float => (float) ($cycle->evaluation?->total_weighted_score ?? -1))
            ->values();

        return view('recap.index', [
            'periods' => $periods,
            'period' => $period,
            'vendorType' => $request->string('vendor_type')->toString(),
            'cycles' => $cycles,
            'distribution' => collect(Recommendation::cases())->mapWithKeys(fn (Recommendation $recommendation): array => [
                $recommendation->label() => $cycles->filter(fn (EvaluationCycle $cycle): bool => $cycle->evaluation?->recommendation === $recommendation)->count(),
            ]),
            'averageScore' => $cycles->pluck('evaluation.total_weighted_score')->filter()->avg(),
            'thresholds' => $this->thresholds(),
        ]);
    }

    public function vendor(Request $request, Vendor $vendor): View
    {
        $years = $request->integer('years', 3);
        $cycles = $this->vendorCycles($request, $vendor, $years);
        $latest = $cycles->last(fn (EvaluationCycle $cycle): bool => (bool) $cycle->evaluation?->is_complete);
        $formTemplate = FormTemplate::query()->with('categories.items')->find($latest?->form_template_id);

        return view('recap.vendor', [
            'vendor' => $vendor,
            'vendors' => $request->user()->can('view-all-evaluations') ? Vendor::query()->orderBy('name')->get(['id', 'name']) : collect([$vendor]),
            'years' => $years,
            'cycles' => $cycles,
            'latest' => $latest,
            'categories' => $formTemplate?->categories ?? collect(),
            'categoryScores' => $latest && $formTemplate ? ScoreCalculator::categoryScores($latest->evaluation, $formTemplate->categories) : [],
            'thresholds' => $this->thresholds(),
        ]);
    }

    public function export(Request $request, string $format): BinaryFileResponse|View
    {
        $user = $request->user();
        $period = $request->string('period')->toString() ?: MonitoringReport::defaultPeriod();
        $cycles = MonitoringReport::cycles(['period' => $period], $user)
            ->reject(fn (EvaluationCycle $cycle): bool => $cycle->status === CycleStatus::Cancelled);

        return $this->respond($format, 'Rekap Evaluasi - '.MonitoringReport::periodLabel($period), 'rekap-evaluasi-'.str_replace(' ', '-', MonitoringReport::periodLabel($period)), MonitoringReport::exportHeadings(), MonitoringReport::exportRows($cycles));
    }

    public function exportVendor(Request $request, Vendor $vendor, string $format): BinaryFileResponse|View
    {
        $cycles = $this->vendorCycles($request, $vendor, $request->integer('years', 3));

        return $this->respond($format, 'Riwayat Evaluasi '.$vendor->name, 'riwayat-'.$vendor->vendor_code, MonitoringReport::exportHeadings(), MonitoringReport::exportRows($cycles));
    }

    /**
     * @param  list<string>  $headings
     * @param  list<list<string|int|float|null>>  $rows
     */
    private function respond(string $format, string $title, string $fileName, array $headings, array $rows): BinaryFileResponse|View
    {
        if ($format === 'xlsx') {
            return response()->download(XlsxWriter::create('Rekap', $headings, $rows), $fileName.'.xlsx')->deleteFileAfterSend();
        }

        return view('reports.print', compact('title', 'headings', 'rows'));
    }

    /**
     * @return Collection<int, EvaluationCycle>
     */
    private function vendorCycles(Request $request, Vendor $vendor, int $years): Collection
    {
        $user = $request->user();

        return $vendor->evaluationCycles()
            ->with(['vendor', 'evaluation.scores', 'evaluation.followUps', 'assignments'])
            ->where('status', '!=', CycleStatus::Cancelled->value)
            ->whereDate('start_date', '>=', now()->subYears(max(1, min($years, 10)))->startOfYear()->toDateString())
            ->when($user->cannot('view-all-evaluations'), fn (Builder $query) => $query->whereHas('assignments', fn (Builder $assignment) => $assignment->where('assessor_id', $user->id)))
            ->orderBy('start_date')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return array{recommended: float, with_notes: float}
     */
    private function thresholds(): array
    {
        return [
            'recommended' => Settings::decimal(Settings::RECOMMENDED_MIN_SCORE),
            'with_notes' => Settings::decimal(Settings::RECOMMENDED_WITH_NOTES_MIN_SCORE),
        ];
    }
}
