@extends('layouts.app')

@section('title', 'Pengaturan Sistem')

@php use App\Support\Settings; @endphp

@section('content')
<x-page-header title="Pengaturan Sistem" :breadcrumb="[['label' => 'Beranda', 'url' => route('dashboard')], ['label' => 'Pengaturan']]" />

<form method="POST" action="{{ route('settings.update') }}">
    @csrf
    @method('PUT')
    <div class="card">
        <div class="card-body">
            <div class="row">
                @foreach ([Settings::DEFAULT_SLA_DAYS => 'Tenggat pengisian default (hari)', Settings::FOLLOWUP_DUE_DAYS => 'Tenggat kasus tindak lanjut (hari)'] as $key => $label)
                    <div class="col-md-3 form-group">
                        <label for="{{ $key }}">{{ $label }}</label>
                        <input id="{{ $key }}" type="number" min="1" name="{{ $key }}" class="form-control" value="{{ old($key, $values[$key]) }}" required>
                        <small class="form-text text-muted">{{ $definitions[$key]['description'] }}</small>
                    </div>
                @endforeach
                @foreach ([Settings::RECOMMENDED_MIN_SCORE => 'Batas Recommended', Settings::RECOMMENDED_WITH_NOTES_MIN_SCORE => 'Batas Recommended dengan Catatan'] as $key => $label)
                    <div class="col-md-3 form-group">
                        <label for="{{ $key }}">{{ $label }}</label>
                        <input id="{{ $key }}" type="number" step="0.01" min="1" max="4" name="{{ $key }}" class="form-control" value="{{ old($key, $values[$key]) }}" required>
                        <small class="form-text text-muted">{{ $definitions[$key]['description'] }}</small>
                    </div>
                @endforeach
            </div>
            <div class="custom-control custom-switch mb-2">
                <input type="checkbox" class="custom-control-input" id="{{ Settings::ENFORCE_DEPENDENCY }}" name="{{ Settings::ENFORCE_DEPENDENCY }}" value="1" @checked($values[Settings::ENFORCE_DEPENDENCY] === '1')>
                <label class="custom-control-label" for="{{ Settings::ENFORCE_DEPENDENCY }}">Dependency proses internal: {{ $definitions[Settings::ENFORCE_DEPENDENCY]['description'] }}</label>
            </div>
            <div class="custom-control custom-switch">
                <input type="checkbox" class="custom-control-input" id="{{ Settings::REMINDERS_ENABLED }}" name="{{ Settings::REMINDERS_ENABLED }}" value="1" @checked($values[Settings::REMINDERS_ENABLED] === '1')>
                <label class="custom-control-label" for="{{ Settings::REMINDERS_ENABLED }}">{{ $definitions[Settings::REMINDERS_ENABLED]['description'] }}</label>
            </div>
            <div class="alert alert-light border small mt-3 mb-0">
                <i class="fas fa-info-circle"></i> Dependency di sini bersifat administratif pada level proses evaluasi dan berbeda dari status blocking vendor (suspend/block ID) yang diputuskan melalui modul Tindak Lanjut sesuai SOP.
                Batas rekomendasi masih berupa asumsi tim dan perlu dikonfirmasi terhadap form Excel resmi perusahaan.
            </div>
        </div>
        <div class="card-footer d-flex justify-content-between">
            <button class="btn btn-primary">Simpan Pengaturan</button>
            <small class="text-muted align-self-center">
                @php $latest = $records->sortByDesc('updated_at')->first(); @endphp
                @if ($latest?->updated_at) Terakhir diubah {{ $latest->updated_at->format('d M Y H:i') }} oleh {{ $latest->updater?->full_name ?? 'sistem' }} @endif
            </small>
        </div>
    </div>
</form>
@endsection
