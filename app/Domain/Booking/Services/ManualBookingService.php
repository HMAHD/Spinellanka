<?php

declare(strict_types=1);

namespace App\Domain\Booking\Services;

use App\Domain\Audit\Services\AuditService;
use App\Domain\Availability\Enums\ScheduleBlockKind;
use App\Domain\Availability\Services\AvailabilityService;
use App\Domain\Booking\DTOs\AirportPickupCriteria;
use App\Domain\Booking\DTOs\AirportTransferCriteria;
use App\Domain\Booking\Enums\BookingSource;
use App\Domain\Booking\Enums\BookingStatus;
use App\Domain\Booking\Enums\ConfirmationChannel;
use App\Domain\Booking\Enums\ContactType;
use App\Domain\Booking\ServiceType;
use App\Domain\Identity\Permission;
use App\Domain\Pricing\Services\EstimateService;
use App\Models\AirportPickupDetail;
use App\Models\AirportTransferDetail;
use App\Models\Booking;
use App\Models\BookingContact;
use App\Models\GuestAccessToken;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ManualBookingService
{
    public function __construct(
        protected AvailabilityService $availabilityService,
        protected EstimateService $estimateService,
        protected AuditService $auditService,
    ) {}

    /**
     * @param  array{
     *     source: BookingSource,
     *     criteria: AirportPickupCriteria,
     *     vehicle_id: int,
     *     estimate_reference: ?string,
     *     booker_first_name: string,
     *     booker_last_name: string,
     *     booker_phone: string,
     *     booker_is_whatsapp?: bool,
     *     booker_email?: ?string,
     *     confirmation_channel: ConfirmationChannel,
     *     confirmation_notes: string,
     *     override_reason?: ?string,
     * }  $data
     */
    public function createManualAirportPickup(array $data, User $staff): Booking
    {
        if (! $staff->can(Permission::BookingConfirm->value) && ! $staff->hasPermissionTo(Permission::BookingConfirm->value)) {
            throw new InvalidArgumentException('Staff member does not have booking.confirm permission.');
        }

        return DB::transaction(function () use ($data, $staff) {
            $vehicle = Vehicle::where('id', $data['vehicle_id'])->lockForUpdate()->firstOrFail();

            /** @var AirportPickupCriteria $criteria */
            $criteria = $data['criteria'];

            $tripDurationMinutes = $criteria->durationHours !== null
                ? (int) round($criteria->durationHours * 60)
                : (int) config('booking.airport_pickup.default_trip_duration_minutes', 180);

            $startsAt = (clone $criteria->arrivalTime)->setTimezone('Asia/Colombo');
            $endsAt = (clone $startsAt)->addMinutes($tripDurationMinutes);

            // Create schedule block directly as ConfirmedBooking
            $block = $this->availabilityService->createBlock(
                vehicle: $vehicle,
                kind: ScheduleBlockKind::ConfirmedBooking,
                start: $startsAt,
                end: $endsAt,
                bufferBefore: 0,
                bufferAfter: $vehicle->turnaround_buffer_minutes,
                options: [
                    'service' => ServiceType::AirportPickup,
                    'override_reason' => $data['override_reason'] ?? null,
                    'notes' => "Manual booking created by {$staff->name}",
                ],
                actor: $staff,
            );

            $estimate = null;
            if (! empty($data['estimate_reference'])) {
                $estimate = $this->estimateService->findByReference($data['estimate_reference']);
            }

            $payloadData = [
                'airport' => $criteria->airport,
                'flight_number' => $criteria->flightNumber,
                'arrival_time' => $criteria->arrivalTime->toIso8601String(),
                'destination_text' => $criteria->destinationText,
                'passenger_count' => $criteria->passengerCount,
                'luggage_count' => $criteria->luggageCount,
                'vehicle_id' => $vehicle->id,
                'total_amount_minor_units' => $estimate?->total_amount_minor_units,
            ];
            $requestHash = hash('sha256', serialize($payloadData));

            $now = Carbon::now('Asia/Colombo');
            $booking = Booking::create([
                'reference' => Booking::generateReference(),
                'service_type' => ServiceType::AirportPickup,
                'status' => BookingStatus::Confirmed,
                'vehicle_id' => $vehicle->id,
                'estimate_id' => $estimate?->id,
                'schedule_block_id' => $block->id,
                'source' => $data['source'],
                'assigned_staff_id' => $staff->id,
                'currency' => $estimate ? $estimate->currency : 'USD',
                'total_amount_minor_units' => $estimate?->total_amount_minor_units,
                'approximate_lkr_amount_minor_units' => $estimate?->approximate_lkr_amount_minor_units,
                'request_hash' => $requestHash,
                'confirmation_channel' => $data['confirmation_channel'],
                'confirmation_notes' => trim($data['confirmation_notes']),
                'confirmed_at' => $now,
            ]);

            // Update schedule block morph source
            $block->update([
                'source_type' => Booking::class,
                'source_id' => $booking->id,
            ]);

            // Create Contact
            BookingContact::create([
                'booking_id' => $booking->id,
                'type' => ContactType::Booker,
                'first_name' => trim($data['booker_first_name']),
                'last_name' => trim($data['booker_last_name']),
                'phone' => trim($data['booker_phone']),
                'is_whatsapp' => $data['booker_is_whatsapp'] ?? true,
                'email' => isset($data['booker_email']) && $data['booker_email'] ? trim($data['booker_email']) : null,
                'marketing_consent' => false,
            ]);

            BookingContact::create([
                'booking_id' => $booking->id,
                'type' => ContactType::LeadTraveler,
                'first_name' => trim($data['booker_first_name']),
                'last_name' => trim($data['booker_last_name']),
                'phone' => trim($data['booker_phone']),
                'is_whatsapp' => $data['booker_is_whatsapp'] ?? true,
                'email' => isset($data['booker_email']) && $data['booker_email'] ? trim($data['booker_email']) : null,
                'marketing_consent' => false,
            ]);

            // Create Airport Pickup Details
            AirportPickupDetail::create([
                'booking_id' => $booking->id,
                'airport' => $criteria->airport,
                'flight_number' => $criteria->flightNumber,
                'arrival_time' => $criteria->arrivalTime,
                'destination_text' => $criteria->destinationText,
                'destination_place_id' => $criteria->placeId,
                'destination_lat' => $criteria->destinationLat,
                'destination_lng' => $criteria->destinationLng,
                'destination_notes' => $criteria->destinationNotes,
                'passenger_count' => $criteria->passengerCount,
                'luggage_count' => $criteria->luggageCount,
                'meet_and_greet' => $criteria->meetAndGreet,
                'name_board_text' => $criteria->nameBoardText,
                'waiting_instructions' => $criteria->waitingInstructions,
            ]);

            // Mint guest access token for reservation tracking
            $plainToken = GuestAccessToken::generateToken();
            GuestAccessToken::create([
                'booking_id' => $booking->id,
                'token_hash' => GuestAccessToken::hashToken($plainToken),
                'expires_at' => Carbon::now('Asia/Colombo')->addDays(30),
            ]);

            $this->auditService->record(
                action: 'booking.manual_created',
                actor: $staff,
                summary: "Manually booked {$booking->reference} ({$data['source']->label()}) by {$staff->name}",
                metadata: [
                    'booking_id' => $booking->id,
                    'reference' => $booking->reference,
                    'source' => $data['source']->value,
                    'staff_id' => $staff->id,
                    'channel' => $data['confirmation_channel']->value,
                ],
                auditable: $booking,
                actorType: 'staff'
            );

            return $booking;
        });
    }

    /**
     * @param  array{
     *     source: BookingSource,
     *     criteria: AirportTransferCriteria,
     *     vehicle_id: int,
     *     estimate_reference: string|null,
     *     booker_first_name: string,
     *     booker_last_name: string,
     *     booker_phone: string,
     *     booker_is_whatsapp?: bool,
     *     booker_email?: string|null,
     *     confirmation_channel: ConfirmationChannel,
     *     confirmation_notes: string,
     *     override_reason?: string|null
     * }  $data
     */
    public function createManualAirportTransfer(array $data, User $staff): Booking
    {
        if (! $staff->can(Permission::BookingConfirm->value) && ! $staff->hasPermissionTo(Permission::BookingConfirm->value)) {
            throw new InvalidArgumentException('Staff member does not have booking.confirm permission.');
        }

        return DB::transaction(function () use ($data, $staff) {
            $vehicle = Vehicle::where('id', $data['vehicle_id'])->lockForUpdate()->firstOrFail();

            /** @var AirportTransferCriteria $criteria */
            $criteria = $data['criteria'];

            $tripDurationMinutes = $criteria->durationHours !== null
                ? (int) round($criteria->durationHours * 60)
                : (int) config('booking.airport_transfer.default_trip_duration_minutes', 180);

            $startsAt = (clone $criteria->chosenPickupTime)->setTimezone('Asia/Colombo');
            $endsAt = (clone $startsAt)->addMinutes($tripDurationMinutes);

            // Create schedule block directly as ConfirmedBooking
            $block = $this->availabilityService->createBlock(
                vehicle: $vehicle,
                kind: ScheduleBlockKind::ConfirmedBooking,
                start: $startsAt,
                end: $endsAt,
                bufferBefore: 0,
                bufferAfter: $vehicle->turnaround_buffer_minutes,
                options: [
                    'service' => ServiceType::AirportTransfer,
                    'override_reason' => $data['override_reason'] ?? null,
                    'notes' => "Manual airport transfer booking created by {$staff->name}",
                ],
                actor: $staff,
            );

            $estimate = null;
            if (! empty($data['estimate_reference'])) {
                $estimate = $this->estimateService->findByReference($data['estimate_reference']);
            }

            $payloadData = [
                'type' => 'airport_transfer',
                'airport' => $criteria->airport,
                'flight_number' => $criteria->flightNumber,
                'departure_time' => $criteria->departureTime->toIso8601String(),
                'origin_text' => $criteria->originText,
                'chosen_pickup_time' => $criteria->chosenPickupTime->toIso8601String(),
                'passenger_count' => $criteria->passengerCount,
                'luggage_count' => $criteria->luggageCount,
                'vehicle_id' => $vehicle->id,
                'total_amount_minor_units' => $estimate?->total_amount_minor_units,
            ];
            $requestHash = hash('sha256', serialize($payloadData));

            $now = Carbon::now('Asia/Colombo');
            $booking = Booking::create([
                'reference' => Booking::generateReference(),
                'service_type' => ServiceType::AirportTransfer,
                'status' => BookingStatus::Confirmed,
                'vehicle_id' => $vehicle->id,
                'estimate_id' => $estimate?->id,
                'schedule_block_id' => $block->id,
                'source' => $data['source'],
                'assigned_staff_id' => $staff->id,
                'currency' => $estimate ? $estimate->currency : 'USD',
                'total_amount_minor_units' => $estimate?->total_amount_minor_units,
                'approximate_lkr_amount_minor_units' => $estimate?->approximate_lkr_amount_minor_units,
                'request_hash' => $requestHash,
                'confirmation_channel' => $data['confirmation_channel'],
                'confirmation_notes' => trim($data['confirmation_notes']),
                'confirmed_at' => $now,
            ]);

            $block->update([
                'source_type' => Booking::class,
                'source_id' => $booking->id,
            ]);

            BookingContact::create([
                'booking_id' => $booking->id,
                'type' => ContactType::Booker,
                'first_name' => trim($data['booker_first_name']),
                'last_name' => trim($data['booker_last_name']),
                'phone' => trim($data['booker_phone']),
                'is_whatsapp' => $data['booker_is_whatsapp'] ?? true,
                'email' => isset($data['booker_email']) && $data['booker_email'] ? trim($data['booker_email']) : null,
                'marketing_consent' => false,
            ]);

            BookingContact::create([
                'booking_id' => $booking->id,
                'type' => ContactType::LeadTraveler,
                'first_name' => trim($data['booker_first_name']),
                'last_name' => trim($data['booker_last_name']),
                'phone' => trim($data['booker_phone']),
                'is_whatsapp' => $data['booker_is_whatsapp'] ?? true,
                'email' => isset($data['booker_email']) && $data['booker_email'] ? trim($data['booker_email']) : null,
                'marketing_consent' => false,
            ]);

            AirportTransferDetail::create([
                'booking_id' => $booking->id,
                'airport' => $criteria->airport,
                'flight_number' => $criteria->flightNumber,
                'departure_time' => $criteria->departureTime,
                'origin_text' => $criteria->originText,
                'origin_place_id' => $criteria->placeId,
                'origin_lat' => $criteria->originLat,
                'origin_lng' => $criteria->originLng,
                'origin_notes' => $criteria->originNotes,
                'recommended_pickup_time' => $criteria->recommendedPickupTime,
                'chosen_pickup_time' => $criteria->chosenPickupTime,
                'is_unsafe_time' => $criteria->isUnsafeTime,
                'warning_accepted' => $criteria->warningAccepted,
                'passenger_count' => $criteria->passengerCount,
                'luggage_count' => $criteria->luggageCount,
                'special_instructions' => $criteria->specialInstructions,
            ]);

            $plainToken = GuestAccessToken::generateToken();
            GuestAccessToken::create([
                'booking_id' => $booking->id,
                'token_hash' => GuestAccessToken::hashToken($plainToken),
                'expires_at' => Carbon::now('Asia/Colombo')->addDays(30),
            ]);

            $this->auditService->record(
                action: 'booking.manual_created',
                actor: $staff,
                summary: "Manually booked {$booking->reference} ({$data['source']->label()}) Airport Transfer by {$staff->name}",
                metadata: [
                    'booking_id' => $booking->id,
                    'reference' => $booking->reference,
                    'service_type' => ServiceType::AirportTransfer->value,
                    'source' => $data['source']->value,
                    'staff_id' => $staff->id,
                    'channel' => $data['confirmation_channel']->value,
                ],
                auditable: $booking,
                actorType: 'staff'
            );

            return $booking;
        });
    }
}
