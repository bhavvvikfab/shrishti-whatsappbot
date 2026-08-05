<?php

/**
 * Shrishti Trip business profile — defaults from https://shrishtitrip.com/contact
 * Override per deployment via Settings (Manage Profile) or .env where noted.
 */
return [
    'name' => env('BUSINESS_NAME', 'Shrishti Trip'),
    'tagline' => 'SAVE MONEY. SAFE JOURNEY.',

    'phone' => env('BUSINESS_PHONE', '+91 7042426335'),
    'phone_hours' => env('BUSINESS_PHONE_HOURS', 'Mon–Sat, 9 AM – 7 PM IST'),

    'whatsapp' => env('BUSINESS_WHATSAPP', '+91 8920909501'),
    'email' => env('BUSINESS_EMAIL', 'info@shrishtitrip.com'),
    'website' => env('BUSINESS_WEBSITE', 'https://shrishtitrip.com'),

    'office_address' => env(
        'BUSINESS_OFFICE_ADDRESS',
        'JJ Camp-02, Shiv Mandir Bhai Veer Singh Marg, New Delhi-110001'
    ),
    'office_label' => env('BUSINESS_OFFICE_LABEL', 'JJ Camp-02 | F-39'),

  // Connaught Place / JJ Camp area, New Delhi
    'latitude' => (float) env('BUSINESS_LATITUDE', 28.6310),
    'longitude' => (float) env('BUSINESS_LONGITUDE', 77.2186),
];
