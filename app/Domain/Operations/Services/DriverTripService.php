<?php

declare(strict_types=1);

namespace App\Domain\Operations\Services;

use App\Domain\Audit\Services\AuditService;
use App\Domain\Booking\Enums\BookingStatus;
use App\Domain\Booking\Enums\ContactType;
use App\Domain\Booking\Services\BookingLifecycleService;
use App\Domain\Booking\ServiceType;
use App\Domain\Messaging\Services\OutboxService;
use App\Domain\Operations\DTOs\DriverTripViewDTO;
use App\Domain\Operations\Enums\DriverTripStatus;
use App\Models\Booking;
use App\Models\DriverAccessToken;
use App\Models\DriverAssignment;
use App\Models\DriverTripUpdate;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class DriverTripService
{
    public function __construct(
        protected BookingLifecycleService $lifecycleService,
        protected AuditService $auditService,
        protected OutboxService $outboxService,
    ) {}

    /**
     * Validate an incoming raw token string against hashed storage.
     */
    public function validateToken(string $rawToken): ?DriverAccessToken
    {
        if (trim($rawToken) === '') {
            return null;
        }

        $hash = hash('sha256', trim($rawToken));

        /** @var DriverAccessToken|null $tokenRecord */
        $tokenRecord = DriverAccessToken::where('token_hash', $hash)
            ->whereNull('revoked_at')
            ->where('expires_at', '>', Carbon::now('Asia/Colombo'))
            ->first();

        return $tokenRecord;
    }

    /**
     * Build the strictly allowlisted view DTO for a validated token.
     */
    public function getTripViewData(DriverAccessToken $tokenRecord): DriverTripViewDTO
    {
        $tokenRecord->loadMissing([
            'driverAssignment.booking.vehicle',
            'driverAssignment.booking.contacts',
            'driverAssignment.booking.airportPickupDetail',
            'driverAssignment.booking.airportTransferDetail',
            'driverAssignment.booking.rideDetail.stops',
            'driverAssignment.booking.rentDetail',
            'driverAssignment.driver',
        ]);

        $assignment = $tokenRecord->driverAssignment;
        $booking = $assignment->booking;
        $vehicle = $booking->vehicle;

        $pickupTime = $booking->getServiceStartTime() ?? Carbon::now('Asia/Colombo');
        $pickupTimeColombo = $pickupTime->copy()->setTimezone('Asia/Colombo');

        // Derive service-specific operational parameters
        $pickupText = 'Not specified';
        $pickupNavUrl = null;
        $destinationText = null;
        $destinationNavUrl = null;
        $intermediateStops = [];
        $flightNumber = null;
        $flightArrivalTime = null;
        $nameBoardText = null;
        $waitingInstructions = null;
        $passengerCount = 1;
        $luggageCount = 1;

        if ($booking->airportPickupDetail) {
            $d = $booking->airportPickupDetail;
            $pickupText = $d->airport.' Arrival Hall';
            $pickupNavUrl = 'https://www.google.com/maps/search/?api=1&query='.urlencode($pickupText);
            $destinationText = $d->destination_text;
            $destinationNavUrl = 'https://www.google.com/maps/search/?api=1&query='.urlencode($d->destination_text);
            $flightNumber = $d->flight_number;
            $flightArrivalTime = $d->arrival_time?->copy()->setTimezone('Asia/Colombo');
            $nameBoardText = $d->name_board_text;
            $waitingInstructions = $d->waiting_instructions;
            $passengerCount = $d->passenger_count;
            $luggageCount = $d->luggage_count;
        } elseif ($booking->airportTransferDetail) {
            $d = $booking->airportTransferDetail;
            $pickupText = $d->origin_text;
            $pickupNavUrl = 'https://www.google.com/maps/search/?api=1&query='.urlencode($d->origin_text);
            $destinationText = $d->airport.' Departure Terminal';
            $destinationNavUrl = 'https://www.google.com/maps/search/?api=1&query='.urlencode($destinationText);
            $flightNumber = $d->flight_number;
            $passengerCount = $d->passenger_count;
            $luggageCount = $d->luggage_count;
        } elseif ($booking->rideDetail) {
            $d = $booking->rideDetail;
            $pickupText = $d->origin_text;
            $pickupNavUrl = 'https://www.google.com/maps/search/?api=1&query='.urlencode($d->origin_text);
            $destinationText = $d->destination_text;
            $destinationNavUrl = 'https://www.google.com/maps/search/?api=1&query='.urlencode($d->destination_text);
            $passengerCount = $d->passenger_count;
            $luggageCount = $d->luggage_count;

            foreach ($d->stops as $stop) {
                $intermediateStops[] = [
                    'location' => $stop->location_text,
                    'notes' => $stop->notes,
                ];
            }
        } elseif ($booking->rentDetail) {
            $d = $booking->rentDetail;
            $pickupText = $d->pickup_location_text;
            $destinationText = $d->return_location_text;
        }

        // Contact details with strict time-gating (<= 24 hours before pickup, not older than 12h, and not terminal)
        $leadContact = $booking->contacts->firstWhere('type', ContactType::LeadTraveler) ?? $booking->contacts->first();
        $leadFirstName = $leadContact ? $leadContact->first_name : 'Guest';

        $leadPhoneClean = null;
        $leadWhatsAppUrl = null;

        $nowColombo = Carbon::now('Asia/Colombo');
        $diffHours = $nowColombo->diffInHours($pickupTimeColombo, false);
        $isWithinOperationalWindow = $diffHours <= 24 && $diffHours >= -12;
        $isNotTerminal = ! $assignment->status->isTerminal();

        if ($leadContact && $isWithinOperationalWindow && $isNotTerminal) {
            $rawPhone = preg_replace('/[^0-9+]/', '', $leadContact->phone);
            $leadPhoneClean = $rawPhone;
            $whatsAppNumber = ltrim($rawPhone ?: '', '+');
            if ($whatsAppNumber !== '') {
                $leadWhatsAppUrl = "https://wa.me/{$whatsAppNumber}";
            }
        }

        return new DriverTripViewDTO(
            reference: $booking->reference,
            service_type: $booking->service_type,
            service_type_label: $booking->service_type->label(),
            service_type_badge: match ($booking->service_type) {
                ServiceType::AirportPickup => 'Airport Pickup',
                ServiceType::AirportTransfer => 'Airport Transfer',
                ServiceType::Ride => 'Chauffeured Ride',
                ServiceType::Rent => 'Vehicle Rental',
            },
            scheduled_pickup_time: $pickupTimeColombo,
            scheduled_pickup_time_formatted: $pickupTimeColombo->format('l, d M Y · h:i A (T)'),
            pickup_location_text: $pickupText,
            pickup_navigation_url: $pickupNavUrl,
            destination_text: $destinationText,
            destination_navigation_url: $destinationNavUrl,
            intermediate_stops: $intermediateStops,
            flight_number: $flightNumber,
            flight_arrival_time: $flightArrivalTime,
            flight_arrival_time_formatted: $flightArrivalTime?->format('d M · h:i A'),
            name_board_text: $nameBoardText,
            waiting_instructions: $waitingInstructions,
            passenger_count: $passengerCount,
            luggage_count: $luggageCount,
            lead_traveler_first_name: $leadFirstName,
            lead_traveler_phone_clean: $leadPhoneClean,
            lead_traveler_whatsapp_url: $leadWhatsAppUrl,
            current_status: $assignment->status,
            current_status_label: $assignment->status->label(),
            current_status_icon: $assignment->status->icon(),
            allowed_next_milestones: array_values(array_filter(
                $assignment->status->allowedNextStates(),
                fn ($s) => $s !== DriverTripStatus::Cancelled
            )),
            issue_notes: $assignment->issue_reported_notes,
            issue_reported_at: $assignment->issue_reported_at,
            vehicle_name: "{$vehicle->make} {$vehicle->model}",
            vehicle_license_plate: $vehicle->license_plate,
        );
    }

    /**
     * Advance trip milestone monotonically and idempotently.
     */
    public function updateTripStatus(
        DriverAccessToken $tokenRecord,
        DriverTripStatus $newStatus,
        ?string $notes = null,
        ?string $clientTimestamp = null,
    ): DriverAssignment {
        return DB::transaction(function () use ($tokenRecord, $newStatus, $notes, $clientTimestamp) {
            /** @var DriverAccessToken|null $activeToken */
            $activeToken = DriverAccessToken::where('id', $tokenRecord->id)
                ->lockForUpdate()
                ->first();

            if (! $activeToken || $activeToken->revoked_at !== null || $activeToken->expires_at->lte(Carbon::now('Asia/Colombo'))) {
                throw new InvalidArgumentException('Driver access token is invalid, expired, or has been revoked.');
            }

            /** @var DriverAssignment $assignment */
            $assignment = DriverAssignment::where('id', $activeToken->driver_assignment_id)
                ->firstOrFail();

            // 1. Lock parent aggregate root first (consistent hierarchical locking order)
            /** @var Booking $booking */
            $booking = Booking::where('id', $assignment->booking_id)
                ->lockForUpdate()
                ->firstOrFail();

            // 2. Lock child assignment
            $assignment = DriverAssignment::where('id', $assignment->id)
                ->lockForUpdate()
                ->firstOrFail();

            // Idempotency: if already at the requested milestone, return unchanged
            if ($assignment->status === $newStatus) {
                return $assignment;
            }

            if ($booking->status !== BookingStatus::Confirmed) {
                throw new InvalidArgumentException("Cannot update trip milestones for booking in status {$booking->status->value}.");
            }

            // Monotonicity check: target state must be allowed
            if (! in_array($newStatus, $assignment->status->allowedNextStates(), true)) {
                throw new InvalidArgumentException("Transition from {$assignment->status->value} to {$newStatus->value} is not permitted.");
            }

            $now = Carbon::now('Asia/Colombo');
            $updateData = ['status' => $newStatus];

            match ($newStatus) {
                DriverTripStatus::Acknowledged => $updateData['acknowledged_at'] = $now,
                DriverTripStatus::EnRoute => $updateData['en_route_at'] = $now,
                DriverTripStatus::Arrived => $updateData['arrived_at'] = $now,
                DriverTripStatus::Started => $updateData['started_at'] = $now,
                DriverTripStatus::Completed => $updateData['completed_at'] = $now,
                default => null,
            };

            $assignment->update($updateData);

            DriverTripUpdate::create([
                'driver_assignment_id' => $assignment->id,
                'status' => $newStatus->value,
                'notes' => $notes,
                'client_device_timestamp' => $clientTimestamp,
                'recorded_at' => $now,
            ]);

            // If completing the trip, trigger BookingLifecycleService::complete()
            if ($newStatus === DriverTripStatus::Completed) {
                $this->lifecycleService->complete($booking);
            }

            $this->auditService->record(
                action: 'driver.trip_status_updated',
                actor: null,
                summary: "Driver updated trip status to {$newStatus->label()}",
                metadata: [
                    'driver_id' => $assignment->driver_id,
                    'booking_id' => $assignment->booking_id,
                    'driver_assignment_id' => $assignment->id,
                    'new_status' => $newStatus->value,
                    'notes' => $notes,
                ],
                auditable: $assignment,
                actorType: 'driver'
            );

            $this->outboxService->record(
                eventType: 'driver.trip_updated',
                aggregate: $assignment,
                payload: [
                    'driver_assignment_id' => $assignment->id,
                    'booking_id' => $assignment->booking_id,
                    'driver_id' => $assignment->driver_id,
                    'status' => $newStatus->value,
                    'recorded_at' => $now->toIso8601String(),
                ],
                idempotencyKey: "evt_driver_assign_{$assignment->id}_status_{$newStatus->value}"
            );

            return $assignment;
        });
    }

    /**
     * Report an operational issue or delay without breaking the linear milestone progression.
     */
    public function reportIssue(
        DriverAccessToken $tokenRecord,
        string $notes,
        ?string $clientTimestamp = null,
    ): DriverAssignment {
        if (trim($notes) === '') {
            throw new InvalidArgumentException('Issue description cannot be empty.');
        }

        return DB::transaction(function () use ($tokenRecord, $notes, $clientTimestamp) {
            /** @var DriverAccessToken|null $activeToken */
            $activeToken = DriverAccessToken::where('id', $tokenRecord->id)
                ->lockForUpdate()
                ->first();

            if (! $activeToken || $activeToken->revoked_at !== null || $activeToken->expires_at->lte(Carbon::now('Asia/Colombo'))) {
                throw new InvalidArgumentException('Driver access token is invalid, expired, or has been revoked.');
            }

            /** @var DriverAssignment $assignment */
            $assignment = DriverAssignment::where('id', $activeToken->driver_assignment_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($assignment->status->isTerminal()) {
                throw new InvalidArgumentException("Cannot report issue on {$assignment->status->label()} trip.");
            }

            $now = Carbon::now('Asia/Colombo');

            $assignment->update([
                'issue_reported_notes' => trim($notes),
                'issue_reported_at' => $now,
            ]);

            DriverTripUpdate::create([
                'driver_assignment_id' => $assignment->id,
                'status' => 'issue_reported',
                'notes' => trim($notes),
                'client_device_timestamp' => $clientTimestamp,
                'recorded_at' => $now,
            ]);

            $this->auditService->record(
                action: 'driver.issue_reported',
                actor: null,
                summary: "Driver reported issue: {$notes}",
                metadata: [
                    'driver_id' => $assignment->driver_id,
                    'booking_id' => $assignment->booking_id,
                    'driver_assignment_id' => $assignment->id,
                    'notes' => trim($notes),
                ],
                auditable: $assignment,
                actorType: 'driver'
            );

            $uniqueEntropy = bin2hex(random_bytes(4));
            $this->outboxService->record(
                eventType: 'driver.issue_reported',
                aggregate: $assignment,
                payload: [
                    'driver_assignment_id' => $assignment->id,
                    'booking_id' => $assignment->booking_id,
                    'driver_id' => $assignment->driver_id,
                    'issue_notes' => trim($notes),
                    'reported_at' => $now->toIso8601String(),
                ],
                idempotencyKey: "evt_driver_assign_{$assignment->id}_issue_{$now->timestamp}_{$uniqueEntropy}"
            );

            return $assignment;
        });
    }
}
