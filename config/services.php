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
    ],
    'whatsapp' => [
        'provider' => env('WHATSAPP_PROVIDER', 'fake'),
        'token' => env('WHATSAPP_API_TOKEN'),
    ],
    'transactional_mailer' => [
        'provider' => env('TRANSACTIONAL_MAILER_PROVIDER', 'fake'),
    ],
    'payments' => [
        'provider' => env('PAYMENT_PROVIDER'),
        'key' => env('PAYMENT_API_KEY'),
        'webhook_secret' => env('PAYMENT_WEBHOOK_SECRET'),
    ],
];
