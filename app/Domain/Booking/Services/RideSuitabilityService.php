<?php

declare(strict_types=1);

namespace App\Domain\Booking\Services;

use App\Domain\Availability\Services\AvailabilityService;
use App\Domain\Booking\DTOs\RideCriteria;
use App\Domain\Booking\DTOs\SuitabilityResult;
use App\Domain\Booking\DTOs\VehicleSuitabilityItem;
use App\Domain\Booking\ServiceType;
use App\Domain\Catalog\Enums\VehicleStatus;
use App\Domain\Catalog\Services\DriverAssignmentService;
use App\Domain\Pricing\DTOs\EstimateRequestData;
use App\Domain\Pricing\Services\PricingService;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class RideSuitabilityService
{
    public function __construct(
        protected AvailabilityService $availabilityService,
        protected DriverAssignmentService $driverAssignmentService,
        protected PricingService $pricingService,
    ) {}

    public function evaluate(RideCriteria $criteria): SuitabilityResult
    {
        $minimumNoticeHours = (int) config('booking.ride.minimum_notice_hours', 6);
        $minimumNoticeTime = Carbon::now('Asia/Colombo')->addHours($minimumNoticeHours);

        $violatesNotice = $criteria->pickupTime->lt($minimumNoticeTime);

        $outboundStart = (clone $criteria->pickupTime)->setTimezone('Asia/Colombo');
        $outboundEnd = (clone $outboundStart)->addMinutes($criteria->effectiveOutboundDurationMinutes());

        $hasReturn = $criteria->hasReturn && $criteria->returnPickupTime !== null;
        $returnStart = $hasReturn ? (clone $criteria->returnPickupTime)->setTimezone('Asia/Colombo') : null;
        $returnEnd = $returnStart ? (clone $returnStart)->addMinutes($criteria->effectiveReturnDurationMinutes()) : null;

        /** @var Collection<int, Vehicle> $candidateVehicles */
        $candidateVehicles = Vehicle::where('eligible_for_ride', true)
            ->where('status', VehicleStatus::Active->value)
            ->with(['media', 'publicApprovedMedia'])
            ->orderBy('passenger_capacity')
            ->get();

        $suitable = new Collection;
        $unsuitable = new Collection;

        foreach ($candidateVehicles as $vehicle) {
            $reasons = [];

            if ($violatesNotice) {
                $reasons[] = "Minimum notice of {$minimumNoticeHours} hours required before pickup.";
            }

            if ($vehicle->passenger_capacity < $criteria->passengerCount) {
                $reasons[] = "Passenger capacity ({$vehicle->passenger_capacity}) is less than required ({$criteria->passengerCount}).";
            }

            if ($vehicle->luggage_capacity < $criteria->luggageCount) {
                $reasons[] = "Luggage capacity ({$vehicle->luggage_capacity}) is less than required ({$criteria->luggageCount}).";
            }

            // Ride is a chauffeured service: vehicle must have an active effective driver
            $outboundDriver = $this->driverAssignmentService->getEffectiveDriver($vehicle, $outboundStart);
            if (! $outboundDriver) {
                $reasons[] = 'No active driver currently assigned to this vehicle for outbound journey.';
            }

            if ($hasReturn && $returnStart) {
                $returnDriver = $this->driverAssignmentService->getEffectiveDriver($vehicle, $returnStart);
                if (! $returnDriver) {
                    $reasons[] = 'No active driver currently assigned to this vehicle for return journey.';
                }
            }

            // Check availability for outbound leg
            $isOutboundAvailable = $this->availabilityService->checkAvailability(
                vehicle: $vehicle,
                start: $outboundStart,
                end: $outboundEnd,
                service: ServiceType::Ride,
                bufferBefore: 0,
                bufferAfter: $vehicle->turnaround_buffer_minutes,
            );

            if (! $isOutboundAvailable) {
                $reasons[] = 'Vehicle has an existing booking or operational block during requested outbound time.';
            }

            // Check availability for return leg if requested
            if ($hasReturn && $returnStart && $returnEnd) {
                $isReturnAvailable = $this->availabilityService->checkAvailability(
                    vehicle: $vehicle,
                    start: $returnStart,
                    end: $returnEnd,
                    service: ServiceType::Ride,
                    bufferBefore: 0,
                    bufferAfter: $vehicle->turnaround_buffer_minutes,
                );

                if (! $isReturnAvailable) {
                    $reasons[] = 'Vehicle has an existing booking or operational block during requested return time.';
                }
            }

            // Calculate pricing estimate
            $totalDistanceKm = ($criteria->outboundDistanceKm ?? 0.0) + ($criteria->returnDistanceKm ?? 0.0);
            $totalDurationMinutes = $criteria->effectiveOutboundDurationMinutes() + ($hasReturn ? $criteria->effectiveReturnDurationMinutes() : 0);

            $estimateRequest = new EstimateRequestData(
                serviceType: ServiceType::Ride,
                vehicleId: $vehicle->id,
                serviceDate: $outboundStart,
                distanceKm: $totalDistanceKm > 0 ? $totalDistanceKm : null,
                durationHours: $totalDurationMinutes > 0 ? (float) ($totalDurationMinutes / 60) : null,
                stopCount: count($criteria->stops),
                waitingHours: (int) ceil($criteria->totalWaitingMinutes() / 60),
                assumptions: [
                    'origin' => $criteria->originText,
                    'destination' => $criteria->destinationText,
                    'has_return' => $hasReturn,
                    'total_waiting_minutes' => $criteria->totalWaitingMinutes(),
                    'stops_count' => count($criteria->stops),
                ],
            );

            $estimate = $this->pricingService->calculateEstimate($estimateRequest);

            $item = new VehicleSuitabilityItem(
                vehicle: $vehicle,
                isSuitable: empty($reasons),
                reasons: $reasons,
                estimate: $estimate,
            );

            if (empty($reasons)) {
                $suitable->push($item);
            } else {
                $unsuitable->push($item);
            }
        }

        return new SuitabilityResult(
            suitable: $suitable,
            unsuitable: $unsuitable,
        );
    }
}
