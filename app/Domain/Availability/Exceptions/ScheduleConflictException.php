<?php

declare(strict_types=1);

namespace App\Domain\Availability\Exceptions;

use App\Models\VehicleScheduleBlock;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use RuntimeException;
use Throwable;

class ScheduleConflictException extends RuntimeException
{
    /**
     * @param  Collection<int, VehicleScheduleBlock>  $conflicts
     */
    public function __construct(
        public readonly Collection $conflicts,
        public readonly int $vehicleId,
        public readonly Carbon $requestedStart,
        public readonly Carbon $requestedEnd,
        string $message = 'The requested schedule interval conflicts with an existing schedule block.',
        int $code = 409,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
