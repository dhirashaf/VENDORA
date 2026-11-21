@extends('layouts.app')

@section('title', 'Siklus Evaluasi')

@section('content')
<x-page-header title="Siklus Evaluasi" :breadcrumb="[['label' => 'Beranda', 'url' => route('dashboard')], ['label' => 'Siklus Evaluasi']]" />

<div class="card">
    <div class="card-header">
        <h3 class="card-title">Jadwal evaluasi rutin (tahunan/semesteran) dan ad-hoc</h3>
        @can('manage-cycles')
            <div class="card-tools d-flex">
                <form method="POST" action="{{ route('cycles.generate') }}" data-confirm="Buat siklus rutin periode berjalan untuk seluruh strategic vendor aktif yang belum memiliki siklus? Form akan langsung dikirim ke penilai." class="mr-1">
                    @csrf
                    <button class="btn btn-outline-primary btn-sm" type="submit"><i class="fas fa-magic"></i> Generate Siklus Rutin Periode Ini</button>
                </form>
                <a href="{{ route('cycles.create') }}" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Siklus Baru / Ad-hoc</a>
            </div>
        @endcan
    </div>
    <div class="card-body">
        <form method="GET" class="row mb-3">
            <div class="col-md-3">
                <select name="period" class="form-control" aria-label="Periode">
                    <option value="">Semua periode</option>
                    @foreach ($periods as $period)
                        <option value="{{ $period }}" @selected(($filters['period'] ?? '') === $period)>{{ $period }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="cycle_type" class="form-control" aria-label="Tipe siklus">
                    <option value="">Semua tipe</option>
                    @foreach (\App\Enums\CycleType::options() as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['cycle_type'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <select name="status" class="form-control" aria-label="Status siklus">
                    <option value="">Semua status</option>
                    @foreach (\App\Enums\CycleStatus::options() as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3"><input type="search" name="search" class="form-control" value="{{ $filters['search'] ?? '' }}" placeholder="Kode siklus / vendor" aria-label="Cari"></div>
            <div class="col-md-1"><button class="btn btn-outline-secondary btn-block">Filter</button></div>
        </form>

        <div class="table-responsive">
            <table class="table table-sm table-hover table-bordered">
                <thead class="thead-light"><tr><th>Kode Siklus</th><th>Vendor</th><th>Periode</th><th>Tipe</th><th>Mulai</th><th>Tenggat (SLA)</th><th>Progres Penilai</th><th>Status</th><th class="text-right">Skor</th><th></th></tr></thead>
                <tbody>
                    @forelse ($cycles as $cycle)
                        @php $submitted = $cycle->assignments->filter->isSubmitted()->count(); @endphp
                        <tr>
                            <td class="text-nowrap">{{ $cycle->cycle_code }}</td>
                            <td>{{ $cycle->vendor->name }}</td>
                            <td>{{ $cycle->period_label }}</td>
                            <td>{{ $cycle->cycle_type->label() }}@if ($cycle->adhoc_reason)<br><small class="text-muted">{{ \Illuminate\Support\Str::limit($cycle->adhoc_reason, 40) }}</small>@endif</td>
                            <td class="text-nowrap">{{ $cycle->start_date->format('d M Y') }}</td>
                            <td class="text-nowrap {{ $cycle->isOverdue() ? 'text-danger font-weight-bold' : '' }}">{{ $cycle->due_date->format('d M Y') }} <small class="text-muted">({{ $cycle->sla_days }} hr)</small></td>
                            <td>{{ $submitted }}/{{ $cycle->assignments->count() }} submit</td>
                            <td>@if ($cycle->isOverdue())<x-status-badge status="Lewat Tenggat" />@else<x-status-badge :status="$cycle->status" />@endif</td>
                            <td class="text-right">{{ $cycle->evaluation?->total_weighted_score !== null ? number_format((float) $cycle->evaluation->total_weighted_score, 2, ',', '.') : '-' }}</td>
                            <td><a href="{{ route('cycles.show', $cycle) }}" class="btn btn-xs btn-outline-secondary">Detail</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="10" class="text-center text-muted py-4">Belum ada siklus evaluasi.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $cycles->links('pagination::bootstrap-4') }}
    </div>
</div>
@endsection
