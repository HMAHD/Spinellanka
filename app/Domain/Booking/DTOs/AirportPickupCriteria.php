<?php

declare(strict_types=1);

namespace App\Domain\Booking\DTOs;

use Carbon\Carbon;

final class AirportPickupCriteria
{
    /**
     * @param  array<int>  $extraIds
     */
    public function __construct(
        public readonly string $airport,
        public readonly string $flightNumber,
        public readonly Carbon $arrivalTime,
        public readonly string $destinationText,
        public readonly int $passengerCount,
        public readonly int $luggageCount,
        public readonly ?float $distanceKm = null,
        public readonly ?float $durationHours = null,
        public readonly ?string $placeId = null,
        public readonly ?float $destinationLat = null,
        public readonly ?float $destinationLng = null,
        public readonly ?string $destinationNotes = null,
        public readonly bool $meetAndGreet = true,
        public readonly ?string $nameBoardText = null,
        public readonly ?string $waitingInstructions = null,
        public readonly array $extraIds = [],
    ) {}
}

