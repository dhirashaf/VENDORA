<?php

namespace App\Enums;

enum ReminderTrigger: string
{
    case BeforeDue = 'Before_Due';
    case AfterDue = 'After_Due';
    case Escalation = 'Escalation';

    public function label(): string
    {
        return match ($this) {
            self::BeforeDue => 'Sebelum Tenggat',
            self::AfterDue => 'Setelah Tenggat',
            self::Escalation => 'Eskalasi',
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
