@extends('layouts.app')

@section('title', 'Form Evaluasi')

@section('content')
<x-page-header title="Form Evaluasi" :breadcrumb="[['label' => 'Beranda', 'url' => route('dashboard')], ['label' => 'Form Evaluasi']]" />

<div class="card">
    <div class="card-header">
        <h3 class="card-title">{{ auth()->user()->can('view-all-evaluations') ? 'Seluruh penugasan penilai' : 'Penugasan penilaian Anda' }}</h3>
        <div class="card-tools">
            <form method="GET" class="form-inline">
                <select name="status" class="form-control form-control-sm" onchange="this.form.submit()" aria-label="Filter status">
                    <option value="">Belum submit</option>
                    @foreach (\App\Enums\AssignmentStatus::options() as $value => $label)
                        <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </form>
        </div>
    </div>
    <div class="card-body table-responsive p-0">
        <table class="table table-hover table-sm mb-0">
            <thead class="thead-light"><tr><th>Vendor</th><th>Periode</th><th>Fungsi</th>@can('view-all-evaluations')<th>Penilai</th>@endcan<th>Tenggat</th><th>Status</th><th style="width: 160px;">Progres</th><th></th></tr></thead>
            <tbody>
                @forelse ($assignments as $assignment)
                    @php $remaining = $assignment->cycle->remainingDays(); @endphp
                    <tr>
                        <td>{{ $assignment->cycle->vendor->name }}<br><small class="text-muted">{{ $assignment->cycle->cycle_code }}</small></td>
                        <td>{{ $assignment->cycle->period_label }}</td>
                        <td>{{ $assignment->department->label() }}</td>
                        @can('view-all-evaluations')<td>{{ $assignment->assessor->full_name }}</td>@endcan
                        <td class="text-nowrap">
                            {{ $assignment->cycle->due_date->format('d M Y') }}
                            @unless ($assignment->isSubmitted())
                                <br><small class="{{ $remaining < 0 ? 'text-danger' : ($remaining <= 3 ? 'text-warning' : 'text-muted') }}">{{ $remaining < 0 ? abs($remaining).' hari terlewat' : $remaining.' hari lagi' }}</small>
                            @endunless
                        </td>
                        <td><x-status-badge :status="$assignment->status" /></td>
                        <td>
                            <div class="progress progress-xs mb-1"><div class="progress-bar bg-{{ $assignment->isSubmitted() ? 'success' : 'warning' }}" style="width: {{ $assignment->progress_pct }}%"></div></div>
                            <small>{{ number_format((float) $assignment->progress_pct, 0) }}%</small>
                        </td>
                        <td class="text-nowrap">
                            <a href="{{ route('assessments.edit', $assignment) }}" class="btn btn-xs {{ auth()->user()->can('fill-assignment', $assignment) && ! $assignment->isSubmitted() ? 'btn-primary' : 'btn-outline-secondary' }}">
                                {{ auth()->user()->can('fill-assignment', $assignment) && ! $assignment->isSubmitted() && $assignment->cycle->isCollecting() ? 'Isi form' : 'Lihat' }}
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-muted py-4">Tidak ada form evaluasi yang perlu diisi. 🎉</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer">{{ $assignments->links('pagination::bootstrap-4') }}</div>
</div>
@endsection
