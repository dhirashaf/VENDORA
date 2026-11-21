<?php

namespace App\Enums;

enum RoleCode: string
{
    case VmSpecialist = 'VM_SPECIALIST';
    case Assessor = 'ASSESSOR';
    case PvmManager = 'PVM_MANAGER';
    case User = 'USER';

    public function label(): string
    {
        return match ($this) {
            self::VmSpecialist => 'Vendor Management Specialist',
            self::Assessor => 'Penilai',
            self::PvmManager => 'PVM Manager',
            self::User => 'User',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case): array => [$case->value => $case->label()])->all();
    }
}
