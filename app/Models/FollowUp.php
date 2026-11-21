<?php

namespace App\Models;

use App\Enums\FollowupStatus;
use App\Enums\NonconformanceCategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class FollowUp extends Model
{
    public $timestamps = false;

    protected $table = 'follow_ups';

    protected $fillable = [
        'followup_no', 'evaluation_id', 'vendor_id', 'status', 'pic_user_id',
        'due_date', 'nc_category', 'nc_description', 'root_cause',
        'requested_corrective_action', 'preventive_action', 'impact_assessment',
        'vendor_explanation', 'vendor_corrective_action', 'target_completion_date',
        'result_recommendation', 'opened_at', 'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'target_completion_date' => 'date',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'status' => FollowupStatus::class,
            'nc_category' => NonconformanceCategory::class,
        ];
    }

    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(Evaluation::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function pic(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pic_user_id');
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(FollowupStatusHistory::class, 'followup_id')->orderBy('changed_at')->orderBy('id');
    }

    public function ethicsReviews(): HasMany
    {
        return $this->hasMany(EthicsReview::class, 'followup_id');
    }

    public function meetings(): HasMany
    {
        return $this->hasMany(FollowupMeeting::class, 'followup_id')->orderBy('scheduled_at');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(FollowupAttachment::class, 'followup_id');
    }

    public function statusDecision(): HasOne
    {
        return $this->hasOne(VendorStatusDecision::class, 'followup_id');
    }

    public function isLocked(): bool
    {
        return $this->status->isFinal();
    }
}
