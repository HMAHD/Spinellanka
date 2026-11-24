<?php

declare(strict_types=1);

namespace App\Domain\Booking\DTOs;

use App\Domain\Pricing\DTOs\EstimateResult;
use App\Models\Vehicle;

final class VehicleSuitabilityItem
{
    /**
     * @param  array<string>  $reasons
     */
    public function __construct(
        public readonly Vehicle $vehicle,
        public readonly bool $isSuitable,
        public readonly array $reasons = [],
        public readonly ?EstimateResult $estimate = null,
    ) {}
}

