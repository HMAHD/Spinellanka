<?php

declare(strict_types=1);

namespace App\Domain\Booking\DTOs;

use Illuminate\Support\Collection;

final class SuitabilityResult
{
    /**
     * @param  Collection<int, VehicleSuitabilityItem>  $suitable
     * @param  Collection<int, VehicleSuitabilityItem>  $unsuitable
     */
    public function __construct(
        public readonly Collection $suitable,
        public readonly Collection $unsuitable,
    ) {}
}
