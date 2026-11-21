<?php

namespace App\Models;

use App\Enums\AssignmentStatus;
use App\Enums\Department;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EvaluationAssignment extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'cycle_id', 'assessor_id', 'department', 'status', 'progress_pct',
        'sent_at', 'last_saved_at', 'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'progress_pct' => 'decimal:2',
            'sent_at' => 'datetime',
            'last_saved_at' => 'datetime',
            'submitted_at' => 'datetime',
            'department' => Department::class,
            'status' => AssignmentStatus::class,
        ];
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(EvaluationCycle::class, 'cycle_id');
    }

    public function assessor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assessor_id');
    }

    public function scores(): HasMany
    {
        return $this->hasMany(EvaluationScore::class, 'assignment_id');
    }

    public function reminderLogs(): HasMany
    {
        return $this->hasMany(ReminderLog::class, 'assignment_id');
    }

    public function isSubmitted(): bool
    {
        return $this->status === AssignmentStatus::Submitted;
    }
}
