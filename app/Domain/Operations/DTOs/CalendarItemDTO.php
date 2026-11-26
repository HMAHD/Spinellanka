<?php

declare(strict_types=1);

namespace App\Domain\Operations\DTOs;

use App\Domain\Availability\Enums\ScheduleBlockKind;
use App\Domain\Availability\Enums\ScheduleBlockStatus;
use App\Domain\Booking\ServiceType;
use App\Domain\Operations\Enums\DriverTripStatus;
use Carbon\Carbon;

readonly class CalendarItemDTO
{
    public function __construct(
        public int $id,
        public int $vehicle_id,
        public string $vehicle_name,
        public string $vehicle_plate,
        public ScheduleBlockKind $kind,
        public string $kind_label,
        public string $kind_icon,
        public string $border_style, // solid, dashed, dotted, diagonal_hatch, double
        public string $badge_class,
        public ScheduleBlockStatus $status,
        public string $status_label,
        public Carbon $starts_at,
        public Carbon $ends_at,
        public Carbon $effective_start,
        public Carbon $effective_end,
        public ?int $booking_id,
        public ?string $booking_reference,
        public ?ServiceType $service_type,
        public string $service_label,
        public ?int $driver_id,
        public ?string $driver_name,
        public bool $is_replacement,
        public ?DriverTripStatus $driver_trip_status,
        public ?string $driver_trip_status_label,
        public ?string $notes,
        public bool $has_conflict,
    ) {}
}
