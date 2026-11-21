@extends('layouts.app')

@section('title', 'Form Evaluasi '.$cycle->vendor->name)

@php
    $ownItemCount = $ownItemIds->count();
    $progress = $ownItemCount ? round($filledCount / $ownItemCount * 100) : 100;
    $evaluation = $cycle->evaluation;
@endphp

@section('content')
<x-page-header :title="'Form Evaluasi Vendor: '.$cycle->vendor->name.' ('.$cycle->vendor->vendor_type->label().')'" :breadcrumb="[['label' => 'Form Evaluasi', 'url' => route('assessments.index')], ['label' => $cycle->vendor->name]]" />
<p class="text-muted mt-n2">7 kategori, 14 item, skor 1 sampai 4. Anda menilai {{ $ownItemCount }} item fungsi <strong>{{ $assignment->department->label() }}</strong>; seluruh item wajib diisi sebelum submit.</p>

<div class="row">
    @foreach ([
        ['Vendor', $cycle->vendor->name.' · '.$cycle->vendor->vendor_code],
        ['Kategori vendor', $cycle->vendor->vendor_type->label().' / '.$cycle->vendor->vendor_category->label()],
        ['Periode', $cycle->period_label.' ('.$cycle->cycle_type->label().')'],
        ['Tenggat pengisian', $cycle->due_date->format('d M Y').($cycle->isCollecting() ? ' · '.($cycle->remainingDays() < 0 ? abs($cycle->remainingDays()).' hari terlewat' : $cycle->remainingDays().' hari lagi') : '')],
    ] as [$label, $value])
        <div class="col-md-3 col-sm-6">
            <div class="info-box shadow-none border mb-2"><div class="info-box-content"><span class="info-box-text text-muted small">{{ $label }}</span><span class="info-box-number">{{ $value }}</span></div></div>
        </div>
    @endforeach
</div>

<div class="card card-outline card-success mb-3">
    <div class="card-body py-2">
        <div class="d-flex justify-content-between"><span id="progress_text">Progres pengisian: {{ $progress }}% ({{ $filledCount }}/{{ $ownItemCount }} item terisi)</span><span id="progress_pct">{{ $progress }}%</span></div>
        <div class="progress progress-sm"><div id="progress_bar" class="progress-bar bg-success" style="width: {{ $progress }}%"></div></div>
        <small class="text-muted">Status penugasan: <x-status-badge :status="$assignment->status" />
            @if ($assignment->last_saved_at) · terakhir disimpan {{ $assignment->last_saved_at->format('d M Y H:i') }}@endif
            @if ($assignment->submitted_at) · disubmit {{ $assignment->submitted_at->format('d M Y H:i') }}@endif
        </small>
    </div>
</div>

<div id="missing_alert" class="alert alert-warning" style="display: none;" role="alert">
    <strong>Field wajib belum lengkap.</strong> <span id="missing_text"></span> Submit akan ditolak hingga seluruh item terisi.
</div>

<form method="POST" action="{{ route('assessments.update', $assignment) }}" id="assessment_form" novalidate>
    @csrf
    @method('PUT')
    @foreach ($categories as $category)
        <div class="card mb-3">
            <div class="card-header bg-light d-flex justify-content-between">
                <strong>{{ $category->code }}. {{ $category->name }}</strong>
                <span class="small text-muted">Bobot kategori {{ number_format($category->items->sum('weight_pct'), 0) }}%</span>
            </div>
            <div class="card-body p-0">
                @foreach ($category->items as $item)
                    @php
                        $score = $scoresByItem->get($item->id);
                        $isOwn = $ownItemIds->contains($item->id);
                        $value = old('scores.'.$item->id.'.score', $score?->score);
                    @endphp
                    <div class="row mx-0 py-2 border-bottom item-row {{ $isOwn ? '' : 'bg-light' }}" data-category="{{ $category->code }}" data-own="{{ $isOwn ? 1 : 0 }}">
                        <div class="col-md-6">
                            <div class="font-weight-bold">{{ $item->item_no }}. {{ $item->description }}</div>
                            @if ($isOwn && $canEdit)
                                <input type="text" name="scores[{{ $item->id }}][notes]" class="form-control form-control-sm mt-1" value="{{ old('scores.'.$item->id.'.notes', $score?->notes) }}" placeholder="Catatan (opsional)" maxlength="2000" aria-label="Catatan item {{ $item->item_no }}">
                                <input type="url" name="scores[{{ $item->id }}][evidence_url]" class="form-control form-control-sm mt-1 @error('scores.'.$item->id.'.evidence_url') is-invalid @enderror" value="{{ old('scores.'.$item->id.'.evidence_url', $score?->evidence_url) }}" placeholder="Tautan bukti https://... (opsional)" maxlength="500" aria-label="Tautan bukti item {{ $item->item_no }}">
                            @elseif ($score?->notes || $score?->evidence_url)
                                <div class="small text-muted mt-1">{{ $score->notes }} @if ($score->evidence_url)<a href="{{ $score->evidence_url }}" target="_blank" rel="noopener noreferrer">bukti</a>@endif</div>
                            @endif
                        </div>
                        <div class="col-md-3 d-flex align-items-center">
                            @if ($isOwn && $canEdit)
                                <select name="scores[{{ $item->id }}][score]" class="form-control form-control-sm score-input" data-weight="{{ $item->weight_pct }}" aria-label="Skor item {{ $item->item_no }}">
                                    <option value="">-- Pilih skor --</option>
                                    @foreach ($scales as $scale)
                                        <option value="{{ $scale->score }}" @selected((string) $value === (string) $scale->score) title="{{ $scale->definition }}">{{ $scale->score }} - {{ $scale->label }}</option>
                                    @endforeach
                                </select>
                            @elseif ($isOwn || auth()->user()->can('view-all-evaluations'))
                                <span>{{ $score ? $score->score.' - '.$scales->firstWhere('score', $score->score)?->label : '-' }}</span>
                            @else
                                <span class="small text-muted"><i class="fas fa-user-lock"></i> Diisi {{ $item->assessor_department->label() }}</span>
                            @endif
                        </div>
                        <div class="col-md-3 d-flex align-items-center justify-content-between small">
                            <span class="text-muted">Bobot: {{ number_format((float) $item->weight_pct, 0) }}%</span>
                            <span class="weighted-score font-weight-bold" data-item="{{ $item->id }}">{{ $score && ($isOwn || auth()->user()->can('view-all-evaluations')) ? number_format((float) $score->weighted_score, 2, ',', '.') : '' }}</span>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach

    <div class="sticky-action d-flex flex-wrap align-items-center">
        @if ($canEdit)
            <button type="submit" name="action" value="draft" class="btn btn-outline-secondary mr-2"><i class="fas fa-save"></i> Simpan Draf</button>
            <button type="submit" name="action" value="submit" id="submit_button" class="btn btn-primary mr-3"><i class="fas fa-paper-plane"></i> Submit Evaluasi</button>
            <span id="submit_hint" class="small text-danger mr-auto"></span>
        @else
            <span class="small text-muted mr-auto">
                <i class="fas fa-lock"></i>
                @if ($assignment->isSubmitted()) Form telah disubmit dan terkunci.@elseif (! $cycle->isCollecting()) Siklus tidak lagi menerima pengisian.@else Mode lihat saja.@endif
            </span>
        @endif
        @if ($canReopen)
            <button type="submit" form="reopen_form" class="btn btn-outline-warning btn-sm mr-3"><i class="fas fa-unlock"></i> Buka kembali untuk revisi</button>
        @endif
        <span class="ml-auto">Total weighted score:
            <strong id="total_score">
                @if ($evaluation->is_complete)
                    {{ number_format((float) $evaluation->total_weighted_score, 2, ',', '.') }} / 4,00 ({{ number_format((float) $evaluation->achievement_pct, 2, ',', '.') }}%) · {{ $evaluation->recommendation->label() }}
                @else
                    belum dapat dihitung
                @endif
            </strong>
        </span>
    </div>
</form>
@if ($canReopen)
    <form method="POST" action="{{ route('assignments.reopen', $assignment) }}" id="reopen_form" data-confirm="Buka kembali form untuk revisi? Status evaluasi akan kembali menjadi belum lengkap.">
        @csrf
    </form>
@endif
@endsection

@push('scripts')
@if ($canEdit)
<script>
    $(function () {
        const ownCount = {{ $ownItemCount }};

        function format(value) {
            return value.toFixed(2).replace('.', ',');
        }

        function refresh(markMissing) {
            let filled = 0;
            const missingCategories = new Set();

            $('.score-input').each(function () {
                const $select = $(this);
                const $row = $select.closest('.item-row');
                const weight = parseFloat($select.data('weight'));
                const score = parseInt($select.val(), 10);
                const $weighted = $row.find('.weighted-score');

                if (score) {
                    filled++;
                    $weighted.text(format(score * weight / 100));
                    $row.removeClass('item-missing');
                } else {
                    $weighted.text('');
                    missingCategories.add($row.data('category'));
                    if (markMissing) {
                        $row.addClass('item-missing');
                    }
                }
            });

            const pct = ownCount ? Math.round(filled / ownCount * 100) : 100;
            $('#progress_bar').css('width', pct + '%');
            $('#progress_pct').text(pct + '%');
            $('#progress_text').text('Progres pengisian: ' + pct + '% (' + filled + '/' + ownCount + ' item terisi)');

            const empty = ownCount - filled;
            $('#submit_hint').text(empty > 0 ? empty + ' item masih kosong.' : '');

            if (markMissing && empty > 0) {
                $('#missing_text').text('Kategori ' + Array.from(missingCategories).sort().join(', ') + ' belum lengkap.');
                $('#missing_alert').show();
            } else if (empty === 0) {
                $('#missing_alert').hide();
            }

            return empty;
        }

        $('.score-input').on('change', function () { refresh(false); });

        $('#assessment_form').on('submit', function (event) {
            const action = event.originalEvent && event.originalEvent.submitter ? event.originalEvent.submitter.value : 'draft';
            if (action === 'submit' && refresh(true) > 0) {
                event.preventDefault();
                $('html, body').animate({ scrollTop: $('.item-missing').first().offset().top - 120 }, 200);
                $('.item-missing').first().find('select').trigger('focus');
            }
        });

        refresh(false);
    });
</script>
@endif
@endpush
