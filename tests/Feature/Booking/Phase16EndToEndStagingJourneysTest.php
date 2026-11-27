<?php

declare(strict_types=1);

use App\Domain\Booking\DTOs\CreateProposalData;
use App\Domain\Booking\Enums\BookingStatus;
use App\Domain\Booking\Enums\ConfirmationChannel;
use App\Domain\Booking\Enums\PaymentOption;
use App\Domain\Booking\Enums\ProposalPaymentChoice;
use App\Domain\Booking\Services\BookingLifecycleService;
use App\Domain\Booking\Services\ProposalService;
use App\Domain\Booking\ServiceType;
use App\Domain\Catalog\Services\DriverAssignmentService;
use App\Domain\Catalog\Services\DriverService;
use App\Domain\Catalog\Services\ExtraService;
use App\Domain\Catalog\Services\VehicleService;
use App\Domain\Identity\Role;
use App\Domain\Operations\Enums\DriverTripStatus;
use App\Domain\Operations\Enums\FuelLevel;
use App\Domain\Operations\Enums\HandoverType;
use App\Domain\Operations\Enums\IncidentSeverity;
use App\Domain\Operations\Enums\IncidentType;
use App\Domain\Operations\Services\ConditionComparisonService;
use App\Domain\Operations\Services\DriverAssignmentService as OperationsDriverAssignmentService;
use App\Domain\Operations\Services\DriverTripService;
use App\Domain\Operations\Services\HandoverService;
use App\Domain\Operations\Services\IncidentService;
use App\Domain\Payment\Services\PaymentService;
use App\Domain\Payment\Services\PaymentWebhookService;
use App\Domain\Pricing\DTOs\EstimateItemData;
use App\Domain\Pricing\DTOs\EstimateRequestData;
use App\Domain\Pricing\Enums\PricingComponentType;
use App\Domain\Pricing\Enums\PricingUnit;
use App\Domain\Pricing\Services\ExchangeRateService;
use App\Domain\Pricing\Services\PricingRuleService;
use App\Domain\Pricing\Services\PricingService;
use App\Domain\Pricing\Services\RentPolicyService;
use App\Domain\Pricing\ValueObjects\Money;
use App\Models\Booking;
use App\Models\DriverAccessToken;
use App\Models\Extra;
use App\Models\GuestAccessToken;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\Carbon;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\StagingSyntheticFixturesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('local');
    Storage::fake('public');
    $this->seed(RoleAndPermissionSeeder::class);

    app(StagingSyntheticFixturesSeeder::class)->run(
        app(VehicleService::class),
        app(DriverService::class),
        app(DriverAssignmentService::class),
        app(ExtraService::class),
        app(PricingRuleService::class),
        app(ExchangeRateService::class),
        app(RentPolicyService::class),
    );
});

it('verifies deterministic pricing examples across all four services against synthetic staging rules', function (): void {
    $pricingService = app(PricingService::class);
    $date = Carbon::now('Asia/Colombo')->addDays(7);

    // 1. Airport Pickup: Base ($25) + 35km * $0.80 ($28) + AirportFee ($5) = $58.00 (5800 cents), LKR ~ 17,893.00
    $v1 = Vehicle::where('internal_fleet_id', 'SYNTH-VEH-01')->firstOrFail();
    $pickupEst = $pricingService->calculateEstimate(new EstimateRequestData(
        serviceType: ServiceType::AirportPickup,
        vehicleId: $v1->id,
        serviceDate: $date,
        distanceKm: 35.0,
    ));
    expect($pickupEst->isCustomQuote)->toBeFalse()
        ->and($pickupEst->grandTotal?->amountMinorUnits)->toBe(5800)
        ->and($pickupEst->approximateLkr?->amountMinorUnits)->toBe(1789300);

    // 2. Airport Transfer: Base ($22) + 120km * $0.80 ($96) = $118.00 (11800 cents)
    $transferEst = $pricingService->calculateEstimate(new EstimateRequestData(
        serviceType: ServiceType::AirportTransfer,
        vehicleId: $v1->id,
        serviceDate: $date,
        distanceKm: 120.0,
    ));
    expect($transferEst->grandTotal?->amountMinorUnits)->toBe(11800);

    // 3. Ride: Base ($30) + 100km * $0.90 ($90) + 2 stops * $5 ($10) + 3 waiting hours * $6 ($18) = $148.00 (14800 cents)
    $v2 = Vehicle::where('internal_fleet_id', 'SYNTH-VEH-02')->firstOrFail();
    $rideEst = $pricingService->calculateEstimate(new EstimateRequestData(
        serviceType: ServiceType::Ride,
        vehicleId: $v2->id,
        serviceDate: $date,
        distanceKm: 100.0,
        stopCount: 2,
        waitingHours: 3,
    ));
    expect($rideEst->grandTotal?->amountMinorUnits)->toBe(14800);

    // 4. Rent: 3 days * $55/day ($165) + Child seat ($10) = $175.00 service subtotal, $500.00 segregated deposit, $675.00 grandTotal
    $v5 = Vehicle::where('internal_fleet_id', 'SYNTH-VEH-05')->firstOrFail();
    $childSeat = Extra::where('slug', 'synth-child-seat')->firstOrFail();
    $rentEst = $pricingService->calculateEstimate(new EstimateRequestData(
        serviceType: ServiceType::Rent,
        vehicleId: $v5->id,
        serviceDate: $date,
        rentalDays: 3,
        extraIds: [$childSeat->id],
    ));
    expect($rentEst->subtotal?->amountMinorUnits)->toBe(17500)
        ->and($rentEst->depositTotal?->amountMinorUnits)->toBe(50000)
        ->and($rentEst->grandTotal?->amountMinorUnits)->toBe(67500);
});

it('executes all four synthetic end-to-end journeys through request, proposal, sandbox payment, operations, and field flows', function (): void {
    $staff = User::factory()->create(['is_active' => true]);
    $staff->assignRole(Role::OwnerAdmin->value);

    $lifecycleService = app(BookingLifecycleService::class);
    $proposalService = app(ProposalService::class);
    $paymentService = app(PaymentService::class);
    $webhookService = app(PaymentWebhookService::class);
    $opsDriverService = app(OperationsDriverAssignmentService::class);
    $driverTripService = app(DriverTripService::class);

    // --- Journey 1: Airport Pickup (Web Request -> Hold -> Submit -> Proposal -> Accept -> Sandbox Webhook Payment -> Driver Trip) ---
    $v1 = Vehicle::where('internal_fleet_id', 'SYNTH-VEH-01')->firstOrFail();
    $arrivalDate = Carbon::now('Asia/Colombo')->addDays(4)->format('Y-m-d');

    $this->post(route('booking.airport-pickup.search'), [
        'airport' => 'CMB',
        'flight_number' => 'UL504',
        'arrival_date' => $arrivalDate,
        'arrival_time' => '14:30',
        'destination_text' => 'Galle Fort Heritage Area, Galle',
        'place_id' => 'galle_fort',
        'passenger_count' => 2,
        'luggage_count' => 2,
        'meet_and_greet' => true,
        'name_board_text' => 'SYNTHETIC TRAVELER 01',
    ])->assertRedirect(route('booking.airport-pickup.vehicles'));

    $this->post(route('booking.airport-pickup.hold'), ['vehicle_id' => $v1->id])
        ->assertRedirect(route('booking.airport-pickup.contact'));

    $this->post(route('booking.airport-pickup.submit'), [
        'booker_first_name' => 'Synthetic',
        'booker_last_name' => 'TravelerOne',
        'booker_phone' => '+94000001001',
        'booker_is_whatsapp' => '1',
        'booker_email' => 'synth1@example.invalid',
        'lead_traveler_same' => '1',
        'terms_accepted' => '1',
        'privacy_accepted' => '1',
    ])->assertRedirect();

    $pickupBooking = Booking::where('service_type', ServiceType::AirportPickup->value)->latest('id')->firstOrFail();
    expect($pickupBooking->status)->toBe(BookingStatus::Submitted);

    $pickupBooking = $lifecycleService->markUnderReview($pickupBooking, $staff);
    $startsAt = Carbon::parse("{$arrivalDate} 14:30", 'Asia/Colombo');
    $pickupProposal = $proposalService->createDraft($pickupBooking, new CreateProposalData(
        vehicleId: $v1->id,
        serviceType: ServiceType::AirportPickup,
        startsAt: $startsAt,
        endsAt: $startsAt->copy()->addHours(3),
        currency: 'USD',
        totalAmountMinorUnits: 12000,
        approximateLkrAmountMinorUnits: 3702000,
        depositRequired: false,
        depositAmountMinorUnits: 0,
        paymentChoice: ProposalPaymentChoice::FullOnly,
        expiresAt: Carbon::now('Asia/Colombo')->addDays(2),
        items: [
            new EstimateItemData(
                componentType: PricingComponentType::Base,
                description: 'Airport Pickup CMB to Galle',
                unit: PricingUnit::Flat,
                quantity: 1.0,
                unitPrice: Money::of(12000, 'USD'),
                totalAmount: Money::of(12000, 'USD'),
                sortOrder: 1,
            ),
        ],
        snapshotData: ['synthetic_staging' => true],
    ), $staff);
    $pickupProposal = $proposalService->sendProposal($pickupProposal, $staff);

    $guestToken = GuestAccessToken::generateToken();
    GuestAccessToken::create([
        'booking_id' => $pickupBooking->id,
        'token_hash' => GuestAccessToken::hashToken($guestToken),
        'purpose' => 'guest_status',
        'expires_at' => now()->addDays(7),
    ]);

    $this->post(route('booking.status.accept', ['reference' => $pickupBooking->reference, 'token' => $guestToken]), [
        'payment_option' => PaymentOption::Full->value,
    ])->assertRedirect();

    $checkoutSession = $paymentService->initiateCheckout(
        $pickupBooking->fresh() ?? $pickupBooking,
        $pickupProposal->fresh() ?? $pickupProposal,
        PaymentOption::Full
    );
    $txnRef = $checkoutSession->transactionReference;

    $webhookReq = Request::create('/webhooks/payment/fake', 'POST', [
        'payment_id' => 'EVT-SYNTH-'.$txnRef,
        'order_id' => $txnRef,
        'amount' => '120.00',
        'currency' => 'USD',
        'status_code' => '2',
        'signature' => 'fake_valid_sig',
    ]);
    $webhookResult = $webhookService->handleWebhook('fake', $webhookReq);
    expect($webhookResult->success)->toBeTrue()
        ->and($pickupBooking->fresh()?->status)->toBe(BookingStatus::Confirmed);

    $assigned = $opsDriverService->assignDriver(
        $pickupBooking->fresh() ?? $pickupBooking,
        $v1->driverAssignments()->where('is_permanent', true)->firstOrFail()->driver,
        $staff
    );
    $driverTokenRecord = $driverTripService->validateToken($assigned['plainToken']);
    expect($driverTokenRecord)->not->toBeNull();
    assert($driverTokenRecord instanceof DriverAccessToken);

    foreach ([
        DriverTripStatus::Acknowledged,
        DriverTripStatus::EnRoute,
        DriverTripStatus::Arrived,
        DriverTripStatus::Started,
        DriverTripStatus::Completed,
    ] as $milestone) {
        $driverTripService->updateTripStatus($driverTokenRecord->fresh() ?? $driverTokenRecord, $milestone);
    }
    expect($pickupBooking->fresh()?->status)->toBe(BookingStatus::Completed);

    // --- Journey 2: Airport Transfer (Recommended -> Unsafe Custom Override) ---
    $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.12']);
    $v2 = Vehicle::where('internal_fleet_id', 'SYNTH-VEH-02')->firstOrFail();
    $depDate = Carbon::now('Asia/Colombo')->addDays(6);
    $unsafePickup = $depDate->copy()->setTime(14, 30);

    $this->post(route('booking.airport-transfer.search'), [
        'airport' => 'CMB',
        'flight_number' => 'EK651',
        'departure_date' => $depDate->format('Y-m-d'),
        'departure_time' => '18:00',
        'origin_text' => 'Colombo Fort / Galle Face',
        'place_id' => 'colombo_fort',
        'passenger_count' => 3,
        'luggage_count' => 3,
        'pickup_timing_mode' => 'custom',
        'custom_pickup_date' => $unsafePickup->format('Y-m-d'),
        'custom_pickup_time' => $unsafePickup->format('H:i'),
        'warning_accepted' => '1',
    ])->assertRedirect(route('booking.airport-transfer.vehicles'));

    $this->post(route('booking.airport-transfer.hold'), ['vehicle_id' => $v2->id])
        ->assertRedirect(route('booking.airport-transfer.contact'));

    $this->post(route('booking.airport-transfer.submit'), [
        'booker_first_name' => 'Synthetic',
        'booker_last_name' => 'TravelerTwo',
        'booker_phone' => '+94000001002',
        'booker_is_whatsapp' => '1',
        'lead_traveler_same' => '1',
        'terms_accepted' => '1',
        'privacy_accepted' => '1',
    ])->assertRedirect();

    $transferBooking = Booking::where('service_type', ServiceType::AirportTransfer->value)->latest('id')->firstOrFail();
    expect($transferBooking->airportTransferDetail?->is_unsafe_time)->toBeTrue();

    // --- Journey 3: Ride (Multi-stop + Return Leg + Same Vehicle) ---
    $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.13']);
    $v3 = Vehicle::where('internal_fleet_id', 'SYNTH-VEH-03')->firstOrFail();
    $rideDate = Carbon::now('Asia/Colombo')->addDays(9);

    $this->post(route('booking.ride.search'), [
        'origin_text' => 'Colombo Fort / Galle Face',
        'destination_text' => 'Kandy City Center',
        'pickup_date' => $rideDate->format('Y-m-d'),
        'pickup_time' => '08:00',
        'passenger_count' => 2,
        'luggage_count' => 2,
        'stops' => [
            ['location_text' => 'Pinnawala Stop', 'waiting_minutes' => 60],
        ],
        'has_return' => '1',
        'return_date' => $rideDate->copy()->addDays(1)->format('Y-m-d'),
        'return_time' => '14:00',
    ])->assertRedirect(route('booking.ride.vehicles'));

    $this->post(route('booking.ride.hold'), ['vehicle_id' => $v3->id])
        ->assertRedirect(route('booking.ride.contact'));

    $this->post(route('booking.ride.submit'), [
        'booker_first_name' => 'Synthetic',
        'booker_last_name' => 'TravelerThree',
        'booker_phone' => '+94000001003',
        'booker_is_whatsapp' => '1',
        'lead_traveler_same' => '1',
        'terms_accepted' => '1',
        'privacy_accepted' => '1',
    ])->assertRedirect();

    $rideBooking = Booking::where('service_type', ServiceType::Ride->value)->latest('id')->firstOrFail();
    expect($rideBooking->rideDetail?->stops()->count())->toBe(1);

    // --- Journey 4: Rent (Self-Drive -> Confirm -> Collection Handover -> Return Comparison -> Incident) ---
    $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.14']);
    $v5 = Vehicle::where('internal_fleet_id', 'SYNTH-VEH-05')->firstOrFail();
    $rentPickup = Carbon::now('Asia/Colombo')->addDays(12)->setTime(10, 0);
    $rentReturn = $rentPickup->copy()->addDays(2);

    $this->post(route('booking.rent.search'), [
        'pickup_date' => $rentPickup->format('Y-m-d'),
        'pickup_time' => '10:00',
        'return_date' => $rentReturn->format('Y-m-d'),
        'return_time' => '10:00',
        'pickup_location_type' => 'office',
        'pickup_location_text' => 'Spinellanka Colombo Office',
        'return_location_type' => 'office',
        'return_location_text' => 'Spinellanka Colombo Office',
        'driver_age' => 30,
        'driver_licence_country' => 'United Kingdom',
        'driver_licence_years' => 6,
        'has_idp' => '1',
        'expected_distance_km' => 150,
    ])->assertRedirect(route('booking.rent.vehicles'));

    $this->post(route('booking.rent.hold'), ['vehicle_id' => $v5->id])
        ->assertRedirect(route('booking.rent.contact'));

    $this->post(route('booking.rent.submit'), [
        'booker_first_name' => 'Synthetic',
        'booker_last_name' => 'RenterFour',
        'booker_phone' => '+94000001004',
        'booker_is_whatsapp' => '1',
        'lead_driver_same_as_booker' => '1',
        'terms_accepted' => '1',
        'privacy_accepted' => '1',
        'original_documents_acknowledged' => '1',
    ])->assertRedirect();

    $rentBooking = Booking::where('service_type', ServiceType::Rent->value)->latest('id')->firstOrFail();
    $lifecycleService->confirm(
        $rentBooking,
        $staff,
        ConfirmationChannel::WhatsApp,
        'Confirmed synthetic rent journey'
    );

    $handoverService = app(HandoverService::class);
    $collection = $handoverService->createHandover(
        booking: $rentBooking->fresh() ?? $rentBooking,
        type: HandoverType::Collection,
        scheduledAt: $rentPickup,
        locationType: 'office',
        locationText: 'Spinellanka Colombo Office',
        staffUser: $staff,
    );

    $handoverService->recordDocumentInspection(
        handover: $collection,
        staff: $staff,
        docTypes: ['passport', 'national_licence', 'idp'],
        licenceCountry: 'GB',
        licenceExpiry: Carbon::now('Asia/Colombo')->addYears(2),
        idpOrAacVerified: true,
        notes: 'Originals visually inspected; zero copies stored.',
    );

    $handoverService->recordCondition(
        handover: $collection->fresh() ?? $collection,
        staff: $staff,
        odometerKm: 25000,
        fuelLevel: FuelLevel::Full,
        accessoriesChecklist: ['spare_tire' => true, 'jack' => true],
        isClean: true,
        smokingDetected: false,
    );

    // Minimal valid 220x90 PNG signature binary
    $img = imagecreatetruecolor(220, 90);
    ob_start();
    imagepng($img);
    $pngBase64 = 'data:image/png;base64,'.base64_encode((string) ob_get_clean());
    imagedestroy($img);

    $handoverService->captureSignatures($collection->fresh() ?? $collection, $pngBase64, 'Synthetic Renter Four', $pngBase64, $staff);
    $released = $handoverService->releaseVehicle($collection->fresh() ?? $collection, $staff);
    expect($released->is_released)->toBeTrue();

    // Return handover + condition comparison (50km over 200km allowance -> 50 * $0.30 = $15.00 excess)
    $return = $handoverService->createHandover(
        booking: $rentBooking->fresh() ?? $rentBooking,
        type: HandoverType::Return,
        scheduledAt: $rentReturn,
        locationType: 'office',
        locationText: 'Spinellanka Colombo Office',
        staffUser: $staff,
    );
    $handoverService->recordCondition(
        handover: $return,
        staff: $staff,
        odometerKm: 25250,
        fuelLevel: FuelLevel::Full,
        accessoriesChecklist: ['spare_tire' => true, 'jack' => true],
        isClean: true,
        smokingDetected: false,
    );
    $handoverService->captureSignatures($return->fresh() ?? $return, $pngBase64, 'Synthetic Renter Four', $pngBase64, $staff);
    $completedReturn = $handoverService->completeReturn($return->fresh() ?? $return, $staff);

    $comparison = app(ConditionComparisonService::class)->compareConditions(
        $released->fresh() ?? $released,
        $completedReturn->fresh() ?? $completedReturn
    );
    expect($comparison->excessDistanceKm)->toBe(50)
        ->and($comparison->excessMileageChargeMinorUnits)->toBe(1500);

    // Synthetic incident recording
    $incident = app(IncidentService::class)->createIncident($staff, [
        'booking_id' => $rentBooking->id,
        'vehicle_id' => $v5->id,
        'type' => IncidentType::Damage->value,
        'severity' => IncidentSeverity::Minor->value,
        'occurred_at' => Carbon::now('Asia/Colombo'),
        'description' => 'Synthetic minor scuff test record.',
    ]);
    expect($incident->incident_number)->toStartWith('INC-');

    // Outbox drain completes cleanly
    expect(Artisan::call('outbox:drain'))->toBe(0);
});
