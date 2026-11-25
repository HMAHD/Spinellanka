<?php

declare(strict_types=1);

namespace Tests\Feature\Messaging;

use App\Domain\Availability\Services\AvailabilityService;
use App\Domain\Booking\DTOs\AirportPickupCriteria;
use App\Domain\Booking\DTOs\SubmitAirportPickupData;
use App\Domain\Booking\Enums\BookingSource;
use App\Domain\Booking\Enums\BookingStatus;
use App\Domain\Booking\Enums\ConfirmationChannel;
use App\Domain\Booking\Services\BookingLifecycleService;
use App\Domain\Booking\Services\BookingSubmissionService;
use App\Domain\Booking\Services\ManualBookingService;
use App\Domain\Booking\ServiceType;
use App\Domain\Catalog\Enums\DriverStatus;
use App\Domain\Catalog\Enums\VehicleStatus;
use App\Domain\Identity\Permission;
use App\Domain\Identity\Role;
use App\Domain\Messaging\Enums\OutboxEventStatus;
use App\Domain\Messaging\Services\OutboxService;
use App\Domain\Pricing\DTOs\EstimateRequestData;
use App\Domain\Pricing\Enums\PricingComponentType;
use App\Domain\Pricing\Services\EstimateService;
use App\Domain\Pricing\Services\PricingService;
use App\Jobs\ProcessOutboxEventJob;
use App\Models\Booking;
use App\Models\Driver;
use App\Models\OutboxEvent;
use App\Models\PricingRule;
use App\Models\PricingRuleComponent;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleDriverAssignment;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed();
    $this->submissionService = app(BookingSubmissionService::class);
    $this->lifecycleService = app(BookingLifecycleService::class);
    $this->manualBookingService = app(ManualBookingService::class);
    $this->availabilityService = app(AvailabilityService::class);
    $this->pricingService = app(PricingService::class);
    $this->estimateService = app(EstimateService::class);

    $this->staff = User::create([
        'name' => 'Support Staff',
        'email' => 'staff_outbox@spinellanka.test',
        'password' => Hash::make('Password123456!'),
        'is_active' => true,
        'token_version' => 1,
    ]);
    $this->staff->assignRole(Role::Staff->value);
    $this->staff->givePermissionTo(Permission::BookingConfirm->value);
    $this->staff->givePermissionTo(Permission::BookingReview->value);
    $this->staff->givePermissionTo(Permission::BookingCancel->value);
});

test('submitting a booking records an outbox event in the same transaction', function (): void {
    Queue::fake([ProcessOutboxEventJob::class]);

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
        'amount_minor_units' => 7000,
    ]);

    $arrivalTime = Carbon::now('Asia/Colombo')->addDays(3)->setHour(10)->setMinute(0);

    $estimateData = new EstimateRequestData(
        serviceType: ServiceType::AirportPickup,
        vehicleId: $vehicle->id,
        serviceDate: $arrivalTime,
        distanceKm: 35.0,
        durationHours: 1.0,
    );
    $estimateResult = $this->pricingService->calculateEstimate($estimateData);
    $estimate = $this->estimateService->snapshotEstimate($estimateResult, $estimateData);

    $endsAt = (clone $arrivalTime)->addMinutes(60);
    $holdResult = $this->availabilityService->createHold(
        vehicle: $vehicle,
        start: $arrivalTime,
        end: $endsAt,
        service: ServiceType::AirportPickup,
    );

    $criteria = new AirportPickupCriteria(
        airport: 'CMB',
        flightNumber: 'UL101',
        arrivalTime: $arrivalTime,
        destinationText: 'Galle Face Hotel, Colombo',
        passengerCount: 2,
        luggageCount: 2,
        distanceKm: 35.0,
        durationHours: 1.0,
    );

    $submitData = new SubmitAirportPickupData(
        criteria: $criteria,
        vehicleId: $vehicle->id,
        holdPlainToken: $holdResult->plainToken,
        estimateReference: $estimate->reference,
        bookerFirstName: 'Nalinda',
        bookerLastName: 'Perera',
        bookerPhone: '+94771234567',
        bookerIsWhatsapp: true,
        bookerEmail: 'nalinda@example.com',
        bookerMarketingConsent: false,
        leadTravelerSameAsBooker: true,
        leadTravelerFirstName: null,
        leadTravelerLastName: null,
        leadTravelerPhone: null,
        leadTravelerIsWhatsapp: true,
        leadTravelerEmail: null,
        termsAccepted: true,
        privacyAccepted: true,
        ipAddress: '127.0.0.1',
        userAgent: 'Pest/Test',
    );

    $result = $this->submissionService->submitAirportPickup($submitData);

    // Verify outbox event created
    $outboxEvent = OutboxEvent::where('aggregate_type', Booking::class)
        ->where('aggregate_id', $result->booking->id)
        ->where('event_type', 'booking.submitted')
        ->first();

    expect($outboxEvent)->not->toBeNull()
        ->and($outboxEvent->status)->toBe(OutboxEventStatus::Pending)
        ->and($outboxEvent->idempotency_key)->toBe("evt_booking_{$result->booking->id}_submitted_v1")
        ->and($outboxEvent->payload['booking_id'])->toBe($result->booking->id)
        ->and($outboxEvent->payload['reference'])->toBe($result->booking->reference)
        ->and($outboxEvent->payload['status_url'])->toBe($result->privateUrl);
});

test('transaction rollback rolls back outbox events leaving zero orphan events', function (): void {
    $initialEventCount = OutboxEvent::count();

    try {
        DB::transaction(function (): void {
            $booking = Booking::factory()->create([
                'status' => BookingStatus::Submitted,
            ]);

            app(OutboxService::class)->record(
                eventType: 'booking.submitted',
                aggregate: $booking,
                payload: ['booking_id' => $booking->id, 'reference' => $booking->reference],
            );

            // Simulate failure inside transaction
            throw new \RuntimeException('Simulated transaction failure');
        });
    } catch (\RuntimeException) {
        // Expected
    }

    expect(OutboxEvent::count())->toBe($initialEventCount);
});

test('lifecycle confirm, decline, and cancel record outbox events', function (): void {
    $vehicle = Vehicle::factory()->create(['status' => VehicleStatus::Active]);

    $booking = Booking::factory()->create([
        'status' => BookingStatus::Submitted,
        'vehicle_id' => $vehicle->id,
        'expires_at' => Carbon::now('Asia/Colombo')->addHours(2),
    ]);

    // 1. Confirm
    $this->lifecycleService->confirm(
        booking: $booking,
        staff: $this->staff,
        channel: ConfirmationChannel::WhatsApp,
        notes: 'Confirmed directly with customer on WhatsApp',
    );

    $confirmedEvent = OutboxEvent::where('aggregate_id', $booking->id)
        ->where('event_type', 'booking.confirmed')
        ->first();

    expect($confirmedEvent)->not->toBeNull()
        ->and($confirmedEvent->payload['reference'])->toBe($booking->reference)
        ->and($confirmedEvent->payload['channel'])->toBe('whatsapp');

    // 2. Decline on a separate submitted booking
    $declinedBooking = Booking::factory()->create([
        'status' => BookingStatus::Submitted,
        'vehicle_id' => $vehicle->id,
        'expires_at' => Carbon::now('Asia/Colombo')->addHours(2),
    ]);

    $this->lifecycleService->decline(
        booking: $declinedBooking,
        staff: $this->staff,
        reason: 'Fleet fully booked for that time window.',
    );

    $declinedEvent = OutboxEvent::where('aggregate_id', $declinedBooking->id)
        ->where('event_type', 'booking.declined')
        ->first();

    expect($declinedEvent)->not->toBeNull()
        ->and($declinedEvent->payload['reference'])->toBe($declinedBooking->reference)
        ->and($declinedEvent->payload['decline_reason'])->toBe('Fleet fully booked for that time window.');

    // 3. Cancel on a confirmed booking
    $this->lifecycleService->cancel(
        booking: $booking->fresh(),
        reason: 'Customer requested cancellation.',
        actor: $this->staff,
    );

    $cancelledEvent = OutboxEvent::where('aggregate_id', $booking->id)
        ->where('event_type', 'booking.cancelled')
        ->first();

    expect($cancelledEvent)->not->toBeNull()
        ->and($cancelledEvent->payload['reference'])->toBe($booking->reference)
        ->and($cancelledEvent->payload['cancellation_reason'])->toBe('Customer requested cancellation.');
});

test('manual booking creation records booking.confirmed outbox event', function (): void {
    $vehicle = Vehicle::factory()->create(['status' => VehicleStatus::Active]);
    $start = Carbon::now('Asia/Colombo')->addDays(5)->setHour(9)->setMinute(0);

    $criteria = new AirportPickupCriteria(
        airport: 'CMB',
        flightNumber: 'EK650',
        arrivalTime: $start,
        destinationText: 'Kingsbury Hotel, Colombo',
        passengerCount: 2,
        luggageCount: 2,
        distanceKm: 32.0,
        durationHours: 1.0,
    );

    $booking = $this->manualBookingService->createManualAirportPickup([
        'source' => BookingSource::ManualPhone,
        'criteria' => $criteria,
        'vehicle_id' => $vehicle->id,
        'estimate_reference' => null,
        'booker_first_name' => 'Kasun',
        'booker_last_name' => 'Silva',
        'booker_phone' => '+94712345678',
        'booker_is_whatsapp' => true,
        'booker_email' => 'kasun@example.com',
        'confirmation_channel' => ConfirmationChannel::Phone,
        'confirmation_notes' => 'Customer booked over phone.',
    ], $this->staff);

    $outboxEvent = OutboxEvent::where('aggregate_id', $booking->id)
        ->where('event_type', 'booking.confirmed')
        ->first();

    expect($outboxEvent)->not->toBeNull()
        ->and($outboxEvent->payload['reference'])->toBe($booking->reference)
        ->and($outboxEvent->payload['channel'])->toBe('phone');
});
