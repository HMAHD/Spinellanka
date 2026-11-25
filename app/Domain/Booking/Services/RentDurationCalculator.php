<?php

declare(strict_types=1);

namespace App\Domain\Booking\Services;

use App\Domain\Booking\DTOs\RentDurationResult;
use App\Models\RentPolicy;
use Carbon\Carbon;
use InvalidArgumentException;

class RentDurationCalculator
{
    /**
     * Calculate 24-hour cycle duration, grace period, and extra hours in Asia/Colombo.
     */
    public function calculate(Carbon $pickup, Carbon $return, ?RentPolicy $policy = null): RentDurationResult
    {
        $policy = $policy ?? RentPolicy::current();

        $pickupColombo = (clone $pickup)->setTimezone('Asia/Colombo');
        $returnColombo = (clone $return)->setTimezone('Asia/Colombo');

        if ($returnColombo->lte($pickupColombo)) {
            throw new InvalidArgumentException('Return date and time must be after pickup date and time.');
        }

        $totalMinutes = (int) $pickupColombo->diffInMinutes($returnColombo);

        $graceMinutes = $policy->grace_period_minutes;
        $extraHoursThreshold = $policy->extra_hours_threshold;

        // Minimum rental period is 1 day (24 hours = 1440 minutes)
        if ($totalMinutes <= 1440) {
            return new RentDurationResult(
                days: 1,
                extraHours: 0,
                totalMinutes: $totalMinutes,
                isWithinGracePeriod: false,
                formattedSummary: '1 Day (24 Hours)',
            );
        }

        $fullDays = intdiv($totalMinutes, 1440);
        $remainderMinutes = $totalMinutes % 1440;

        if ($remainderMinutes === 0) {
            return new RentDurationResult(
                days: $fullDays,
                extraHours: 0,
                totalMinutes: $totalMinutes,
                isWithinGracePeriod: false,
                formattedSummary: "{$fullDays} Days",
            );
        }

        // Within grace period (e.g. up to 59 minutes past 24-hour cycle)
        if ($remainderMinutes <= $graceMinutes) {
            return new RentDurationResult(
                days: $fullDays,
                extraHours: 0,
                totalMinutes: $totalMinutes,
                isWithinGracePeriod: true,
                formattedSummary: "{$fullDays} Days (Grace Period Included)",
            );
        }

        // Over grace period: check if within extra-hour threshold (e.g. 1 to 5 extra hours)
        $maxExtraMinutes = $extraHoursThreshold * 60;
        if ($remainderMinutes <= $maxExtraMinutes) {
            $extraHours = (int) ceil($remainderMinutes / 60);

            return new RentDurationResult(
                days: $fullDays,
                extraHours: $extraHours,
                totalMinutes: $totalMinutes,
                isWithinGracePeriod: false,
                formattedSummary: "{$fullDays} Days + {$extraHours} Extra ".($extraHours === 1 ? 'Hour' : 'Hours'),
            );
        }

        // Beyond extra-hour threshold (6+ hours past 24h cycle): rounds up to next full 24-hour day
        $finalDays = $fullDays + 1;

        return new RentDurationResult(
            days: $finalDays,
            extraHours: 0,
            totalMinutes: $totalMinutes,
            isWithinGracePeriod: false,
            formattedSummary: "{$finalDays} Days",
        );
    }
}
