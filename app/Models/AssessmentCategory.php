<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssessmentCategory extends Model
{
    public $timestamps = false;

    protected $fillable = ['form_template_id', 'code', 'name', 'sort_order'];

    public function formTemplate(): BelongsTo
    {
        return $this->belongsTo(FormTemplate::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(AssessmentItem::class, 'category_id')->orderBy('sort_order');
    }
}
