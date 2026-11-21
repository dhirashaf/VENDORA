@extends('layouts.auth')

@section('title', 'Masuk')

@section('content')
<div class="login-box" style="width: 420px; max-width: 94vw;">
    <div class="login-logo">PT Chakra Jawara</div>
    <div class="card">
        <div class="card-body login-card-body">
            <p class="login-box-msg"><strong>Sistem Evaluasi Kinerja Vendor</strong><br><small class="text-muted">Divisi Procurement &amp; Vendor Management</small></p>
            <form action="{{ route('auth.authenticate') }}" method="post">
                @csrf
                <div class="input-group mb-3">
                    <input type="email" name="work_email" class="form-control @error('work_email') is-invalid @enderror" value="{{ old('work_email') }}" aria-label="Email kerja" placeholder="Email kerja" required autofocus>
                    <div class="input-group-append"><div class="input-group-text"><span class="fas fa-envelope"></span></div></div>
                    @error('work_email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="input-group mb-3">
                    <input type="password" name="password" class="form-control" aria-label="Kata sandi" placeholder="Kata sandi" required>
                    <div class="input-group-append"><div class="input-group-text"><span class="fas fa-lock"></span></div></div>
                </div>
                <div class="icheck-primary mb-3">
                    <input type="checkbox" id="remember" name="remember" value="1">
                    <label for="remember" class="font-weight-normal">Ingat saya</label>
                </div>
                <button type="submit" class="btn btn-primary btn-block">Masuk</button>
           
        </div>
    </div>
</div>
@endsection
