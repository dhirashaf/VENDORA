<?php

namespace App\Enums;

enum VendorStatus: string
{
    case Active = 'Active';
    case Suspended = 'Suspended';
    case NotRecommended = 'Not_Recommended';
    case Blocked = 'Blocked';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Aktif',
            self::Suspended => 'Suspend Sementara',
            self::NotRecommended => 'Tidak Direkomendasikan',
            self::Blocked => 'Block ID',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Suspended => 'warning',
            self::NotRecommended => 'danger',
            self::Blocked => 'dark',
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
