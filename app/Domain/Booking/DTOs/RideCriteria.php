<?php

declare(strict_types=1);

namespace App\Domain\Booking\DTOs;

use Carbon\Carbon;

final class RideCriteria
{
    /**
     * @param  array<int, RideStopData>  $stops
     * @param  array<int>  $extraIds
     */
    public function __construct(
        public readonly Carbon $pickupTime,
        public readonly string $originText,
        public readonly string $destinationText,
        public readonly int $passengerCount,
        public readonly int $luggageCount,
        public readonly array $stops = [],
        public readonly bool $hasReturn = false,
        public readonly ?Carbon $returnPickupTime = null,
        public readonly ?string $returnOriginText = null,
        public readonly ?string $returnDestinationText = null,
        public readonly ?float $originLat = null,
        public readonly ?float $originLng = null,
        public readonly ?string $originNotes = null,
        public readonly ?float $destinationLat = null,
        public readonly ?float $destinationLng = null,
        public readonly ?string $destinationNotes = null,
        public readonly ?string $returnNotes = null,
        public readonly ?float $outboundDistanceKm = null,
        public readonly ?int $outboundDurationMinutes = null,
        public readonly ?float $returnDistanceKm = null,
        public readonly ?int $returnDurationMinutes = null,
        public readonly ?string $specialInstructions = null,
        public readonly array $extraIds = [],
    ) {}

    public function totalWaitingMinutes(): int
    {
        return array_sum(array_map(fn (RideStopData $stop) => $stop->plannedWaitingMinutes, $this->stops));
    }

    public function effectiveOutboundDurationMinutes(): int
    {
        $defaultDrive = (int) config('booking.ride.default_trip_duration_minutes', 180);
        $driveDuration = $this->outboundDurationMinutes ?? $defaultDrive;

        return $driveDuration + $this->totalWaitingMinutes();
    }

    public function outboundArrivalEstimate(): Carbon
    {
        return (clone $this->pickupTime)->addMinutes($this->effectiveOutboundDurationMinutes());
    }

    public function effectiveReturnDurationMinutes(): int
    {
        $defaultDrive = (int) config('booking.ride.default_trip_duration_minutes', 180);

        return $this->returnDurationMinutes ?? $defaultDrive;
    }

    public function returnArrivalEstimate(): ?Carbon
    {
        if (! $this->hasReturn || ! $this->returnPickupTime) {
            return null;
        }

        return (clone $this->returnPickupTime)->addMinutes($this->effectiveReturnDurationMinutes());
    }
}
