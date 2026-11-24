<?php

declare(strict_types=1);

use App\Domain\Availability\Enums\ScheduleBlockKind;
use App\Domain\Booking\DTOs\AirportTransferCriteria;
use App\Domain\Booking\Enums\BookingSource;
use App\Domain\Booking\Enums\BookingStatus;
use App\Domain\Booking\Enums\ConfirmationChannel;
use App\Domain\Booking\Services\ManualBookingService;
use App\Domain\Booking\ServiceType;
use App\Domain\Identity\Permission;
use App\Domain\Pricing\Enums\PricingComponentType;
use App\Models\PricingRule;
use App\Models\PricingRuleComponent;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission as SpatiePermission;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed();
    $this->manualBookingService = app(ManualBookingService::class);

    $this->staff = User::factory()->create();
    SpatiePermission::firstOrCreate(['name' => Permission::BookingConfirm->value, 'guard_name' => 'web']);
    $this->staff->givePermissionTo(Permission::BookingConfirm->value);
});

test('staff with booking.confirm permission can manually create confirmed airport transfer', function (): void {
    $vehicle = Vehicle::factory()->create([
        'make' => 'Nissan',
        'model' => 'Caravan Luxury Van',
        'license_plate' => 'WP-NB-8899',
        'eligible_for_transfer' => true,
        'turnaround_buffer_minutes' => 60,
    ]);

    $rule = PricingRule::factory()->create([
        'service_type' => ServiceType::AirportTransfer,
        'vehicle_id' => $vehicle->id,
    ]);
    PricingRuleComponent::factory()->create([
        'pricing_rule_id' => $rule->id,
        'component_type' => PricingComponentType::Base,
        'amount_minor_units' => 11000,
    ]);

    $departure = Carbon::now('Asia/Colombo')->addDays(2)->setHour(23)->setMinute(0);
    $recommended = (clone $departure)->subHours(5);
    $chosen = (clone $departure)->subHours(4); // 19:00

    $criteria = new AirportTransferCriteria(
        airport: 'CMB',
        flightNumber: 'QR665',
        departureTime: $departure,
        originText: 'Bentota Beach Hotel',
        passengerCount: 3,
        luggageCount: 3,
        recommendedPickupTime: $recommended,
        chosenPickupTime: $chosen,
        isUnsafeTime: true,
        warningAccepted: true,
    );

    $manualData = [
        'source' => BookingSource::ManualWhatsApp,
        'criteria' => $criteria,
        'vehicle_id' => $vehicle->id,
        'estimate_reference' => null,
        'booker_first_name' => 'Chloe',
        'booker_last_name' => 'Bennett',
        'booker_phone' => '+61 412 345 678',
        'confirmation_channel' => ConfirmationChannel::WhatsApp,
        'confirmation_notes' => 'Confirmed departure transfer details with guest directly on WhatsApp.',
    ];

    $booking = $this->manualBookingService->createManualAirportTransfer($manualData, $this->staff);

    expect($booking)->not->toBeNull()
        ->and($booking->status)->toBe(BookingStatus::Confirmed)
        ->and($booking->service_type)->toBe(ServiceType::AirportTransfer)
        ->and($booking->source)->toBe(BookingSource::ManualWhatsApp)
        ->and($booking->assigned_staff_id)->toBe($this->staff->id)
        ->and($booking->scheduleBlock)->not->toBeNull()
        ->and($booking->scheduleBlock->kind)->toBe(ScheduleBlockKind::ConfirmedBooking);

    $this->assertDatabaseHas('airport_transfer_details', [
        'booking_id' => $booking->id,
        'flight_number' => 'QR665',
        'origin_text' => 'Bentota Beach Hotel',
        'is_unsafe_time' => 1,
    ]);

    // Verify request hash matches computed hash for data integrity
    expect($booking->computeRequestHash())->toBe($booking->request_hash);
});

test('manual airport transfer can record separate lead traveler details', function (): void {
    $vehicle = Vehicle::factory()->create([
        'eligible_for_transfer' => true,
    ]);

    $departure = Carbon::now('Asia/Colombo')->addDays(2)->setHour(20)->setMinute(0);
    $criteria = new AirportTransferCriteria(
        airport: 'CMB',
        flightNumber: 'EK651',
        departureTime: $departure,
        originText: 'Cinnamon Grand, Colombo',
        passengerCount: 2,
        luggageCount: 2,
        recommendedPickupTime: (clone $departure)->subHours(4),
        chosenPickupTime: (clone $departure)->subHours(4),
    );

    $manualData = [
        'source' => BookingSource::ManualWhatsApp,
        'criteria' => $criteria,
        'vehicle_id' => $vehicle->id,
        'estimate_reference' => null,
        'booker_first_name' => 'Corporate',
        'booker_last_name' => 'Booker',
        'booker_phone' => '+44 20 7946 0991',
        'booker_email' => 'corp@example.com',
        'lead_traveler_first_name' => 'Actual',
        'lead_traveler_last_name' => 'Passenger',
        'lead_traveler_phone' => '+44 7911 554433',
        'lead_traveler_email' => 'passenger@example.com',
        'confirmation_channel' => ConfirmationChannel::WhatsApp,
        'confirmation_notes' => 'Booked on behalf of guest.',
    ];

    $booking = $this->manualBookingService->createManualAirportTransfer($manualData, $this->staff);

    expect($booking->booker)->not->toBeNull()
        ->and($booking->booker->first_name)->toBe('Corporate')
        ->and($booking->leadTraveler)->not->toBeNull()
        ->and($booking->leadTraveler->first_name)->toBe('Actual')
        ->and($booking->leadTraveler->phone)->toBe('+44 7911 554433')
        ->and($booking->computeRequestHash())->toBe($booking->request_hash);
});

test('manual booking fails without booking.confirm permission', function (): void {
    $unauthorizedStaff = User::factory()->create();

    $vehicle = Vehicle::factory()->create(['eligible_for_transfer' => true]);
    $departure = Carbon::now('Asia/Colombo')->addDays(2);
    $criteria = new AirportTransferCriteria(
        airport: 'CMB',
        flightNumber: 'UL504',
        departureTime: $departure,
        originText: 'Colombo 01',
        passengerCount: 1,
        luggageCount: 1,
        recommendedPickupTime: $departure->copy()->subHours(4),
        chosenPickupTime: $departure->copy()->subHours(4),
    );

    $manualData = [
        'source' => BookingSource::ManualPhone,
        'criteria' => $criteria,
        'vehicle_id' => $vehicle->id,
        'estimate_reference' => null,
        'booker_first_name' => 'John',
        'booker_last_name' => 'Doe',
        'booker_phone' => '+1 555 123 4567',
        'confirmation_channel' => ConfirmationChannel::Phone,
        'confirmation_notes' => 'Test',
    ];

    expect(fn () => $this->manualBookingService->createManualAirportTransfer($manualData, $unauthorizedStaff))
        ->toThrow(InvalidArgumentException::class, 'booking.confirm');
});
