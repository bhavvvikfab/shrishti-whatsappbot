<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect_uri' => env('GOOGLE_REDIRECT_URI'),
        'auto_sync' => env('GOOGLE_CALENDAR_AUTO_SYNC', true),
        'scopes' => [
            'https://www.googleapis.com/auth/calendar',
            'https://www.googleapis.com/auth/calendar.events',
        ],
    ],

    'whatsapp' => [
        'verify_token' => env('WHATSAPP_VERIFY_TOKEN', 'fablead_whatsapp_verify'),
        // Timestamps stay stored in UTC and are converted only for chat display.
        'display_timezone' => env('WHATSAPP_DISPLAY_TIMEZONE', 'Asia/Kolkata'),
        // Inbound still saves when false; stops AI auto-reply (avoids 131030 noise while testing).
        'ai_auto_reply' => env('WHATSAPP_AI_AUTO_REPLY', true),
        // Wait this many minutes after inbound before AI runs, unless an agent sends from CRM first.
        'ai_reply_delay_minutes' => max(1, (int) env('WHATSAPP_AI_REPLY_DELAY_MINUTES', 1)),
        // Seconds to wait after inbound before AI replies (used when not on queue worker).
        'ai_reply_delay_seconds' => max(15, (int) env('WHATSAPP_AI_REPLY_DELAY_SECONDS', 60)),
        // When true, delayed AI uses the queue (requires `php artisan queue:work`). Otherwise runs after webhook response.
        'ai_use_queue' => (bool) env('WHATSAPP_AI_USE_QUEUE', false),
        // Inbox live refresh intervals (milliseconds) for browser polling.
        'inbox_poll_ms' => max(2000, (int) env('WHATSAPP_INBOX_POLL_MS', 5000)),
        'chat_poll_ms' => max(2000, (int) env('WHATSAPP_CHAT_POLL_MS', 3000)),
        'sidebar_poll_ms' => max(2000, (int) env('WHATSAPP_SIDEBAR_POLL_MS', 4000)),
        'ai_business_name' => env('WHATSAPP_AI_BUSINESS_NAME', 'Shrishti Trip'),
        // Used by WhatsApp Auto AI replies (OpenAIService). Override in .env if needed.
        'ai_company_profile' => env(
            'WHATSAPP_AI_COMPANY_PROFILE',
            'Shrishti Trip is a tour and travel company (SAVE MONEY. SAFE JOURNEY.). We plan domestic and international trips, tour packages, hotels, transport, and custom itineraries. Office: JJ Camp-02, Shiv Mandir Bhai Veer Singh Marg, New Delhi-110001. Call +91 7042426335 (Mon–Sat 9 AM–7 PM IST), WhatsApp +91 8920909501, email info@shrishtitrip.com, website https://shrishtitrip.com.'
        ),
    ],

    'meta_leads' => [
        'verify_token' => env('META_LEADS_VERIFY_TOKEN', 'fablead_meta_leads_verify'),
        'access_token' => env('META_LEADS_ACCESS_TOKEN'),
        'graph_version' => env('META_GRAPH_API_VERSION', 'v23.0'),
    ],

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
        'model' => env('OPENAI_MODEL', 'gpt-3.5-turbo'),
    ],

];
