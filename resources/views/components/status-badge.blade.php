@props(['status'])
@php
    if ($status instanceof \BackedEnum && method_exists($status, 'badge')) {
        $color = $status->badge();
        $label = $status->label();
    } elseif ($status instanceof \BackedEnum && method_exists($status, 'label')) {
        $color = 'light';
        $label = $status->label();
    } else {
        $color = [
            'Belum Lengkap' => 'warning',
            'Lewat Tenggat' => 'danger',
        ][$status] ?? 'secondary';
        $label = $status;
    }
@endphp
<span {{ $attributes->merge(['class' => 'badge badge-'.$color]) }}>{{ $label }}</span>
