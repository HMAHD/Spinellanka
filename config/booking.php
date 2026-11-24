<?php

declare(strict_types=1);

return [
    'pending_request_ttl_hours' => 24,
    'airport_pickup' => [
        'default_trip_duration_minutes' => 180, // 3 hours fallback duration for unestimated routes
        'minimum_notice_hours' => 6, // Minimum hours before flight arrival to submit
        'default_airport' => 'CMB',
        'included_waiting_minutes' => 90, // Customs + baggage buffer
    ],
    'policies' => [
        'terms' => [
            'type' => 'terms_of_service',
            'version' => '2026-11-23',
            'view' => 'legal.terms',
        ],
        'privacy' => [
            'type' => 'privacy_policy',
            'version' => '2026-11-23',
            'view' => 'legal.privacy',
        ],
    ],
];

