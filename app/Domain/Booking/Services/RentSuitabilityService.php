<?php

declare(strict_types=1);

namespace App\Domain\Booking\Services;

use App\Domain\Availability\Services\AvailabilityService;
use App\Domain\Booking\DTOs\RentCriteria;
use App\Domain\Booking\DTOs\SuitabilityResult;
use App\Domain\Booking\DTOs\VehicleSuitabilityItem;
use App\Domain\Booking\ServiceType;
use App\Domain\Catalog\Enums\VehicleStatus;
use App\Domain\Pricing\DTOs\EstimateRequestData;
use App\Domain\Pricing\Services\PricingService;
use App\Models\RentPolicy;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class RentSuitabilityService
{
    public function __construct(
        protected AvailabilityService $availabilityService,
        protected PricingService $pricingService,
    ) {}

    public function evaluate(RentCriteria $criteria, ?RentPolicy $policy = null): SuitabilityResult
    {
        $policy = $policy ?? RentPolicy::current();

        $minimumNoticeHours = (int) config('booking.rent.minimum_notice_hours', 12);
        $minimumNoticeTime = Carbon::now('Asia/Colombo')->addHours($minimumNoticeHours);

        $violatesNotice = $criteria->pickupDateTime->lt($minimumNoticeTime);

        $startsAt = (clone $criteria->pickupDateTime)->setTimezone('Asia/Colombo');
        $endsAt = (clone $criteria->returnDateTime)->setTimezone('Asia/Colombo');

        // Eligibility validation against policy
        $eligibilityErrors = [];
        if ($criteria->driverAge < $policy->min_driver_age || $criteria->driverAge > $policy->max_driver_age) {
            $eligibilityErrors[] = "Lead driver age ({$criteria->driverAge}) must be between {$policy->min_driver_age} and {$policy->max_driver_age} years.";
        }

        if ($criteria->driverLicenceYears < $policy->min_licence_years) {
            $eligibilityErrors[] = "Lead driver must have held a valid licence for at least {$policy->min_licence_years} years (current: {$criteria->driverLicenceYears}).";
        }

        if ($policy->idp_required && ! $criteria->hasIdp) {
            $eligibilityErrors[] = 'An International Driving Permit (IDP) or Sri Lankan temporary driving permit endorsement is required.';
        }

        if ($criteria->hasAdditionalDriver) {
            if ($criteria->additionalDriverAge !== null && ($criteria->additionalDriverAge < $policy->min_driver_age || $criteria->additionalDriverAge > $policy->max_driver_age)) {
                $eligibilityErrors[] = "Additional driver age ({$criteria->additionalDriverAge}) must be between {$policy->min_driver_age} and {$policy->max_driver_age} years.";
            }

            if ($criteria->additionalDriverLicenceYears !== null && $criteria->additionalDriverLicenceYears < $policy->min_licence_years) {
                $eligibilityErrors[] = "Additional driver licence experience must be at least {$policy->min_licence_years} years.";
            }

            if ($policy->idp_required && ! $criteria->additionalDriverHasIdp) {
                $eligibilityErrors[] = 'Additional driver must hold a valid International Driving Permit (IDP).';
            }
        }

        /** @var Collection<int, Vehicle> $candidateVehicles */
        $candidateVehicles = Vehicle::where('eligible_for_rent', true)
            ->where('status', VehicleStatus::Active->value)
            ->with(['media', 'publicApprovedMedia'])
            ->orderBy('passenger_capacity')
            ->get();

        $suitable = new Collection;
        $unsuitable = new Collection;

        foreach ($candidateVehicles as $vehicle) {
            $reasons = $eligibilityErrors;

            if ($violatesNotice) {
                $reasons[] = "Minimum notice of {$minimumNoticeHours} hours required before pickup.";
            }

            // Invariant 6: Rent creates NO driver assignment! Driver checks must never be run.

            // Check availability across the entire rental window + turnaround buffer
            $isAvailable = $this->availabilityService->checkAvailability(
                vehicle: $vehicle,
                start: $startsAt,
                end: $endsAt,
                service: ServiceType::Rent,
                bufferBefore: 0,
                bufferAfter: $vehicle->turnaround_buffer_minutes,
            );

            if (! $isAvailable) {
                $reasons[] = 'Vehicle schedule conflicts with an existing booking or maintenance block.';
            }

            // Calculate estimate
            $estimateRequest = new EstimateRequestData(
                serviceType: ServiceType::Rent,
                vehicleId: $vehicle->id,
                serviceDate: $startsAt,
                rentalDays: $criteria->durationDays,
                durationHours: $criteria->extraHours > 0 ? (float) $criteria->extraHours : null,
                extraIds: $criteria->extraIds,
                assumptions: [
                    'pickup_datetime' => $criteria->pickupDateTime->toIso8601String(),
                    'return_datetime' => $criteria->returnDateTime->toIso8601String(),
                    'duration_days' => $criteria->durationDays,
                    'extra_hours' => $criteria->extraHours,
                    'pickup_location' => $criteria->pickupLocationText,
                    'return_location' => $criteria->returnLocationText,
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

        return new SuitabilityResult($suitable, $unsuitable);
    }
}
