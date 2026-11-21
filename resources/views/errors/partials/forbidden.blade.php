@extends('layouts.app')

@section('title', 'Akses Ditolak')

@section('content')
<div class="error-page mt-5">
    <h2 class="headline text-warning">403</h2>
    <div class="error-content pt-2">
        <h3><i class="fas fa-lock text-warning"></i> Akses ditolak.</h3>
        <p>{{ $message ?: 'Anda tidak memiliki akses ke halaman ini.' }}</p>
        <p class="text-muted small">Hak akses diatur berdasarkan peran Anda ({{ auth()->user()->role->code->label() }}) sesuai matriks peran dan hak akses sistem.</p>
        <a href="{{ route('dashboard') }}" class="btn btn-primary btn-sm"><i class="fas fa-arrow-left"></i> Kembali ke Dashboard</a>
    </div>
</div>
@endsection
