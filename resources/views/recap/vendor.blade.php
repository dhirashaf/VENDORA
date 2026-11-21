@extends('layouts.app')

@section('title', 'Riwayat '.$vendor->name)

@php
    $completed = $cycles->filter(fn ($cycle) => $cycle->evaluation?->is_complete);
@endphp

@section('content')
<x-page-header title="Rekapitulasi & Riwayat Evaluasi Vendor" :breadcrumb="[['label' => 'Rekapitulasi', 'url' => auth()->user()->can('view-all-evaluations') ? route('recap.index') : route('dashboard')], ['label' => $vendor->name]]" />
<p class="text-muted mt-n2">Bandingkan kinerja vendor dari waktu ke waktu tanpa kompilasi manual (FR-03).</p>

<div class="card">
    <div class="card-body">
        <form method="GET" class="row" id="history_filter">
            <div class="col-md-5">
                <label for="vendor_select">Vendor</label>
                <select id="vendor_select" class="form-control select2">
                    @foreach ($vendors as $option)
                        <option value="{{ route('recap.vendor', $option) }}" @selected($option->id === $vendor->id)>{{ $option->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label for="years">Rentang periode</label>
                <select id="years" name="years" class="form-control" onchange="this.form.submit()">
                    @foreach ([1 => now()->year, 2 => (now()->year - 1).' - '.now()->year, 3 => (now()->year - 2).' - '.now()->year, 5 => '5 tahun terakhir'] as $value => $label)
                        <option value="{{ $value }}" @selected($years === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4 text-right align-self-end">
                @can('export-reports')
                    <a href="{{ route('recap.vendor.export', ['vendor' => $vendor, 'format' => 'xlsx', 'years' => $years]) }}" class="btn btn-success btn-sm"><i class="fas fa-file-excel"></i> Ekspor Excel</a>
                    <a href="{{ route('recap.vendor.export', ['vendor' => $vendor, 'format' => 'pdf', 'years' => $years]) }}" target="_blank" class="btn btn-danger btn-sm"><i class="fas fa-file-pdf"></i> Ekspor PDF</a>
                @endcan
            </div>
        </form>
    </div>
</div>

<div class="row">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><h3 class="card-title">Tren total weighted score antarperiode</h3></div>
            <div class="card-body">
                @if ($completed->isEmpty())
                    <p class="text-muted text-center py-5 mb-0">Belum ada evaluasi lengkap pada rentang ini.</p>
                @else
                    <div style="height: 260px;"><canvas id="trend_chart" aria-label="Grafik tren skor" role="img"></canvas></div>
                    <p class="small text-muted mb-0 mt-2">Garis putus-putus: batas Recommended {{ number_format($thresholds['recommended'], 2, ',', '.') }} dan batas tindak lanjut {{ number_format($thresholds['with_notes'], 2, ',', '.') }}.</p>
                @endif
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><h3 class="card-title">Skor per kategori (A sampai G){{ $latest ? ', '.$latest->period_label : '' }}</h3></div>
            <div class="card-body">
                @if (! $latest)
                    <p class="text-muted text-center py-5 mb-0">Belum ada evaluasi lengkap.</p>
                @else
                    <div style="height: 260px;"><canvas id="category_chart" aria-label="Grafik skor per kategori" role="img"></canvas></div>
                    <p class="small text-muted mb-0 mt-2">
                        @foreach ($categories as $category){{ $category->code }} = {{ $category->name }}{{ $loop->last ? '' : '; ' }}@endforeach
                    </p>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3 class="card-title">Riwayat evaluasi {{ $vendor->name }}</h3> <span class="float-right"><x-status-badge :status="$vendor->status" /></span></div>
    <div class="card-body table-responsive p-0">
        <table class="table table-sm mb-0">
            <thead class="thead-light"><tr><th>Periode</th><th>Tipe</th><th>Kode Siklus</th><th>Status</th><th class="text-right">Weighted Score</th><th class="text-right">Pencapaian</th><th>Rekomendasi</th><th>Tindak Lanjut</th></tr></thead>
            <tbody>
                @forelse ($cycles->reverse() as $cycle)
                    <tr>
                        <td>{{ $cycle->period_label }}</td>
                        <td>{{ $cycle->cycle_type->label() }}</td>
                        <td>@can('view-cycle', $cycle)<a href="{{ route('cycles.show', $cycle) }}">{{ $cycle->cycle_code }}</a>@else{{ $cycle->cycle_code }}@endcan</td>
                        <td><x-status-badge :status="$cycle->status" /></td>
                        <td class="text-right">{{ $cycle->evaluation?->total_weighted_score !== null ? number_format((float) $cycle->evaluation->total_weighted_score, 2, ',', '.') : '-' }}</td>
                        <td class="text-right">{{ $cycle->evaluation?->achievement_pct !== null ? number_format((float) $cycle->evaluation->achievement_pct, 2, ',', '.').'%' : '-' }}</td>
                        <td>@if ($cycle->evaluation)<x-status-badge :status="$cycle->evaluation->recommendation" />@endif</td>
                        <td>
                            @forelse ($cycle->evaluation?->followUps ?? [] as $followUp)
                                @can('view-followups')<a href="{{ route('follow-ups.show', $followUp) }}">{{ $followUp->followup_no }}</a>@else{{ $followUp->followup_no }}@endcan
                                <x-status-badge :status="$followUp->status" />
                            @empty
                                -
                            @endforelse
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-muted py-4">Belum ada riwayat evaluasi.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
    $('#vendor_select').on('change', function () {
        window.location = this.value + '?years=' + $('#years').val();
    });

    const ink = '#495057';
    const grid = 'rgba(0, 0, 0, 0.06)';
    const series = '#1f4e78';
    const numberId = (value) => Number(value).toFixed(2).replace('.', ',');
    Chart.defaults.color = ink;
    Chart.defaults.font.family = 'system-ui, -apple-system, "Segoe UI", Roboto, sans-serif';

    @if ($completed->isNotEmpty())
    const trend = @json($completed->values()->map(fn ($cycle) => ['label' => $cycle->period_label, 'score' => (float) $cycle->evaluation->total_weighted_score]));
    const thresholdLine = (value, label) => ({
        label, data: trend.map(() => value), borderColor: '#adb5bd', borderWidth: 1, borderDash: [5, 4], pointRadius: 0, pointHitRadius: 0,
    });
    new Chart(document.getElementById('trend_chart'), {
        type: 'line',
        data: {
            labels: trend.map((row) => row.label),
            datasets: [
                { label: 'Total weighted score', data: trend.map((row) => row.score), borderColor: series, backgroundColor: series, borderWidth: 2, pointRadius: 4, pointHoverRadius: 6, tension: 0 },
                thresholdLine({{ $thresholds['recommended'] }}, 'Batas Recommended'),
                thresholdLine({{ $thresholds['with_notes'] }}, 'Batas tindak lanjut'),
            ],
        },
        options: {
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: { legend: { display: false }, tooltip: { callbacks: { label: (context) => context.dataset.label + ': ' + numberId(context.parsed.y) } } },
            scales: { y: { min: 0, max: 4, ticks: { stepSize: 1 }, grid: { color: grid } }, x: { grid: { display: false } } },
        },
    });
    @endif

    @if ($latest)
    const categories = @json(collect($categoryScores)->map(fn ($score, $code) => ['code' => $code, 'score' => $score])->values());
    new Chart(document.getElementById('category_chart'), {
        type: 'bar',
        data: {
            labels: categories.map((row) => row.code),
            datasets: [{ label: 'Skor kategori', data: categories.map((row) => row.score), backgroundColor: series, borderRadius: 4, borderSkipped: 'start', maxBarThickness: 36 }],
        },
        options: {
            maintainAspectRatio: false,
            plugins: { legend: { display: false }, tooltip: { callbacks: { label: (context) => 'Skor: ' + numberId(context.parsed.y) } } },
            scales: { y: { min: 0, max: 4, ticks: { stepSize: 1 }, grid: { color: grid } }, x: { grid: { display: false } } },
        },
    });
    @endif
</script>
@endpush
