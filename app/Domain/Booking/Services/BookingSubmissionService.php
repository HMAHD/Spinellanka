<?php

declare(strict_types=1);

namespace App\Domain\Booking\Services;

use App\Domain\Audit\Services\AuditService;
use App\Domain\Availability\Services\AvailabilityService;
use App\Domain\Booking\DTOs\BookingSubmissionResult;
use App\Domain\Booking\DTOs\SubmitAirportPickupData;
use App\Domain\Booking\DTOs\SubmitAirportTransferData;
use App\Domain\Booking\DTOs\SubmitRentData;
use App\Domain\Booking\Enums\BookingSource;
use App\Domain\Booking\Enums\BookingStatus;
use App\Domain\Booking\Enums\ContactType;
use App\Domain\Booking\ServiceType;
use App\Domain\Pricing\Services\EstimateService;
use App\Models\AirportPickupDetail;
use App\Models\AirportTransferDetail;
use App\Models\Booking;
use App\Models\BookingContact;
use App\Models\BookingPolicyAcceptance;
use App\Models\GuestAccessToken;
use App\Models\RentDetail;
use App\Models\RentPolicy;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use InvalidArgumentException;

class BookingSubmissionService
{
    public function __construct(
        protected AvailabilityService $availabilityService,
        protected EstimateService $estimateService,
        protected AuditService $auditService,
    ) {}

    public function submitAirportPickup(SubmitAirportPickupData $data): BookingSubmissionResult
    {
        if (! $data->termsAccepted) {
            throw new InvalidArgumentException('You must accept the Terms of Service to proceed.');
        }

        if (! $data->privacyAccepted) {
            throw new InvalidArgumentException('You must accept the Privacy Policy to proceed.');
        }

        if (trim($data->bookerPhone) === '') {
            throw new InvalidArgumentException('A valid international mobile/WhatsApp phone number is required.');
        }

        return DB::transaction(function () use ($data) {
            // 1. Validate estimate snapshot
            $estimate = $this->estimateService->findByReference($data->estimateReference);
            if (! $estimate) {
                throw new InvalidArgumentException('Pricing estimate not found or invalid reference.');
            }

            if ($estimate->expires_at->isPast()) {
                throw new InvalidArgumentException('Pricing estimate snapshot has expired. Please recalculate.');
            }

            if ($estimate->vehicle_id !== null && $estimate->vehicle_id !== $data->vehicleId) {
                throw new InvalidArgumentException('Pricing estimate vehicle does not match selected vehicle.');
            }

            if ($estimate->service_type !== ServiceType::AirportPickup) {
                throw new InvalidArgumentException('Pricing estimate is not for Airport Pickup.');
            }

            // 2. Pending request expiration window
            $ttlHours = (int) config('booking.pending_request_ttl_hours', 24);
            $pendingExpiresAt = Carbon::now('Asia/Colombo')->addHours($ttlHours);

            // 3. Compute immutable request payload hash
            $payloadData = [
                'airport' => $data->criteria->airport,
                'flight_number' => $data->criteria->flightNumber,
                'arrival_time' => $data->criteria->arrivalTime->setTimezone('Asia/Colombo')->format('Y-m-d H:i:s'),
                'destination_text' => $data->criteria->destinationText,
                'passenger_count' => $data->criteria->passengerCount,
                'luggage_count' => $data->criteria->luggageCount,
                'vehicle_id' => $data->vehicleId,
                'total_amount_minor_units' => $estimate->total_amount_minor_units,
            ];
            $requestHash = hash('sha256', serialize($payloadData));

            // 4. Create Booking record in Submitted status
            $booking = Booking::create([
                'reference' => Booking::generateReference(),
                'service_type' => ServiceType::AirportPickup,
                'status' => BookingStatus::Submitted,
                'vehicle_id' => $data->vehicleId,
                'estimate_id' => $estimate->id,
                'schedule_block_id' => null, // populated below after atomic hold conversion
                'source' => BookingSource::Web,
                'currency' => $estimate->currency ?? 'USD',
                'total_amount_minor_units' => $estimate->total_amount_minor_units,
                'approximate_lkr_amount_minor_units' => $estimate->approximate_lkr_amount_minor_units,
                'request_hash' => $requestHash,
                'expires_at' => $pendingExpiresAt,
            ]);

            // 5. Convert checkout hold to submitted block atomically under vehicle lock
            $block = $this->availabilityService->convertHoldToSubmittedBlock(
                plainHoldToken: $data->holdPlainToken,
                expiresAt: $pendingExpiresAt,
                sourceType: Booking::class,
                sourceId: $booking->id,
                actor: null
            );

            $booking->update([
                'schedule_block_id' => $block->id,
            ]);

            // 6. Create Contacts: Booker & Lead Traveler
            BookingContact::create([
                'booking_id' => $booking->id,
                'type' => ContactType::Booker,
                'first_name' => trim($data->bookerFirstName),
                'last_name' => trim($data->bookerLastName),
                'phone' => trim($data->bookerPhone),
                'is_whatsapp' => $data->bookerIsWhatsapp,
                'email' => $data->bookerEmail ? trim($data->bookerEmail) : null,
                'marketing_consent' => $data->bookerMarketingConsent,
            ]);

            if ($data->leadTravelerSameAsBooker) {
                BookingContact::create([
                    'booking_id' => $booking->id,
                    'type' => ContactType::LeadTraveler,
                    'first_name' => trim($data->bookerFirstName),
                    'last_name' => trim($data->bookerLastName),
                    'phone' => trim($data->bookerPhone),
                    'is_whatsapp' => $data->bookerIsWhatsapp,
                    'email' => $data->bookerEmail ? trim($data->bookerEmail) : null,
                    'marketing_consent' => false,
                ]);
            } else {
                BookingContact::create([
                    'booking_id' => $booking->id,
                    'type' => ContactType::LeadTraveler,
                    'first_name' => trim((string) $data->leadTravelerFirstName),
                    'last_name' => trim((string) $data->leadTravelerLastName),
                    'phone' => trim((string) $data->leadTravelerPhone),
                    'is_whatsapp' => $data->leadTravelerIsWhatsapp,
                    'email' => $data->leadTravelerEmail ? trim($data->leadTravelerEmail) : null,
                    'marketing_consent' => false,
                ]);
            }

            // 7. Create Airport Pickup Details
            AirportPickupDetail::create([
                'booking_id' => $booking->id,
                'airport' => $data->criteria->airport,
                'flight_number' => $data->criteria->flightNumber,
                'arrival_time' => $data->criteria->arrivalTime,
                'destination_text' => $data->criteria->destinationText,
                'destination_place_id' => $data->criteria->placeId,
                'destination_lat' => $data->criteria->destinationLat,
                'destination_lng' => $data->criteria->destinationLng,
                'destination_notes' => $data->criteria->destinationNotes,
                'passenger_count' => $data->criteria->passengerCount,
                'luggage_count' => $data->criteria->luggageCount,
                'meet_and_greet' => $data->criteria->meetAndGreet,
                'name_board_text' => $data->criteria->nameBoardText,
                'waiting_instructions' => $data->criteria->waitingInstructions,
            ]);

            // 8. Record Policy Acceptances
            $this->recordPolicyAcceptance(
                booking: $booking,
                policyConfigKey: 'terms',
                ipAddress: $data->ipAddress,
                userAgent: $data->userAgent
            );

            $this->recordPolicyAcceptance(
                booking: $booking,
                policyConfigKey: 'privacy',
                ipAddress: $data->ipAddress,
                userAgent: $data->userAgent
            );

            // 9. Generate 256-bit CSPRNG Guest Access Token (stores hash only)
            $plainGuestToken = bin2hex(random_bytes(32));
            GuestAccessToken::create([
                'booking_id' => $booking->id,
                'token_hash' => GuestAccessToken::hashToken($plainGuestToken),
                'purpose' => 'guest_access',
                'expires_at' => Carbon::now('Asia/Colombo')->addDays(30),
            ]);

            // 10. Audit Record
            $this->auditService->record(
                action: 'booking.submitted',
                actor: null,
                summary: "Airport Pickup request submitted ({$booking->reference}) for vehicle ID {$data->vehicleId}",
                metadata: [
                    'booking_id' => $booking->id,
                    'reference' => $booking->reference,
                    'vehicle_id' => $data->vehicleId,
                    'flight_number' => $data->criteria->flightNumber,
                    'arrival_time' => $data->criteria->arrivalTime->setTimezone('Asia/Colombo')->format('Y-m-d H:i:s'),
                    'total_amount_minor_units' => $booking->total_amount_minor_units,
                    'expires_at' => $pendingExpiresAt->toIso8601String(),
                ],
                auditable: $booking,
                actorType: 'guest'
            );

            $privateUrl = route('booking.status', [
                'reference' => $booking->reference,
                'token' => $plainGuestToken,
            ]);

            return new BookingSubmissionResult(
                booking: $booking,
                plainGuestToken: $plainGuestToken,
                privateUrl: $privateUrl
            );
        });
    }

    public function submitAirportTransfer(SubmitAirportTransferData $data): BookingSubmissionResult
    {
        if (! $data->termsAccepted) {
            throw new InvalidArgumentException('You must accept the Terms of Service to proceed.');
        }

        if (! $data->privacyAccepted) {
            throw new InvalidArgumentException('You must accept the Privacy Policy to proceed.');
        }

        if (trim($data->bookerPhone) === '') {
            throw new InvalidArgumentException('A valid international mobile/WhatsApp phone number is required.');
        }

        if ($data->criteria->isUnsafeTime && ! $data->criteria->warningAccepted) {
            throw new InvalidArgumentException('You must acknowledge and accept the timing risk for chosen pickup times later than recommended.');
        }

        return DB::transaction(function () use ($data) {
            // 1. Validate estimate snapshot
            $estimate = $this->estimateService->findByReference($data->estimateReference);
            if (! $estimate) {
                throw new InvalidArgumentException('Pricing estimate not found or invalid reference.');
            }

            if ($estimate->expires_at->isPast()) {
                throw new InvalidArgumentException('Pricing estimate snapshot has expired. Please recalculate.');
            }

            if ($estimate->vehicle_id !== null && $estimate->vehicle_id !== $data->vehicleId) {
                throw new InvalidArgumentException('Pricing estimate vehicle does not match selected vehicle.');
            }

            if ($estimate->service_type !== ServiceType::AirportTransfer) {
                throw new InvalidArgumentException('Pricing estimate is not for Airport Transfer.');
            }

            // 2. Pending request expiration window
            $ttlHours = (int) config('booking.pending_request_ttl_hours', 24);
            $pendingExpiresAt = Carbon::now('Asia/Colombo')->addHours($ttlHours);

            // 3. Compute immutable request payload hash
            $payloadData = [
                'type' => 'airport_transfer',
                'airport' => $data->criteria->airport,
                'flight_number' => $data->criteria->flightNumber,
                'departure_time' => $data->criteria->departureTime->setTimezone('Asia/Colombo')->format('Y-m-d H:i:s'),
                'origin_text' => $data->criteria->originText,
                'chosen_pickup_time' => $data->criteria->chosenPickupTime->setTimezone('Asia/Colombo')->format('Y-m-d H:i:s'),
                'passenger_count' => $data->criteria->passengerCount,
                'luggage_count' => $data->criteria->luggageCount,
                'vehicle_id' => $data->vehicleId,
                'total_amount_minor_units' => $estimate->total_amount_minor_units,
            ];
            $requestHash = hash('sha256', serialize($payloadData));

            // 4. Create Booking record in Submitted status
            $booking = Booking::create([
                'reference' => Booking::generateReference(),
                'service_type' => ServiceType::AirportTransfer,
                'status' => BookingStatus::Submitted,
                'vehicle_id' => $data->vehicleId,
                'estimate_id' => $estimate->id,
                'schedule_block_id' => null,
                'source' => BookingSource::Web,
                'currency' => $estimate->currency ?? 'USD',
                'total_amount_minor_units' => $estimate->total_amount_minor_units,
                'approximate_lkr_amount_minor_units' => $estimate->approximate_lkr_amount_minor_units,
                'request_hash' => $requestHash,
                'expires_at' => $pendingExpiresAt,
            ]);

            // 5. Convert checkout hold to submitted block atomically under vehicle lock
            $block = $this->availabilityService->convertHoldToSubmittedBlock(
                plainHoldToken: $data->holdPlainToken,
                expiresAt: $pendingExpiresAt,
                sourceType: Booking::class,
                sourceId: $booking->id,
                actor: null
            );

            $booking->update([
                'schedule_block_id' => $block->id,
            ]);

            // 6. Create Contacts: Booker & Lead Traveler
            BookingContact::create([
                'booking_id' => $booking->id,
                'type' => ContactType::Booker,
                'first_name' => trim($data->bookerFirstName),
                'last_name' => trim($data->bookerLastName),
                'phone' => trim($data->bookerPhone),
                'is_whatsapp' => $data->bookerIsWhatsapp,
                'email' => $data->bookerEmail ? trim($data->bookerEmail) : null,
                'marketing_consent' => $data->bookerMarketingConsent,
            ]);

            if ($data->leadTravelerSameAsBooker) {
                BookingContact::create([
                    'booking_id' => $booking->id,
                    'type' => ContactType::LeadTraveler,
                    'first_name' => trim($data->bookerFirstName),
                    'last_name' => trim($data->bookerLastName),
                    'phone' => trim($data->bookerPhone),
                    'is_whatsapp' => $data->bookerIsWhatsapp,
                    'email' => $data->bookerEmail ? trim($data->bookerEmail) : null,
                    'marketing_consent' => false,
                ]);
            } else {
                BookingContact::create([
                    'booking_id' => $booking->id,
                    'type' => ContactType::LeadTraveler,
                    'first_name' => trim((string) $data->leadTravelerFirstName),
                    'last_name' => trim((string) $data->leadTravelerLastName),
                    'phone' => trim((string) $data->leadTravelerPhone),
                    'is_whatsapp' => $data->leadTravelerIsWhatsapp,
                    'email' => $data->leadTravelerEmail ? trim($data->leadTravelerEmail) : null,
                    'marketing_consent' => false,
                ]);
            }

            // 7. Create Airport Transfer Details
            AirportTransferDetail::create([
                'booking_id' => $booking->id,
                'airport' => $data->criteria->airport,
                'flight_number' => $data->criteria->flightNumber,
                'departure_time' => $data->criteria->departureTime,
                'origin_text' => $data->criteria->originText,
                'origin_place_id' => $data->criteria->placeId,
                'origin_lat' => $data->criteria->originLat,
                'origin_lng' => $data->criteria->originLng,
                'origin_notes' => $data->criteria->originNotes,
                'recommended_pickup_time' => $data->criteria->recommendedPickupTime,
                'chosen_pickup_time' => $data->criteria->chosenPickupTime,
                'is_unsafe_time' => $data->criteria->isUnsafeTime,
                'warning_accepted' => $data->criteria->warningAccepted,
                'passenger_count' => $data->criteria->passengerCount,
                'luggage_count' => $data->criteria->luggageCount,
                'special_instructions' => $data->criteria->specialInstructions,
            ]);

            // 8. Record Policy Acceptances
            $this->recordPolicyAcceptance(
                booking: $booking,
                policyConfigKey: 'terms',
                ipAddress: $data->ipAddress,
                userAgent: $data->userAgent
            );

            $this->recordPolicyAcceptance(
                booking: $booking,
                policyConfigKey: 'privacy',
                ipAddress: $data->ipAddress,
                userAgent: $data->userAgent
            );

            // 9. Generate 256-bit CSPRNG Guest Access Token (stores hash only)
            $plainGuestToken = bin2hex(random_bytes(32));
            GuestAccessToken::create([
                'booking_id' => $booking->id,
                'token_hash' => GuestAccessToken::hashToken($plainGuestToken),
                'purpose' => 'guest_access',
                'expires_at' => Carbon::now('Asia/Colombo')->addDays(30),
            ]);

            // 10. Audit Record
            $this->auditService->record(
                action: 'booking.submitted',
                actor: null,
                summary: "Airport Transfer request submitted ({$booking->reference}) for vehicle ID {$data->vehicleId}".($data->criteria->isUnsafeTime ? ' [Unsafe Pickup Time Flagged]' : ''),
                metadata: [
                    'booking_id' => $booking->id,
                    'reference' => $booking->reference,
                    'service_type' => ServiceType::AirportTransfer->value,
                    'vehicle_id' => $data->vehicleId,
                    'flight_number' => $data->criteria->flightNumber,
                    'departure_time' => $data->criteria->departureTime->setTimezone('Asia/Colombo')->format('Y-m-d H:i:s'),
                    'chosen_pickup_time' => $data->criteria->chosenPickupTime->setTimezone('Asia/Colombo')->format('Y-m-d H:i:s'),
                    'is_unsafe_time' => $data->criteria->isUnsafeTime,
                    'total_amount_minor_units' => $booking->total_amount_minor_units,
                    'expires_at' => $pendingExpiresAt->toIso8601String(),
                ],
                auditable: $booking,
                actorType: 'guest'
            );

            $privateUrl = route('booking.status', [
                'reference' => $booking->reference,
                'token' => $plainGuestToken,
            ]);

            return new BookingSubmissionResult(
                booking: $booking,
                plainGuestToken: $plainGuestToken,
                privateUrl: $privateUrl
            );
        });
    }

    public function submitRent(SubmitRentData $data): BookingSubmissionResult
    {
        if (! $data->termsAccepted) {
            throw new InvalidArgumentException('You must accept the Terms of Service to proceed.');
        }

        if (! $data->privacyAccepted) {
            throw new InvalidArgumentException('You must accept the Privacy Policy to proceed.');
        }

        if (! $data->originalDocumentsAcknowledged) {
            throw new InvalidArgumentException('You must acknowledge that physical original documents are required at vehicle handover.');
        }

        if (trim($data->bookerPhone) === '') {
            throw new InvalidArgumentException('A valid international mobile/WhatsApp phone number is required.');
        }

        return DB::transaction(function () use ($data) {
            // 1. Validate estimate snapshot
            $estimate = $this->estimateService->findByReference($data->estimateReference);
            if (! $estimate) {
                throw new InvalidArgumentException('Pricing estimate not found or invalid reference.');
            }

            if ($estimate->expires_at->isPast()) {
                throw new InvalidArgumentException('Pricing estimate snapshot has expired. Please recalculate.');
            }

            if ($estimate->vehicle_id !== null && $estimate->vehicle_id !== $data->vehicleId) {
                throw new InvalidArgumentException('Pricing estimate vehicle does not match selected vehicle.');
            }

            if ($estimate->service_type !== ServiceType::Rent) {
                throw new InvalidArgumentException('Pricing estimate is not for Rent service.');
            }

            // 2. Pending request expiration window
            $ttlHours = (int) config('booking.pending_request_ttl_hours', 24);
            $pendingExpiresAt = Carbon::now('Asia/Colombo')->addHours($ttlHours);

            // 3. Compute immutable request payload hash
            $payloadData = [
                'type' => 'rent',
                'pickup_datetime' => $data->criteria->pickupDateTime->setTimezone('Asia/Colombo')->format('Y-m-d H:i:s'),
                'return_datetime' => $data->criteria->returnDateTime->setTimezone('Asia/Colombo')->format('Y-m-d H:i:s'),
                'duration_days' => $data->criteria->durationDays,
                'extra_hours' => $data->criteria->extraHours,
                'pickup_location_text' => $data->criteria->pickupLocationText,
                'return_location_text' => $data->criteria->returnLocationText,
                'driver_age' => $data->criteria->driverAge,
                'driver_licence_country' => $data->criteria->driverLicenceCountry,
                'driver_licence_years' => $data->criteria->driverLicenceYears,
                'vehicle_id' => $data->vehicleId,
                'total_amount_minor_units' => $estimate->total_amount_minor_units,
            ];
            $requestHash = hash('sha256', serialize($payloadData));

            // 4. Create Booking record in Submitted status
            $booking = Booking::create([
                'reference' => Booking::generateReference(),
                'service_type' => ServiceType::Rent,
                'status' => BookingStatus::Submitted,
                'vehicle_id' => $data->vehicleId,
                'estimate_id' => $estimate->id,
                'schedule_block_id' => null,
                'source' => BookingSource::Web,
                'currency' => $estimate->currency ?? 'USD',
                'total_amount_minor_units' => $estimate->total_amount_minor_units,
                'approximate_lkr_amount_minor_units' => $estimate->approximate_lkr_amount_minor_units,
                'request_hash' => $requestHash,
                'expires_at' => $pendingExpiresAt,
            ]);

            // 5. Convert checkout hold to submitted block atomically under vehicle lock
            $block = $this->availabilityService->convertHoldToSubmittedBlock(
                plainHoldToken: $data->holdPlainToken,
                expiresAt: $pendingExpiresAt,
                sourceType: Booking::class,
                sourceId: $booking->id,
                actor: null
            );

            $booking->update([
                'schedule_block_id' => $block->id,
            ]);

            // 6. Create Contacts: Booker & Lead Driver
            BookingContact::create([
                'booking_id' => $booking->id,
                'type' => ContactType::Booker,
                'first_name' => trim($data->bookerFirstName),
                'last_name' => trim($data->bookerLastName),
                'phone' => trim($data->bookerPhone),
                'is_whatsapp' => $data->bookerIsWhatsapp,
                'email' => $data->bookerEmail ? trim($data->bookerEmail) : null,
                'marketing_consent' => $data->bookerMarketingConsent,
            ]);

            if ($data->leadDriverSameAsBooker) {
                BookingContact::create([
                    'booking_id' => $booking->id,
                    'type' => ContactType::LeadTraveler,
                    'first_name' => trim($data->bookerFirstName),
                    'last_name' => trim($data->bookerLastName),
                    'phone' => trim($data->bookerPhone),
                    'is_whatsapp' => $data->bookerIsWhatsapp,
                    'email' => $data->bookerEmail ? trim($data->bookerEmail) : null,
                    'marketing_consent' => false,
                ]);
            } else {
                BookingContact::create([
                    'booking_id' => $booking->id,
                    'type' => ContactType::LeadTraveler,
                    'first_name' => trim((string) $data->leadDriverFirstName),
                    'last_name' => trim((string) $data->leadDriverLastName),
                    'phone' => trim((string) $data->leadDriverPhone),
                    'is_whatsapp' => $data->leadDriverIsWhatsapp,
                    'email' => $data->leadDriverEmail ? trim($data->leadDriverEmail) : null,
                    'marketing_consent' => false,
                ]);
            }

            // 7. Create Rent Details
            $policy = RentPolicy::current();
            $includedDistanceKm = $data->criteria->durationDays * $policy->daily_mileage_km;

            RentDetail::create([
                'booking_id' => $booking->id,
                'pickup_datetime' => $data->criteria->pickupDateTime,
                'return_datetime' => $data->criteria->returnDateTime,
                'duration_days' => $data->criteria->durationDays,
                'extra_hours' => $data->criteria->extraHours,
                'pickup_location_type' => $data->criteria->pickupLocationType,
                'pickup_location_text' => $data->criteria->pickupLocationText,
                'pickup_place_id' => $data->criteria->pickupPlaceId,
                'pickup_lat' => $data->criteria->pickupLat,
                'pickup_lng' => $data->criteria->pickupLng,
                'return_location_type' => $data->criteria->returnLocationType,
                'return_location_text' => $data->criteria->returnLocationText,
                'return_place_id' => $data->criteria->returnPlaceId,
                'return_lat' => $data->criteria->returnLat,
                'return_lng' => $data->criteria->returnLng,
                'driver_age' => $data->criteria->driverAge,
                'driver_licence_country' => $data->criteria->driverLicenceCountry,
                'driver_licence_years' => $data->criteria->driverLicenceYears,
                'has_idp' => $data->criteria->hasIdp,
                'has_additional_driver' => $data->criteria->hasAdditionalDriver,
                'additional_driver_name' => $data->criteria->additionalDriverName,
                'additional_driver_age' => $data->criteria->additionalDriverAge,
                'additional_driver_licence_country' => $data->criteria->additionalDriverLicenceCountry,
                'additional_driver_licence_years' => $data->criteria->additionalDriverLicenceYears,
                'additional_driver_has_idp' => $data->criteria->additionalDriverHasIdp,
                'expected_distance_km' => $data->criteria->expectedDistanceKm,
                'included_distance_km' => $includedDistanceKm,
                'security_deposit_minor_units' => $policy->security_deposit_minor_units,
                'insurance_excess_minor_units' => $policy->insurance_excess_minor_units,
                'original_documents_acknowledged' => true,
                'special_instructions' => $data->criteria->specialInstructions,
            ]);

            // 8. Record Policy Acceptances
            $this->recordPolicyAcceptance(
                booking: $booking,
                policyConfigKey: 'terms',
                ipAddress: $data->ipAddress,
                userAgent: $data->userAgent
            );

            $this->recordPolicyAcceptance(
                booking: $booking,
                policyConfigKey: 'privacy',
                ipAddress: $data->ipAddress,
                userAgent: $data->userAgent
            );

            // 9. Generate 256-bit CSPRNG Guest Access Token
            $plainGuestToken = bin2hex(random_bytes(32));
            GuestAccessToken::create([
                'booking_id' => $booking->id,
                'token_hash' => GuestAccessToken::hashToken($plainGuestToken),
                'purpose' => 'guest_access',
                'expires_at' => Carbon::now('Asia/Colombo')->addDays(30),
            ]);

            // 10. Audit Record
            $this->auditService->record(
                action: 'booking.submitted',
                actor: null,
                summary: "Rent (Self-Drive) request submitted ({$booking->reference}) for vehicle ID {$data->vehicleId} for {$data->criteria->durationDays} days",
                metadata: [
                    'booking_id' => $booking->id,
                    'reference' => $booking->reference,
                    'service_type' => ServiceType::Rent->value,
                    'vehicle_id' => $data->vehicleId,
                    'duration_days' => $data->criteria->durationDays,
                    'extra_hours' => $data->criteria->extraHours,
                    'pickup_datetime' => $data->criteria->pickupDateTime->setTimezone('Asia/Colombo')->format('Y-m-d H:i:s'),
                    'return_datetime' => $data->criteria->returnDateTime->setTimezone('Asia/Colombo')->format('Y-m-d H:i:s'),
                    'total_amount_minor_units' => $booking->total_amount_minor_units,
                    'expires_at' => $pendingExpiresAt->toIso8601String(),
                ],
                auditable: $booking,
                actorType: 'guest'
            );

            $privateUrl = route('booking.status', [
                'reference' => $booking->reference,
                'token' => $plainGuestToken,
            ]);

            return new BookingSubmissionResult(
                booking: $booking,
                plainGuestToken: $plainGuestToken,
                privateUrl: $privateUrl
            );
        });
    }

    protected function recordPolicyAcceptance(
        Booking $booking,
        string $policyConfigKey,
        ?string $ipAddress,
        ?string $userAgent
    ): void {
        $policyConfig = config("booking.policies.{$policyConfigKey}");
        $policyType = $policyConfig['type'] ?? $policyConfigKey;
        $version = (string) ($policyConfig['version'] ?? '2026-11-23');

        $viewName = $policyConfig['view'] ?? null;
        $textHash = hash('sha256', $version);
        if ($viewName && View::exists($viewName)) {
            $textHash = hash('sha256', View::make($viewName)->render());
        }

        BookingPolicyAcceptance::create([
            'booking_id' => $booking->id,
            'policy_type' => $policyType,
            'version' => $version,
            'text_hash' => $textHash,
            'accepted_at' => Carbon::now('Asia/Colombo'),
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
        ]);
    }
}
