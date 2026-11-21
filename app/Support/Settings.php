<?php

namespace App\Support;

use App\Models\SystemSetting;
use App\Models\User;

/**
 * Parameter sistem yang dapat dikonfigurasi tanpa ubah kode (tabel system_settings).
 */
class Settings
{
    public const DEFAULT_SLA_DAYS = 'default_sla_days';

    public const ENFORCE_DEPENDENCY = 'enforce_dependency';

    public const RECOMMENDED_MIN_SCORE = 'recommended_min_score';

    public const RECOMMENDED_WITH_NOTES_MIN_SCORE = 'recommended_with_notes_min_score';

    public const FOLLOWUP_DUE_DAYS = 'followup_due_days';

    public const REMINDERS_ENABLED = 'reminders_enabled';

    /**
     * @return array<string, array{value: string, description: string, type: string}>
     */
    public static function definitions(): array
    {
        return [
            self::DEFAULT_SLA_DAYS => ['value' => '14', 'type' => 'integer', 'description' => 'Tenggat pengisian default (hari kalender) - FR-05'],
            self::ENFORCE_DEPENDENCY => ['value' => '1', 'type' => 'boolean', 'description' => 'Tahan status "evaluasi selesai" selama masih ada penilai yang belum submit - FR-09'],
            self::RECOMMENDED_MIN_SCORE => ['value' => '3.00', 'type' => 'decimal', 'description' => 'Skor minimum (dari 4,00) untuk rekomendasi "Recommended"'],
            self::RECOMMENDED_WITH_NOTES_MIN_SCORE => ['value' => '2.50', 'type' => 'decimal', 'description' => 'Skor minimum "Recommended dengan Catatan"; di bawahnya Not Recommended dan tindak lanjut dibuka otomatis'],
            self::FOLLOWUP_DUE_DAYS => ['value' => '45', 'type' => 'integer', 'description' => 'Tenggat bawaan kasus tindak lanjut (hari)'],
            self::REMINDERS_ENABLED => ['value' => '1', 'type' => 'boolean', 'description' => 'Aktifkan pengiriman reminder terjadwal - FR-07'],
        ];
    }

    private const CONTAINER_KEY = 'system-settings.values';

    public static function get(string $key): string
    {
        if (! app()->bound(self::CONTAINER_KEY)) {
            app()->instance(self::CONTAINER_KEY, SystemSetting::query()->pluck('setting_value', 'setting_key')->all());
        }

        return app(self::CONTAINER_KEY)[$key] ?? self::definitions()[$key]['value'];
    }

    public static function integer(string $key): int
    {
        return (int) self::get($key);
    }

    public static function decimal(string $key): float
    {
        return (float) self::get($key);
    }

    public static function boolean(string $key): bool
    {
        return self::get($key) === '1';
    }

    public static function set(string $key, string $value, ?User $user = null): void
    {
        SystemSetting::query()->updateOrCreate(
            ['setting_key' => $key],
            [
                'setting_value' => $value,
                'description' => self::definitions()[$key]['description'] ?? null,
                'updated_by' => $user?->id,
            ],
        );

        self::flush();
    }

    public static function flush(): void
    {
        app()->forgetInstance(self::CONTAINER_KEY);
    }
}
