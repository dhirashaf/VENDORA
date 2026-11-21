@extends('layouts.app')

@section('title', 'Tindak Lanjut')

@section('content')
<x-page-header title="Tindak Lanjut Vendor" :breadcrumb="[['label' => 'Beranda', 'url' => route('dashboard')], ['label' => 'Tindak Lanjut']]" />
<p class="text-muted mt-n2">Kasus dibuka otomatis saat evaluasi dengan skor di bawah standar disetujui, lalu dilacak mengikuti alur SOP Vendor Evaluation &amp; Development.</p>

<div class="card">
    <div class="card-body">
        <form method="GET" class="row mb-3">
            <div class="col-md-4">
                <select name="status" class="form-control" aria-label="Status">
                    <option value="">Semua status</option>
                    @foreach (\App\Enums\FollowupStatus::options() as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6"><input type="search" name="search" class="form-control" value="{{ $filters['search'] ?? '' }}" placeholder="No. kasus atau nama vendor" aria-label="Cari"></div>
            <div class="col-md-2"><button class="btn btn-outline-secondary btn-block">Filter</button></div>
        </form>
        <div class="table-responsive">
            <table class="table table-sm table-hover table-bordered">
                <thead class="thead-light"><tr><th>No. Kasus</th><th>Vendor</th><th>Evaluasi Terkait</th><th>Kategori NC</th><th>Status</th><th>PIC</th><th>Tenggat</th><th>Keputusan</th><th></th></tr></thead>
                <tbody>
                    @forelse ($followUps as $followUp)
                        <tr>
                            <td class="text-nowrap">{{ $followUp->followup_no }}</td>
                            <td>{{ $followUp->vendor->name }}</td>
                            <td>{{ $followUp->evaluation->cycle->period_label }} · skor {{ number_format((float) $followUp->evaluation->total_weighted_score, 2, ',', '.') }}</td>
                            <td>{{ $followUp->nc_category?->label() ?? '-' }}</td>
                            <td><x-status-badge :status="$followUp->status" /></td>
                            <td>{{ $followUp->pic->full_name }}</td>
                            <td class="text-nowrap {{ $followUp->due_date && $followUp->due_date->isPast() && ! $followUp->isLocked() ? 'text-danger font-weight-bold' : '' }}">{{ $followUp->due_date?->format('d M Y') ?? '-' }}</td>
                            <td>
                                @if ($followUp->statusDecision)
                                    <x-status-badge :status="$followUp->statusDecision->decision" />
                                    @unless ($followUp->statusDecision->isApproved())<br><small class="text-warning">menunggu approval</small>@endunless
                                @else - @endif
                            </td>
                            <td><a href="{{ route('follow-ups.show', $followUp) }}" class="btn btn-xs btn-outline-primary">Buka</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="text-center text-muted py-4">Belum ada kasus tindak lanjut.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $followUps->links('pagination::bootstrap-4') }}
    </div>
</div>
@endsection
