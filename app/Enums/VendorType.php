<?php

namespace App\Enums;

enum VendorType: string
{
    case Trade = 'Trade';
    case NonTrade = 'Non_Trade';

    public function label(): string
    {
        return match ($this) {
            self::Trade => 'Trade',
            self::NonTrade => 'Non-Trade',
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
