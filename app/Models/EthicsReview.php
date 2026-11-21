<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EthicsReview extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'followup_id', 'reviewed_by', 'fraud_indication', 'due_diligence_required', 'findings', 'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'fraud_indication' => 'boolean',
            'due_diligence_required' => 'boolean',
            'reviewed_at' => 'datetime',
        ];
    }

    public function followUp(): BelongsTo
    {
        return $this->belongsTo(FollowUp::class, 'followup_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
