@extends('layouts.app')

@section('title', 'Pengguna & Peran')

@section('content')
<x-page-header title="Pengguna & Peran" :breadcrumb="[['label' => 'Beranda', 'url' => route('dashboard')], ['label' => 'Pengguna']]" />

<div class="card">
    <div class="card-header">
        <h3 class="card-title">Akun pengguna internal</h3>
        <div class="card-tools"><a href="{{ route('users.create') }}" class="btn btn-primary btn-sm"><i class="fas fa-user-plus"></i> Tambah Pengguna</a></div>
    </div>
    <div class="card-body table-responsive p-0">
        <table class="table table-hover table-sm mb-0">
            <thead class="thead-light"><tr><th>Nama</th><th>Email Kerja</th><th>Peran</th><th>Fungsi</th><th>Jabatan</th><th>Status</th><th></th></tr></thead>
            <tbody>
                @foreach ($users as $user)
                    <tr>
                        <td>{{ $user->full_name }}</td>
                        <td>{{ $user->work_email }}</td>
                        <td><span class="badge badge-info">{{ $user->role->code->label() }}</span></td>
                        <td>{{ $user->department->label() }}</td>
                        <td>{{ $user->job_title ?? '-' }}</td>
                        <td>{!! $user->is_active ? '<span class="badge badge-success">Aktif</span>' : '<span class="badge badge-secondary">Nonaktif</span>' !!}</td>
                        <td><a href="{{ route('users.edit', $user) }}" class="btn btn-xs btn-outline-primary">Ubah</a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="card-footer small text-muted">Data pribadi dibatasi pada nama, fungsi/jabatan, dan email kerja (UU No. 27/2022 &amp; PP No. 33/2026). Kata sandi disimpan dalam bentuk hash bcrypt.</div>
</div>
@endsection
