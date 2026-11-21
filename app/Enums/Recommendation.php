<?php

namespace App\Enums;

enum Recommendation: string
{
    case Recommended = 'Recommended';
    case RecommendedWithNotes = 'Recommended_With_Notes';
    case NotRecommended = 'Not_Recommended';
    case CannotBeCalculated = 'Cannot_Be_Calculated';

    public function label(): string
    {
        return match ($this) {
            self::Recommended => 'Recommended',
            self::RecommendedWithNotes => 'Recommended dengan Catatan',
            self::NotRecommended => 'Not Recommended',
            self::CannotBeCalculated => 'Belum Dapat Dihitung',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Recommended => 'success',
            self::RecommendedWithNotes => 'primary',
            self::NotRecommended => 'danger',
            self::CannotBeCalculated => 'secondary',
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
