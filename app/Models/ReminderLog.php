<?php

namespace App\Models;

use App\Enums\Channel;
use App\Enums\SendStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReminderLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'assignment_id', 'rule_id', 'recipient_id', 'channel', 'scheduled_at',
        'sent_at', 'status', 'is_escalation', 'error_message',
    ];

    protected function casts(): array
    {
        return [
            'channel' => Channel::class,
            'status' => SendStatus::class,
            'scheduled_at' => 'datetime',
            'sent_at' => 'datetime',
            'is_escalation' => 'boolean',
        ];
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(EvaluationAssignment::class, 'assignment_id');
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(ReminderRule::class, 'rule_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }
}
