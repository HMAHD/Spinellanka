<?php

declare(strict_types=1);

namespace App\Domain\Availability\DTOs;

use Carbon\Carbon;
use InvalidArgumentException;

class ScheduleIntervalData
{
    public function __construct(
        public Carbon $startsAt,
        public Carbon $endsAt,
        public int $bufferMinutesBefore = 0,
        public int $bufferMinutesAfter = 0,
        public ?string $notes = null,
    ) {
        if ($bufferMinutesBefore < 0 || $bufferMinutesAfter < 0) {
            throw new InvalidArgumentException('Buffer minutes cannot be negative.');
        }

        $this->startsAt = (clone $startsAt)->setTimezone('Asia/Colombo');
        $this->endsAt = (clone $endsAt)->setTimezone('Asia/Colombo');

        if ($this->endsAt->lte($this->startsAt)) {
            throw new InvalidArgumentException('Schedule interval end time must be after start time.');
        }
    }

    public function effectiveStart(): Carbon
    {
        return (clone $this->startsAt)->subMinutes($this->bufferMinutesBefore);
    }

    public function effectiveEnd(): Carbon
    {
        return (clone $this->endsAt)->addMinutes($this->bufferMinutesAfter);
    }
}
