@if (auth()->check())
    @include('errors.partials.forbidden', ['message' => $exception->getMessage()])
@else
    <!DOCTYPE html>
    <html lang="id">
    <head><meta charset="utf-8"><title>Akses Ditolak</title></head>
    <body style="font-family: Arial, sans-serif; text-align: center; padding-top: 15vh;">
        <h1>403</h1>
        <p>Anda tidak memiliki akses ke halaman ini.</p>
        <a href="{{ route('login') }}">Masuk</a>
    </body>
    </html>
@endif
