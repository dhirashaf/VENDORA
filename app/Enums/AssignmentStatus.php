<?php

namespace App\Enums;

enum AssignmentStatus: string
{
    case NotStarted = 'Not_Started';
    case Draft = 'Draft';
    case Submitted = 'Submitted';
    case Overdue = 'Overdue';

    public function label(): string
    {
        return match ($this) {
            self::NotStarted => 'Belum Mulai',
            self::Draft => 'Draf',
            self::Submitted => 'Tersubmit',
            self::Overdue => 'Lewat Tenggat',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::NotStarted => 'secondary',
            self::Draft => 'warning',
            self::Submitted => 'success',
            self::Overdue => 'danger',
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
