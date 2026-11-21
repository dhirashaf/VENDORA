<?php

namespace App\Http\Controllers;

use App\Models\SystemSetting;
use App\Support\AuditLogger;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Parameter sistem configurable, termasuk dependency proses internal (FR-09) dan batas rekomendasi.
 */
class SettingController extends Controller
{
    public function edit(): View
    {
        return view('settings.edit', [
            'definitions' => Settings::definitions(),
            'values' => collect(Settings::definitions())->mapWithKeys(fn (array $definition, string $key): array => [$key => Settings::get($key)]),
            'records' => SystemSetting::query()->with('updater')->get()->keyBy('setting_key'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            Settings::DEFAULT_SLA_DAYS => ['required', 'integer', 'min:1', 'max:90'],
            Settings::FOLLOWUP_DUE_DAYS => ['required', 'integer', 'min:1', 'max:365'],
            Settings::RECOMMENDED_MIN_SCORE => ['required', 'numeric', 'min:1', 'max:4'],
            Settings::RECOMMENDED_WITH_NOTES_MIN_SCORE => ['required', 'numeric', 'min:1', 'lte:'.Settings::RECOMMENDED_MIN_SCORE],
        ], [
            Settings::RECOMMENDED_WITH_NOTES_MIN_SCORE.'.lte' => 'Batas "Recommended dengan Catatan" tidak boleh melebihi batas "Recommended".',
        ]);

        $data[Settings::ENFORCE_DEPENDENCY] = $request->boolean(Settings::ENFORCE_DEPENDENCY) ? '1' : '0';
        $data[Settings::REMINDERS_ENABLED] = $request->boolean(Settings::REMINDERS_ENABLED) ? '1' : '0';

        foreach ($data as $key => $value) {
            $value = Settings::definitions()[$key]['type'] === 'decimal' ? number_format((float) $value, 2, '.', '') : (string) $value;
            $previous = Settings::get($key);

            if ($previous !== $value) {
                Settings::set($key, $value, $request->user());
                AuditLogger::record(AuditLogger::UPDATE, 'system_settings', [$key => $previous], [$key => $value]);
            }
        }

        return back()->with('success', 'Pengaturan sistem tersimpan.');
    }
}
