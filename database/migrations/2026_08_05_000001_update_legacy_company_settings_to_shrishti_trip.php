<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    private const LEGACY_NAME_MARKERS = ['fablead', 'lakyashvi'];

    private const LEGACY_ADDRESS_MARKERS = [
        'ascon plaza',
        'anand mahal',
        'adajan',
        'fablead developers',
    ];

    public function up(): void
    {
        $this->replaceLegacySetting('company_name', 'name', self::LEGACY_NAME_MARKERS);
        $this->replaceLegacySetting('company_address', 'office_address', self::LEGACY_ADDRESS_MARKERS);

        $lat = Setting::query()->where('key', 'company_latitude')->value('value');
        $lng = Setting::query()->where('key', 'company_longitude')->value('value');

        if ($lat !== null && $lng !== null) {
            $latFloat = (float) $lat;
            $lngFloat = (float) $lng;

            if (abs($latFloat - 21.207227) < 0.02 && abs($lngFloat - 72.782756) < 0.02) {
                $this->upsertSetting('company_latitude', (string) config('shrishti_trip.latitude', 28.6310));
                $this->upsertSetting('company_longitude', (string) config('shrishti_trip.longitude', 77.2186));
            }
        }
    }

    public function down(): void
    {
        // Legacy values are not restored — Shrishti Trip profile should remain.
    }

    private function replaceLegacySetting(string $key, string $configKey, array $legacyMarkers): void
    {
        $current = Setting::query()->where('key', $key)->value('value');

        if ($current === null || $this->isLegacyText($current, $legacyMarkers)) {
            $this->upsertSetting($key, (string) config("shrishti_trip.{$configKey}", ''));
        }
    }

    private function upsertSetting(string $key, string $value): void
    {
        Setting::updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'group' => 'general', 'type' => 'string']
        );
    }

    private function isLegacyText(string $value, array $markers): bool
    {
        $lower = strtolower($value);

        foreach ($markers as $marker) {
            if (str_contains($lower, $marker)) {
                return true;
            }
        }

        return false;
    }
};
