<?php

declare(strict_types=1);

use App\Domain\Availability\Enums\ScheduleBlockKind;
use App\Domain\Availability\Services\AvailabilityService;
use App\Domain\Booking\DTOs\RentCriteria;
use App\Domain\Booking\DTOs\SubmitRentData;
use App\Domain\Booking\Enums\BookingSource;
use App\Domain\Booking\Enums\BookingStatus;
use App\Domain\Booking\Enums\ConfirmationChannel;
use App\Domain\Booking\Enums\ContactType;
use App\Domain\Booking\Services\BookingSubmissionService;
use App\Domain\Booking\Services\ManualBookingService;
use App\Domain\Booking\ServiceType;
use App\Domain\Catalog\Enums\VehicleStatus;
use App\Domain\Identity\Permission;
use App\Models\Booking;
use App\Models\Estimate;
use App\Models\RentPolicy;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Spatie\Permission\Models\Permission::firstOrCreate(['name' => Permission::BookingConfirm->value, 'guard_name' => 'web']);

    $this->policy = RentPolicy::factory()->create([
        'min_driver_age' => 23,
        'max_driver_age' => 70,
        'min_licence_years' => 2,
        'idp_required' => true,
        'security_deposit_minor_units' => 50000,
        'insurance_excess_minor_units' => 50000,
        'daily_mileage_km' => 100,
    ]);

    $this->vehicle = Vehicle::factory()->create([
        'public_name' => 'Toyota Land Cruiser Prado',
        'status' => VehicleStatus::Active,
        'eligible_for_rent' => true,
        'turnaround_buffer_minutes' => 60,
    ]);

    $this->availabilityService = app(AvailabilityService::class);
    $this->submissionService = app(BookingSubmissionService::class);
    $this->manualService = app(ManualBookingService::class);
});

test('submits online rent request atomically converting checkout hold to submitted block', function (): void {
    $pickup = Carbon::now('Asia/Colombo')->addDays(3)->setHour(10)->setMinute(0)->setSecond(0);
    $return = (clone $pickup)->addDays(4); // 4 days

    // Create checkout hold for Rent
    $holdResult = $this->availabilityService->createHold(
        vehicle: $this->vehicle,
        start: $pickup,
        end: $return,
        service: ServiceType::Rent,
        bufferBefore: 0,
        bufferAfter: $this->vehicle->turnaround_buffer_minutes,
    );

    // Create Estimate snapshot
    $estimate = Estimate::factory()->create([
        'service_type' => ServiceType::Rent,
        'vehicle_id' => $this->vehicle->id,
        'subtotal_minor_units' => 40000,
        'tax_minor_units' => 0,
        'deposit_minor_units' => 50000,
        'total_amount_minor_units' => 90000,
        'currency' => 'USD',
        'approximate_lkr_amount_minor_units' => 27000000,
        'expires_at' => Carbon::now('Asia/Colombo')->addMinutes(30),
    ]);

    $criteria = new RentCriteria(
        pickupDateTime: $pickup,
        returnDateTime: $return,
        pickupLocationType: 'office',
        pickupLocationText: 'Spinellanka Head Office, Colombo 03',
        returnLocationType: 'office',
        returnLocationText: 'Spinellanka Head Office, Colombo 03',
        driverAge: 32,
        driverLicenceCountry: 'United Kingdom',
        driverLicenceYears: 8,
        hasIdp: true,
        durationDays: 4,
        extraHours: 0,
        hasAdditionalDriver: true,
        additionalDriverName: 'Sarah Jenkins',
        additionalDriverAge: 30,
        additionalDriverLicenceCountry: 'United Kingdom',
        additionalDriverLicenceYears: 6,
        additionalDriverHasIdp: true,
        expectedDistanceKm: 400,
    );

    $submitData = new SubmitRentData(
        criteria: $criteria,
        vehicleId: $this->vehicle->id,
        holdPlainToken: $holdResult->plainToken,
        estimateReference: $estimate->reference,
        bookerFirstName: 'David',
        bookerLastName: 'Jenkins',
        bookerPhone: '+44 7700 900077',
        bookerIsWhatsapp: true,
        bookerEmail: 'david@example.com',
        bookerMarketingConsent: true,
        leadDriverSameAsBooker: true,
        termsAccepted: true,
        privacyAccepted: true,
        originalDocumentsAcknowledged: true,
        ipAddress: '127.0.0.1',
        userAgent: 'Pest/Test'
    );

    $result = $this->submissionService->submitRent($submitData);

    expect($result->booking)->toBeInstanceOf(Booking::class)
        ->and($result->booking->status)->toBe(BookingStatus::Submitted)
        ->and($result->booking->service_type)->toBe(ServiceType::Rent)
        ->and($result->booking->vehicle_id)->toBe($this->vehicle->id)
        ->and($result->plainGuestToken)->not->toBeEmpty();

    // Verify Schedule Block converted from hold to submitted
    $block = $result->booking->scheduleBlock;
    expect($block)->not->toBeNull()
        ->and($block->kind)->toBe(ScheduleBlockKind::SubmittedRequest)
        ->and($block->is_chauffeured_only)->toBeFalse();

    // Invariant 6: Verify no driver assigned or tied
    expect($block->driver_id)->toBeNull();

    // Verify Contacts
    $contacts = $result->booking->contacts;
    expect($contacts)->toHaveCount(2);
    $booker = $contacts->firstWhere('type', ContactType::Booker);
    $driver = $contacts->firstWhere('type', ContactType::LeadTraveler);
    expect($booker->first_name)->toBe('David')
        ->and($driver->first_name)->toBe('David');

    // Verify Rent Details
    $detail = $result->booking->rentDetail;
    expect($detail)->not->toBeNull()
        ->and($detail->duration_days)->toBe(4)
        ->and($detail->extra_hours)->toBe(0)
        ->and($detail->driver_age)->toBe(32)
        ->and($detail->has_idp)->toBeTrue()
        ->and($detail->has_additional_driver)->toBeTrue()
        ->and($detail->additional_driver_name)->toBe('Sarah Jenkins')
        ->and($detail->included_distance_km)->toBe(400) // 4 days * 100 km
        ->and($detail->security_deposit_minor_units)->toBe(50000)
        ->and($detail->insurance_excess_minor_units)->toBe(50000)
        ->and($detail->original_documents_acknowledged)->toBeTrue();
});

test('submitting rent requires original documents acknowledgement', function (): void {
    $pickup = Carbon::now('Asia/Colombo')->addDays(2);
    $return = (clone $pickup)->addDays(2);

    $criteria = new RentCriteria(
        pickupDateTime: $pickup,
        returnDateTime: $return,
        pickupLocationType: 'office',
        pickupLocationText: 'Office',
        returnLocationType: 'office',
        returnLocationText: 'Office',
        driverAge: 30,
        driverLicenceCountry: 'UK',
        driverLicenceYears: 5,
        hasIdp: true,
        durationDays: 2,
    );

    $submitData = new SubmitRentData(
        criteria: $criteria,
        vehicleId: $this->vehicle->id,
        holdPlainToken: 'fake-token',
        estimateReference: 'fake-est',
        bookerFirstName: 'David',
        bookerLastName: 'Jenkins',
        bookerPhone: '+44 7700 900077',
        termsAccepted: true,
        privacyAccepted: true,
        originalDocumentsAcknowledged: false, // Not acknowledged!
    );

    $this->submissionService->submitRent($submitData);
})->throws(InvalidArgumentException::class, 'You must acknowledge that physical original documents are required at vehicle handover.');

test('staff creates manual rent booking atomically with Confirmed status', function (): void {
    $staff = User::factory()->create();
    $staff->givePermissionTo(Permission::BookingConfirm->value);

    $pickup = Carbon::now('Asia/Colombo')->addDays(3)->setHour(10)->setMinute(0)->setSecond(0);
    $return = (clone $pickup)->addDays(5);

    $criteria = new RentCriteria(
        pickupDateTime: $pickup,
        returnDateTime: $return,
        pickupLocationType: 'office',
        pickupLocationText: 'Spinellanka Colombo Office',
        returnLocationType: 'airport',
        returnLocationText: 'Bandaranaike International Airport (CMB)',
        driverAge: 40,
        driverLicenceCountry: 'Australia',
        driverLicenceYears: 15,
        hasIdp: true,
        durationDays: 5,
        extraHours: 0,
    );

    $manualData = [
        'source' => BookingSource::ManualPhone,
        'criteria' => $criteria,
        'vehicle_id' => $this->vehicle->id,
        'estimate_reference' => null,
        'booker_first_name' => 'Michael',
        'booker_last_name' => 'Taylor',
        'booker_phone' => '+61 400 123 456',
        'booker_is_whatsapp' => true,
        'booker_email' => 'michael@example.com.au',
        'confirmation_channel' => ConfirmationChannel::Phone,
        'confirmation_notes' => 'Customer confirmed phone reservation for self-drive Prado.',
    ];

    $booking = $this->manualService->createManualRent($manualData, $staff);

    expect($booking)->toBeInstanceOf(Booking::class)
        ->and($booking->status)->toBe(BookingStatus::Confirmed)
        ->and($booking->service_type)->toBe(ServiceType::Rent)
        ->and($booking->assigned_staff_id)->toBe($staff->id)
        ->and($booking->confirmation_channel)->toBe(ConfirmationChannel::Phone);

    // Verify schedule block is confirmed booking
    $block = $booking->scheduleBlock;
    expect($block)->not->toBeNull()
        ->and($block->kind)->toBe(ScheduleBlockKind::ConfirmedBooking)
        ->and($block->driver_id)->toBeNull(); // Invariant 6

    // Verify Rent detail
    expect($booking->rentDetail)->not->toBeNull()
        ->and($booking->rentDetail->duration_days)->toBe(5)
        ->and($booking->rentDetail->included_distance_km)->toBe(500);
});
