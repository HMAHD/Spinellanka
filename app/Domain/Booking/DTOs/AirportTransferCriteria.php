<?php

declare(strict_types=1);

namespace App\Domain\Booking\DTOs;

use Carbon\Carbon;

final class AirportTransferCriteria
{
    /**
     * @param  array<int>  $extraIds
     */
    public function __construct(
        public readonly string $airport,
        public readonly string $flightNumber,
        public readonly Carbon $departureTime,
        public readonly string $originText,
        public readonly int $passengerCount,
        public readonly int $luggageCount,
        public readonly Carbon $recommendedPickupTime,
        public readonly Carbon $chosenPickupTime,
        public readonly bool $isUnsafeTime = false,
        public readonly bool $warningAccepted = false,
        public readonly ?float $distanceKm = null,
        public readonly ?float $durationHours = null,
        public readonly ?string $placeId = null,
        public readonly ?float $originLat = null,
        public readonly ?float $originLng = null,
        public readonly ?string $originNotes = null,
        public readonly ?string $specialInstructions = null,
        public readonly array $extraIds = [],
    ) {}
}
