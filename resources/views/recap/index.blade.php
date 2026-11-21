@extends('layouts.app')

@section('title', 'Rekapitulasi Evaluasi')

@section('content')
<x-page-header title="Rekapitulasi Evaluasi" :breadcrumb="[['label' => 'Beranda', 'url' => route('dashboard')], ['label' => 'Rekapitulasi']]" />
<p class="text-muted mt-n2">Rekap otomatis total weighted score, persentase pencapaian, dan rekomendasi seluruh vendor per periode, tanpa kompilasi manual (FR-15, FR-17).</p>

<div class="card">
    <div class="card-body">
        <form method="GET" class="row">
            <div class="col-md-3">
                <label for="period">Periode</label>
                <select id="period" name="period" class="form-control" onchange="this.form.submit()">
                    <option value="{{ \App\Support\MonitoringReport::CURRENT_PERIOD }}" @selected($period === \App\Support\MonitoringReport::CURRENT_PERIOD)>Periode berjalan (aktif)</option>
                    @foreach ($periods as $item)
                        <option value="{{ $item }}" @selected($period === $item)>{{ $item }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label for="vendor_type">Jenis vendor</label>
                <select id="vendor_type" name="vendor_type" class="form-control" onchange="this.form.submit()">
                    <option value="">Semua</option>
                    @foreach (\App\Enums\VendorType::options() as $value => $label)
                        <option value="{{ $value }}" @selected($vendorType === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6 text-right align-self-end">
                @can('export-reports')
                    <a href="{{ route('recap.export', ['format' => 'xlsx', 'period' => $period]) }}" class="btn btn-success btn-sm"><i class="fas fa-file-excel"></i> Ekspor Excel</a>
                    <a href="{{ route('recap.export', ['format' => 'pdf', 'period' => $period]) }}" target="_blank" class="btn btn-danger btn-sm"><i class="fas fa-file-pdf"></i> Ekspor PDF</a>
                @endcan
            </div>
        </form>
    </div>
</div>

<div class="row">
    <div class="col-md-3 col-6">
        <x-stat-card title="Rata-rata skor periode" :value="$averageScore !== null ? number_format($averageScore, 2, ',', '.') : '-'" icon="fas fa-star-half-alt" color="info" :footer="$cycles->count().' evaluasi'" />
    </div>
    @foreach ($distribution as $label => $count)
        <div class="col-md-{{ $loop->last ? 3 : 2 }} col-6">
            <div class="info-box shadow-none border">
                <div class="info-box-content">
                    <span class="info-box-text small">{{ $label }}</span>
                    <span class="info-box-number h4 mb-0">{{ $count }}</span>
                </div>
            </div>
        </div>
    @endforeach
</div>

<div class="card">
    <div class="card-header"><h3 class="card-title">Hasil evaluasi {{ \App\Support\MonitoringReport::periodLabel($period) }}</h3></div>
    <div class="card-body table-responsive p-0">
        <table class="table table-sm table-hover mb-0">
            <thead class="thead-light"><tr><th>#</th><th>Vendor</th><th>Jenis</th><th>Status Siklus</th><th class="text-right">Total Weighted Score</th><th class="text-right">Pencapaian</th><th>Rekomendasi</th><th>Tindak Lanjut</th><th></th></tr></thead>
            <tbody>
                @forelse ($cycles as $cycle)
                    @php $evaluation = $cycle->evaluation; @endphp
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $cycle->vendor->name }}<br><small class="text-muted">{{ $cycle->vendor->vendor_code }}</small></td>
                        <td>{{ $cycle->vendor->vendor_type->label() }}</td>
                        <td><x-status-badge :status="$cycle->status" /></td>
                        <td class="text-right font-weight-bold {{ $evaluation?->total_weighted_score !== null && (float) $evaluation->total_weighted_score < $thresholds['with_notes'] ? 'text-danger' : '' }}">
                            {{ $evaluation?->total_weighted_score !== null ? number_format((float) $evaluation->total_weighted_score, 2, ',', '.') : 'belum dapat dihitung' }}
                        </td>
                        <td class="text-right">{{ $evaluation?->achievement_pct !== null ? number_format((float) $evaluation->achievement_pct, 2, ',', '.').'%' : '-' }}</td>
                        <td>@if ($evaluation)<x-status-badge :status="$evaluation->recommendation" />@endif</td>
                        <td>
                            @foreach ($evaluation?->followUps ?? [] as $followUp)
                                <a href="{{ route('follow-ups.show', $followUp) }}">{{ $followUp->followup_no }}</a>
                                <x-status-badge :status="$followUp->status" />
                            @endforeach
                        </td>
                        <td class="text-nowrap"><a href="{{ route('recap.vendor', $cycle->vendor) }}" class="btn btn-xs btn-outline-secondary">Riwayat</a></td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="text-center text-muted py-4">Belum ada evaluasi pada periode ini.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer small text-muted">
        Weighted score = skor (1-4) × bobot item, dijumlahkan (maks 4,00). Rekomendasi: ≥ {{ number_format($thresholds['recommended'], 2, ',', '.') }} Recommended,
        ≥ {{ number_format($thresholds['with_notes'], 2, ',', '.') }} Recommended dengan Catatan, di bawahnya Not Recommended. Batas dapat diubah pada Pengaturan Sistem.
    </div>
</div>
@endsection
