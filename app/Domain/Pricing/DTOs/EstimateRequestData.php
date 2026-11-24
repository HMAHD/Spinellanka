<?php

declare(strict_types=1);

namespace App\Domain\Pricing\DTOs;

use App\Domain\Booking\ServiceType;
use Carbon\Carbon;

final class EstimateRequestData
{
    /**
     * @param  array<int>  $extraIds
     * @param  array<string, mixed>  $assumptions
     */
    public function __construct(
        public readonly ServiceType $serviceType,
        public readonly ?int $vehicleId,
        public readonly Carbon $serviceDate,
        public readonly ?float $distanceKm = null,
        public readonly ?float $durationHours = null,
        public readonly ?int $rentalDays = null,
        public readonly ?int $stopCount = null,
        public readonly ?int $waitingHours = null,
        public readonly array $extraIds = [],
        public readonly array $assumptions = [],
        public readonly ?string $sessionId = null,
    ) {}
}
