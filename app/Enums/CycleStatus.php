<?php

namespace App\Enums;

enum CycleStatus: string
{
    case Scheduled = 'Scheduled';
    case Open = 'Open';
    case InProgress = 'In_Progress';
    case Compiled = 'Compiled';
    case PendingApproval = 'Pending_Approval';
    case Approved = 'Approved';
    case Closed = 'Closed';
    case Cancelled = 'Cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => 'Terjadwal',
            self::Open => 'Form Terkirim',
            self::InProgress => 'Sedang Diisi',
            self::Compiled => 'Terkompilasi',
            self::PendingApproval => 'Menunggu Approval',
            self::Approved => 'Approved',
            self::Closed => 'Ditutup',
            self::Cancelled => 'Dibatalkan',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Scheduled => 'secondary',
            self::Open => 'info',
            self::InProgress => 'warning',
            self::Compiled => 'primary',
            self::PendingApproval => 'info',
            self::Approved => 'success',
            self::Closed => 'dark',
            self::Cancelled => 'secondary',
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
