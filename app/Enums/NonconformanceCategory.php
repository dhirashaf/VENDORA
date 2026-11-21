<?php

namespace App\Enums;

enum NonconformanceCategory: string
{
    case Quality = 'Quality';
    case Delivery = 'Delivery';
    case Cost = 'Cost';
    case Service = 'Service';
    case Compliance = 'Compliance';
    case Other = 'Other';

    public function label(): string
    {
        return match ($this) {
            self::Quality => 'Quality Issue',
            self::Delivery => 'Delivery Issue',
            self::Cost => 'Cost/Pricing Issue',
            self::Service => 'Service Issue',
            self::Compliance => 'Compliance Issue',
            self::Other => 'Lainnya',
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
