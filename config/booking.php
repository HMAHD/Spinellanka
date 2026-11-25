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
    'airport_transfer' => [
        'default_trip_duration_minutes' => 180, // Fallback duration for unestimated routes
        'minimum_notice_hours' => 6, // Minimum hours before flight departure to submit
        'default_airport' => 'CMB',
        'airport_arrival_buffer_minutes' => 180, // 3 hours prior to international flight departure
        'route_contingency_minutes' => 45, // Contingency buffer added to driving time
        'latest_cutoff_buffer_minutes' => 90, // Hard minimum airport arrival buffer (departure - (drive + 90m))
        'unsafe_warning_message' => 'Your selected pickup time is later than our recommended time. Arriving less than 3 hours before an international flight significantly increases the risk of missing check-in or baggage cutoffs due to traffic or airport queues.',
        'unsafe_acknowledgment_text' => 'I acknowledge that my chosen pickup time provides less than the recommended buffer and I accept personal responsibility for my flight departure timing.',
        'liability_disclaimer' => 'Spinellanka provides chauffeured airport transfers based on estimated travel times and client-selected pickup times. Spinellanka does not guarantee airline check-in or flight departure and accepts no financial or legal liability for missed flights resulting from customer-chosen departure times, airline schedule changes, airport security/immigration queues, or unforeseen road conditions.',
        'expressway_tolls_included' => true,
        'airport_fees_included' => true,
    ],
    'rent' => [
        'min_driver_age' => 23,
        'max_driver_age' => 70,
        'min_licence_years' => 2,
        'idp_required' => true,
        'max_additional_drivers' => 1,
        'security_deposit_minor_units' => 50000, // $500.00 USD
        'insurance_excess_minor_units' => 50000, // $500.00 USD
        'daily_mileage_km' => 100, // 100 km/day cumulative
        'excess_mileage_rate_minor_units' => 30, // $0.30 USD/km
        'grace_period_minutes' => 59, // Up to 59m past 24h cycle free
        'extra_hours_threshold' => 5, // 1-5h billed at extra hourly rate
        'full_day_threshold_hours' => 6, // 6h+ rounds to full extra day
        'minimum_notice_hours' => 12, // Minimum advance notice to submit
        'default_pickup_location_type' => 'office',
        'hotel_delivery_fee_minor_units' => 2000, // $20.00 USD
        'prohibited_roads_text' => 'Paved public roads only; off-road driving strictly prohibited.',
        'smoking_penalty_minor_units' => 15000, // $150.00 USD
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
