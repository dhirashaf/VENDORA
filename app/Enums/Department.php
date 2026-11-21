<?php

namespace App\Enums;

enum Department: string
{
    case VendorManagement = 'Vendor_Management';
    case Finance = 'Finance';
    case Engineering = 'Engineering';
    case Procurement = 'Procurement';
    case Other = 'Other';

    public function label(): string
    {
        return match ($this) {
            self::VendorManagement => 'Vendor Management',
            self::Finance => 'Finance',
            self::Engineering => 'Engineering',
            self::Procurement => 'Procurement',
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
