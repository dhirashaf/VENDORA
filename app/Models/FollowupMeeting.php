<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FollowupMeeting extends Model
{
    public $timestamps = false;

    protected $fillable = ['followup_id', 'scheduled_at', 'location_or_link', 'organized_by', 'minutes', 'held_at'];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'held_at' => 'datetime',
        ];
    }

    public function followUp(): BelongsTo
    {
        return $this->belongsTo(FollowUp::class, 'followup_id');
    }

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'organized_by');
    }
}
