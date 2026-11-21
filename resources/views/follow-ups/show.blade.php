@extends('layouts.app')

@section('title', 'Tindak Lanjut '.$followUp->followup_no)

@php
    use App\Enums\FollowupStatus;
    $canManage = auth()->user()->can('manage-followups') && ! $followUp->isLocked();
    $evaluation = $followUp->evaluation;
    $decision = $followUp->statusDecision;
    $steps = array_slice(FollowupStatus::workflow(), 1, 6);
    $field = fn (string $name) => old($name, $followUp->{$name} instanceof \BackedEnum ? $followUp->{$name}->value : ($followUp->{$name} instanceof \Carbon\CarbonInterface ? $followUp->{$name}->toDateString() : $followUp->{$name}));
@endphp

@section('content')
<x-page-header :title="'Tindak Lanjut: '.$followUp->vendor->name.' (skor '.number_format((float) $evaluation->total_weighted_score, 2, ',', '.').', di bawah standar)'" :breadcrumb="[['label' => 'Tindak Lanjut', 'url' => route('follow-ups.index')], ['label' => $followUp->followup_no]]" />
<p class="text-muted mt-n2">{{ $followUp->followup_no }} · alur 6 tahap mengikuti SOP Vendor Evaluation &amp; Development.</p>

<div class="stepper mb-2">
    @foreach ($steps as $index => $step)
        @php
            $isDone = $followUp->status->position() > $step->position() || $followUp->status === FollowupStatus::Closed;
            $isCurrent = $followUp->status === $step || ($followUp->status === FollowupStatus::Open && $index === 0);
        @endphp
        <div class="step {{ $isDone ? 'done' : ($isCurrent ? 'current' : '') }}">
            {!! $isDone ? '<i class="fas fa-check text-success"></i>' : ($index + 1).'.' !!} {{ $step === FollowupStatus::DecisionMade ? 'Keputusan' : $step->label() }}
        </div>
    @endforeach
</div>
<p class="font-weight-bold text-warning mb-3">Status saat ini: {{ $followUp->status->label() }}{{ $followUp->isLocked() ? '' : ' (in progress)' }}</p>

<div class="row">
    <div class="col-lg-8">
        {{-- Deviation & Corrective Action Form (FR-18) --}}
        <form method="POST" action="{{ route('follow-ups.update', $followUp) }}">
            @csrf
            @method('PUT')
            <div class="card">
                <div class="card-header"><h3 class="card-title">Deviation &amp; Corrective Action Form</h3></div>
                <div class="card-body">
                    <fieldset @disabled(! $canManage)>
                        <h6 class="text-primary">1. Informasi Umum &amp; Vendor</h6>
                        <div class="row">
                            <div class="col-md-4 form-group"><label>Vendor</label><input class="form-control" value="{{ $followUp->vendor->name }} ({{ $followUp->vendor->vendor_type->label() }})" disabled></div>
                            <div class="col-md-4 form-group"><label>Evaluasi terkait</label>
                                <div class="form-control-plaintext"><a href="{{ route('recap.vendor', $followUp->vendor) }}">{{ $evaluation->cycle->period_label }}, skor {{ number_format((float) $evaluation->total_weighted_score, 2, ',', '.') }}</a></div>
                            </div>
                            <div class="col-md-4 form-group"><label>Dibuka</label><input class="form-control" value="{{ $followUp->opened_at->format('d M Y H:i') }}" disabled></div>
                            <div class="col-md-6 form-group">
                                <label for="pic_user_id">Penanggung jawab <span class="text-danger">*</span></label>
                                <select id="pic_user_id" name="pic_user_id" class="form-control" required>
                                    @foreach ($picOptions->push($followUp->pic)->unique('id') as $pic)
                                        <option value="{{ $pic->id }}" @selected((int) $field('pic_user_id') === $pic->id)>{{ $pic->full_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6 form-group">
                                <label for="due_date">Tenggat kasus</label>
                                <input id="due_date" type="date" name="due_date" class="form-control" value="{{ $field('due_date') }}">
                            </div>
                        </div>

                        <h6 class="text-primary mt-2">2. Non-Conformance</h6>
                        <div class="row">
                            <div class="col-md-4 form-group">
                                <label for="nc_category">Kategori</label>
                                <select id="nc_category" name="nc_category" class="form-control">
                                    <option value="">-- Pilih --</option>
                                    @foreach (\App\Enums\NonconformanceCategory::options() as $value => $label)
                                        <option value="{{ $value }}" @selected($field('nc_category') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-8 form-group">
                                <label for="nc_description">Deskripsi ketidaksesuaian</label>
                                <textarea id="nc_description" name="nc_description" rows="2" class="form-control">{{ $field('nc_description') }}</textarea>
                            </div>
                            <div class="col-md-6 form-group">
                                <label for="root_cause">Root cause</label>
                                <textarea id="root_cause" name="root_cause" rows="2" class="form-control">{{ $field('root_cause') }}</textarea>
                            </div>
                            <div class="col-md-6 form-group">
                                <label for="impact_assessment">Impact assessment</label>
                                <textarea id="impact_assessment" name="impact_assessment" rows="2" class="form-control">{{ $field('impact_assessment') }}</textarea>
                            </div>
                        </div>

                        <h6 class="text-primary mt-2">3. Tindakan yang Diminta</h6>
                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label for="requested_corrective_action">Requested corrective action</label>
                                <textarea id="requested_corrective_action" name="requested_corrective_action" rows="2" class="form-control">{{ $field('requested_corrective_action') }}</textarea>
                            </div>
                            <div class="col-md-6 form-group">
                                <label for="preventive_action">Preventive action</label>
                                <textarea id="preventive_action" name="preventive_action" rows="2" class="form-control">{{ $field('preventive_action') }}</textarea>
                            </div>
                        </div>

                        <h6 class="text-primary mt-2">4. Vendor Response</h6>
                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label for="vendor_explanation">Penjelasan vendor</label>
                                <textarea id="vendor_explanation" name="vendor_explanation" rows="2" class="form-control">{{ $field('vendor_explanation') }}</textarea>
                            </div>
                            <div class="col-md-6 form-group">
                                <label for="vendor_corrective_action">Corrective action diajukan vendor</label>
                                <textarea id="vendor_corrective_action" name="vendor_corrective_action" rows="2" class="form-control">{{ $field('vendor_corrective_action') }}</textarea>
                            </div>
                            <div class="col-md-4 form-group">
                                <label for="target_completion_date">Target completion date</label>
                                <input id="target_completion_date" type="date" name="target_completion_date" class="form-control" value="{{ $field('target_completion_date') }}">
                            </div>
                        </div>

                        <h6 class="text-primary mt-2">5. Result &amp; Recommendation</h6>
                        <textarea name="result_recommendation" rows="2" class="form-control" aria-label="Result and recommendation">{{ $field('result_recommendation') }}</textarea>
                    </fieldset>
                </div>
                @if ($canManage)
                    <div class="card-footer d-flex flex-wrap align-items-center">
                        <button type="submit" class="btn btn-outline-primary mr-2"><i class="fas fa-save"></i> Simpan Form</button>
                        @if ($followUp->status !== FollowupStatus::Monitoring)
                            <button type="submit" form="advance_form" class="btn btn-primary mr-2" @disabled($missingRequirements !== [])>
                                <i class="fas fa-forward"></i> Tandai {{ $followUp->status === FollowupStatus::Open ? 'mulai review kode etik' : strtolower($followUp->status->label()).' selesai' }}
                            </button>
                        @endif
                        @if ($missingRequirements !== [])
                            <small class="text-danger">Lengkapi untuk lanjut: {{ implode('; ', $missingRequirements) }} (simpan form dulu).</small>
                        @endif
                    </div>
                @endif
            </div>
        </form>
        <form method="POST" action="{{ route('follow-ups.advance', $followUp) }}" id="advance_form">@csrf</form>

        <div class="row">
            {{-- Review kode etik --}}
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header"><h3 class="card-title">Review Kode Etik / Due Diligence</h3></div>
                    <div class="card-body">
                        @forelse ($followUp->ethicsReviews as $review)
                            <div class="border-bottom pb-2 mb-2 small">
                                <strong>{{ $review->reviewer->full_name }}</strong> · {{ $review->reviewed_at?->format('d M Y H:i') }}<br>
                                @if ($review->fraud_indication)<span class="badge badge-danger">Indikasi fraud</span>@endif
                                @if ($review->due_diligence_required)<span class="badge badge-warning">Perlu due diligence</span>@endif
                                <div>{{ $review->findings }}</div>
                            </div>
                        @empty
                            <p class="text-muted small">Belum ada review.</p>
                        @endforelse
                        @if ($canManage && in_array($followUp->status, [FollowupStatus::Open, FollowupStatus::EthicsReview], true))
                            <form method="POST" action="{{ route('follow-ups.ethics-reviews.store', $followUp) }}">
                                @csrf
                                <textarea name="findings" rows="2" class="form-control form-control-sm mb-1" placeholder="Temuan berdasarkan history PO, hasil audit, dan evidence" aria-label="Temuan review" required></textarea>
                                <div class="custom-control custom-checkbox"><input type="hidden" name="fraud_indication" value="0"><input type="checkbox" class="custom-control-input" id="fraud_indication" name="fraud_indication" value="1"><label class="custom-control-label small" for="fraud_indication">Ada indikasi fraud</label></div>
                                <div class="custom-control custom-checkbox mb-2"><input type="hidden" name="due_diligence_required" value="0"><input type="checkbox" class="custom-control-input" id="due_diligence_required" name="due_diligence_required" value="1"><label class="custom-control-label small" for="due_diligence_required">Perlu vendor due diligence</label></div>
                                <button class="btn btn-sm btn-outline-primary">Simpan Review</button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
            {{-- Meeting klarifikasi --}}
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header"><h3 class="card-title">Meeting Klarifikasi</h3></div>
                    <div class="card-body">
                        @forelse ($followUp->meetings as $meeting)
                            <div class="border-bottom pb-2 mb-2 small">
                                <strong>{{ $meeting->scheduled_at->format('d M Y H:i') }}</strong> · {{ $meeting->location_or_link ?? '-' }}<br>
                                Penyelenggara: {{ $meeting->organizer->full_name }}
                                @if ($meeting->held_at)
                                    <div class="mt-1"><span class="badge badge-success">Terlaksana {{ $meeting->held_at->format('d M Y') }}</span> {{ $meeting->minutes }}</div>
                                @elseif ($canManage)
                                    <form method="POST" action="{{ route('follow-ups.meetings.update', [$followUp, $meeting]) }}" class="mt-1">
                                        @csrf @method('PUT')
                                        <input type="datetime-local" name="held_at" class="form-control form-control-sm mb-1" value="{{ $meeting->scheduled_at->format('Y-m-d\TH:i') }}" aria-label="Waktu pelaksanaan" required>
                                        <textarea name="minutes" rows="2" class="form-control form-control-sm mb-1" placeholder="Notulen / hasil klarifikasi" aria-label="Notulen" required></textarea>
                                        <button class="btn btn-xs btn-outline-success">Tandai terlaksana</button>
                                    </form>
                                @endif
                            </div>
                        @empty
                            <p class="text-muted small">Belum ada undangan meeting.</p>
                        @endforelse
                        @if ($canManage && $followUp->status->position() >= FollowupStatus::MeetingInvited->position())
                            <form method="POST" action="{{ route('follow-ups.meetings.store', $followUp) }}">
                                @csrf
                                <input type="datetime-local" name="scheduled_at" class="form-control form-control-sm mb-1" aria-label="Jadwal meeting" required>
                                <input name="location_or_link" class="form-control form-control-sm mb-1" placeholder="Lokasi / tautan meeting" maxlength="300" aria-label="Lokasi">
                                <button class="btn btn-sm btn-outline-primary">Kirim Undangan Meeting</button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Lampiran --}}
        <div class="card">
            <div class="card-header"><h3 class="card-title">Bukti / Lampiran Non-Conformance</h3></div>
            <div class="card-body">
                <ul class="mb-2">
                    @forelse ($followUp->attachments as $attachment)
                        <li><a href="{{ route('follow-ups.attachments.download', [$followUp, $attachment]) }}">{{ $attachment->file_name }}</a> <small class="text-muted">· {{ $attachment->uploader->full_name }}, {{ $attachment->uploaded_at->format('d M Y H:i') }}</small></li>
                    @empty
                        <li class="text-muted small list-unstyled">Belum ada lampiran.</li>
                    @endforelse
                </ul>
                @if ($canManage)
                    <form method="POST" action="{{ route('follow-ups.attachments.store', $followUp) }}" enctype="multipart/form-data" class="form-inline">
                        @csrf
                        <input type="file" name="file" class="form-control-file mr-2" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx" aria-label="Berkas lampiran" required>
                        <button class="btn btn-sm btn-outline-primary">Unggah</button>
                        <small class="text-muted ml-2">PDF, gambar, Word, Excel; maks 10 MB.</small>
                    </form>
                @endif
            </div>
        </div>

        {{-- Keputusan akhir (FR-20) --}}
        <div class="card card-outline {{ $decision ? 'card-success' : 'card-secondary' }}">
            <div class="card-header"><h3 class="card-title">Keputusan Akhir</h3></div>
            <div class="card-body">
                @if ($decision)
                    <p class="mb-1"><x-status-badge :status="$decision->decision" class="p-2" /></p>
                    <p class="mb-1">{{ $decision->reason }}</p>
                    <p class="small text-muted mb-2">Dicatat {{ $decision->decider->full_name }} pada {{ $decision->decided_at->format('d M Y H:i') }}, berlaku {{ $decision->effective_date?->format('d M Y') }}.
                        @if ($decision->isApproved()) Disetujui {{ $decision->approver->full_name }}. Status vendor: <x-status-badge :status="$followUp->vendor->status" />@endif
                    </p>
                    @if (! $decision->isApproved())
                        @can('approve-decision')
                            <div class="d-flex flex-wrap">
                                <form method="POST" action="{{ route('follow-ups.decision.approve', $followUp) }}" class="mr-2" data-confirm="Setujui keputusan ini? Status vendor akan diperbarui.">@csrf
                                    <button class="btn btn-success btn-sm"><i class="fas fa-check"></i> Approve Keputusan</button>
                                </form>
                                <form method="POST" action="{{ route('follow-ups.decision.reject', $followUp) }}" class="form-inline">@csrf
                                    <input name="notes" class="form-control form-control-sm mr-1" placeholder="Alasan dikembalikan" aria-label="Alasan" required>
                                    <button class="btn btn-outline-warning btn-sm">Kembalikan</button>
                                </form>
                            </div>
                        @else
                            <p class="small text-warning mb-0"><i class="fas fa-hourglass-half"></i> Menunggu approval PVM Manager.</p>
                        @endcan
                    @endif
                @elseif ($canManage && $canDecide)
                    <form method="POST" action="{{ route('follow-ups.decide', $followUp) }}" data-confirm="Catat keputusan akhir? Kasus akan dikunci dan menunggu approval PVM Manager.">
                        @csrf
                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label for="decision">Keputusan <span class="text-danger">*</span></label>
                                <select id="decision" name="decision" class="form-control" required>
                                    @foreach (\App\Enums\Decision::options() as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6 form-group">
                                <label for="effective_date">Tanggal berlaku</label>
                                <input id="effective_date" type="date" name="effective_date" class="form-control" value="{{ now()->toDateString() }}">
                            </div>
                            <div class="col-md-12 form-group">
                                <label for="reason">Alasan <span class="text-danger">*</span></label>
                                <textarea id="reason" name="reason" rows="2" class="form-control" required placeholder="Berdasarkan hasil monitoring/corrective action"></textarea>
                            </div>
                        </div>
                        <button class="btn btn-primary">Catat Keputusan</button>
                    </form>
                @else
                    <p class="text-muted mb-2">Tersedia setelah monitoring selesai (atau segera bila review kode etik menemukan indikasi fraud):</p>
                    @foreach (\App\Enums\Decision::cases() as $option)
                        <span class="btn btn-sm btn-outline-{{ $option->badge() }} disabled mb-1">{{ $option->label() }}</span>
                    @endforeach
                @endif
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><h3 class="card-title">Riwayat status</h3></div>
            <div class="card-body">
                <div class="timeline-simple">
                    @foreach ($followUp->statusHistory as $history)
                        <div class="entry">
                            <strong>{{ $history->to_status->label() }}</strong><br>
                            <small class="text-muted">{{ $history->changed_at->format('d M Y H:i') }} · {{ $history->changer->full_name }}</small>
                            @if ($history->notes)<div class="small">{{ $history->notes }}</div>@endif
                        </div>
                    @endforeach
                    @unless ($followUp->isLocked())
                        <div class="entry text-muted"><strong>{{ $followUp->status->label() }} berjalan</strong><br><small>Status terkini</small></div>
                    @endunless
                </div>
            </div>
        </div>
        @if ($archive->isNotEmpty())
            <div class="card">
                <div class="card-header"><h3 class="card-title">Kasus sebelumnya vendor ini</h3></div>
                <ul class="list-group list-group-flush">
                    @foreach ($archive as $previous)
                        <li class="list-group-item small"><a href="{{ route('follow-ups.show', $previous) }}">{{ $previous->followup_no }}</a> <x-status-badge :status="$previous->status" /></li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
</div>
@endsection
