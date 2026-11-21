<?php

namespace App\Http\Controllers;

use App\Enums\CycleStatus;
use App\Enums\CycleType;
use App\Enums\EvaluationFrequency;
use App\Enums\RoleCode;
use App\Models\EvaluationAssignment;
use App\Models\EvaluationCycle;
use App\Models\FormTemplate;
use App\Models\User;
use App\Models\Vendor;
use App\Support\CycleScheduler;
use App\Support\EvaluationWorkflow;
use App\Support\MonitoringReport;
use App\Support\ReminderDispatcher;
use App\Support\ScoreCalculator;
use App\Support\Settings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Siklus evaluasi rutin & ad-hoc, penugasan penilai, kompilasi, dan approval (FR-05, FR-06, FR-09).
 */
class CycleController extends Controller
{
    public function __construct(private EvaluationWorkflow $workflow) {}

    public function index(Request $request): View
    {
        $user = $request->user();

        $cycles = EvaluationCycle::query()
            ->with(['vendor', 'evaluation', 'assignments'])
            ->when($user->cannot('view-all-evaluations'), fn (Builder $query) => $query->whereHas('assignments', fn (Builder $assignment) => $assignment->where('assessor_id', $user->id)))
            ->when($request->string('period')->toString(), fn (Builder $query, string $period) => $query->where('period_label', $period))
            ->when($request->string('status')->toString(), fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($request->string('cycle_type')->toString(), fn (Builder $query, string $type) => $query->where('cycle_type', $type))
            ->when($request->string('search')->toString(), fn (Builder $query, string $search) => $query->where(fn (Builder $match) => $match
                ->where('cycle_code', 'like', '%'.$search.'%')
                ->orWhereHas('vendor', fn (Builder $vendor) => $vendor->where('name', 'like', '%'.$search.'%'))))
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('cycles.index', [
            'cycles' => $cycles,
            'periods' => MonitoringReport::periods($user),
            'filters' => $request->only(['period', 'status', 'cycle_type', 'search']),
        ]);
    }

    public function create(Request $request): View
    {
        $formTemplate = FormTemplate::active();

        return view('cycles.create', [
            'vendors' => Vendor::query()->orderBy('name')->get(),
            'selectedVendorId' => $request->integer('vendor_id') ?: null,
            'departments' => $formTemplate ? $this->workflow->requiredDepartments($formTemplate) : collect(),
            'assessors' => User::query()->active()->withRole(RoleCode::Assessor)->orderBy('full_name')->get()->groupBy(fn (User $user): string => $user->department->value),
            'defaultAssessors' => CycleScheduler::defaultAssessors(),
            'defaultSlaDays' => Settings::integer(Settings::DEFAULT_SLA_DAYS),
            'formTemplate' => $formTemplate,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'vendor_id' => ['required', 'exists:vendors,id'],
            'cycle_type' => ['required', Rule::enum(CycleType::class)],
            'period_label' => ['nullable', 'string', 'max:30', 'regex:/^[A-Za-z0-9\-]+$/'],
            'adhoc_reason' => ['nullable', 'required_if:cycle_type,'.CycleType::AdHoc->value, 'string', 'max:1000'],
            'start_date' => ['required', 'date'],
            'sla_days' => ['required', 'integer', 'min:1', 'max:90'],
            'assessors' => ['required', 'array'],
            'assessors.*' => ['required', 'integer', Rule::exists('users', 'id')->where('is_active', true)],
            'send_now' => ['boolean'],
        ], [
            'adhoc_reason.required_if' => 'Alasan evaluasi ad-hoc wajib diisi (mis. masalah vendor atau menjelang perpanjangan kontrak).',
        ]);

        $vendor = Vendor::query()->findOrFail($data['vendor_id']);
        $startDate = Carbon::parse($data['start_date']);
        $cycleType = CycleType::from($data['cycle_type']);
        $data['period_label'] = strtoupper(($data['period_label'] ?? null) ?: match ($cycleType) {
            CycleType::AdHoc => CycleScheduler::adHocPeriodLabel($startDate),
            CycleType::Semester => CycleScheduler::periodLabel(EvaluationFrequency::Semester, $startDate),
            CycleType::Annual => CycleScheduler::periodLabel(EvaluationFrequency::Annual, $startDate),
        });

        $cycle = $this->workflow->createCycle($vendor, $data, $data['assessors'], $request->user(), $request->boolean('send_now', true));

        $message = $cycle->status === CycleStatus::Open
            ? 'Siklus dibuat dan form telah dikirim ke penilai.'
            : 'Siklus dijadwalkan. Form akan dikirim otomatis pada tanggal mulai.';

        return redirect()->route('cycles.show', $cycle)->with('success', $message);
    }

    public function show(EvaluationCycle $cycle): View
    {
        Gate::authorize('view-cycle', $cycle);

        $cycle->load(['vendor', 'creator', 'evaluation.compiler', 'evaluation.approver', 'evaluation.scores', 'evaluation.followUps',
            'assignments.assessor', 'assignments.reminderLogs.rule', 'formTemplate.categories.items']);

        return view('cycles.show', [
            'cycle' => $cycle,
            'categoryScores' => $cycle->evaluation ? ScoreCalculator::categoryScores($cycle->evaluation, $cycle->formTemplate->categories) : [],
            'scoresByItem' => $cycle->evaluation?->scores->keyBy('item_id') ?? collect(),
            'enforceDependency' => Settings::boolean(Settings::ENFORCE_DEPENDENCY),
        ]);
    }

    /**
     * Buat siklus rutin periode berjalan untuk seluruh strategic vendor aktif (FR-05).
     */
    public function generate(Request $request, CycleScheduler $scheduler): RedirectResponse
    {
        $result = $scheduler->generate($request->user());

        return redirect()->route('cycles.index')->with('success', "{$result['created']} siklus rutin dibuat, {$result['skipped']} vendor sudah memiliki siklus periode ini.");
    }

    public function send(EvaluationCycle $cycle, Request $request): RedirectResponse
    {
        $this->workflow->sendForms($cycle, $request->user());

        return back()->with('success', 'Form evaluasi dikirim ke seluruh penilai.');
    }

    public function remind(EvaluationAssignment $assignment, Request $request, ReminderDispatcher $reminders): RedirectResponse
    {
        abort_if($assignment->isSubmitted(), 422, 'Penilai ini sudah submit.');
        $reminders->sendManual($assignment->load(['assessor', 'cycle.vendor']), $request->user());

        return back()->with('success', 'Reminder dikirim ke '.$assignment->assessor->full_name.'.');
    }

    public function reopen(EvaluationAssignment $assignment, Request $request): RedirectResponse
    {
        Gate::authorize('reopen-assignment', $assignment);
        $this->workflow->reopenAssignment($assignment, $request->user());

        return back()->with('success', 'Form dibuka kembali untuk revisi.');
    }

    public function submitForApproval(EvaluationCycle $cycle, Request $request): RedirectResponse
    {
        $this->workflow->submitForApproval($cycle, $request->user());

        return back()->with('success', 'Hasil evaluasi dikompilasi dan diajukan ke PVM Manager.');
    }

    public function approve(EvaluationCycle $cycle, Request $request): RedirectResponse
    {
        $data = $request->validate(['approval_notes' => ['nullable', 'string', 'max:2000']]);
        $this->workflow->approve($cycle, $request->user(), $data['approval_notes'] ?? null);

        $followUp = $cycle->evaluation->followUps()->first();

        return $followUp
            ? redirect()->route('follow-ups.show', $followUp)->with('warning', 'Evaluasi disetujui. Skor di bawah standar: kasus tindak lanjut '.$followUp->followup_no.' dibuka otomatis.')
            : back()->with('success', 'Hasil evaluasi disetujui.');
    }

    public function returnForRevision(EvaluationCycle $cycle, Request $request): RedirectResponse
    {
        $data = $request->validate(['approval_notes' => ['required', 'string', 'max:2000']]);
        $this->workflow->returnForRevision($cycle, $request->user(), $data['approval_notes']);

        return back()->with('success', 'Hasil evaluasi dikembalikan ke Vendor Management.');
    }

    public function cancel(EvaluationCycle $cycle, Request $request): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $this->workflow->cancel($cycle, $request->user(), $data['reason']);

        return back()->with('success', 'Siklus evaluasi dibatalkan.');
    }
}
