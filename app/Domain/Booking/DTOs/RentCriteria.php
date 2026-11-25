<?php

declare(strict_types=1);

namespace App\Domain\Booking\DTOs;

use Carbon\Carbon;

final class RentCriteria
{
    /**
     * @param  array<int>  $extraIds
     */
    public function __construct(
        public readonly Carbon $pickupDateTime,
        public readonly Carbon $returnDateTime,
        public readonly string $pickupLocationType,
        public readonly string $pickupLocationText,
        public readonly string $returnLocationType,
        public readonly string $returnLocationText,
        public readonly int $driverAge,
        public readonly string $driverLicenceCountry,
        public readonly int $driverLicenceYears,
        public readonly bool $hasIdp,
        public readonly int $durationDays,
        public readonly int $extraHours = 0,
        public readonly bool $hasAdditionalDriver = false,
        public readonly ?string $additionalDriverName = null,
        public readonly ?int $additionalDriverAge = null,
        public readonly ?string $additionalDriverLicenceCountry = null,
        public readonly ?int $additionalDriverLicenceYears = null,
        public readonly bool $additionalDriverHasIdp = false,
        public readonly ?int $expectedDistanceKm = null,
        public readonly ?string $pickupPlaceId = null,
        public readonly ?float $pickupLat = null,
        public readonly ?float $pickupLng = null,
        public readonly ?string $returnPlaceId = null,
        public readonly ?float $returnLat = null,
        public readonly ?float $returnLng = null,
        public readonly int $deliveryCount = 0,
        public readonly ?string $specialInstructions = null,
        public readonly array $extraIds = [],
    ) {}
}
