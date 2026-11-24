<?php

declare(strict_types=1);

use App\Domain\Availability\Enums\HoldStatus;
use App\Domain\Availability\Enums\ScheduleBlockKind;
use App\Domain\Availability\Enums\ScheduleBlockStatus;
use App\Domain\Availability\Services\AvailabilityService;
use App\Domain\Booking\DTOs\AirportPickupCriteria;
use App\Domain\Booking\DTOs\SubmitAirportPickupData;
use App\Domain\Booking\Enums\BookingStatus;
use App\Domain\Booking\Enums\ContactType;
use App\Domain\Booking\Services\BookingSubmissionService;
use App\Domain\Booking\ServiceType;
use App\Domain\Catalog\Enums\DriverStatus;
use App\Domain\Catalog\Enums\VehicleStatus;
use App\Domain\Pricing\DTOs\EstimateRequestData;
use App\Domain\Pricing\Enums\PricingComponentType;
use App\Domain\Pricing\Services\EstimateService;
use App\Domain\Pricing\Services\PricingService;
use App\Models\AuditEvent;
use App\Models\Booking;
use App\Models\BookingHold;
use App\Models\Driver;
use App\Models\GuestAccessToken;
use App\Models\PricingRule;
use App\Models\PricingRuleComponent;
use App\Models\Vehicle;
use App\Models\VehicleDriverAssignment;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed();
    $this->submissionService = app(BookingSubmissionService::class);
    $this->availabilityService = app(AvailabilityService::class);
    $this->pricingService = app(PricingService::class);
    $this->estimateService = app(EstimateService::class);
});

test('guest can submit airport pickup request with valid hold, estimate, and separate contacts', function (): void {
    $vehicle = Vehicle::factory()->create([
        'eligible_for_pickup' => true,
        'status' => VehicleStatus::Active,
        'passenger_capacity' => 4,
        'luggage_capacity' => 4,
        'turnaround_buffer_minutes' => 60,
    ]);

    $driver = Driver::factory()->create(['status' => DriverStatus::Active]);
    VehicleDriverAssignment::create([
        'vehicle_id' => $vehicle->id,
        'driver_id' => $driver->id,
        'is_permanent' => true,
        'starts_at' => Carbon::now('Asia/Colombo')->subDays(10),
    ]);

    $rule = PricingRule::factory()->create([
        'service_type' => ServiceType::AirportPickup,
        'vehicle_id' => $vehicle->id,
        'effective_from' => Carbon::now('Asia/Colombo')->subDays(5),
    ]);

    PricingRuleComponent::factory()->create([
        'pricing_rule_id' => $rule->id,
        'component_type' => PricingComponentType::Base,
        'amount_minor_units' => 6500,
    ]);

    $arrivalTime = Carbon::now('Asia/Colombo')->addDays(3)->setHour(14)->setMinute(30);

    // 1. Calculate and snapshot estimate
    $estimateData = new EstimateRequestData(
        serviceType: ServiceType::AirportPickup,
        vehicleId: $vehicle->id,
        serviceDate: $arrivalTime,
        distanceKm: 40.0,
        durationHours: 1.5,
    );
    $estimateResult = $this->pricingService->calculateEstimate($estimateData);
    $estimate = $this->estimateService->snapshotEstimate($estimateResult, $estimateData);

    // 2. Create 15-minute checkout hold
    $endsAt = (clone $arrivalTime)->addMinutes(90);
    $holdResult = $this->availabilityService->createHold(
        vehicle: $vehicle,
        start: $arrivalTime,
        end: $endsAt,
        service: ServiceType::AirportPickup,
    );

    // 3. Prepare submission data (Booker in UK, Lead Traveler landing in Colombo)
    $criteria = new AirportPickupCriteria(
        airport: 'CMB',
        flightNumber: 'QR664',
        arrivalTime: $arrivalTime,
        destinationText: 'Jetwing Blue, Negombo',
        passengerCount: 2,
        luggageCount: 2,
        distanceKm: 40.0,
        durationHours: 1.5,
    );

    $submitData = new SubmitAirportPickupData(
        criteria: $criteria,
        vehicleId: $vehicle->id,
        holdPlainToken: $holdResult->plainToken,
        estimateReference: $estimate->reference,
        bookerFirstName: 'Eleanor',
        bookerLastName: 'Vance',
        bookerPhone: '+447700900123',
        bookerIsWhatsapp: true,
        bookerEmail: 'eleanor@example.co.uk',
        bookerMarketingConsent: true,
        leadTravelerSameAsBooker: false,
        leadTravelerFirstName: 'Arthur',
        leadTravelerLastName: 'Dent',
        leadTravelerPhone: '+447700900999',
        leadTravelerIsWhatsapp: true,
        leadTravelerEmail: null,
        termsAccepted: true,
        privacyAccepted: true,
        ipAddress: '192.168.1.1',
        userAgent: 'Safari/17.0',
    );

    $result = $this->submissionService->submitAirportPickup($submitData);

    // Assert booking created
    $booking = $result->booking;
    expect($booking->status)->toBe(BookingStatus::Submitted)
        ->and($booking->reference)->toStartWith('BKG-')
        ->and($booking->total_amount_minor_units)->toBe(6500)
        ->and($booking->vehicle_id)->toBe($vehicle->id)
        ->and($booking->schedule_block_id)->not->toBeNull();

    // Assert hold converted and block updated in place
    $hold = BookingHold::where('id', $holdResult->hold->id)->first();
    expect($hold->status)->toBe(HoldStatus::Converted);

    $block = $booking->scheduleBlock;
    expect($block->kind)->toBe(ScheduleBlockKind::SubmittedRequest)
        ->and($block->status)->toBe(ScheduleBlockStatus::Active)
        ->and($block->source_type)->toBe(Booking::class)
        ->and($block->source_id)->toBe($booking->id);

    // Assert separate Booker and Lead Traveler contacts
    expect($booking->contacts)->toHaveCount(2);

    $booker = $booking->contacts->where('type', ContactType::Booker)->first();
    expect($booker->first_name)->toBe('Eleanor')
        ->and($booker->phone)->toBe('+447700900123')
        ->and($booker->marketing_consent)->toBeTrue();

    $traveler = $booking->contacts->where('type', ContactType::LeadTraveler)->first();
    expect($traveler->first_name)->toBe('Arthur')
        ->and($traveler->phone)->toBe('+447700900999')
        ->and($traveler->marketing_consent)->toBeFalse(); // Unchecked marketing consent

    // Assert policy acceptances recorded
    expect($booking->policyAcceptances)->toHaveCount(2);

    // Assert 256-bit guest token (only hash stored in database)
    expect($result->plainGuestToken)->toHaveLength(64); // 32 bytes hex = 64 chars
    $guestToken = GuestAccessToken::where('booking_id', $booking->id)->first();
    expect($guestToken->token_hash)->toBe(GuestAccessToken::hashToken($result->plainGuestToken))
        ->and($guestToken->isValid())->toBeTrue();

    // Assert audit event recorded
    expect(AuditEvent::where('action', 'booking.submitted')->where('auditable_id', $booking->id)->exists())->toBeTrue();
});

test('submission rejected if terms or privacy policy not accepted', function (): void {
    $criteria = new AirportPickupCriteria(
        airport: 'CMB',
        flightNumber: 'UL504',
        arrivalTime: Carbon::now('Asia/Colombo')->addDays(3),
        destinationText: 'Colombo 03',
        passengerCount: 2,
        luggageCount: 2,
    );

    $submitData = new SubmitAirportPickupData(
        criteria: $criteria,
        vehicleId: 1,
        holdPlainToken: 'fake-token',
        estimateReference: 'EST-1234',
        bookerFirstName: 'John',
        bookerLastName: 'Doe',
        bookerPhone: '+447700900123',
        termsAccepted: false, // Rejected
        privacyAccepted: true,
    );

    expect(fn () => $this->submissionService->submitAirportPickup($submitData))
        ->toThrow(InvalidArgumentException::class, 'Terms of Service');
});
