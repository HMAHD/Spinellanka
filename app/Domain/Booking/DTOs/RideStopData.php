<?php

declare(strict_types=1);

namespace App\Domain\Booking\DTOs;

final class RideStopData
{
    public function __construct(
        public readonly int $stopOrder,
        public readonly string $locationText,
        public readonly int $plannedWaitingMinutes = 0,
        public readonly ?float $latitude = null,
        public readonly ?float $longitude = null,
        public readonly ?string $notes = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'stop_order' => $this->stopOrder,
            'location_text' => $this->locationText,
            'planned_waiting_minutes' => $this->plannedWaitingMinutes,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'notes' => $this->notes,
        ];
    }
}
