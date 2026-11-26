<?php

declare(strict_types=1);

namespace App\Domain\Operations\Services;

use App\Domain\Availability\Enums\ScheduleBlockKind;
use App\Domain\Availability\Enums\ScheduleBlockStatus;
use App\Domain\Booking\Enums\BookingStatus;
use App\Domain\Booking\Enums\ProposalStatus;
use App\Domain\Booking\ServiceType;
use App\Domain\Messaging\Enums\MessageDeliveryStatus;
use App\Domain\Operations\DTOs\OperationalAlertDTO;
use App\Domain\Operations\Enums\OperationalAlertSeverity;
use App\Models\Booking;
use App\Models\MessageDelivery;
use App\Models\Proposal;
use App\Models\VehicleScheduleBlock;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class OperationsAlertService
{
    /**
     * Aggregate all active operational alerts ranked by severity.
     *
     * @return array<int, OperationalAlertDTO>
     */
    public function getActiveAlerts(): array
    {
        $alerts = array_merge(
            $this->getConflictAlerts(),
            $this->getUnassignedTripAlerts(),
            $this->getDeadlineAlerts(),
            $this->getStaleRequestAlerts(),
            $this->getDeliveryFailureAlerts(),
        );

        // Sort: Critical first, then Warning, then Info
        usort($alerts, function (OperationalAlertDTO $a, OperationalAlertDTO $b) {
            $severityOrder = [
                OperationalAlertSeverity::Critical->value => 1,
                OperationalAlertSeverity::Warning->value => 2,
                OperationalAlertSeverity::Info->value => 3,
            ];

            return ($severityOrder[$a->severity->value] ?? 99) <=> ($severityOrder[$b->severity->value] ?? 99);
        });

        return $alerts;
    }

    /**
     * Detect overlapping schedule conflicts on vehicles and drivers.
     *
     * @return array<int, OperationalAlertDTO>
     */
    public function getConflictAlerts(): array
    {
        $alerts = [];

        // 1. Overlapping blocks on same vehicle (excluding same group_id)
        $vehicleOverlaps = DB::table('vehicle_schedule_blocks as b1')
            ->join('vehicle_schedule_blocks as b2', function ($join) {
                $join->on('b1.vehicle_id', '=', 'b2.vehicle_id')
                    ->whereColumn('b1.id', '<', 'b2.id')
                    ->whereRaw('b1.effective_start < b2.effective_end')
                    ->whereRaw('b1.effective_end > b2.effective_start')
                    ->whereRaw('(b1.group_id IS NULL OR b1.group_id != b2.group_id)');
            })
            ->join('vehicles', 'b1.vehicle_id', '=', 'vehicles.id')
            ->where('b1.status', ScheduleBlockStatus::Active->value)
            ->where('b2.status', ScheduleBlockStatus::Active->value)
            ->where('b1.blocks_availability', true)
            ->where('b2.blocks_availability', true)
            ->select(
                'b1.id as block_1_id',
                'b2.id as block_2_id',
                'b1.vehicle_id',
                'vehicles.make',
                'vehicles.model',
                'vehicles.license_plate'
            )
            ->get();

        foreach ($vehicleOverlaps as $overlap) {
            $alerts[] = new OperationalAlertDTO(
                id: "conflict_veh_{$overlap->vehicle_id}_{$overlap->block_1_id}_{$overlap->block_2_id}",
                severity: OperationalAlertSeverity::Critical,
                title: "Schedule Conflict on {$overlap->make} {$overlap->model} ({$overlap->license_plate})",
                description: "Blocks #{$overlap->block_1_id} and #{$overlap->block_2_id} overlap and both block availability.",
                category: 'conflict',
                vehicle_id: (int) $overlap->vehicle_id,
            );
        }

        // 2. Overlapping blocks for the same driver across different vehicles
        $driverOverlaps = DB::table('vehicle_schedule_blocks as b1')
            ->join('vehicle_schedule_blocks as b2', function ($join) {
                $join->on('b1.driver_id', '=', 'b2.driver_id')
                    ->whereColumn('b1.id', '<', 'b2.id')
                    ->whereColumn('b1.vehicle_id', '!=', 'b2.vehicle_id')
                    ->whereRaw('b1.effective_start < b2.effective_end')
                    ->whereRaw('b1.effective_end > b2.effective_start');
            })
            ->join('drivers', 'b1.driver_id', '=', 'drivers.id')
            ->where('b1.status', ScheduleBlockStatus::Active->value)
            ->where('b2.status', ScheduleBlockStatus::Active->value)
            ->where('b1.blocks_availability', true)
            ->where('b2.blocks_availability', true)
            ->whereNotNull('b1.driver_id')
            ->select(
                'b1.id as block_1_id',
                'b2.id as block_2_id',
                'b1.driver_id',
                'drivers.public_name as driver_name'
            )
            ->get();

        foreach ($driverOverlaps as $overlap) {
            $alerts[] = new OperationalAlertDTO(
                id: "conflict_drv_{$overlap->driver_id}_{$overlap->block_1_id}_{$overlap->block_2_id}",
                severity: OperationalAlertSeverity::Critical,
                title: "Driver Assignment Conflict: {$overlap->driver_name}",
                description: "Driver is scheduled for overlapping blocks across different vehicles (#{$overlap->block_1_id} & #{$overlap->block_2_id}).",
                category: 'conflict',
                driver_id: (int) $overlap->driver_id,
            );
        }

        return $alerts;
    }

    /**
     * Detect confirmed chauffeured trips within 48h that lack an assigned driver.
     *
     * @return array<int, OperationalAlertDTO>
     */
    public function getUnassignedTripAlerts(): array
    {
        $alerts = [];
        $now = Carbon::now('Asia/Colombo');
        $horizon = $now->copy()->addHours(48);

        $unassignedBookings = Booking::query()
            ->with(['vehicle'])
            ->where('status', BookingStatus::Confirmed)
            ->whereIn('service_type', [
                ServiceType::AirportPickup,
                ServiceType::AirportTransfer,
                ServiceType::Ride,
            ])
            ->whereNull('assigned_driver_id')
            ->get();

        foreach ($unassignedBookings as $booking) {
            $startTime = $booking->getServiceStartTime();
            if ($startTime && $startTime->gte($now) && $startTime->lte($horizon)) {
                $hoursUntil = (int) round($now->diffInHours($startTime, false));
                $alerts[] = new OperationalAlertDTO(
                    id: "unassigned_{$booking->id}",
                    severity: OperationalAlertSeverity::Critical,
                    title: "Unassigned Chauffeured Trip: {$booking->reference}",
                    description: "Confirmed {$booking->service_type->label()} starts in ~{$hoursUntil}h ({$startTime->format('d M h:i A')}) with no driver assigned.",
                    category: 'unassigned_trip',
                    booking_id: $booking->id,
                    booking_reference: $booking->reference,
                    vehicle_id: $booking->vehicle_id,
                );
            }
        }

        return $alerts;
    }

    /**
     * Detect imminent checkout holds (<5m) and proposal response deadlines (<2h).
     *
     * @return array<int, OperationalAlertDTO>
     */
    public function getDeadlineAlerts(): array
    {
        $alerts = [];
        $now = Carbon::now('Asia/Colombo');

        // Checkout holds expiring within 5 minutes
        $expiringHolds = VehicleScheduleBlock::query()
            ->with(['vehicle'])
            ->where('kind', ScheduleBlockKind::CheckoutHold)
            ->where('status', ScheduleBlockStatus::Active)
            ->whereNotNull('expires_at')
            ->where('expires_at', '>', $now)
            ->where('expires_at', '<=', $now->copy()->addMinutes(5))
            ->get();

        foreach ($expiringHolds as $hold) {
            $minsLeft = max(1, (int) round($now->diffInMinutes($hold->expires_at)));
            $alerts[] = new OperationalAlertDTO(
                id: "hold_expiry_{$hold->id}",
                severity: OperationalAlertSeverity::Warning,
                title: "Checkout Hold Expiring: {$hold->vehicle->make} {$hold->vehicle->model}",
                description: "Checkout hold #{$hold->id} expires in {$minsLeft} minute(s).",
                category: 'deadline',
                vehicle_id: $hold->vehicle_id,
            );
        }

        // Proposals expiring within 2 hours
        $expiringProposals = Proposal::query()
            ->with(['booking'])
            ->where('status', ProposalStatus::Sent)
            ->whereNotNull('expires_at')
            ->where('expires_at', '>', $now)
            ->where('expires_at', '<=', $now->copy()->addHours(2))
            ->get();

        foreach ($expiringProposals as $proposal) {
            $minsLeft = (int) round($now->diffInMinutes($proposal->expires_at));
            $alerts[] = new OperationalAlertDTO(
                id: "prop_expiry_{$proposal->id}",
                severity: OperationalAlertSeverity::Warning,
                title: "Proposal Deadline Imminent: {$proposal->reference}",
                description: "Proposal version {$proposal->version} for booking {$proposal->booking->reference} expires in {$minsLeft} minute(s).",
                category: 'deadline',
                booking_id: $proposal->booking_id,
                booking_reference: $proposal->booking->reference,
            );
        }

        return $alerts;
    }

    /**
     * Detect bookings submitted > 24 hours ago that remain unreviewed.
     *
     * @return array<int, OperationalAlertDTO>
     */
    public function getStaleRequestAlerts(): array
    {
        $alerts = [];
        $cutoff = Carbon::now('Asia/Colombo')->subHours(24);

        $staleBookings = Booking::query()
            ->where('status', BookingStatus::Submitted)
            ->where('created_at', '<=', $cutoff)
            ->get();

        foreach ($staleBookings as $booking) {
            $hoursWaiting = (int) round($booking->created_at->diffInHours(Carbon::now('Asia/Colombo')));
            $alerts[] = new OperationalAlertDTO(
                id: "stale_sub_{$booking->id}",
                severity: OperationalAlertSeverity::Warning,
                title: "Stale Booking Request: {$booking->reference}",
                description: "Customer request submitted {$hoursWaiting}h ago has not yet been reviewed by staff.",
                category: 'stale_request',
                booking_id: $booking->id,
                booking_reference: $booking->reference,
            );
        }

        return $alerts;
    }

    /**
     * Detect failed outbox message deliveries.
     *
     * @return array<int, OperationalAlertDTO>
     */
    public function getDeliveryFailureAlerts(): array
    {
        $alerts = [];

        $failedDeliveries = MessageDelivery::query()
            ->with(['booking'])
            ->where('status', MessageDeliveryStatus::Failed)
            ->latest('id')
            ->limit(5)
            ->get();

        foreach ($failedDeliveries as $delivery) {
            $alerts[] = new OperationalAlertDTO(
                id: "failed_msg_{$delivery->id}",
                severity: OperationalAlertSeverity::Warning,
                title: "Message Delivery Failed: {$delivery->channel->value}",
                description: "Delivery #{$delivery->id} to {$delivery->recipient_identifier} failed. Error: ".($delivery->last_error_message ?? 'Unknown error'),
                category: 'delivery_failure',
                booking_id: $delivery->booking_id,
                booking_reference: $delivery->booking?->reference,
            );
        }

        return $alerts;
    }
}
