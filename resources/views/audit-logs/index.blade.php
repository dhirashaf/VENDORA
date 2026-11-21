@extends('layouts.app')

@section('title', 'Log Aktivitas')

@section('content')
<x-page-header title="Log Aktivitas Pengguna" :breadcrumb="[['label' => 'Beranda', 'url' => route('dashboard')], ['label' => 'Log Aktivitas']]" />

<div class="card">
    <div class="card-body">
        <form method="GET" class="form-row mb-3">
            <div class="col-md-2">
                <select name="action" class="form-control form-control-sm" aria-label="Aksi">
                    <option value="">Semua aksi</option>
                    @foreach ($actions as $action)<option value="{{ $action }}" @selected(($filters['action'] ?? '') === $action)>{{ $action }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-3">
                <select name="entity" class="form-control form-control-sm" aria-label="Entitas">
                    <option value="">Semua entitas</option>
                    @foreach ($entities as $entity)<option value="{{ $entity }}" @selected(($filters['entity'] ?? '') === $entity)>{{ $entity }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-3">
                <select name="user_id" class="form-control form-control-sm" aria-label="Pengguna">
                    <option value="">Semua pengguna</option>
                    @foreach ($users as $user)<option value="{{ $user->id }}" @selected((int) ($filters['user_id'] ?? 0) === $user->id)>{{ $user->full_name }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-2"><input type="date" name="from" class="form-control form-control-sm" value="{{ $filters['from'] ?? '' }}" aria-label="Dari tanggal"></div>
            <div class="col-md-1"><input type="date" name="to" class="form-control form-control-sm" value="{{ $filters['to'] ?? '' }}" aria-label="Sampai tanggal"></div>
            <div class="col-md-1"><button class="btn btn-sm btn-outline-secondary btn-block">Filter</button></div>
        </form>
        <div class="table-responsive">
            <table class="table table-sm table-striped">
                <thead class="thead-light"><tr><th>Waktu</th><th>Pengguna</th><th>Aksi</th><th>Entitas</th><th>Nilai Lama</th><th>Nilai Baru</th><th>IP</th></tr></thead>
                <tbody>
                    @forelse ($logs as $log)
                        <tr>
                            <td class="text-nowrap small">{{ $log->created_at->format('d M Y H:i:s') }}</td>
                            <td>{{ $log->user?->full_name ?? 'Sistem' }}</td>
                            <td><span class="badge badge-light">{{ $log->action }}</span></td>
                            <td class="small">{{ $log->entity_name }}{{ $log->entity_id ? ' #'.$log->entity_id : '' }}</td>
                            <td class="small text-muted text-break">{{ \Illuminate\Support\Str::limit($log->old_value, 120) }}</td>
                            <td class="small text-break">{{ \Illuminate\Support\Str::limit($log->new_value, 120) }}</td>
                            <td class="small">{{ $log->ip_address }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted">Belum ada log.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $logs->links('pagination::bootstrap-4') }}
    </div>
</div>
@endsection
