<?php

namespace App\Enums;

enum Channel: string
{
    case Email = 'Email';
    case InApp = 'In_App';

    public function label(): string
    {
        return match ($this) {
            self::Email => 'Email',
            self::InApp => 'Notifikasi Aplikasi',
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
