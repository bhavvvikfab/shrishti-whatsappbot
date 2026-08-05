<?php

namespace App\Support;

use App\Models\Setting;

class BusinessProfile
{
    private const LEGACY_NAME_MARKERS = ['fablead', 'lakyashvi'];

    private const LEGACY_ADDRESS_MARKERS = [
        'ascon plaza',
        'anand mahal',
        'adajan',
        'fablead developers',
    ];

    private static function isLegacyText(?string $value, array $markers): bool
    {
        if ($value === null || trim($value) === '') {
            return false;
        }

        $lower = strtolower($value);

        foreach ($markers as $marker) {
            if (str_contains($lower, $marker)) {
                return true;
            }
        }

        return false;
    }

    private static function isLegacySuratCoordinates(float $lat, float $lng): bool
    {
        return abs($lat - 21.207227) < 0.02 && abs($lng - 72.782756) < 0.02;
    }

    private static function companySetting(string $key, string $configKey, array $legacyMarkers = []): string
    {
        $fromDb = Setting::getValue($key);

        if ($fromDb !== null && $fromDb !== '' && ! static::isLegacyText($fromDb, $legacyMarkers)) {
            return (string) $fromDb;
        }

        return (string) config("shrishti_trip.{$configKey}", '');
    }

    public static function name(): string
    {
        $value = static::companySetting('company_name', 'name', self::LEGACY_NAME_MARKERS);

        return $value !== '' ? $value : 'Shrishti Trip';
    }

    public static function addressLine(): string
    {
        return static::companySetting('company_address', 'office_address', self::LEGACY_ADDRESS_MARKERS);
    }

    public static function fullAddress(): string
    {
        $line = trim(static::addressLine());
        $label = trim((string) config('shrishti_trip.office_label', ''));

        if ($line === '') {
            return $label;
        }

        if ($label === '' || str_contains($line, $label)) {
            return $line;
        }

        return $line.' | '.$label;
    }

    public static function phone(): string
    {
        $fromDb = Setting::getValue('company_phone');

        if ($fromDb !== null && $fromDb !== '' && ! static::isLegacyText($fromDb, self::LEGACY_NAME_MARKERS)) {
            return (string) $fromDb;
        }

        return (string) config('shrishti_trip.phone', '');
    }

    public static function phoneHours(): string
    {
        return (string) config('shrishti_trip.phone_hours', '');
    }

    public static function whatsapp(): string
    {
        $fromDb = Setting::getValue('company_whatsapp');

        if ($fromDb !== null && $fromDb !== '' && ! static::isLegacyText($fromDb, self::LEGACY_NAME_MARKERS)) {
            return (string) $fromDb;
        }

        return (string) config('shrishti_trip.whatsapp', '');
    }

    public static function email(): string
    {
        $fromDb = Setting::getValue('company_email');

        if ($fromDb !== null && $fromDb !== '' && ! static::isLegacyText($fromDb, self::LEGACY_NAME_MARKERS)) {
            return (string) $fromDb;
        }

        return (string) config('shrishti_trip.email', '');
    }

    public static function website(): string
    {
        return (string) config('shrishti_trip.website', 'https://shrishtitrip.com');
    }

    public static function latitude(): float
    {
        $fromSetting = Setting::getValue('company_latitude');

        if ($fromSetting !== null && $fromSetting !== '') {
            $lat = (float) $fromSetting;
            $lngSetting = Setting::getValue('company_longitude');
            $lng = $lngSetting !== null && $lngSetting !== '' ? (float) $lngSetting : 0.0;

            if (! static::isLegacySuratCoordinates($lat, $lng)) {
                return $lat;
            }
        }

        return (float) config('shrishti_trip.latitude', 28.6310);
    }

    public static function longitude(): float
    {
        $fromSetting = Setting::getValue('company_longitude');

        if ($fromSetting !== null && $fromSetting !== '') {
            $lng = (float) $fromSetting;
            $latSetting = Setting::getValue('company_latitude');
            $lat = $latSetting !== null && $latSetting !== '' ? (float) $latSetting : 0.0;

            if (! static::isLegacySuratCoordinates($lat, $lng)) {
                return $lng;
            }
        }

        return (float) config('shrishti_trip.longitude', 77.2186);
    }

    /**
     * WhatsApp location payload for "Office Location" in chat.
     *
     * @return array{name: string, address: string, latitude: float, longitude: float}
     */
    public static function officeLocation(): array
    {
        return [
            'name' => static::name(),
            'address' => static::fullAddress(),
            'latitude' => static::latitude(),
            'longitude' => static::longitude(),
        ];
    }

    /**
     * Contact block for AI system prompts.
     */
    public static function aiContactBlock(): string
    {
        $parts = array_filter([
            static::phone() ? 'Call '.static::phone().(static::phoneHours() ? ' ('.static::phoneHours().')' : '') : null,
            static::whatsapp() ? 'WhatsApp '.static::whatsapp() : null,
            static::email() ? 'Email '.static::email() : null,
            static::fullAddress() ? 'Office '.static::fullAddress() : null,
            static::website() ? 'Website '.static::website() : null,
        ]);

        return implode('; ', $parts);
    }
}
