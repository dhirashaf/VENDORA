<?php

namespace Database\Factories;

use App\Enums\Department;
use App\Enums\RoleCode;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'role_id' => fn () => $this->roleId(RoleCode::User),
            'full_name' => fake()->name(),
            'work_email' => fake()->unique()->safeEmail(),
            'password_hash' => static::$password ??= Hash::make('password'),
            'department' => Department::Other,
            'job_title' => null,
            'is_active' => true,
            'email_verified_at' => now(),
            'remember_token' => Str::random(10),
        ];
    }

    public function vmSpecialist(): static
    {
        return $this->state(fn () => [
            'role_id' => $this->roleId(RoleCode::VmSpecialist),
            'department' => Department::VendorManagement,
            'job_title' => 'Vendor Management Specialist',
        ]);
    }

    public function pvmManager(): static
    {
        return $this->state(fn () => [
            'role_id' => $this->roleId(RoleCode::PvmManager),
            'department' => Department::VendorManagement,
            'job_title' => 'Procurement & Vendor Management Manager',
        ]);
    }

    public function assessor(Department $department): static
    {
        return $this->state(fn () => [
            'role_id' => $this->roleId(RoleCode::Assessor),
            'department' => $department,
            'job_title' => 'Penilai '.$department->label(),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    private function roleId(RoleCode $code): int
    {
        return Role::query()->firstOrCreate(['code' => $code->value], ['name' => $code->label()])->id;
    }
}
