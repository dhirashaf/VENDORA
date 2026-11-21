<?php

namespace App\Models;

use App\Enums\Department;
use App\Enums\RoleCode;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'role_id',
        'full_name',
        'work_email',
        'password_hash',
        'department',
        'job_title',
        'is_active',
    ];

    protected $hidden = [
        'password_hash',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'is_active' => 'boolean',
            'department' => Department::class,
        ];
    }

    public function getAuthPassword(): string
    {
        return (string) $this->password_hash;
    }

    /**
     * Notifikasi email dikirim ke email kerja pengguna.
     */
    public function routeNotificationForMail(): string
    {
        return $this->work_email;
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function evaluationAssignments(): HasMany
    {
        return $this->hasMany(EvaluationAssignment::class, 'assessor_id');
    }

    public function reminderLogs(): HasMany
    {
        return $this->hasMany(ReminderLog::class, 'recipient_id');
    }

    public function hasRole(RoleCode ...$roles): bool
    {
        return in_array($this->role?->code, $roles, true);
    }

    public function isVmSpecialist(): bool
    {
        return $this->hasRole(RoleCode::VmSpecialist);
    }

    public function isAssessor(): bool
    {
        return $this->hasRole(RoleCode::Assessor);
    }

    public function isPvmManager(): bool
    {
        return $this->hasRole(RoleCode::PvmManager);
    }

    /**
     * @param  Builder<User>  $query
     */
    public function scopeWithRole(Builder $query, RoleCode $role): void
    {
        $query->whereHas('role', fn (Builder $roleQuery) => $roleQuery->where('code', $role->value));
    }

    /**
     * @param  Builder<User>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
