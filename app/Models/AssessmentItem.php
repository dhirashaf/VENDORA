<?php

namespace App\Models;

use App\Enums\Department;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssessmentItem extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'category_id', 'item_no', 'description', 'weight_pct',
        'assessor_department', 'sort_order', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'weight_pct' => 'decimal:2',
            'assessor_department' => Department::class,
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(AssessmentCategory::class, 'category_id');
    }
}
