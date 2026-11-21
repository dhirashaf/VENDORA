@extends('layouts.app')

@section('title', $cycle->cycle_code)

@php
    use App\Enums\CycleStatus;
    $evaluation = $cycle->evaluation;
    $pendingAssignments = $cycle->assignments->reject->isSubmitted();
    $canSubmitForApproval = auth()->user()->can('manage-cycles')
        && ($cycle->status === CycleStatus::Compiled || (! $enforceDependency && $cycle->isCollecting()));
@endphp

@section('content')
<x-page-header :title="'Siklus '.$cycle->cycle_code" :breadcrumb="[['label' => 'Siklus Evaluasi', 'url' => route('cycles.index')], ['label' => $cycle->cycle_code]]" />

<div class="row">
    <div class="col-lg-4">
        <div class="card card-outline card-primary">
            <div class="card-header"><h3 class="card-title">{{ $cycle->vendor->name }}</h3></div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <tr><th>Kode vendor</th><td>{{ $cycle->vendor->vendor_code }}</td></tr>
                    <tr><th>Jenis / kategori</th><td>{{ $cycle->vendor->vendor_type->label() }} / {{ $cycle->vendor->vendor_category->label() }}</td></tr>
                    <tr><th>Periode</th><td>{{ $cycle->period_label }} ({{ $cycle->cycle_type->label() }})</td></tr>
                    @if ($cycle->adhoc_reason)<tr><th>Alasan ad-hoc</th><td>{{ $cycle->adhoc_reason }}</td></tr>@endif
                    <tr><th>Mulai</th><td>{{ $cycle->start_date->format('d M Y') }}</td></tr>
                    <tr><th>Tenggat</th><td class="{{ $cycle->isOverdue() ? 'text-danger font-weight-bold' : '' }}">{{ $cycle->due_date->format('d M Y') }} (SLA {{ $cycle->sla_days }} hari)</td></tr>
                    <tr><th>Aging</th><td>{{ $cycle->agingDays() }} hari</td></tr>
                    <tr><th>Status</th><td>@if ($cycle->isOverdue())<x-status-badge status="Lewat Tenggat" /> @endif<x-status-badge :status="$cycle->status" /></td></tr>
                    <tr><th>Dibuat oleh</th><td>{{ $cycle->creator->full_name }}</td></tr>
                    <tr><th>Form</th><td>{{ $cycle->formTemplate->name }} {{ $cycle->formTemplate->version }}</td></tr>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h3 class="card-title">Ringkasan Hasil (FR-15)</h3></div>
            <div class="card-body text-center">
                @if ($evaluation?->is_complete)
                    <div class="display-4 font-weight-bold">{{ number_format((float) $evaluation->total_weighted_score, 2, ',', '.') }}</div>
                    <div class="text-muted mb-2">Total weighted score dari 4,00 &middot; pencapaian {{ number_format((float) $evaluation->achievement_pct, 2, ',', '.') }}%</div>
                    <x-status-badge :status="$evaluation->recommendation" class="p-2" />
                @else
                    <div class="h4 text-muted">Belum dapat dihitung</div>
                    <p class="small mb-0">Menunggu penilai: <strong class="text-danger">{{ $pendingAssignments->map(fn ($a) => $a->department->label().' ('.$a->assessor->full_name.')')->join(', ') ?: '-' }}</strong></p>
                @endif
                @if ($evaluation?->compiled_at)
                    <p class="small text-muted mt-3 mb-0">Dikompilasi {{ $evaluation->compiler?->full_name }} pada {{ $evaluation->compiled_at->format('d M Y H:i') }}</p>
                @endif
                @if ($evaluation?->approved_at)
                    <p class="small text-muted mb-0">Disetujui {{ $evaluation->approver?->full_name }} pada {{ $evaluation->approved_at->format('d M Y H:i') }}</p>
                @endif
                @if ($evaluation?->approval_notes)
                    <div class="alert alert-light small text-left mt-2 mb-0"><strong>Catatan:</strong> {{ $evaluation->approval_notes }}</div>
                @endif
                @foreach ($evaluation?->followUps ?? [] as $followUp)
                    <a href="{{ route('follow-ups.show', $followUp) }}" class="btn btn-sm btn-outline-danger mt-2"><i class="fas fa-tools"></i> Tindak lanjut {{ $followUp->followup_no }}</a>
                @endforeach
            </div>
        </div>

        <div class="card no-print">
            <div class="card-header"><h3 class="card-title">Aksi</h3></div>
            <div class="card-body">
                @can('manage-cycles')
                    @if ($cycle->status === CycleStatus::Scheduled)
                        <form method="POST" action="{{ route('cycles.send', $cycle) }}" class="mb-2">@csrf
                            <button class="btn btn-primary btn-block"><i class="fas fa-paper-plane"></i> Kirim Form ke Penilai Sekarang</button>
                        </form>
                    @endif
                    @if ($canSubmitForApproval)
                        <form method="POST" action="{{ route('cycles.submit-approval', $cycle) }}" class="mb-2" @if (! $evaluation?->is_complete) data-confirm="Dependency nonaktif: hasil belum lengkap dan akan diajukan berstatus 'Belum Dapat Dihitung'. Lanjutkan?" @endif>@csrf
                            <button class="btn btn-success btn-block"><i class="fas fa-layer-group"></i> Kompilasi &amp; Ajukan Approval</button>
                        </form>
                    @elseif ($cycle->isCollecting())
                        <p class="small text-muted"><i class="fas fa-lock"></i> Dependency aktif: pengajuan approval ditahan sampai seluruh penilai submit (FR-09).</p>
                    @endif
                @endcan
                @can('approve-evaluation')
                    @if ($cycle->status === CycleStatus::PendingApproval)
                        <form method="POST" action="{{ route('cycles.approve', $cycle) }}" class="mb-2">@csrf
                            <textarea name="approval_notes" class="form-control mb-2" rows="2" placeholder="Catatan approval (opsional)" aria-label="Catatan approval"></textarea>
                            <button class="btn btn-success btn-block"><i class="fas fa-check-circle"></i> Approve Hasil Evaluasi</button>
                        </form>
                        <form method="POST" action="{{ route('cycles.return', $cycle) }}" class="mb-2">@csrf
                            <textarea name="approval_notes" class="form-control mb-2" rows="2" placeholder="Alasan dikembalikan (wajib)" aria-label="Alasan dikembalikan" required></textarea>
                            <button class="btn btn-outline-warning btn-block"><i class="fas fa-undo"></i> Kembalikan ke VM</button>
                        </form>
                    @endif
                @endcan
                @can('manage-cycles')
                    @if (! in_array($cycle->status, [CycleStatus::Approved, CycleStatus::Closed, CycleStatus::Cancelled], true))
                        <form method="POST" action="{{ route('cycles.cancel', $cycle) }}" data-confirm="Batalkan siklus evaluasi ini?">@csrf
                            <div class="input-group input-group-sm">
                                <input name="reason" class="form-control" placeholder="Alasan pembatalan" aria-label="Alasan pembatalan" required>
                                <div class="input-group-append"><button class="btn btn-outline-danger">Batalkan</button></div>
                            </div>
                        </form>
                    @endif
                @endcan
                @if ($cycle->status === CycleStatus::Approved || $cycle->status === CycleStatus::Closed || $cycle->status === CycleStatus::Cancelled)
                    <p class="small text-muted mb-0">Siklus telah selesai; data terkunci untuk keterlacakan.</p>
                @endif
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card">
            <div class="card-header"><h3 class="card-title">Penugasan Penilai (FR-06, FR-10, FR-14)</h3></div>
            <div class="card-body table-responsive p-0">
                <table class="table table-sm table-hover mb-0">
                    <thead class="thead-light"><tr><th>Fungsi</th><th>Penilai</th><th>Status</th><th style="width: 140px;">Progres</th><th>Dikirim</th><th>Submit</th><th class="text-center">Reminder</th><th class="no-print">Aksi</th></tr></thead>
                    <tbody>
                        @foreach ($cycle->assignments as $assignment)
                            <tr>
                                <td>{{ $assignment->department->label() }}</td>
                                <td>{{ $assignment->assessor->full_name }}</td>
                                <td><x-status-badge :status="$assignment->status" /></td>
                                <td>
                                    <div class="progress progress-xs mb-1"><div class="progress-bar bg-{{ $assignment->isSubmitted() ? 'success' : 'warning' }}" style="width: {{ $assignment->progress_pct }}%"></div></div>
                                    <small>{{ number_format((float) $assignment->progress_pct, 0) }}%</small>
                                </td>
                                <td class="small">{{ $assignment->sent_at?->format('d M H:i') ?? '-' }}</td>
                                <td class="small">{{ $assignment->submitted_at?->format('d M H:i') ?? '-' }}</td>
                                <td class="text-center">{{ $assignment->reminderLogs->where('channel', \App\Enums\Channel::Email)->count() }}</td>
                                <td class="text-nowrap no-print">
                                    @can('view-assignment', $assignment)
                                        <a href="{{ route('assessments.edit', $assignment) }}" class="btn btn-xs btn-outline-secondary">{{ auth()->user()->can('fill-assignment', $assignment) && ! $assignment->isSubmitted() && $cycle->isCollecting() ? 'Isi form' : 'Lihat' }}</a>
                                    @endcan
                                    @can('manage-cycles')
                                        @if (! $assignment->isSubmitted() && $cycle->isCollecting())
                                            <form method="POST" action="{{ route('assignments.remind', $assignment) }}" class="d-inline">@csrf
                                                <button class="btn btn-xs btn-warning">Ingatkan</button>
                                            </form>
                                        @endif
                                    @endcan
                                    @if ($assignment->isSubmitted() && in_array($cycle->status, [CycleStatus::InProgress, CycleStatus::Compiled], true) && auth()->user()->can('reopen-assignment', $assignment))
                                        <form method="POST" action="{{ route('assignments.reopen', $assignment) }}" class="d-inline" data-confirm="Buka kembali form ini untuk revisi?">@csrf
                                            <button class="btn btn-xs btn-outline-warning">Buka kembali</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h3 class="card-title">Skor per Kategori & Item</h3></div>
            <div class="card-body table-responsive p-0">
                <table class="table table-sm mb-0">
                    <thead class="thead-light"><tr><th>Item</th><th>Fungsi Penilai</th><th class="text-right">Bobot</th><th class="text-center">Skor</th><th class="text-right">Skor Berbobot</th><th>Catatan / Bukti</th></tr></thead>
                    <tbody>
                        @foreach ($cycle->formTemplate->categories as $category)
                            <tr class="table-primary">
                                <td colspan="2"><strong>{{ $category->code }}. {{ $category->name }}</strong></td>
                                <td class="text-right"><strong>{{ number_format($category->items->sum('weight_pct'), 0) }}%</strong></td>
                                <td class="text-center" colspan="2"><strong>{{ isset($categoryScores[$category->code]) ? 'rata-rata '.number_format($categoryScores[$category->code], 2, ',', '.') : '-' }}</strong></td>
                                <td></td>
                            </tr>
                            @foreach ($category->items as $item)
                                @php $score = $scoresByItem->get($item->id); @endphp
                                <tr>
                                    <td>{{ $item->item_no }}. {{ $item->description }}</td>
                                    <td>{{ $item->assessor_department->label() }}</td>
                                    <td class="text-right">{{ number_format((float) $item->weight_pct, 0) }}%</td>
                                    <td class="text-center">{{ $score?->score ?? '-' }}</td>
                                    <td class="text-right">{{ $score ? number_format((float) $score->weighted_score, 2, ',', '.') : '-' }}</td>
                                    <td class="small">{{ $score?->notes }} @if ($score?->evidence_url)<a href="{{ $score->evidence_url }}" target="_blank" rel="noopener noreferrer">bukti</a>@endif</td>
                                </tr>
                            @endforeach
                        @endforeach
                    </tbody>
                    @if ($evaluation?->is_complete)
                        <tfoot><tr class="font-weight-bold"><td colspan="4" class="text-right">Total weighted score</td><td class="text-right">{{ number_format((float) $evaluation->total_weighted_score, 2, ',', '.') }}</td><td></td></tr></tfoot>
                    @endif
                </table>
            </div>
        </div>

        @can('manage-cycles')
            @php $logs = $cycle->assignments->flatMap->reminderLogs->where('channel', \App\Enums\Channel::Email)->sortByDesc('scheduled_at'); @endphp
            <div class="card collapsed-card">
                <div class="card-header">
                    <h3 class="card-title">Log Reminder & Notifikasi ({{ $logs->count() }})</h3>
                    <div class="card-tools"><button type="button" class="btn btn-tool" data-card-widget="collapse" aria-label="Buka"><i class="fas fa-plus"></i></button></div>
                </div>
                <div class="card-body table-responsive p-0">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>Waktu</th><th>Aturan</th><th>Penerima</th><th>Status</th></tr></thead>
                        <tbody>
                            @forelse ($logs as $log)
                                <tr>
                                    <td class="small">{{ $log->scheduled_at->format('d M Y H:i') }}</td>
                                    <td>{{ $log->rule->name }} @if ($log->is_escalation)<span class="badge badge-danger">Eskalasi</span>@endif</td>
                                    <td>{{ $cycle->assignments->firstWhere('id', $log->assignment_id)?->assessor->id === $log->recipient_id ? $cycle->assignments->firstWhere('id', $log->assignment_id)->assessor->full_name : 'Vendor Management' }}</td>
                                    <td><x-status-badge :status="$log->status" /></td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-muted text-center">Belum ada reminder.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endcan
    </div>
</div>
@endsection
