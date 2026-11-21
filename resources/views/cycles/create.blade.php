@extends('layouts.app')

@section('title', 'Siklus Evaluasi Baru')

@section('content')
<x-page-header title="Siklus Evaluasi Baru" :breadcrumb="[['label' => 'Siklus Evaluasi', 'url' => route('cycles.index')], ['label' => 'Baru']]" />

@unless ($formTemplate)
    <div class="alert alert-danger">Belum ada form Vendor Performance Evaluation yang aktif.</div>
@endunless

<form method="POST" action="{{ route('cycles.store') }}">
    @csrf
    <div class="row">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Jadwal Evaluasi (FR-05)</h3></div>
                <div class="card-body row">
                    <div class="col-md-12 form-group">
                        <label for="vendor_id">Vendor <span class="text-danger">*</span></label>
                        <select id="vendor_id" name="vendor_id" class="form-control select2" required>
                            <option value="">-- Pilih vendor --</option>
                            @foreach ($vendors as $vendor)
                                <option value="{{ $vendor->id }}" data-frequency="{{ $vendor->evaluation_frequency->value }}" @selected((int) old('vendor_id', $selectedVendorId) === $vendor->id)>
                                    {{ $vendor->vendor_code }} - {{ $vendor->name }} ({{ $vendor->vendor_type->label() }}, {{ $vendor->evaluation_frequency->label() }}){{ $vendor->status !== \App\Enums\VendorStatus::Active ? ' · '.$vendor->status->label() : '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4 form-group">
                        <label for="cycle_type">Tipe Siklus <span class="text-danger">*</span></label>
                        <select id="cycle_type" name="cycle_type" class="form-control" required>
                            @foreach (\App\Enums\CycleType::options() as $value => $label)
                                <option value="{{ $value }}" @selected(old('cycle_type', $selectedVendorId ? \App\Enums\CycleType::AdHoc->value : \App\Enums\CycleType::Semester->value) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4 form-group">
                        <label for="start_date">Tanggal Mulai <span class="text-danger">*</span></label>
                        <input id="start_date" type="date" name="start_date" class="form-control" value="{{ old('start_date', now()->toDateString()) }}" required>
                    </div>
                    <div class="col-md-4 form-group">
                        <label for="sla_days">Tenggat Pengisian (hari) <span class="text-danger">*</span></label>
                        <input id="sla_days" type="number" min="1" max="90" name="sla_days" class="form-control" value="{{ old('sla_days', $defaultSlaDays) }}" required>
                        <small class="form-text text-muted">Default {{ $defaultSlaDays }} hari kalender. Tenggat: <strong id="due_preview">-</strong></small>
                    </div>
                    <div class="col-md-6 form-group">
                        <label for="period_label">Label Periode</label>
                        <input id="period_label" name="period_label" class="form-control" value="{{ old('period_label') }}" placeholder="Otomatis, mis. 2026-S2 / 2026-ANNUAL" maxlength="30">
                    </div>
                    <div class="col-md-12 form-group" id="adhoc_group">
                        <label for="adhoc_reason">Alasan Evaluasi Ad-hoc <span class="text-danger">*</span></label>
                        <textarea id="adhoc_reason" name="adhoc_reason" rows="2" class="form-control" placeholder="Mis. ada masalah dengan vendor / menjelang perpanjangan kontrak">{{ old('adhoc_reason') }}</textarea>
                    </div>
                    <div class="col-md-12">
                        <div class="custom-control custom-switch">
                            <input type="hidden" name="send_now" value="0">
                            <input type="checkbox" class="custom-control-input" id="send_now" name="send_now" value="1" checked>
                            <label class="custom-control-label" for="send_now">Kirim form ke penilai segera bila tanggal mulai sudah tiba (FR-06)</label>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Penugasan Penilai per Fungsi</h3></div>
                <div class="card-body">
                    <p class="small text-muted">Item form dibagi per fungsi penilai berdasarkan kolom <code>assessor_department</code> pada form resmi.</p>
                    @foreach ($departments as $department)
                        <div class="form-group">
                            <label for="assessor_{{ $department->value }}">{{ $department->label() }} <span class="text-danger">*</span></label>
                            <select id="assessor_{{ $department->value }}" name="assessors[{{ $department->value }}]" class="form-control" required>
                                <option value="">-- Pilih penilai --</option>
                                @foreach ($assessors->get($department->value, collect()) as $assessor)
                                    <option value="{{ $assessor->id }}" @selected((int) old('assessors.'.$department->value, $defaultAssessors[$department->value] ?? 0) === $assessor->id)>{{ $assessor->full_name }}{{ $assessor->job_title ? ' - '.$assessor->job_title : '' }}</option>
                                @endforeach
                            </select>
                            @if ($assessors->get($department->value, collect())->isEmpty())
                                <small class="text-danger">Belum ada akun penilai aktif untuk fungsi ini. Tambahkan di menu Pengguna.</small>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
    <div class="sticky-action">
        <button type="submit" class="btn btn-primary" @disabled(! $formTemplate)><i class="fas fa-paper-plane"></i> Buat Siklus</button>
        <a href="{{ route('cycles.index') }}" class="btn btn-link">Batal</a>
    </div>
</form>
@endsection

@push('scripts')
<script>
    $(function () {
        function refresh() {
            const isAdhoc = $('#cycle_type').val() === 'Ad_hoc';
            $('#adhoc_group').toggle(isAdhoc);
            $('#adhoc_reason').prop('required', isAdhoc);
            const start = new Date($('#start_date').val());
            const sla = parseInt($('#sla_days').val(), 10);
            if (!isNaN(start) && sla > 0) {
                start.setDate(start.getDate() + sla);
                $('#due_preview').text(start.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }));
            }
        }
        $('#cycle_type, #start_date, #sla_days').on('change input', refresh);
        $('#vendor_id').on('change', function () {
            const frequency = $(this).find(':selected').data('frequency');
            if (frequency && $('#cycle_type').val() !== 'Ad_hoc') {
                $('#cycle_type').val(frequency).trigger('change');
            }
        });
        refresh();
    });
</script>
@endpush
