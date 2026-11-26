<?php

declare(strict_types=1);

namespace App\Domain\Operations\Services;

use App\Domain\Availability\Enums\ScheduleBlockKind;
use App\Domain\Availability\Enums\ScheduleBlockStatus;
use App\Domain\Operations\DTOs\AgendaItemDTO;
use App\Domain\Operations\DTOs\CalendarItemDTO;
use App\Models\Booking;
use App\Models\BookingHold;
use App\Models\Vehicle;
use App\Models\VehicleScheduleBlock;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class OperationsScheduleProjectionService
{
    /**
     * Project month grid items grouped by date ('YYYY-MM-DD').
     *
     * @return array<string, array<int, CalendarItemDTO>>
     */
    public function projectMonth(Carbon $month, ?int $vehicleId = null): array
    {
        $startColombo = $month->copy()->setTimezone('Asia/Colombo')->startOfMonth()->startOfWeek(Carbon::MONDAY)->startOfDay();
        $endColombo = $month->copy()->setTimezone('Asia/Colombo')->endOfMonth()->endOfWeek(Carbon::SUNDAY)->endOfDay();

        return $this->projectInterval($startColombo, $endColombo, $vehicleId);
    }

    /**
     * Project week grid items grouped by date ('YYYY-MM-DD').
     *
     * @return array<string, array<int, CalendarItemDTO>>
     */
    public function projectWeek(Carbon $weekDate, ?int $vehicleId = null): array
    {
        $startColombo = $weekDate->copy()->setTimezone('Asia/Colombo')->startOfWeek(Carbon::MONDAY)->startOfDay();
        $endColombo = $weekDate->copy()->setTimezone('Asia/Colombo')->endOfWeek(Carbon::SUNDAY)->endOfDay();

        return $this->projectInterval($startColombo, $endColombo, $vehicleId);
    }

    /**
     * Project day items for a single day.
     *
     * @return array<int, CalendarItemDTO>
     */
    public function projectDay(Carbon $day, ?int $vehicleId = null): array
    {
        $startColombo = $day->copy()->setTimezone('Asia/Colombo')->startOfDay();
        $endColombo = $day->copy()->setTimezone('Asia/Colombo')->endOfDay();

        $grouped = $this->projectInterval($startColombo, $endColombo, $vehicleId);
        $dateKey = $startColombo->format('Y-m-d');

        return $grouped[$dateKey] ?? [];
    }

    /**
     * Project agenda view for a sequence of days.
     *
     * @return array<string, array<int, AgendaItemDTO>>
     */
    public function projectAgenda(Carbon $startDate, int $days = 7, ?int $vehicleId = null): array
    {
        $startColombo = $startDate->copy()->setTimezone('Asia/Colombo')->startOfDay();
        $endColombo = $startColombo->copy()->addDays($days)->endOfDay();

        $calendarInterval = $this->projectInterval($startColombo, $endColombo, $vehicleId);
        $agenda = [];

        /** @var Collection<int, VehicleScheduleBlock> $blocks */
        $blocks = $this->queryBlocks($startColombo, $endColombo, $vehicleId);
        $blocksById = $blocks->keyBy('id');

        foreach ($calendarInterval as $dateKey => $items) {
            $agenda[$dateKey] = [];
            foreach ($items as $item) {
                $block = $blocksById->get($item->id);
                $booking = $block?->source instanceof Booking ? $block->source : null;

                $origin = 'Fleet Base';
                $destination = null;
                $stops = [];

                if ($booking) {
                    if ($booking->airportPickupDetail) {
                        $origin = $booking->airportPickupDetail->airport.' Arrival';
                        $destination = $booking->airportPickupDetail->destination_text;
                    } elseif ($booking->airportTransferDetail) {
                        $origin = $booking->airportTransferDetail->origin_text;
                        $destination = $booking->airportTransferDetail->airport.' Departure';
                    } elseif ($booking->rideDetail) {
                        $origin = $booking->rideDetail->origin_text;
                        $destination = $booking->rideDetail->destination_text;
                        foreach ($booking->rideDetail->stops as $stop) {
                            $stops[] = $stop->location_text;
                        }
                    } elseif ($booking->rentDetail) {
                        $origin = $booking->rentDetail->pickup_location_text;
                        $destination = $booking->rentDetail->return_location_text;
                    }
                } elseif ($item->notes) {
                    $origin = $item->notes;
                }

                $agenda[$dateKey][] = new AgendaItemDTO(
                    id: $item->id,
                    time: $item->starts_at,
                    time_formatted: $item->starts_at->format('h:i A'),
                    booking_reference: $item->booking_reference,
                    booking_id: $item->booking_id,
                    service_type: $item->service_type ?? $item->kind->value,
                    service_label: $item->service_label,
                    origin_text: $origin,
                    destination_text: $destination,
                    intermediate_stops: $stops,
                    vehicle_name: $item->vehicle_name,
                    vehicle_plate: $item->vehicle_plate,
                    driver_name: $item->driver_name,
                    trip_status: $item->driver_trip_status,
                    trip_status_label: $item->driver_trip_status_label,
                    is_replacement_driver: $item->is_replacement,
                    has_conflict: $item->has_conflict,
                    notes: $item->notes,
                );
            }
        }

        return $agenda;
    }

    /**
     * Query schedule blocks with eager loading.
     *
     * @return Collection<int, VehicleScheduleBlock>
     */
    protected function queryBlocks(Carbon $start, Carbon $end, ?int $vehicleId = null): Collection
    {
        $startUtc = $start->copy()->utc();
        $endUtc = $end->copy()->utc();

        $query = VehicleScheduleBlock::query()
            ->with([
                'vehicle',
                'driver',
                'source' => fn (MorphTo $morphTo) => $morphTo->morphWith([
                    Booking::class => [
                        'vehicle',
                        'assignedDriver',
                        'currentDriverAssignment',
                        'airportPickupDetail',
                        'airportTransferDetail',
                        'rideDetail.stops',
                        'rentDetail',
                    ],
                    BookingHold::class => [
                        'vehicle',
                    ],
                ]),
            ])
            ->where('status', ScheduleBlockStatus::Active->value)
            ->where('effective_start', '<', $endUtc)
            ->where('effective_end', '>', $startUtc);

        if ($vehicleId !== null) {
            $query->where('vehicle_id', $vehicleId);
        }

        return $query->orderBy('effective_start')->get();
    }

    /**
     * Project interval and detect in-memory conflicts.
     *
     * @return array<string, array<int, CalendarItemDTO>>
     */
    protected function projectInterval(Carbon $start, Carbon $end, ?int $vehicleId = null): array
    {
        $blocks = $this->queryBlocks($start, $end, $vehicleId);

        // In-memory conflict detection per vehicle
        $conflictedBlockIds = $this->detectConflicts($blocks);

        // Group into daily buckets
        $grouped = [];
        $current = $start->copy()->startOfDay();

        while ($current->lte($end)) {
            $grouped[$current->format('Y-m-d')] = [];
            $current->addDay();
        }

        foreach ($blocks as $block) {
            $item = $this->mapBlockToDTO($block, isset($conflictedBlockIds[$block->id]));

            $cur = $block->effective_start->copy()->setTimezone('Asia/Colombo')->startOfDay();
            $last = $block->effective_end->copy()->setTimezone('Asia/Colombo');
            $endDay = ($last->hour === 0 && $last->minute === 0 && $last->second === 0 && $last->gt($cur))
                ? $last->copy()->subSecond()->startOfDay()
                : $last->copy()->startOfDay();

            while ($cur->lte($endDay)) {
                $dateKey = $cur->format('Y-m-d');
                if (isset($grouped[$dateKey])) {
                    $grouped[$dateKey][] = $item;
                }
                $cur->addDay();
            }
        }

        return $grouped;
    }

    /**
     * Detect in-memory interval overlaps per vehicle (ignoring grouped legs).
     *
     * @param  Collection<int, VehicleScheduleBlock>  $blocks
     * @return array<int, bool> Set of conflicted block IDs
     */
    protected function detectConflicts(Collection $blocks): array
    {
        $conflicts = [];
        $byVehicle = $blocks->where('blocks_availability', true)->groupBy('vehicle_id');

        foreach ($byVehicle as $vehicleBlocks) {
            $vehicleBlocks = $vehicleBlocks->values();
            $count = $vehicleBlocks->count();
            for ($i = 0; $i < $count; $i++) {
                $b1 = $vehicleBlocks[$i];
                for ($j = $i + 1; $j < $count; $j++) {
                    $b2 = $vehicleBlocks[$j];

                    // Ignore if sharing the same non-null group_id
                    if ($b1->group_id !== null && $b1->group_id === $b2->group_id) {
                        continue;
                    }

                    // Interval overlap test: start1 < end2 AND end1 > start2
                    if ($b1->effective_start->lt($b2->effective_end) && $b1->effective_end->gt($b2->effective_start)) {
                        $conflicts[$b1->id] = true;
                        $conflicts[$b2->id] = true;
                    }
                }
            }
        }

        return $conflicts;
    }

    /**
     * Map a VehicleScheduleBlock to a CalendarItemDTO with full WCAG 2.2 AA non-color encodings.
     */
    protected function mapBlockToDTO(VehicleScheduleBlock $block, bool $hasConflict): CalendarItemDTO
    {
        $booking = $block->source instanceof Booking ? $block->source : null;
        $vehicle = $block->vehicle;

        $kindIcon = match ($block->kind) {
            ScheduleBlockKind::CheckoutHold, ScheduleBlockKind::ProposalHold => 'heroicon-o-clock',
            ScheduleBlockKind::SubmittedRequest => 'heroicon-o-inbox-arrow-down',
            ScheduleBlockKind::ConfirmedBooking => 'heroicon-o-check-circle',
            ScheduleBlockKind::InProgress => 'heroicon-o-play',
            ScheduleBlockKind::Handover => 'heroicon-o-key',
            ScheduleBlockKind::Return => 'heroicon-o-arrow-path',
            ScheduleBlockKind::Buffer => 'heroicon-o-pause',
            ScheduleBlockKind::Maintenance => 'heroicon-o-wrench',
            ScheduleBlockKind::DriverUnavailability => 'heroicon-o-user-minus',
            ScheduleBlockKind::ManualBlock => 'heroicon-o-no-symbol',
        };

        $borderStyle = match ($block->kind) {
            ScheduleBlockKind::CheckoutHold, ScheduleBlockKind::ProposalHold, ScheduleBlockKind::Buffer => 'dotted',
            ScheduleBlockKind::SubmittedRequest, ScheduleBlockKind::Return => 'dashed',
            ScheduleBlockKind::ConfirmedBooking, ScheduleBlockKind::Handover => 'solid',
            ScheduleBlockKind::InProgress => 'double',
            ScheduleBlockKind::Maintenance, ScheduleBlockKind::DriverUnavailability => 'diagonal_hatch',
            ScheduleBlockKind::ManualBlock => 'cross_hatch',
        };

        $badgeClass = match ($block->kind) {
            ScheduleBlockKind::CheckoutHold, ScheduleBlockKind::ProposalHold => 'bg-amber-100 text-amber-900 border-amber-300 dark:bg-amber-950 dark:text-amber-200',
            ScheduleBlockKind::SubmittedRequest => 'bg-blue-100 text-blue-900 border-blue-300 dark:bg-blue-950 dark:text-blue-200',
            ScheduleBlockKind::ConfirmedBooking => 'bg-emerald-100 text-emerald-900 border-emerald-300 dark:bg-emerald-950 dark:text-emerald-200',
            ScheduleBlockKind::InProgress => 'bg-sky-100 text-sky-900 border-sky-300 dark:bg-sky-950 dark:text-sky-200',
            ScheduleBlockKind::Handover, ScheduleBlockKind::Return => 'bg-purple-100 text-purple-900 border-purple-300 dark:bg-purple-950 dark:text-purple-200',
            ScheduleBlockKind::Buffer => 'bg-slate-100 text-slate-800 border-slate-300 dark:bg-slate-800 dark:text-slate-200',
            ScheduleBlockKind::Maintenance, ScheduleBlockKind::DriverUnavailability => 'bg-rose-100 text-rose-900 border-rose-300 dark:bg-rose-950 dark:text-rose-200',
            ScheduleBlockKind::ManualBlock => 'bg-zinc-100 text-zinc-900 border-zinc-300 dark:bg-zinc-800 dark:text-zinc-200',
        };

        $assignment = $booking?->currentDriverAssignment;
        $driver = $block->driver ?? $booking?->assignedDriver;

        return new CalendarItemDTO(
            id: $block->id,
            vehicle_id: $block->vehicle_id,
            vehicle_name: $vehicle ? "{$vehicle->make} {$vehicle->model}" : "Vehicle #{$block->vehicle_id}",
            vehicle_plate: $vehicle ? $vehicle->license_plate : 'N/A',
            kind: $block->kind,
            kind_label: $block->kind->label(),
            kind_icon: $kindIcon,
            border_style: $borderStyle,
            badge_class: $badgeClass,
            status: $block->status,
            status_label: $block->status->label(),
            starts_at: $block->starts_at->copy()->setTimezone('Asia/Colombo'),
            ends_at: $block->ends_at->copy()->setTimezone('Asia/Colombo'),
            effective_start: $block->effective_start->copy()->setTimezone('Asia/Colombo'),
            effective_end: $block->effective_end->copy()->setTimezone('Asia/Colombo'),
            booking_id: $booking?->id,
            booking_reference: $booking?->reference,
            service_type: $booking?->service_type,
            service_label: $booking?->service_type?->label() ?? $block->kind->label(),
            driver_id: $driver?->id,
            driver_name: $driver?->public_name,
            is_replacement: (bool) ($assignment ? $assignment->is_replacement : false),
            driver_trip_status: $assignment?->status,
            driver_trip_status_label: $assignment?->status?->label(),
            notes: $block->notes,
            has_conflict: $hasConflict,
        );
    }
}
