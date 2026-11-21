<?php

namespace App\Enums;

enum EvaluationFrequency: string
{
    case Annual = 'Annual';
    case Semester = 'Semester';

    public function label(): string
    {
        return match ($this) {
            self::Annual => 'Tahunan',
            self::Semester => 'Semesteran',
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
