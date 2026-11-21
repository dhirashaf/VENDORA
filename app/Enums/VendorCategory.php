<?php

namespace App\Enums;

enum VendorCategory: string
{
    case Production = 'Production';
    case Service = 'Service';
    case General = 'General';

    public function label(): string
    {
        return match ($this) {
            self::Production => 'Production',
            self::Service => 'Service',
            self::General => 'General',
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
