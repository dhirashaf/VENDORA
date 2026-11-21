<nav class="main-header navbar navbar-expand navbar-white navbar-light">
    <ul class="navbar-nav">
        <li class="nav-item">
            <a class="nav-link" data-widget="pushmenu" href="#" role="button" aria-label="Buka/tutup menu"><i class="fas fa-bars"></i></a>
        </li>
    </ul>
    <ul class="navbar-nav ml-auto">
        <li class="nav-item dropdown">
            <a class="nav-link" data-toggle="dropdown" href="#" aria-label="Notifikasi">
                <i class="far fa-bell"></i>
                @if ($notifications->isNotEmpty())
                    <span class="badge badge-warning navbar-badge">{{ $notifications->count() }}</span>
                @endif
            </a>
            <div class="dropdown-menu dropdown-menu-lg dropdown-menu-right">
                <span class="dropdown-header">Notifikasi 7 hari terakhir</span>
                @forelse ($notifications as $notification)
                    <div class="dropdown-divider"></div>
                    <a href="{{ route('assessments.edit', $notification->assignment_id) }}" class="dropdown-item small text-wrap">
                        <i class="fas {{ $notification->is_escalation ? 'fa-exclamation-circle text-danger' : 'fa-bell text-warning' }} mr-1"></i>
                        <strong>{{ $notification->rule->name }}</strong><br>
                        {{ $notification->assignment->cycle->vendor->name }} &middot; tenggat {{ $notification->assignment->cycle->due_date->format('d M Y') }}
                        <span class="float-right text-muted">{{ $notification->scheduled_at->diffForHumans() }}</span>
                    </a>
                @empty
                    <div class="dropdown-divider"></div>
                    <span class="dropdown-item small text-muted">Belum ada notifikasi.</span>
                @endforelse
                <div class="dropdown-divider"></div>
                <a href="{{ route('notifications.index') }}" class="dropdown-item dropdown-footer">Lihat semua notifikasi</a>
            </div>
        </li>
        <li class="nav-item dropdown">
            <a class="nav-link" data-toggle="dropdown" href="#">
                <i class="far fa-user-circle mr-1"></i>
                <span class="d-none d-md-inline">{{ auth()->user()->full_name }}</span>
                <span class="badge badge-info ml-1">{{ auth()->user()->role->code->label() }}</span>
            </a>
            <div class="dropdown-menu dropdown-menu-right">
                <span class="dropdown-item-text small">
                    {{ auth()->user()->work_email }}<br>
                    <span class="text-muted">{{ auth()->user()->department->label() }}{{ auth()->user()->job_title ? ' · '.auth()->user()->job_title : '' }}</span>
                </span>
                <div class="dropdown-divider"></div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="dropdown-item"><i class="fas fa-sign-out-alt mr-1"></i> Keluar</button>
                </form>
            </div>
        </li>
    </ul>
</nav>
