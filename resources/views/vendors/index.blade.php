@extends('layouts.app')

@section('title', 'Master Vendor')

@section('content')
<x-page-header title="Master Vendor" :breadcrumb="[['label' => 'Beranda', 'url' => route('dashboard')], ['label' => 'Master Vendor']]" />

<div class="card">
    <div class="card-header">
        <h3 class="card-title">{{ $vendors->total() }} vendor terdaftar</h3>
        <div class="card-tools">
            <a href="{{ route('vendors.create') }}" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Tambah Vendor</a>
        </div>
    </div>
    <div class="card-body">
        <form method="GET" class="row mb-3">
            <div class="col-md-5">
                <input type="search" name="search" class="form-control" value="{{ $filters['search'] ?? '' }}" placeholder="Cari nama, kode vendor, atau ID SAP" aria-label="Cari vendor">
            </div>
            <div class="col-md-3">
                <select name="vendor_type" class="form-control" aria-label="Jenis vendor">
                    <option value="">Semua jenis</option>
                    @foreach (\App\Enums\VendorType::options() as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['vendor_type'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <select name="status" class="form-control" aria-label="Status vendor">
                    <option value="">Semua status</option>
                    @foreach (\App\Enums\VendorStatus::options() as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-1"><button class="btn btn-outline-secondary btn-block" type="submit">Cari</button></div>
        </form>

        <div class="table-responsive">
            <table class="table table-sm table-bordered table-hover">
                <thead class="thead-light">
                    <tr><th>Kode</th><th>ID SAP</th><th>Nama Vendor</th><th>Jenis</th><th>Kategori</th><th>Strategic</th><th>Frekuensi</th><th>Akhir Kontrak</th><th>Status</th><th class="text-center">Siklus</th><th>Aksi</th></tr>
                </thead>
                <tbody>
                    @forelse ($vendors as $vendor)
                        <tr>
                            <td class="text-nowrap">{{ $vendor->vendor_code }}</td>
                            <td>{{ $vendor->sap_vendor_id ?? '-' }}</td>
                            <td>{{ $vendor->name }}<br><small class="text-muted">{{ $vendor->contact_person }} {{ $vendor->contact_email ? '· '.$vendor->contact_email : '' }}</small></td>
                            <td>{{ $vendor->vendor_type->label() }}</td>
                            <td>{{ $vendor->vendor_category->label() }}</td>
                            <td>{!! $vendor->is_strategic ? '<i class="fas fa-check text-success" aria-label="Ya"></i>' : '<span class="text-muted">-</span>' !!}</td>
                            <td>{{ $vendor->evaluation_frequency->label() }}</td>
                            <td class="text-nowrap">{{ $vendor->contract_end_date?->format('d M Y') ?? '-' }}</td>
                            <td><x-status-badge :status="$vendor->status" /></td>
                            <td class="text-center">{{ $vendor->evaluation_cycles_count }}</td>
                            <td class="text-nowrap">
                                <a href="{{ route('vendors.edit', $vendor) }}" class="btn btn-xs btn-outline-primary">Ubah</a>
                                <a href="{{ route('recap.vendor', $vendor) }}" class="btn btn-xs btn-outline-secondary">Riwayat</a>
                                <a href="{{ route('cycles.create', ['vendor_id' => $vendor->id]) }}" class="btn btn-xs btn-outline-info">Evaluasi ad-hoc</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="11" class="text-center text-muted py-4">Belum ada vendor.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $vendors->links('pagination::bootstrap-4') }}
    </div>
</div>
@endsection
