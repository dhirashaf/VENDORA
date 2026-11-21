<?php

namespace App\Models;

use App\Enums\CycleStatus;
use App\Enums\CycleType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

class EvaluationCycle extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'cycle_code', 'vendor_id', 'form_template_id', 'period_label', 'cycle_type',
        'adhoc_reason', 'start_date', 'sla_days', 'due_date', 'status', 'created_by', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'due_date' => 'date',
            'completed_at' => 'datetime',
            'sla_days' => 'integer',
            'cycle_type' => CycleType::class,
            'status' => CycleStatus::class,
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function formTemplate(): BelongsTo
    {
        return $this->belongsTo(FormTemplate::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(EvaluationAssignment::class, 'cycle_id');
    }

    public function evaluation(): HasOne
    {
        return $this->hasOne(Evaluation::class, 'cycle_id');
    }

    /**
     * Siklus sedang menunggu pengisian penilai.
     */
    public function isCollecting(): bool
    {
        return in_array($this->status, [CycleStatus::Open, CycleStatus::InProgress], true);
    }

    public function isOverdue(?Carbon $today = null): bool
    {
        return $this->isCollecting() && $this->due_date->lt($today ?? Carbon::today());
    }

    /**
     * Sisa hari menuju tenggat (negatif bila terlewat).
     */
    public function remainingDays(?Carbon $today = null): int
    {
        return (int) ($today ?? Carbon::today())->diffInDays($this->due_date, false);
    }

    /**
     * Umur (aging): hari terlambat sejak tenggat untuk yang belum lengkap,
     * atau hari sejak dikompilasi untuk yang menunggu approval (FR-10).
     */
    public function agingDays(?Carbon $today = null): int
    {
        $today ??= Carbon::today();

        if ($this->isCollecting()) {
            return max(0, (int) $this->due_date->diffInDays($today, false));
        }

        if ($this->status === CycleStatus::PendingApproval && $this->evaluation?->compiled_at) {
            return max(0, (int) $this->evaluation->compiled_at->copy()->startOfDay()->diffInDays($today, false));
        }

        return 0;
    }
}
