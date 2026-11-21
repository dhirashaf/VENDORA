<?php

namespace Database\Factories;

use App\Enums\EvaluationFrequency;
use App\Enums\VendorCategory;
use App\Enums\VendorStatus;
use App\Enums\VendorType;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vendor>
 */
class VendorFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'vendor_code' => 'VND-'.fake()->unique()->numerify('9###'),
            'sap_vendor_id' => fake()->optional()->numerify('100#####'),
            'name' => 'PT '.fake()->unique()->company(),
            'vendor_type' => fake()->randomElement(VendorType::cases()),
            'vendor_category' => fake()->randomElement(VendorCategory::cases()),
            'is_strategic' => true,
            'evaluation_frequency' => EvaluationFrequency::Semester,
            'contract_end_date' => fake()->dateTimeBetween('+3 months', '+2 years'),
            'contact_person' => fake()->name(),
            'contact_email' => fake()->safeEmail(),
            'status' => VendorStatus::Active,
        ];
    }

    public function trade(): static
    {
        return $this->state(fn () => ['vendor_type' => VendorType::Trade]);
    }

    public function nonTrade(): static
    {
        return $this->state(fn () => ['vendor_type' => VendorType::NonTrade]);
    }

    public function annual(): static
    {
        return $this->state(fn () => ['evaluation_frequency' => EvaluationFrequency::Annual]);
    }

    public function nonStrategic(): static
    {
        return $this->state(fn () => ['is_strategic' => false]);
    }
}
