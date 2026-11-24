<?php

declare(strict_types=1);

namespace App\Domain\Location\Contracts;

use App\Domain\Location\DTOs\LocationCoordinate;
use App\Domain\Location\DTOs\RouteEstimateResult;

interface RouteEstimateContract
{
    public function estimateRoute(
        LocationCoordinate $origin,
        LocationCoordinate $destination,
    ): ?RouteEstimateResult;
}

