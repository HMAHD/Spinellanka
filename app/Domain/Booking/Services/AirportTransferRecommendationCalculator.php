<?php

declare(strict_types=1);

namespace App\Domain\Booking\Services;

use Carbon\Carbon;

class AirportTransferRecommendationCalculator
{
    public function calculateRecommendedPickupTime(
        Carbon $departureTime,
        int $estimatedDurationMinutes,
        ?int $airportArrivalBufferMinutes = null,
        ?int $routeContingencyMinutes = null,
    ): Carbon {
        $airportArrivalBuffer = $airportArrivalBufferMinutes ?? (int) config('booking.airport_transfer.airport_arrival_buffer_minutes', 180);
        $routeContingency = $routeContingencyMinutes ?? (int) config('booking.airport_transfer.route_contingency_minutes', 45);

        $totalSubtractedMinutes = $airportArrivalBuffer + $estimatedDurationMinutes + $routeContingency;

        return (clone $departureTime)->subMinutes($totalSubtractedMinutes);
    }

    public function calculateLatestAllowedPickupTime(
        Carbon $departureTime,
        int $estimatedDurationMinutes,
        ?int $latestCutoffBufferMinutes = null,
    ): Carbon {
        $latestCutoffBuffer = $latestCutoffBufferMinutes ?? (int) config('booking.airport_transfer.latest_cutoff_buffer_minutes', 90);

        $totalSubtractedMinutes = $latestCutoffBuffer + $estimatedDurationMinutes;

        return (clone $departureTime)->subMinutes($totalSubtractedMinutes);
    }

    public function isPermissibleChoice(
        Carbon $departureTime,
        Carbon $chosenPickupTime,
        int $estimatedDurationMinutes,
        ?int $latestCutoffBufferMinutes = null,
    ): bool {
        $latestAllowed = $this->calculateLatestAllowedPickupTime($departureTime, $estimatedDurationMinutes, $latestCutoffBufferMinutes);

        return $chosenPickupTime->lte($latestAllowed) && $chosenPickupTime->lt($departureTime);
    }

    public function isUnsafeChoice(
        Carbon $departureTime,
        Carbon $chosenPickupTime,
        int $estimatedDurationMinutes,
        ?int $airportArrivalBufferMinutes = null,
        ?int $routeContingencyMinutes = null,
    ): bool {
        $recommended = $this->calculateRecommendedPickupTime($departureTime, $estimatedDurationMinutes, $airportArrivalBufferMinutes, $routeContingencyMinutes);

        return $chosenPickupTime->gt($recommended);
    }
}
