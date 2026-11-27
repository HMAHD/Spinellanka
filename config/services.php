<?php

return [
    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
        'server_token' => env('POSTMARK_SERVER_TOKEN'),
    ],
    'meta_whatsapp' => [
        'phone_number_id' => env('META_WHATSAPP_PHONE_NUMBER_ID'),
        'system_user_token' => env('META_WHATSAPP_SYSTEM_USER_TOKEN'),
        'api_version' => env('META_WHATSAPP_API_VERSION', 'v21.0'),
    ],
    'maps' => [
        'provider' => env('MAPS_PROVIDER'),
        'key' => env('MAPS_API_KEY'),
        'allowed_origin' => env('MAPS_ALLOWED_ORIGIN'),
        'timeout_seconds' => (int) env('MAPS_TIMEOUT_SECONDS', 5),
    ],
    'whatsapp' => [
        'provider' => env('WHATSAPP_PROVIDER', 'fake'),
        'token' => env('WHATSAPP_API_TOKEN'),
    ],
    'transactional_mailer' => [
        'provider' => env('TRANSACTIONAL_MAILER_PROVIDER', 'fake'),
    ],
    'payment' => [
        'gateway' => env('PAYMENT_GATEWAY', 'fake'),
    ],
    'payhere' => [
        'merchant_id' => env('PAYHERE_MERCHANT_ID'),
        'merchant_secret' => env('PAYHERE_MERCHANT_SECRET'),
        'sandbox' => env('PAYHERE_SANDBOX', true),
    ],
    'sentry' => [
        'dsn' => env('SENTRY_LARAVEL_DSN'),
    ],
    'backup' => [
        'off_host_disk' => env('BACKUP_OFF_HOST_DISK'),
    ],
    'analytics' => [
        'provider' => env('ANALYTICS_PROVIDER', 'none'),
        'plausible_domain' => env('PLAUSIBLE_DOMAIN'),
        'plausible_script_url' => env('PLAUSIBLE_SCRIPT_URL', 'https://plausible.io/js/script.js'),
    ],
];
