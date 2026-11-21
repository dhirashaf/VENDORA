<?php

namespace App\Enums;

enum SendStatus: string
{
    case Scheduled = 'Scheduled';
    case Sent = 'Sent';
    case Failed = 'Failed';
    case Cancelled = 'Cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => 'Terjadwal',
            self::Sent => 'Terkirim',
            self::Failed => 'Gagal',
            self::Cancelled => 'Dibatalkan',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Scheduled => 'secondary',
            self::Sent => 'success',
            self::Failed => 'danger',
            self::Cancelled => 'dark',
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
