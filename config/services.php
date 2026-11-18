<?php

return [
    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],
    'maps' => [
        'provider' => env('MAPS_PROVIDER'),
        'key' => env('MAPS_API_KEY'),
    ],
    'whatsapp' => [
        'provider' => env('WHATSAPP_PROVIDER'),
        'token' => env('WHATSAPP_API_TOKEN'),
    ],
    'payments' => [
        'provider' => env('PAYMENT_PROVIDER'),
        'key' => env('PAYMENT_API_KEY'),
        'webhook_secret' => env('PAYMENT_WEBHOOK_SECRET'),
    ],
];
