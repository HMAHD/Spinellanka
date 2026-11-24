<?php

declare(strict_types=1);

namespace App\Domain\Booking\Services;

use App\Domain\Audit\Services\AuditService;
use App\Domain\Availability\Services\AvailabilityService;
use App\Domain\Booking\DTOs\BookingSubmissionResult;
use App\Domain\Booking\DTOs\SubmitAirportPickupData;
use App\Domain\Booking\Enums\BookingSource;
use App\Domain\Booking\Enums\BookingStatus;
use App\Domain\Booking\Enums\ContactType;
use App\Domain\Booking\ServiceType;
use App\Domain\Pricing\Services\EstimateService;
use App\Models\AirportPickupDetail;
use App\Models\Booking;
use App\Models\BookingContact;
use App\Models\BookingPolicyAcceptance;
use App\Models\GuestAccessToken;
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
