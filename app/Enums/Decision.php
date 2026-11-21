<?php

namespace App\Enums;

enum Decision: string
{
    case Recommended = 'Recommended';
    case NotRecommendedSuspend = 'Not_Recommended_Suspend';
    case NotRecommendedBlockId = 'Not_Recommended_Block_ID';
    case ReDevelopment = 'Re_development';

    public function label(): string
    {
        return match ($this) {
            self::Recommended => 'Recommended',
            self::NotRecommendedSuspend => 'Not Recommended - Suspend Vendor',
            self::NotRecommendedBlockId => 'Not Recommended - Block ID Vendor',
            self::ReDevelopment => 'Re-development',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Recommended => 'success',
            self::NotRecommendedSuspend => 'warning',
            self::NotRecommendedBlockId => 'danger',
            self::ReDevelopment => 'info',
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
