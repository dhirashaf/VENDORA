<?php

namespace App\Models;

use App\Enums\EvaluationFrequency;
use App\Enums\VendorCategory;
use App\Enums\VendorStatus;
use App\Enums\VendorType;
use Database\Factories\VendorFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vendor extends Model
{
    /** @use HasFactory<VendorFactory> */
    use HasFactory;

    protected $fillable = [
        'vendor_code', 'sap_vendor_id', 'name', 'vendor_type', 'vendor_category',
        'is_strategic', 'evaluation_frequency', 'contract_end_date',
        'contact_person', 'contact_email', 'status',
    ];

    protected function casts(): array
    {
        return [
            'is_strategic' => 'boolean',
            'contract_end_date' => 'date',
            'vendor_type' => VendorType::class,
            'vendor_category' => VendorCategory::class,
            'evaluation_frequency' => EvaluationFrequency::class,
            'status' => VendorStatus::class,
        ];
    }

    public function evaluationCycles(): HasMany
    {
        return $this->hasMany(EvaluationCycle::class);
    }

    public function followUps(): HasMany
    {
        return $this->hasMany(FollowUp::class);
    }

    public function statusDecisions(): HasMany
    {
        return $this->hasMany(VendorStatusDecision::class);
    }
}
