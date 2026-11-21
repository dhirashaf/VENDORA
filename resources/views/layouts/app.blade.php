<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') - Sistem Evaluasi Kinerja Vendor</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@ttskch/select2-bootstrap4-theme@1.5.2/dist/select2-bootstrap4.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastr@2.1.4/build/toastr.min.css">
    <style>
        body { font-size: 14px; }
        .brand-link { background: #1f4e78; }
        .main-sidebar { background: #263238; }
        .sidebar-dark-primary .nav-sidebar > .nav-item > .nav-link.active { background: #0d6efd; }
        .content-wrapper { min-height: calc(100vh - 114px); }
        .small-box-footer { text-align: left; padding-left: 10px !important; }
        .sticky-action { position: sticky; bottom: 0; z-index: 10; background: #fff; border-top: 1px solid #dee2e6; padding: 12px; box-shadow: 0 -2px 6px rgba(0,0,0,.06); }
        .focus-visible, :focus-visible { outline: 3px solid #f6c23e !important; outline-offset: 2px; }
        .table td, .table th { vertical-align: middle; }
        .item-missing { background: #fff3cd !important; }
        .stepper { display: flex; flex-wrap: wrap; gap: 6px; }
        .stepper .step { flex: 1 1 0; min-width: 120px; padding: 8px 10px; border: 1px solid #dee2e6; border-radius: 4px; font-size: 12px; background: #fff; }
        .stepper .step.done { background: #e8f5e9; border-color: #a5d6a7; }
        .stepper .step.current { background: #fff8e1; border-color: #f6c23e; font-weight: 600; }
        .timeline-simple { border-left: 3px solid #1f4e78; padding-left: 12px; }
        .timeline-simple .entry { margin-bottom: 10px; }
        @media print { .main-sidebar, .main-header, .main-footer, .no-print { display: none !important; } .content-wrapper { margin-left: 0 !important; } }
    </style>
    @stack('styles')
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">
    @include('partials.navbar')
    @include('partials.sidebar')
    <div class="content-wrapper">
        <section class="content pt-3">
            <div class="container-fluid">
                @if ($errors->any())
                    <div class="alert alert-danger alert-dismissible" role="alert">
                        <button type="button" class="close" data-dismiss="alert" aria-label="Tutup">&times;</button>
                        <strong><i class="fas fa-exclamation-triangle mr-1"></i> Periksa kembali isian:</strong>
                        <ul class="mb-0 pl-3">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                @yield('content')
            </div>
        </section>
    </div>
    <footer class="main-footer text-sm">
        <strong>Sistem Evaluasi Kinerja Vendor</strong> &middot; Divisi Procurement &amp; Vendor Management PT Chakra Jawara
        <span class="float-right text-muted d-none d-sm-inline">{{ now()->translatedFormat('d F Y') }}</span>
    </footer>
</div>

<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.4/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/toastr@2.1.4/build/toastr.min.js"></script>
<script>
    $(function () {
        $('.select2').select2({ theme: 'bootstrap4', width: '100%' });
        $('form[data-confirm]').on('submit', function (event) {
            if (!window.confirm(this.dataset.confirm)) {
                event.preventDefault();
            }
        });
    });
</script>
@include('partials.toast')
@stack('scripts')
</body>
</html>
