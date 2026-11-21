@extends('layouts.app')

@section('title', 'Notifikasi')

@section('content')
<x-page-header title="Notifikasi" :breadcrumb="[['label' => 'Beranda', 'url' => route('dashboard')], ['label' => 'Notifikasi']]" />

<div class="card">
    <ul class="list-group list-group-flush">
        @forelse ($notifications as $notification)
            @php $cycle = $notification->assignment->cycle; @endphp
            <li class="list-group-item">
                <i class="fas {{ $notification->is_escalation ? 'fa-exclamation-circle text-danger' : 'fa-bell text-warning' }} mr-2"></i>
                <strong>{{ $notification->rule->name }}</strong>
                &mdash; {{ $cycle->vendor->name }} ({{ $cycle->period_label }}), fungsi {{ $notification->assignment->department->label() }}
                @if ($notification->is_escalation) penilai {{ $notification->assignment->assessor->full_name }} @endif
                &middot; tenggat {{ $cycle->due_date->format('d M Y') }}
                <span class="float-right small text-muted">{{ $notification->scheduled_at->format('d M Y H:i') }}</span>
                <div><a href="{{ $notification->is_escalation ? route('cycles.show', $cycle) : route('assessments.edit', $notification->assignment) }}" class="small">Buka</a></div>
            </li>
        @empty
            <li class="list-group-item text-muted">Belum ada notifikasi.</li>
        @endforelse
    </ul>
    <div class="card-footer">{{ $notifications->links('pagination::bootstrap-4') }}</div>
</div>
@endsection
