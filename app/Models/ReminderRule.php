<?php

namespace App\Models;

use App\Enums\ReminderTrigger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class ReminderRule extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'name', 'trigger_type', 'offset_days', 'repeat_every_days',
        'template_id', 'escalate_to_role_id', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'trigger_type' => ReminderTrigger::class,
            'offset_days' => 'integer',
            'repeat_every_days' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(ReminderTemplate::class, 'template_id');
    }

    public function escalateToRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'escalate_to_role_id');
    }

    /**
     * Apakah aturan ini jatuh pada tanggal tertentu relatif terhadap tenggat.
     * Before_Due: tepat H-offset. After_Due / Escalation: H+offset,
     * lalu berulang tiap repeat_every_days bila diisi.
     */
    public function isDueOn(Carbon $dueDate, Carbon $date): bool
    {
        $daysAfterDue = (int) $dueDate->copy()->startOfDay()->diffInDays($date->copy()->startOfDay(), false);

        if ($this->trigger_type === ReminderTrigger::BeforeDue) {
            return $daysAfterDue === -$this->offset_days;
        }

        if ($daysAfterDue < $this->offset_days) {
            return false;
        }

        if ($daysAfterDue === $this->offset_days) {
            return true;
        }

        return $this->repeat_every_days > 0
            && ($daysAfterDue - $this->offset_days) % $this->repeat_every_days === 0;
    }

    public function describeSchedule(): string
    {
        $base = $this->trigger_type === ReminderTrigger::BeforeDue
            ? 'H-'.$this->offset_days
            : 'H+'.$this->offset_days;

        return $this->repeat_every_days ? $base.', lalu tiap '.$this->repeat_every_days.' hari' : $base;
    }
}
