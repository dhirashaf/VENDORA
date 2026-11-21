<?php

namespace App\Http\Controllers;

use App\Enums\CycleStatus;
use App\Enums\FollowupStatus;
use App\Enums\VendorType;
use App\Models\FollowUp;
use App\Models\Vendor;
use App\Support\MonitoringReport;
use App\Support\Settings;
use App\Support\XlsxWriter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PageController extends Controller
{
    /**
     * Dashboard monitoring status evaluasi (Modul 1 & 2: FR-02, FR-10, FR-17, FR-21).
     */
    public function dashboard(Request $request): View
    {
        $user = $request->user();
        $periods = MonitoringReport::periods($user);
        $filters = $this->filters($request, MonitoringReport::defaultPeriod());
        $cycles = MonitoringReport::cycles($filters, $user);
        $periodCycles = $filters['status'] || $filters['vendor_type'] || $filters['search']
            ? MonitoringReport::cycles(['period' => $filters['period']], $user)
            : $cycles;

        return view('dashboard.index', [
            'filters' => $filters,
            'periods' => $periods,
            'cycles' => $cycles,
            'summary' => MonitoringReport::summary($periodCycles),
            'vendorCounts' => [
                'total' => Vendor::query()->count(),
                'trade' => Vendor::query()->where('vendor_type', VendorType::Trade->value)->count(),
                'non_trade' => Vendor::query()->where('vendor_type', VendorType::NonTrade->value)->count(),
            ],
            'openFollowUps' => Gate::allows('view-followups')
                ? FollowUp::query()->whereNotIn('status', [FollowupStatus::DecisionMade->value, FollowupStatus::Closed->value])->count()
                : null,
            'statusOptions' => [
                MonitoringReport::STATUS_INCOMPLETE => 'Belum Lengkap',
                MonitoringReport::STATUS_OVERDUE => 'Lewat Tenggat',
            ] + CycleStatus::options(),
            'remindersEnabled' => Settings::boolean(Settings::REMINDERS_ENABLED),
            'myTasks' => $user->can('fill-assessments')
                ? $user->evaluationAssignments()->with('cycle.vendor')
                    ->where('status', '!=', 'Submitted')
                    ->whereHas('cycle', fn ($query) => $query->whereIn('status', [CycleStatus::Open->value, CycleStatus::InProgress->value]))
                    ->get()
                : collect(),
        ]);
    }

    /**
     * Ekspor monitoring ke Excel (.xlsx) atau tampilan cetak PDF (FR-04).
     */
    public function exportDashboard(Request $request, string $format): BinaryFileResponse|View
    {
        $user = $request->user();
        $filters = $this->filters($request, MonitoringReport::defaultPeriod());
        $cycles = MonitoringReport::cycles($filters, $user);
        $title = 'Monitoring Evaluasi Vendor - '.MonitoringReport::periodLabel($filters['period']);

        if ($format === 'xlsx') {
            $path = XlsxWriter::create('Monitoring', MonitoringReport::exportHeadings(), MonitoringReport::exportRows($cycles));

            return response()->download($path, 'monitoring-evaluasi-'.str_replace(' ', '-', MonitoringReport::periodLabel($filters['period'])).'.xlsx')->deleteFileAfterSend();
        }

        return view('reports.print', [
            'title' => $title,
            'headings' => MonitoringReport::exportHeadings(),
            'rows' => MonitoringReport::exportRows($cycles),
        ]);
    }

    /**
     * @return array{period: string|null, vendor_type: string|null, status: string|null, search: string|null}
     */
    private function filters(Request $request, ?string $defaultPeriod): array
    {
        $validated = $request->validate([
            'period' => ['nullable', 'string', 'max:30'],
            'vendor_type' => ['nullable', 'in:'.implode(',', array_column(VendorType::cases(), 'value'))],
            'status' => ['nullable', 'string', 'max:30'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        return [
            'period' => $request->has('period') ? ($validated['period'] ?? null) : $defaultPeriod,
            'vendor_type' => $validated['vendor_type'] ?? null,
            'status' => $validated['status'] ?? null,
            'search' => $validated['search'] ?? null,
        ];
    }
}
