<?php

return [

    /*
    | Default business timezone for schedules and reports.
    */
    'timezone' => env('VICE_CEO_TIMEZONE', 'Asia/Kuala_Lumpur'),

    /*
    | Master switch for recurring automations (briefings, reminders).
    | Off by default so nothing is sent from a development machine by accident.
    | Each business must ALSO enable each automation in the dashboard.
    */
    'automations_enabled' => (bool) env('VICE_CEO_AUTOMATIONS_ENABLED', false),

    /*
    | A CEO decision request expires after this many hours.
    */
    'approval_ttl_hours' => (int) env('VICE_CEO_APPROVAL_TTL_HOURS', 24),

    /*
    | Allow self-service registration (creates a business + CEO account).
    */
    'registration_enabled' => (bool) env('VICE_CEO_REGISTRATION_ENABLED', true),

    'ai' => [
        // "anthropic" uses Claude; anything else (or no key) falls back to deterministic rules.
        'provider' => env('VICE_CEO_AI_PROVIDER', 'anthropic'),
        'api_key' => env('ANTHROPIC_API_KEY'),
        'model' => env('VICE_CEO_AI_MODEL', 'claude-opus-5-5'),
        'effort' => env('VICE_CEO_AI_EFFORT', 'low'),
        'timeout' => (int) env('VICE_CEO_AI_TIMEOUT', 60),
    ],

    'whatsapp' => [
        // "log" = mock transport (development). "meta" = official WhatsApp Business Cloud API.
        'driver' => env('WHATSAPP_DRIVER', 'log'),
        'graph_version' => env('WHATSAPP_GRAPH_VERSION', 'v23.0'),
        'access_token' => env('WHATSAPP_ACCESS_TOKEN'),
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'app_secret' => env('WHATSAPP_APP_SECRET'),
        'verify_token' => env('WHATSAPP_VERIFY_TOKEN'),
        'default_country_code' => env('WHATSAPP_DEFAULT_COUNTRY_CODE', '60'),

        // Approved Meta templates, used when the 24-hour customer service window is closed.
        // Each template must take ONE body parameter ({{1}}) holding the message text.
        'templates' => [
            'language' => env('WHATSAPP_TEMPLATE_LANGUAGE', 'ms'),
            'ceo_update' => env('WHATSAPP_TEMPLATE_CEO_UPDATE'),
            'customer_followup' => env('WHATSAPP_TEMPLATE_CUSTOMER_FOLLOWUP'),
        ],
    ],

    'email' => [
        'from_name' => env('VICE_CEO_EMAIL_FROM_NAME', 'Dyno Focus · AI Vice CEO'),
    ],
];
