@extends('layouts.app')

@section('title', $vendor->exists ? 'Ubah Vendor' : 'Tambah Vendor')

@section('content')
<x-page-header :title="$vendor->exists ? 'Ubah Vendor' : 'Tambah Vendor'" :breadcrumb="[['label' => 'Master Vendor', 'url' => route('vendors.index')], ['label' => $vendor->exists ? $vendor->name : 'Tambah']]" />

<form method="POST" action="{{ $vendor->exists ? route('vendors.update', $vendor) : route('vendors.store') }}">
    @csrf
    @if ($vendor->exists) @method('PUT') @endif
    <div class="card">
        <div class="card-body">
            <div class="row">
                <div class="col-md-3 form-group">
                    <label for="vendor_code">Kode Vendor <span class="text-danger">*</span></label>
                    <input id="vendor_code" name="vendor_code" class="form-control @error('vendor_code') is-invalid @enderror" value="{{ old('vendor_code', $vendor->vendor_code) }}" required maxlength="30">
                    @error('vendor_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <small class="form-text text-muted">ID unik, dapat dipetakan ke SAP.</small>
                </div>
                <div class="col-md-3 form-group">
                    <label for="sap_vendor_id">ID Vendor SAP</label>
                    <input id="sap_vendor_id" name="sap_vendor_id" class="form-control" value="{{ old('sap_vendor_id', $vendor->sap_vendor_id) }}" maxlength="30">
                    <small class="form-text text-muted">Opsional, untuk integrasi masa depan.</small>
                </div>
                <div class="col-md-6 form-group">
                    <label for="name">Nama Vendor <span class="text-danger">*</span></label>
                    <input id="name" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $vendor->name) }}" required maxlength="200">
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3 form-group">
                    <label for="vendor_type">Jenis <span class="text-danger">*</span></label>
                    <select id="vendor_type" name="vendor_type" class="form-control" required>
                        @foreach (\App\Enums\VendorType::options() as $value => $label)
                            <option value="{{ $value }}" @selected(old('vendor_type', $vendor->vendor_type?->value) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 form-group">
                    <label for="vendor_category">Kategori Vendor <span class="text-danger">*</span></label>
                    <select id="vendor_category" name="vendor_category" class="form-control" required>
                        @foreach (\App\Enums\VendorCategory::options() as $value => $label)
                            <option value="{{ $value }}" @selected(old('vendor_category', $vendor->vendor_category?->value) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 form-group">
                    <label for="evaluation_frequency">Frekuensi Evaluasi Rutin <span class="text-danger">*</span></label>
                    <select id="evaluation_frequency" name="evaluation_frequency" class="form-control" required>
                        @foreach (\App\Enums\EvaluationFrequency::options() as $value => $label)
                            <option value="{{ $value }}" @selected(old('evaluation_frequency', $vendor->evaluation_frequency?->value) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 form-group">
                    <label for="status">Status Vendor <span class="text-danger">*</span></label>
                    <select id="status" name="status" class="form-control" required>
                        @foreach (\App\Enums\VendorStatus::options() as $value => $label)
                            <option value="{{ $value }}" @selected(old('status', $vendor->status?->value) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 form-group">
                    <label for="contract_end_date">Tanggal Akhir Kontrak</label>
                    <input id="contract_end_date" type="date" name="contract_end_date" class="form-control" value="{{ old('contract_end_date', $vendor->contract_end_date?->toDateString()) }}">
                </div>
                <div class="col-md-4 form-group">
                    <label for="contact_person">Contact Person</label>
                    <input id="contact_person" name="contact_person" class="form-control" value="{{ old('contact_person', $vendor->contact_person) }}" maxlength="150">
                </div>
                <div class="col-md-5 form-group">
                    <label for="contact_email">Email Kontak</label>
                    <input id="contact_email" type="email" name="contact_email" class="form-control @error('contact_email') is-invalid @enderror" value="{{ old('contact_email', $vendor->contact_email) }}" maxlength="150">
                </div>
                <div class="col-md-12">
                    <div class="custom-control custom-switch">
                        <input type="hidden" name="is_strategic" value="0">
                        <input type="checkbox" class="custom-control-input" id="is_strategic" name="is_strategic" value="1" @checked(old('is_strategic', $vendor->is_strategic))>
                        <label class="custom-control-label" for="is_strategic">Strategic vendor (masuk jadwal evaluasi rutin sesuai SOP)</label>
                    </div>
                </div>
            </div>
        </div>
        <div class="card-footer">
            <button type="submit" class="btn btn-primary">Simpan</button>
            <a href="{{ route('vendors.index') }}" class="btn btn-link">Batal</a>
        </div>
    </div>
</form>
@endsection
