<?php

declare(strict_types=1);

namespace App\Domain\Booking\Services;

use App\Domain\Availability\Services\AvailabilityService;
use App\Domain\Booking\DTOs\AirportPickupCriteria;
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

class AirportPickupSuitabilityService
{
    public function __construct(
        protected AvailabilityService $availabilityService,
        protected DriverAssignmentService $driverAssignmentService,
        protected PricingService $pricingService,
    ) {}

    public function evaluate(AirportPickupCriteria $criteria): SuitabilityResult
    {
        $minimumNoticeHours = (int) config('booking.airport_pickup.minimum_notice_hours', 6);
        $minimumNoticeTime = Carbon::now('Asia/Colombo')->addHours($minimumNoticeHours);

        $violatesNotice = $criteria->arrivalTime->lt($minimumNoticeTime);

        // Calculate schedule interval end time
        $tripDurationMinutes = $criteria->durationHours !== null
            ? (int) round($criteria->durationHours * 60)
            : (int) config('booking.airport_pickup.default_trip_duration_minutes', 180);

        $startsAt = (clone $criteria->arrivalTime)->setTimezone('Asia/Colombo');
        $endsAt = (clone $startsAt)->addMinutes($tripDurationMinutes);

        /** @var Collection<int, Vehicle> $candidateVehicles */
        $candidateVehicles = Vehicle::where('eligible_for_pickup', true)
            ->where('status', VehicleStatus::Active->value)
            ->with(['media'])
            ->orderBy('passenger_capacity')
            ->get();

        $suitable = new Collection;
        $unsuitable = new Collection;

        foreach ($candidateVehicles as $vehicle) {
            $reasons = [];

            if ($violatesNotice) {
                $reasons[] = "Minimum notice of {$minimumNoticeHours} hours required before flight arrival.";
            }

            if ($vehicle->passenger_capacity < $criteria->passengerCount) {
                $reasons[] = "Passenger capacity ({$vehicle->passenger_capacity}) is less than required ({$criteria->passengerCount}).";
            }

            if ($vehicle->luggage_capacity < $criteria->luggageCount) {
                $reasons[] = "Luggage capacity ({$vehicle->luggage_capacity}) is less than required ({$criteria->luggageCount}).";
            }

            // Airport pickup is a chauffeured service: vehicle must have an active effective driver
            $effectiveDriver = $this->driverAssignmentService->getEffectiveDriver($vehicle, $startsAt);
            if (! $effectiveDriver) {
                $reasons[] = 'No active driver currently assigned to this vehicle.';
            }

            // Check availability across the window
            $isAvailable = $this->availabilityService->checkAvailability(
                vehicle: $vehicle,
                start: $startsAt,
                end: $endsAt,
                service: ServiceType::AirportPickup,
                bufferBefore: 0,
                bufferAfter: $vehicle->turnaround_buffer_minutes,
            );

            if (! $isAvailable) {
                $reasons[] = 'Vehicle schedule conflicts with existing booking or maintenance block.';
            }

            // Calculate estimate
            $estimateRequest = new EstimateRequestData(
                serviceType: ServiceType::AirportPickup,
                vehicleId: $vehicle->id,
                serviceDate: $startsAt,
                distanceKm: $criteria->distanceKm,
                durationHours: $criteria->durationHours,
                extraIds: $criteria->extraIds,
                assumptions: [
                    'airport' => $criteria->airport,
                    'flight_number' => $criteria->flightNumber,
                    'destination' => $criteria->destinationText,
                ]
            );

            $estimate = $this->pricingService->calculateEstimate($estimateRequest);

            $item = new VehicleSuitabilityItem(
                vehicle: $vehicle,
                isSuitable: empty($reasons),
                reasons: $reasons,
                estimate: $estimate
            );

            if (empty($reasons)) {
                $suitable->push($item);
            } else {
                $unsuitable->push($item);
            }
        }

        return new SuitabilityResult(
            suitable: $suitable,
            unsuitable: $unsuitable
        );
    }
}
