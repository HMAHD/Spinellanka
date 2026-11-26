<?php

declare(strict_types=1);

namespace App\Domain\Operations\DTOs;

use App\Domain\Operations\Enums\OperationalAlertSeverity;

readonly class OperationalAlertDTO
{
    public function __construct(
        public string $id,
        public OperationalAlertSeverity $severity,
        public string $title,
        public string $description,
        public string $category,
        public ?int $booking_id = null,
        public ?string $booking_reference = null,
        public ?int $vehicle_id = null,
        public ?int $driver_id = null,
        public ?string $action_url = null,
        public ?string $action_label = null,
    ) {}
}
