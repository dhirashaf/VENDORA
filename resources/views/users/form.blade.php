@extends('layouts.app')

@section('title', $user->exists ? 'Ubah Pengguna' : 'Tambah Pengguna')

@section('content')
<x-page-header :title="$user->exists ? 'Ubah Pengguna' : 'Tambah Pengguna'" :breadcrumb="[['label' => 'Pengguna', 'url' => route('users.index')], ['label' => $user->exists ? $user->full_name : 'Tambah']]" />

<form method="POST" action="{{ $user->exists ? route('users.update', $user) : route('users.store') }}">
    @csrf
    @if ($user->exists) @method('PUT') @endif
    <div class="card">
        <div class="card-body row">
            <div class="col-md-6 form-group">
                <label for="full_name">Nama Lengkap <span class="text-danger">*</span></label>
                <input id="full_name" name="full_name" class="form-control" value="{{ old('full_name', $user->full_name) }}" required maxlength="150">
            </div>
            <div class="col-md-6 form-group">
                <label for="work_email">Email Kerja <span class="text-danger">*</span></label>
                <input id="work_email" type="email" name="work_email" class="form-control @error('work_email') is-invalid @enderror" value="{{ old('work_email', $user->work_email) }}" required maxlength="150">
                @error('work_email')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4 form-group">
                <label for="role_id">Peran <span class="text-danger">*</span></label>
                <select id="role_id" name="role_id" class="form-control" required>
                    @foreach ($roles as $role)
                        <option value="{{ $role->id }}" @selected((int) old('role_id', $user->role_id) === $role->id)>{{ $role->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4 form-group">
                <label for="department">Fungsi <span class="text-danger">*</span></label>
                <select id="department" name="department" class="form-control" required>
                    @foreach (\App\Enums\Department::options() as $value => $label)
                        <option value="{{ $value }}" @selected(old('department', $user->department?->value) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <small class="form-text text-muted">Penilai hanya mengisi item sesuai fungsinya.</small>
            </div>
            <div class="col-md-4 form-group">
                <label for="job_title">Jabatan</label>
                <input id="job_title" name="job_title" class="form-control" value="{{ old('job_title', $user->job_title) }}" maxlength="100">
            </div>
            <div class="col-md-4 form-group">
                <label for="password">Kata Sandi {{ $user->exists ? '(kosongkan bila tidak diubah)' : '' }}</label>
                <input id="password" type="password" name="password" class="form-control @error('password') is-invalid @enderror" autocomplete="new-password" @required(! $user->exists)>
                @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4 form-group">
                <label for="password_confirmation">Konfirmasi Kata Sandi</label>
                <input id="password_confirmation" type="password" name="password_confirmation" class="form-control" autocomplete="new-password">
            </div>
            <div class="col-md-4 form-group d-flex align-items-end">
                <div class="custom-control custom-switch">
                    <input type="checkbox" class="custom-control-input" id="is_active" name="is_active" value="1" @checked(old('is_active', $user->is_active))>
                    <label class="custom-control-label" for="is_active">Akun aktif</label>
                </div>
            </div>
        </div>
        <div class="card-footer">
            <button type="submit" class="btn btn-primary">Simpan</button>
            <a href="{{ route('users.index') }}" class="btn btn-link">Batal</a>
        </div>
    </div>
</form>
@endsection
