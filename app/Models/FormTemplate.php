<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class FormTemplate extends Model
{
    public $timestamps = false;

    protected $fillable = ['name', 'version', 'effective_date', 'is_active'];

    protected function casts(): array
    {
        return [
            'effective_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function categories(): HasMany
    {
        return $this->hasMany(AssessmentCategory::class)->orderBy('sort_order');
    }

    public function items(): HasManyThrough
    {
        return $this->hasManyThrough(AssessmentItem::class, AssessmentCategory::class, 'form_template_id', 'category_id');
    }

    public static function active(): ?self
    {
        return self::query()->where('is_active', true)->latest('effective_date')->first();
    }
}
