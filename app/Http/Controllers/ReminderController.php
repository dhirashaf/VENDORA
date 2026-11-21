<?php

namespace App\Http\Controllers;

use App\Enums\Channel;
use App\Enums\ReminderTrigger;
use App\Models\ReminderLog;
use App\Models\ReminderRule;
use App\Models\ReminderTemplate;
use App\Models\Role;
use App\Support\AuditLogger;
use App\Support\EvaluationWorkflow;
use App\Support\ReminderDispatcher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Parameter jadwal dan template reminder yang dapat diubah tanpa ubah kode (FR-07, FR-08, NFR-03).
 */
class ReminderController extends Controller
{
    public function index(Request $request): View
    {
        return view('reminders.index', [
            'rules' => ReminderRule::query()->with(['template', 'escalateToRole'])->orderByDesc('is_active')->orderBy('trigger_type')->orderByDesc('offset_days')->get(),
            'templates' => ReminderTemplate::query()->orderBy('code')->get(),
            'roles' => Role::query()->orderBy('id')->get(),
            'logs' => ReminderLog::query()
                ->with(['recipient', 'rule', 'assignment.cycle.vendor'])
                ->when($request->string('status')->toString(), fn (Builder $query, string $status) => $query->where('status', $status))
                ->where('channel', Channel::Email->value)
                ->latest('scheduled_at')
                ->latest('id')
                ->paginate(15)
                ->withQueryString(),
            'placeholders' => ReminderDispatcher::placeholders(),
            'systemTemplates' => ReminderDispatcher::systemTemplates(),
        ]);
    }

    public function storeRule(Request $request): RedirectResponse
    {
        $rule = ReminderRule::query()->create($this->validatedRule($request));
        AuditLogger::record(AuditLogger::CREATE, $rule, null, $rule->only(['name', 'trigger_type', 'offset_days', 'repeat_every_days']));

        return back()->with('success', 'Aturan reminder ditambahkan.');
    }

    public function updateRule(Request $request, ReminderRule $rule): RedirectResponse
    {
        $rule->update($this->validatedRule($request));
        AuditLogger::recordChanges($rule);

        return back()->with('success', "Aturan \"{$rule->name}\" diperbarui.");
    }

    public function updateTemplate(Request $request, ReminderTemplate $template): RedirectResponse
    {
        $template->update($request->validate([
            'subject' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:5000'],
        ]));
        AuditLogger::recordChanges($template);

        return back()->with('success', "Template {$template->code} diperbarui.");
    }

    public function storeTemplate(Request $request): RedirectResponse
    {
        $template = ReminderTemplate::query()->create($request->validate([
            'code' => ['required', 'string', 'max:50', 'alpha_dash', 'unique:reminder_templates,code'],
            'subject' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:5000'],
        ]) + ['channel' => Channel::Email]);
        AuditLogger::record(AuditLogger::CREATE, $template, null, $template->only(['code', 'subject']));

        return back()->with('success', "Template {$template->code} ditambahkan.");
    }

    /**
     * Jalankan proses reminder harian sekarang (untuk simulasi/pengujian NFR-03).
     */
    public function run(ReminderDispatcher $dispatcher, EvaluationWorkflow $workflow): RedirectResponse
    {
        $opened = $dispatcher->openScheduledCycles($workflow);
        $result = $dispatcher->run();

        $message = $result['skipped']
            ? 'Reminder terjadwal sedang dinonaktifkan pada Pengaturan.'
            : "Reminder diproses: {$result['sent']} terkirim, {$result['failed']} gagal, {$result['overdue']} penugasan ditandai lewat tenggat, {$opened} siklus terjadwal dibuka.";

        return back()->with($result['skipped'] ? 'warning' : 'success', $message);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedRule(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'trigger_type' => ['required', Rule::enum(ReminderTrigger::class)],
            'offset_days' => ['required', 'integer', 'min:0', 'max:60'],
            'repeat_every_days' => ['nullable', 'integer', 'min:1', 'max:30'],
            'template_id' => ['required', 'exists:reminder_templates,id'],
            'escalate_to_role_id' => ['nullable', 'required_if:trigger_type,'.ReminderTrigger::Escalation->value, 'exists:roles,id'],
        ], [
            'escalate_to_role_id.required_if' => 'Aturan eskalasi wajib memilih peran tujuan eskalasi.',
        ]);

        return $data + ['is_active' => $request->boolean('is_active')];
    }
}
