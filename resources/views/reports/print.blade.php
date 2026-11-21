<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @page { size: A4 landscape; margin: 12mm; }
        body { font-family: Arial, sans-serif; font-size: 10px; color: #212529; }
        h1 { font-size: 15px; margin: 0 0 2px; }
        .meta { color: #6c757d; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ced4da; padding: 3px 5px; text-align: left; vertical-align: top; }
        th { background: #dceef8; }
        .toolbar { margin-bottom: 10px; }
        @media print { .toolbar { display: none; } }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" onclick="window.print()">Simpan sebagai PDF / Cetak</button>
    </div>
    <h1>{{ $title }}</h1>
    <div class="meta">PT Chakra Jawara &middot; Divisi Procurement &amp; Vendor Management &middot; dicetak {{ now()->format('d M Y H:i') }} oleh {{ auth()->user()->full_name }}</div>
    <table>
        <thead>
            <tr>@foreach ($headings as $heading)<th>{{ $heading }}</th>@endforeach</tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>@foreach ($row as $cell)<td>{{ is_float($cell) ? number_format($cell, 2, ',', '.') : ($cell ?? '-') }}</td>@endforeach</tr>
            @empty
                <tr><td colspan="{{ count($headings) }}">Tidak ada data.</td></tr>
            @endforelse
        </tbody>
    </table>
    <script>window.addEventListener('load', () => window.print());</script>
</body>
</html>
