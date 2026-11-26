<?php

declare(strict_types=1);

namespace App\Domain\Operations\DTOs;

use App\Domain\Booking\ServiceType;
use App\Domain\Operations\Enums\DriverTripStatus;
use Carbon\Carbon;

readonly class DriverTripViewDTO
{
    /**
     * @param  array<int, array{location: string, notes: ?string}>  $intermediate_stops
     * @param  array<int, DriverTripStatus>  $allowed_next_milestones
     */
    public function __construct(
        public string $reference,
        public ServiceType $service_type,
        public string $service_type_label,
        public string $service_type_badge,
        public Carbon $scheduled_pickup_time,
        public string $scheduled_pickup_time_formatted,
        public string $pickup_location_text,
        public ?string $pickup_navigation_url,
        public ?string $destination_text,
        public ?string $destination_navigation_url,
        public array $intermediate_stops,
        public ?string $flight_number,
        public ?Carbon $flight_arrival_time,
        public ?string $flight_arrival_time_formatted,
        public ?string $name_board_text,
        public ?string $waiting_instructions,
        public int $passenger_count,
        public int $luggage_count,
        public string $lead_traveler_first_name,
        public ?string $lead_traveler_phone_clean,
        public ?string $lead_traveler_whatsapp_url,
        public DriverTripStatus $current_status,
        public string $current_status_label,
        public string $current_status_icon,
        public array $allowed_next_milestones,
        public ?string $issue_notes,
        public ?Carbon $issue_reported_at,
        public string $vehicle_name,
        public string $vehicle_license_plate,
    ) {}
}
