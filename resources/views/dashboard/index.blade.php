@extends('layouts.app')

@section('title', 'Dashboard Monitoring')

@section('content')
<x-page-header title="Dashboard Monitoring" :breadcrumb="[['label' => 'Beranda', 'url' => route('dashboard')], ['label' => 'Dashboard']]" />
<p class="text-muted mt-n2">Pantau status pengisian, tenggat, dan umur (aging) tiap evaluasi.</p>

@if ($myTasks->isNotEmpty())
    <div class="callout callout-warning">
        <h5><i class="fas fa-clipboard-list mr-1"></i> Anda memiliki {{ $myTasks->count() }} form evaluasi yang belum disubmit</h5>
        <ul class="mb-0">
            @foreach ($myTasks as $task)
                <li>
                    <a href="{{ route('assessments.edit', $task) }}">{{ $task->cycle->vendor->name }}</a>
                    &middot; {{ $task->cycle->period_label }} &middot; progres {{ number_format((float) $task->progress_pct, 0) }}%
                    &middot; tenggat {{ $task->cycle->due_date->format('d M Y') }}
                    @if ($task->cycle->remainingDays() < 0)
                        <span class="badge badge-danger">lewat {{ abs($task->cycle->remainingDays()) }} hari</span>
                    @else
                        <span class="badge badge-light">{{ $task->cycle->remainingDays() }} hari lagi</span>
                    @endif
                </li>
            @endforeach
        </ul>
    </div>
@endif

<div class="row">
    <div class="col-lg-3 col-md-6">
        <x-stat-card title="Total Vendor" :value="$vendorCounts['total']" icon="fas fa-building" color="primary"
            :footer="$vendorCounts['trade'].' Trade / '.$vendorCounts['non_trade'].' Non-Trade'" />
    </div>
    <div class="col-lg-3 col-md-6">
        <x-stat-card title="Evaluasi Selesai (Approved)" :value="$summary['completed'].' ('.$summary['completed_pct'].'%)'" icon="fas fa-check-double" color="success"
            :footer="'dari '.$summary['total'].' evaluasi, '.\App\Support\MonitoringReport::periodLabel($filters['period'])" />
    </div>
    <div class="col-lg-3 col-md-6">
        <x-stat-card title="Belum Lengkap" :value="$summary['incomplete']" icon="fas fa-file-alt" color="warning"
            :footer="$summary['pending_approval'].' menunggu approval'" />
    </div>
    <div class="col-lg-3 col-md-6">
        <x-stat-card title="Lewat Tenggat" :value="$summary['overdue']" icon="fas fa-clock" color="danger"
            :footer="'Tepat waktu: '.$summary['on_time_pct'].'% (target ≥ 90%)'.($openFollowUps !== null ? ' · '.$openFollowUps.' tindak lanjut aktif' : '')" />
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">Status evaluasi per vendor, {{ \App\Support\MonitoringReport::periodLabel($filters['period']) }}</h3>
        <div class="card-tools">
            @can('manage-cycles')
                <a href="{{ route('cycles.create') }}" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Siklus Evaluasi</a>
            @endcan
            @can('export-reports')
                <a href="{{ route('dashboard.export', ['format' => 'xlsx'] + array_filter($filters)) }}" class="btn btn-success btn-sm"><i class="fas fa-file-excel"></i> Ekspor Excel</a>
                <a href="{{ route('dashboard.export', ['format' => 'pdf'] + array_filter($filters)) }}" target="_blank" class="btn btn-danger btn-sm"><i class="fas fa-file-pdf"></i> Ekspor PDF</a>
            @endcan
        </div>
    </div>
    <div class="card-body">
        <form method="GET" action="{{ route('dashboard') }}" class="row mb-3">
            <div class="col-md-3">
                <label for="period">Periode</label>
                <select id="period" name="period" class="form-control" onchange="this.form.submit()">
                    <option value="">Semua periode</option>
                    <option value="{{ \App\Support\MonitoringReport::CURRENT_PERIOD }}" @selected($filters['period'] === \App\Support\MonitoringReport::CURRENT_PERIOD)>Periode berjalan (aktif)</option>
                    @foreach ($periods as $period)
                        <option value="{{ $period }}" @selected($filters['period'] === $period)>{{ $period }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label for="vendor_type">Kategori</label>
                <select id="vendor_type" name="vendor_type" class="form-control" onchange="this.form.submit()">
                    <option value="">Semua</option>
                    @foreach (\App\Enums\VendorType::options() as $value => $label)
                        <option value="{{ $value }}" @selected($filters['vendor_type'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label for="status">Status</label>
                <select id="status" name="status" class="form-control" onchange="this.form.submit()">
                    <option value="">Semua</option>
                    @foreach ($statusOptions as $value => $label)
                        <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label for="search">Cari vendor</label>
                <div class="input-group">
                    <input id="search" type="search" name="search" class="form-control" value="{{ $filters['search'] }}" placeholder="Nama atau kode vendor">
                    <div class="input-group-append"><button class="btn btn-outline-secondary" type="submit" aria-label="Cari"><i class="fas fa-search"></i></button></div>
                </div>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-bordered table-hover table-sm">
                <thead class="thead-light">
                    <tr>
                        <th>Vendor</th><th>Kategori</th><th>Penilai Belum Submit</th><th>Status</th>
                        <th>Tenggat</th><th class="text-center">Aging</th><th class="text-right">Skor</th><th>Pencapaian</th><th>Rekomendasi</th><th>Status Vendor</th><th class="no-print">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($cycles as $cycle)
                        @php
                            $evaluation = $cycle->evaluation;
                            $pending = \App\Support\MonitoringReport::pendingDepartments($cycle);
                            $remaining = $cycle->remainingDays();
                            $myAssignment = $cycle->assignments->firstWhere('assessor_id', auth()->id());
                            $belowStandard = $evaluation?->total_weighted_score !== null && (float) $evaluation->total_weighted_score < \App\Support\Settings::decimal(\App\Support\Settings::RECOMMENDED_WITH_NOTES_MIN_SCORE);
                        @endphp
                        <tr>
                            <td>
                                <a href="{{ route('recap.vendor', $cycle->vendor) }}">{{ $cycle->vendor->name }}</a><br>
                                <small class="text-muted">{{ $cycle->vendor->vendor_code }} · {{ $cycle->cycle_type->label() }}</small>
                            </td>
                            <td>{{ $cycle->vendor->vendor_type->label() }}<br><small class="text-muted">{{ $cycle->vendor->vendor_category->label() }}</small></td>
                            <td>
                                @if ($pending && $cycle->status !== \App\Enums\CycleStatus::Cancelled)
                                    <span class="text-danger font-weight-bold">{{ $pending }} (belum)</span>
                                @else
                                    <span class="text-success">Lengkap</span>
                                @endif
                            </td>
                            <td>
                                @if ($cycle->isOverdue())
                                    <x-status-badge status="Lewat Tenggat" />
                                @elseif ($cycle->isCollecting())
                                    <x-status-badge status="Belum Lengkap" />
                                @else
                                    <x-status-badge :status="$cycle->status" />
                                @endif
                            </td>
                            <td class="text-nowrap">
                                {{ $cycle->due_date->format('d M Y') }}
                                @if ($cycle->isCollecting())
                                    <br><small class="{{ $remaining < 0 ? 'text-danger' : ($remaining <= 3 ? 'text-warning' : 'text-muted') }}">
                                        {{ $remaining < 0 ? abs($remaining).' hari terlewat' : $remaining.' hari lagi' }}
                                    </small>
                                @endif
                            </td>
                            <td class="text-center">{{ $cycle->agingDays() ? $cycle->agingDays().' hari' : '-' }}</td>
                            <td class="text-right">
                                @if ($evaluation?->total_weighted_score !== null)
                                    <span class="{{ $belowStandard ? 'text-danger font-weight-bold' : '' }}">{{ number_format((float) $evaluation->total_weighted_score, 2, ',', '.') }}</span>
                                    @if ($belowStandard)<span class="text-danger" title="Di bawah standar">(!)</span>@endif
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td style="min-width: 110px;">
                                @if ($evaluation?->achievement_pct !== null)
                                    <div class="progress progress-xs mb-1"><div class="progress-bar {{ $evaluation->achievement_pct < 62.5 ? 'bg-danger' : 'bg-success' }}" style="width: {{ $evaluation->achievement_pct }}%"></div></div>
                                    <small>{{ number_format((float) $evaluation->achievement_pct, 2, ',', '.') }}%</small>
                                @else
                                    <small class="text-muted">Belum dapat dihitung</small>
                                @endif
                            </td>
                            <td>@if ($evaluation)<x-status-badge :status="$evaluation->recommendation" />@endif</td>
                            <td><x-status-badge :status="$cycle->vendor->status" /></td>
                            <td class="text-nowrap no-print">
                                @if ($myAssignment && $cycle->isCollecting() && ! $myAssignment->isSubmitted())
                                    <a href="{{ route('assessments.edit', $myAssignment) }}" class="btn btn-xs btn-primary">Isi form</a>
                                @endif
                                @can('view-cycle', $cycle)
                                    <a href="{{ route('cycles.show', $cycle) }}" class="btn btn-xs btn-outline-secondary">
                                        {{ $cycle->status === \App\Enums\CycleStatus::PendingApproval && auth()->user()->can('approve-evaluation') ? 'Tinjau & Approve' : 'Detail' }}
                                    </a>
                                @endcan
                                @if ($evaluation && $evaluation->followUps->isNotEmpty() && auth()->user()->can('view-followups'))
                                    <a href="{{ route('follow-ups.show', $evaluation->followUps->first()) }}" class="btn btn-xs btn-outline-danger">Tindak lanjut</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="11" class="text-center text-muted py-4">Tidak ada evaluasi yang sesuai filter.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="d-flex justify-content-between align-items-center small text-muted mt-2">
            <span>
                <i class="fas fa-circle {{ $remindersEnabled ? 'text-success' : 'text-secondary' }}"></i>
                Reminder terjadwal {{ $remindersEnabled ? 'aktif' : 'nonaktif' }}
                @can('manage-master') · <a href="{{ route('settings.edit') }}">ubah</a>@endcan
            </span>
            <span>Tanda (!) = skor di bawah standar; saat disetujui otomatis membuka alur tindak lanjut.</span>
        </div>
    </div>
</div>
@endsection

@push('scripts')
@php
    $dueSoon = $cycles->filter(fn ($cycle) => $cycle->isCollecting() && $cycle->remainingDays() <= 3);
@endphp
@if ($remindersEnabled && $dueSoon->isNotEmpty() && auth()->user()->can('manage-cycles'))
<script>
    toastr.warning(@json('Reminder: '.$dueSoon->count().' evaluasi mendekati/melewati tenggat: '.$dueSoon->take(5)->pluck('vendor.name')->join(', ')));
</script>
@endif
@endpush
