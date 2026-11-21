@extends('layouts.app')

@section('title', 'Aturan Reminder')

@section('content')
<x-page-header title="Aturan & Template Reminder" :breadcrumb="[['label' => 'Beranda', 'url' => route('dashboard')], ['label' => 'Reminder']]" />
<p class="text-muted mt-n2">Jadwal reminder, eskalasi, dan template disimpan sebagai data sehingga dapat diubah tanpa perubahan kode (NFR-03). Usulan awal tim: H-7, H-3, H-1, tiap 2 hari setelah tenggat, eskalasi H+3 &mdash; divalidasi bersama perusahaan pada UAT.</p>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">Aturan jadwal</h3>
        <div class="card-tools">
            <form method="POST" action="{{ route('reminders.run') }}" data-confirm="Jalankan proses reminder harian sekarang?">@csrf
                <button class="btn btn-sm btn-warning"><i class="fas fa-play"></i> Jalankan Reminder Sekarang</button>
            </form>
        </div>
    </div>
    <div class="card-body table-responsive p-0">
        <table class="table table-sm mb-0">
            <thead class="thead-light"><tr><th>Nama</th><th>Pemicu</th><th>Hari (offset)</th><th>Ulang tiap</th><th>Template</th><th>Eskalasi ke</th><th>Aktif</th><th></th></tr></thead>
            <tbody>
                @foreach ($rules as $rule)
                    @php $isSystem = in_array($rule->template->code, $systemTemplates, true); @endphp
                    <tr>
                            <td><input form="rule-{{ $rule->id }}" name="name" class="form-control form-control-sm" value="{{ $rule->name }}" aria-label="Nama aturan" required></td>
                            <td>
                                <select form="rule-{{ $rule->id }}" name="trigger_type" class="form-control form-control-sm" aria-label="Pemicu">
                                    @foreach (\App\Enums\ReminderTrigger::options() as $value => $label)
                                        <option value="{{ $value }}" @selected($rule->trigger_type->value === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td style="width: 90px;"><input form="rule-{{ $rule->id }}" type="number" min="0" max="60" name="offset_days" class="form-control form-control-sm" value="{{ $rule->offset_days }}" aria-label="Offset hari"></td>
                            <td style="width: 90px;"><input form="rule-{{ $rule->id }}" type="number" min="1" max="30" name="repeat_every_days" class="form-control form-control-sm" value="{{ $rule->repeat_every_days }}" aria-label="Ulang tiap hari"></td>
                            <td>
                                <select form="rule-{{ $rule->id }}" name="template_id" class="form-control form-control-sm" aria-label="Template">
                                    @foreach ($templates as $template)
                                        <option value="{{ $template->id }}" @selected($rule->template_id === $template->id)>{{ $template->code }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                <select form="rule-{{ $rule->id }}" name="escalate_to_role_id" class="form-control form-control-sm" aria-label="Peran eskalasi">
                                    <option value="">-</option>
                                    @foreach ($roles as $role)
                                        <option value="{{ $role->id }}" @selected($rule->escalate_to_role_id === $role->id)>{{ $role->name }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="text-center">
                                @if ($isSystem)
                                    <span class="badge badge-light" title="Dipakai saat pengiriman form / tombol Ingatkan">Sistem</span>
                                    <input form="rule-{{ $rule->id }}" type="hidden" name="is_active" value="0">
                                @else
                                    <input form="rule-{{ $rule->id }}" type="checkbox" name="is_active" value="1" @checked($rule->is_active) aria-label="Aktif">
                                @endif
                            </td>
                            <td class="text-nowrap"><button form="rule-{{ $rule->id }}" class="btn btn-xs btn-outline-primary">Simpan</button> <small class="text-muted">{{ $isSystem ? '' : $rule->describeSchedule() }}</small></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        @foreach ($rules as $rule)
            <form method="POST" action="{{ route('reminders.rules.update', $rule) }}" id="rule-{{ $rule->id }}">@csrf @method('PUT')</form>
        @endforeach
    </div>
    <div class="card-footer">
        <form method="POST" action="{{ route('reminders.rules.store') }}" class="form-row align-items-end">
            @csrf
            <div class="col-md-3"><label class="small mb-0" for="new_rule_name">Aturan baru</label><input id="new_rule_name" name="name" class="form-control form-control-sm" placeholder="Nama aturan" required></div>
            <div class="col-md-2">
                <select name="trigger_type" class="form-control form-control-sm" aria-label="Pemicu">
                    @foreach (\App\Enums\ReminderTrigger::options() as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-1"><input type="number" name="offset_days" min="0" class="form-control form-control-sm" placeholder="Hari" aria-label="Offset hari" required></div>
            <div class="col-md-1"><input type="number" name="repeat_every_days" min="1" class="form-control form-control-sm" placeholder="Ulang" aria-label="Ulang tiap"></div>
            <div class="col-md-2">
                <select name="template_id" class="form-control form-control-sm" aria-label="Template">
                    @foreach ($templates as $template)<option value="{{ $template->id }}">{{ $template->code }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="escalate_to_role_id" class="form-control form-control-sm" aria-label="Peran eskalasi">
                    <option value="">Tanpa eskalasi</option>
                    @foreach ($roles as $role)<option value="{{ $role->id }}">{{ $role->name }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-1"><input type="hidden" name="is_active" value="1"><button class="btn btn-sm btn-primary btn-block">Tambah</button></div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3 class="card-title">Template pesan</h3></div>
    <div class="card-body">
        <p class="small text-muted">Placeholder: @foreach ($placeholders as $placeholder)<code>{{ '{'.$placeholder.'}' }}</code> @endforeach</p>
        <div class="row">
            @foreach ($templates as $template)
                <div class="col-lg-6">
                    <form method="POST" action="{{ route('reminders.templates.update', $template) }}" class="border rounded p-2 mb-3">
                        @csrf @method('PUT')
                        <strong>{{ $template->code }}</strong> <span class="badge badge-light">{{ $template->channel->label() }}</span>
                        <input name="subject" class="form-control form-control-sm my-1" value="{{ $template->subject }}" aria-label="Subjek {{ $template->code }}" required>
                        <textarea name="body" rows="5" class="form-control form-control-sm mb-1" aria-label="Isi {{ $template->code }}" required>{{ $template->body }}</textarea>
                        <button class="btn btn-xs btn-outline-primary">Simpan template</button>
                    </form>
                </div>
            @endforeach
        </div>
        <form method="POST" action="{{ route('reminders.templates.store') }}" class="form-row">
            @csrf
            <div class="col-md-3"><input name="code" class="form-control form-control-sm" placeholder="KODE_TEMPLATE" aria-label="Kode template" required></div>
            <div class="col-md-4"><input name="subject" class="form-control form-control-sm" placeholder="Subjek" aria-label="Subjek" required></div>
            <div class="col-md-4"><input name="body" class="form-control form-control-sm" placeholder="Isi pesan" aria-label="Isi" required></div>
            <div class="col-md-1"><button class="btn btn-sm btn-primary btn-block">Tambah</button></div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3 class="card-title">Log pengiriman email</h3></div>
    <div class="card-body table-responsive p-0">
        <table class="table table-sm mb-0">
            <thead class="thead-light"><tr><th>Waktu</th><th>Aturan</th><th>Penerima</th><th>Vendor / Siklus</th><th>Status</th><th>Error</th></tr></thead>
            <tbody>
                @forelse ($logs as $log)
                    <tr>
                        <td class="small text-nowrap">{{ $log->scheduled_at->format('d M Y H:i') }}</td>
                        <td>{{ $log->rule->name }} @if ($log->is_escalation)<span class="badge badge-danger">Eskalasi</span>@endif</td>
                        <td>{{ $log->recipient->full_name }}</td>
                        <td><a href="{{ route('cycles.show', $log->assignment->cycle) }}">{{ $log->assignment->cycle->vendor->name }}</a> <small class="text-muted">{{ $log->assignment->cycle->cycle_code }}</small></td>
                        <td><x-status-badge :status="$log->status" /></td>
                        <td class="small text-danger">{{ \Illuminate\Support\Str::limit($log->error_message, 60) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-muted text-center">Belum ada reminder terkirim.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer">{{ $logs->links('pagination::bootstrap-4') }}</div>
</div>
@endsection
