<?php

namespace App\Support;

use App\Models\Setting;

class BusinessProfile
{
    public static function name(): string
    {
        return (string) (Setting::getValue('company_name') ?: config('shrishti_trip.name', 'Shrishti Trip'));
    }

    public static function addressLine(): string
    {
        return (string) (Setting::getValue('company_address') ?: config('shrishti_trip.office_address', ''));
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
        return (string) (Setting::getValue('company_phone') ?: config('shrishti_trip.phone', ''));
    }

    public static function phoneHours(): string
    {
        return (string) config('shrishti_trip.phone_hours', '');
    }

    public static function whatsapp(): string
    {
        return (string) (Setting::getValue('company_whatsapp') ?: config('shrishti_trip.whatsapp', ''));
    }

    public static function email(): string
    {
        return (string) (Setting::getValue('company_email') ?: config('shrishti_trip.email', ''));
    }

    public static function website(): string
    {
        return (string) config('shrishti_trip.website', 'https://shrishtitrip.com');
    }

    public static function latitude(): float
    {
        $fromSetting = Setting::getValue('company_latitude');

        return (float) ($fromSetting !== null && $fromSetting !== ''
            ? $fromSetting
            : config('shrishti_trip.latitude', 28.6310));
    }

    public static function longitude(): float
    {
        $fromSetting = Setting::getValue('company_longitude');

        return (float) ($fromSetting !== null && $fromSetting !== ''
            ? $fromSetting
            : config('shrishti_trip.longitude', 77.2186));
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
