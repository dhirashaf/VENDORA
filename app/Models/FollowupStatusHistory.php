<?php

namespace App\Models;

use App\Enums\FollowupStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FollowupStatusHistory extends Model
{
    public $timestamps = false;

    protected $table = 'followup_status_history';

    protected $fillable = ['followup_id', 'from_status', 'to_status', 'changed_by', 'changed_at', 'notes'];

    protected function casts(): array
    {
        return [
            'from_status' => FollowupStatus::class,
            'to_status' => FollowupStatus::class,
            'changed_at' => 'datetime',
        ];
    }

    public function followUp(): BelongsTo
    {
        return $this->belongsTo(FollowUp::class, 'followup_id');
    }

    public function changer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
