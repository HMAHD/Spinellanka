<?php

declare(strict_types=1);

namespace Tests\Feature\Operations;

use App\Domain\Booking\Enums\BookingStatus;
use App\Domain\Booking\ServiceType;
use App\Domain\Identity\Permission;
use App\Domain\Operations\Enums\FuelLevel;
use App\Domain\Operations\Enums\HandoverStatus;
use App\Domain\Operations\Enums\HandoverType;
use App\Domain\Operations\Exceptions\ReleaseGateBlockedException;
use App\Domain\Operations\Services\HandoverService;
use App\Models\AuditEvent;
use App\Models\Booking;
use App\Models\RentDetail;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed();
    $this->handoverService = app(HandoverService::class);

    $this->staff = User::create([
        'name' => 'Field Operator Sunil',
        'email' => 'field.sunil@spinellanka.test',
        'password' => Hash::make('Secret123456!'),
        'is_active' => true,
        'token_version' => 1,
    ]);
    $this->staff->givePermissionTo(Permission::BookingView->value);
    $this->staff->givePermissionTo(Permission::BookingConfirm->value);

    $this->manager = User::create([
        'name' => 'Operations Manager Kamal',
        'email' => 'manager.kamal@spinellanka.test',
        'password' => Hash::make('Secret123456!'),
        'is_active' => true,
        'token_version' => 1,
    ]);
    $this->manager->givePermissionTo(Permission::BookingOverrideConflict->value);

    $this->vehicle = Vehicle::factory()->create([
        'fuel_tank_capacity_litres' => 50,
    ]);

    $this->booking = Booking::factory()->create([
        'reference' => 'BK-RENT-TEST-001',
        'service_type' => ServiceType::Rent,
        'status' => BookingStatus::Confirmed,
        'vehicle_id' => $this->vehicle->id,
    ]);

    $this->rentDetail = RentDetail::factory()->create([
        'booking_id' => $this->booking->id,
        'duration_days' => 3,
        'included_distance_km' => 300,
        'excess_mileage_rate_minor_units' => 45,
    ]);

    $this->validPngBase64 = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';
});

test('createHandover schedules a collection handover for a rent booking and rejects non-rent booking', function (): void {
    $scheduledAt = Carbon::now('Asia/Colombo')->addHours(2);
    $handover = $this->handoverService->createHandover(
        booking: $this->booking,
        type: HandoverType::Collection,
        scheduledAt: $scheduledAt,
        locationType: 'office',
        locationText: 'Spinellanka Colombo Hub',
        staffUser: $this->staff,
        notes: 'VIP customer arrival'
    );

    expect($handover->id)->not->toBeNull()
        ->and($handover->type)->toBe(HandoverType::Collection)
        ->and($handover->status)->toBe(HandoverStatus::Scheduled)
        ->and($handover->location_text)->toBe('Spinellanka Colombo Hub')
        ->and($handover->documents_inspected)->toBeFalse()
        ->and($handover->is_released)->toBeFalse();

    // Rejects non-rent booking
    $rideBooking = Booking::factory()->create([
        'service_type' => ServiceType::Ride,
    ]);

    expect(fn () => $this->handoverService->createHandover(
        booking: $rideBooking,
        type: HandoverType::Collection,
        scheduledAt: $scheduledAt,
        locationType: 'office',
        locationText: 'Colombo'
    ))->toThrow(InvalidArgumentException::class);
});

test('recordDocumentInspection enforces Sri Lanka statutory rules for foreign and domestic licenses', function (): void {
    $handover = $this->handoverService->createHandover(
        booking: $this->booking,
        type: HandoverType::Collection,
        scheduledAt: Carbon::now('Asia/Colombo')->addHours(2),
        locationType: 'office',
        locationText: 'Colombo Hub',
        staffUser: $this->staff
    );

    // Foreign licence without IDP endorsement fails statutory rule
    expect(fn () => $this->handoverService->recordDocumentInspection(
        handover: $handover,
        staff: $this->staff,
        docTypes: ['passport', 'driver_licence'],
        licenceCountry: 'DE',
        licenceExpiry: Carbon::now('Asia/Colombo')->addYears(2),
        idpOrAacVerified: false
    ))->toThrow(InvalidArgumentException::class, 'Foreign driver licence requires a valid International Driving Permit');

    // Expired licence fails statutory rule
    expect(fn () => $this->handoverService->recordDocumentInspection(
        handover: $handover,
        staff: $this->staff,
        docTypes: ['passport', 'driver_licence'],
        licenceCountry: 'LK',
        licenceExpiry: Carbon::now('Asia/Colombo')->subDays(5),
        idpOrAacVerified: false
    ))->toThrow(InvalidArgumentException::class, 'Driver licence has expired');

    // Domestic Sri Lankan licence succeeds without IDP
    $updatedHandover = $this->handoverService->recordDocumentInspection(
        handover: $handover,
        staff: $this->staff,
        docTypes: ['national_id', 'driver_licence'],
        licenceCountry: 'LK',
        licenceExpiry: Carbon::now('Asia/Colombo')->addYears(3),
        idpOrAacVerified: false,
        notes: 'Physical cards verified in person'
    );

    expect($updatedHandover->documents_inspected)->toBeTrue()
        ->and($updatedHandover->status)->toBe(HandoverStatus::DocumentsVerified)
        ->and($updatedHandover->driver_licence_country)->toBe('LK');

    // RentDetail synchronized
    $this->rentDetail->refresh();
    expect($this->rentDetail->original_documents_inspected)->toBeTrue()
        ->and($this->rentDetail->original_documents_inspected_by)->toBe($this->staff->id);

    // Audit event logged
    expect(AuditEvent::where('action', 'handover.documents_inspected')->exists())->toBeTrue();
});

test('recordCondition validates and saves vehicle odometer and fuel level', function (): void {
    $handover = $this->handoverService->createHandover(
        booking: $this->booking,
        type: HandoverType::Collection,
        scheduledAt: Carbon::now('Asia/Colombo')->addHours(2),
        locationType: 'office',
        locationText: 'Colombo Hub'
    );

    // Negative odometer rejected
    expect(fn () => $this->handoverService->recordCondition(
        handover: $handover,
        staff: $this->staff,
        odometerKm: -50,
        fuelLevel: FuelLevel::Full,
        accessoriesChecklist: ['spare_tire' => true]
    ))->toThrow(InvalidArgumentException::class, 'Odometer reading cannot be negative');

    $record = $this->handoverService->recordCondition(
        handover: $handover,
        staff: $this->staff,
        odometerKm: 45200,
        fuelLevel: FuelLevel::Full,
        accessoriesChecklist: ['spare_tire' => true, 'jack' => true, 'toolkit' => true],
        isClean: true,
        smokingDetected: false,
        exteriorNotes: 'Clean, no visible scratches',
        interiorNotes: 'Pristine'
    );

    expect($record->odometer_km)->toBe(45200)
        ->and($record->fuel_level)->toBe(FuelLevel::Full)
        ->and($record->accessories_checklist['spare_tire'])->toBeTrue();

    $handover->refresh();
    expect($handover->status)->toBe(HandoverStatus::ConditionCaptured);
});

test('captureSignatures stores binary PNG signatures and transitions status', function (): void {
    $handover = $this->handoverService->createHandover(
        booking: $this->booking,
        type: HandoverType::Collection,
        scheduledAt: Carbon::now('Asia/Colombo')->addHours(2),
        locationType: 'office',
        locationText: 'Colombo Hub'
    );

    $updatedHandover = $this->handoverService->captureSignatures(
        handover: $handover,
        customerSignatureBase64: $this->validPngBase64,
        customerSignerName: 'John Doe',
        staffSignatureBase64: $this->validPngBase64,
        staff: $this->staff
    );

    expect($updatedHandover->customer_signature_path)->not->toBeNull()
        ->and($updatedHandover->staff_signature_path)->not->toBeNull()
        ->and($updatedHandover->customer_signer_name)->toBe('John Doe')
        ->and($updatedHandover->status)->toBe(HandoverStatus::SignaturesCaptured);
});

test('releaseVehicle blocks dispatch until all gates pass and dispatches when satisfied', function (): void {
    $handover = $this->handoverService->createHandover(
        booking: $this->booking,
        type: HandoverType::Collection,
        scheduledAt: Carbon::now('Asia/Colombo')->addHours(2),
        locationType: 'office',
        locationText: 'Colombo Hub',
        staffUser: $this->staff
    );

    // Initial state: gates are unfulfilled -> throws ReleaseGateBlockedException
    try {
        $this->handoverService->releaseVehicle($handover, $this->staff);
        $this->fail('Expected ReleaseGateBlockedException was not thrown.');
    } catch (ReleaseGateBlockedException $e) {
        expect($e->violations)->toHaveKey('documents')
            ->and($e->violations)->toHaveKey('condition')
            ->and($e->violations)->toHaveKey('signatures');
    }

    // Step 1: Satisfy documents gate
    $this->handoverService->recordDocumentInspection(
        handover: $handover,
        staff: $this->staff,
        docTypes: ['passport', 'driver_licence'],
        licenceCountry: 'LK',
        licenceExpiry: Carbon::now('Asia/Colombo')->addYears(2),
        idpOrAacVerified: false
    );

    // Step 2: Satisfy condition gate
    $this->handoverService->recordCondition(
        handover: $handover,
        staff: $this->staff,
        odometerKm: 50000,
        fuelLevel: FuelLevel::Full,
        accessoriesChecklist: ['spare_tire' => true]
    );

    // Step 3: Satisfy signatures gate
    $this->handoverService->captureSignatures(
        handover: $handover,
        customerSignatureBase64: $this->validPngBase64,
        customerSignerName: 'Jane Smith',
        staffSignatureBase64: $this->validPngBase64,
        staff: $this->staff
    );

    // All gates satisfied -> release vehicle succeeds
    $releasedHandover = $this->handoverService->releaseVehicle($handover, $this->staff);

    expect($releasedHandover->is_released)->toBeTrue()
        ->and($releasedHandover->status)->toBe(HandoverStatus::Released)
        ->and($releasedHandover->completed_at)->not->toBeNull();

    expect(AuditEvent::where('action', 'handover.vehicle_released')->exists())->toBeTrue();
});

test('authorizeException enforces Four-Eyes principle and requires booking.override_conflict permission', function (): void {
    $handover = $this->handoverService->createHandover(
        booking: $this->booking,
        type: HandoverType::Collection,
        scheduledAt: Carbon::now('Asia/Colombo')->addHours(2),
        locationType: 'office',
        locationText: 'Colombo Hub',
        staffUser: $this->staff
    );

    // 1. Four-Eyes violation: Field staff cannot authorize their own exception
    expect(fn () => $this->handoverService->authorizeException(
        handover: $handover,
        authorizer: $this->staff,
        reason: 'Customer left IDP at hotel'
    ))->toThrow(AuthorizationException::class, 'Four-Eyes principle violation: Field staff cannot authorize their own handover exception');

    // 2. Unauthorized staff without booking.override_conflict cannot authorize exception
    $unauthorizedUser = User::create([
        'name' => 'Junior Staff Nuwan',
        'email' => 'nuwan@spinellanka.test',
        'password' => Hash::make('Secret123456!'),
        'is_active' => true,
        'token_version' => 1,
    ]);

    expect(fn () => $this->handoverService->authorizeException(
        handover: $handover,
        authorizer: $unauthorizedUser,
        reason: 'Customer left IDP at hotel'
    ))->toThrow(AuthorizationException::class, 'Actor does not have booking.override_conflict permission');

    // 3. Manager with permission authorizes exception successfully
    $authorizedHandover = $this->handoverService->authorizeException(
        handover: $handover,
        authorizer: $this->manager,
        reason: 'Physical licence inspected; digital copy of IDP verified via embassy confirmation'
    );

    expect($authorizedHandover->is_released)->toBeTrue()
        ->and($authorizedHandover->has_exception)->toBeTrue()
        ->and($authorizedHandover->exception_authorized_by_user_id)->toBe($this->manager->id)
        ->and($authorizedHandover->status)->toBe(HandoverStatus::ExceptionApproved);

    expect(AuditEvent::where('action', 'handover.exception_authorized')->exists())->toBeTrue();
});

test('completeReturn completes return handover when return condition is recorded', function (): void {
    $returnHandover = $this->handoverService->createHandover(
        booking: $this->booking,
        type: HandoverType::Return,
        scheduledAt: Carbon::now('Asia/Colombo')->addDays(3),
        locationType: 'office',
        locationText: 'Spinellanka Return Depot'
    );

    // Cannot complete return without recorded condition
    expect(fn () => $this->handoverService->completeReturn($returnHandover, $this->staff))
        ->toThrow(ReleaseGateBlockedException::class);

    // Record return condition
    $this->handoverService->recordCondition(
        handover: $returnHandover,
        staff: $this->staff,
        odometerKm: 50450,
        fuelLevel: FuelLevel::ThreeQuarters,
        accessoriesChecklist: ['spare_tire' => true]
    );

    $completedReturn = $this->handoverService->completeReturn($returnHandover, $this->staff);

    expect($completedReturn->status)->toBe(HandoverStatus::Completed)
        ->and($completedReturn->completed_at)->not->toBeNull();

    expect(AuditEvent::where('action', 'handover.return_completed')->exists())->toBeTrue();
});
