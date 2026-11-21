<?php

namespace App\Models;

use App\Enums\Decision;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorStatusDecision extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'followup_id', 'vendor_id', 'decision', 'reason', 'decided_by',
        'approved_by', 'effective_date', 'decided_at',
    ];

    protected function casts(): array
    {
        return [
            'decision' => Decision::class,
            'effective_date' => 'date',
            'decided_at' => 'datetime',
        ];
    }

    public function followUp(): BelongsTo
    {
        return $this->belongsTo(FollowUp::class, 'followup_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isApproved(): bool
    {
        return $this->approved_by !== null;
    }
}
