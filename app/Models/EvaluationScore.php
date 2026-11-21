<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EvaluationScore extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'evaluation_id', 'assignment_id', 'item_id', 'score', 'weight_pct',
        'weighted_score', 'notes', 'evidence_url', 'scored_at',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'integer',
            'weight_pct' => 'decimal:2',
            'weighted_score' => 'decimal:2',
            'scored_at' => 'datetime',
        ];
    }

    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(Evaluation::class);
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(EvaluationAssignment::class, 'assignment_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(AssessmentItem::class, 'item_id');
    }
}
