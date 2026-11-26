<?php

declare(strict_types=1);

namespace App\Domain\Operations\DTOs;

use App\Domain\Booking\ServiceType;
use App\Domain\Operations\Enums\DriverTripStatus;
use Carbon\Carbon;

readonly class AgendaItemDTO
{
    /**
     * @param  array<int, string>  $intermediate_stops
     */
    public function __construct(
        public int $id,
        public Carbon $time,
        public string $time_formatted,
        public ?string $booking_reference,
        public ?int $booking_id,
        public ServiceType|string $service_type,
        public string $service_label,
        public string $origin_text,
        public ?string $destination_text,
        public array $intermediate_stops,
        public string $vehicle_name,
        public string $vehicle_plate,
        public ?string $driver_name,
        public ?DriverTripStatus $trip_status,
        public ?string $trip_status_label,
        public bool $is_replacement_driver,
        public bool $has_conflict,
        public ?string $notes,
    ) {}
}
