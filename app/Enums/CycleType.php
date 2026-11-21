<?php

namespace App\Enums;

enum CycleType: string
{
    case Annual = 'Annual';
    case Semester = 'Semester';
    case AdHoc = 'Ad_hoc';

    public function label(): string
    {
        return match ($this) {
            self::Annual => 'Tahunan',
            self::Semester => 'Semesteran',
            self::AdHoc => 'Ad-hoc',
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
