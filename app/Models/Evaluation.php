<?php

namespace App\Models;

use App\Enums\Recommendation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Evaluation extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'cycle_id', 'total_weighted_score', 'achievement_pct', 'recommendation',
        'is_complete', 'compiled_by', 'compiled_at', 'approved_by', 'approved_at', 'approval_notes',
    ];

    protected function casts(): array
    {
        return [
            'total_weighted_score' => 'decimal:2',
            'achievement_pct' => 'decimal:2',
            'is_complete' => 'boolean',
            'compiled_at' => 'datetime',
            'approved_at' => 'datetime',
            'recommendation' => Recommendation::class,
        ];
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(EvaluationCycle::class, 'cycle_id');
    }

    public function scores(): HasMany
    {
        return $this->hasMany(EvaluationScore::class);
    }

    public function compiler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'compiled_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function followUps(): HasMany
    {
        return $this->hasMany(FollowUp::class);
    }
}
