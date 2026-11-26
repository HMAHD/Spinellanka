<?php

declare(strict_types=1);

namespace Tests\Feature\Operations;

use App\Domain\Booking\Enums\BookingStatus;
use App\Domain\Booking\ServiceType;
use App\Domain\Operations\Enums\FuelLevel;
use App\Domain\Operations\Enums\HandoverType;
use App\Domain\Operations\Enums\IncidentFinancialEntryType;
use App\Domain\Operations\Enums\IncidentFinancialStatus;
use App\Domain\Operations\Enums\IncidentSeverity;
use App\Domain\Operations\Enums\IncidentStatus;
use App\Domain\Operations\Enums\IncidentType;
use App\Domain\Operations\Exceptions\OdometerRollbackAnomalyException;
use App\Domain\Operations\Services\ConditionComparisonService;
use App\Domain\Operations\Services\HandoverService;
use App\Models\Booking;
use App\Models\Incident;
use App\Models\IncidentFinancialEntry;
use App\Models\RentDetail;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed();
    $this->handoverService = app(HandoverService::class);
    $this->comparisonService = app(ConditionComparisonService::class);

    $this->staff = User::create([
        'name' => 'Field Staff Amal',
        'email' => 'amal@spinellanka.test',
        'password' => Hash::make('Secret123456!'),
        'is_active' => true,
        'token_version' => 1,
    ]);

    $this->vehicle = Vehicle::factory()->create([
        'fuel_tank_capacity_litres' => 60,
    ]);

    $this->booking = Booking::factory()->create([
        'reference' => 'BK-COND-TEST-001',
        'service_type' => ServiceType::Rent,
        'status' => BookingStatus::Confirmed,
        'vehicle_id' => $this->vehicle->id,
    ]);

    $this->rentDetail = RentDetail::factory()->create([
        'booking_id' => $this->booking->id,
        'duration_days' => 3,
        'included_distance_km' => 300,
        'excess_mileage_rate_minor_units' => 50,
    ]);

    $this->collectionHandover = $this->handoverService->createHandover(
        booking: $this->booking,
        type: HandoverType::Collection,
        scheduledAt: Carbon::now('Asia/Colombo')->subDays(3),
        locationType: 'office',
        locationText: 'Colombo Depot',
        staffUser: $this->staff
    );

    $this->handoverService->recordCondition(
        handover: $this->collectionHandover,
        staff: $this->staff,
        odometerKm: 50000,
        fuelLevel: FuelLevel::Full,
        accessoriesChecklist: ['spare_tire' => true]
    );

    $this->returnHandover = $this->handoverService->createHandover(
        booking: $this->booking,
        type: HandoverType::Return,
        scheduledAt: Carbon::now('Asia/Colombo'),
        locationType: 'office',
        locationText: 'Colombo Depot',
        staffUser: $this->staff
    );
});

test('compareConditions throws OdometerRollbackAnomalyException when return odometer is lower than collection', function (): void {
    $this->handoverService->recordCondition(
        handover: $this->returnHandover,
        staff: $this->staff,
        odometerKm: 49500, // 500 km less than collection (Rollback anomaly)
        fuelLevel: FuelLevel::Full,
        accessoriesChecklist: ['spare_tire' => true]
    );

    expect(fn () => $this->comparisonService->compareConditions($this->collectionHandover, $this->returnHandover))
        ->toThrow(OdometerRollbackAnomalyException::class);
});

test('compareConditions requires zero km verification for multi-day rentals', function (): void {
    // 0 km driven on 3-day rental without verification flag throws InvalidArgumentException
    $this->handoverService->recordCondition(
        handover: $this->returnHandover,
        staff: $this->staff,
        odometerKm: 50000,
        fuelLevel: FuelLevel::Full,
        accessoriesChecklist: ['spare_tire' => true],
        isZeroKmVerified: false
    );

    expect(fn () => $this->comparisonService->compareConditions($this->collectionHandover, $this->returnHandover))
        ->toThrow(InvalidArgumentException::class, 'Multi-day rental with 0 kilometers driven must have explicit staff verification');

    // With explicit verification and reason recorded, passes cleanly
    $this->handoverService->recordCondition(
        handover: $this->returnHandover,
        staff: $this->staff,
        odometerKm: 50000,
        fuelLevel: FuelLevel::Full,
        accessoriesChecklist: ['spare_tire' => true],
        isZeroKmVerified: true,
        zeroKmReason: 'Customer vehicle remained parked at villa; client utilized private chauffeur'
    );

    $result = $this->comparisonService->compareConditions($this->collectionHandover, $this->returnHandover);
    expect($result->distanceDrivenKm)->toBe(0)
        ->and($result->excessDistanceKm)->toBe(0);
});

test('compareConditions accurately computes excess mileage, fuel shortfall, and smoking penalties', function (): void {
    // Driven: 50450 - 50000 = 450 km. Included: 300 km -> Excess: 150 km.
    // Excess charge: 150 * 50 = 7500 minor units.
    // Fuel: Collection = Full (1.0), Return = Half (0.5) -> Shortfall fraction 0.5.
    // Tank: 60L -> Shortfall: 30.0L.
    // Smoking: true -> penalty applies.
    $this->handoverService->recordCondition(
        handover: $this->returnHandover,
        staff: $this->staff,
        odometerKm: 50450,
        fuelLevel: FuelLevel::Half,
        accessoriesChecklist: ['spare_tire' => true],
        isClean: false,
        smokingDetected: true,
        exteriorNotes: 'Scratch on left bumper'
    );

    $result = $this->comparisonService->compareConditions($this->collectionHandover, $this->returnHandover);

    expect($result->distanceDrivenKm)->toBe(450)
        ->and($result->excessDistanceKm)->toBe(150)
        ->and($result->excessMileageChargeMinorUnits)->toBe(7500)
        ->and($result->fuelShortfallLitres)->toBe(30.0)
        ->and($result->smokingDetected)->toBeTrue()
        ->and($result->smokingPenaltyMinorUnits)->toBeGreaterThan(0)
        ->and($result->damagesRecorded)->toContain('Exterior: Scratch on left bumper');
});

test('compareConditions bridges existing incident charges to prevent double billing', function (): void {
    $this->handoverService->recordCondition(
        handover: $this->returnHandover,
        staff: $this->staff,
        odometerKm: 50200,
        fuelLevel: FuelLevel::Full,
        accessoriesChecklist: ['spare_tire' => true]
    );

    // Create an active incident with a recorded customer charge
    $incident = Incident::create([
        'incident_number' => Incident::generateIncidentNumber(),
        'booking_id' => $this->booking->id,
        'vehicle_id' => $this->vehicle->id,
        'type' => IncidentType::Damage,
        'severity' => IncidentSeverity::Moderate,
        'status' => IncidentStatus::ActionRequired,
        'occurred_at' => Carbon::now('Asia/Colombo')->subDay(),
        'reported_at' => Carbon::now('Asia/Colombo')->subDay(),
        'reported_by_name' => $this->staff->name,
        'location_text' => 'Galle Road',
        'description' => 'Side mirror impact: Left wing mirror damaged',
    ]);

    IncidentFinancialEntry::create([
        'incident_id' => $incident->id,
        'entry_type' => IncidentFinancialEntryType::CustomerCharge,
        'status' => IncidentFinancialStatus::Pending,
        'currency' => 'USD',
        'amount_minor_units' => 12000,
        'description' => 'Mirror replacement fee',
        'created_by_user_id' => $this->staff->id,
    ]);

    $result = $this->comparisonService->compareConditions($this->collectionHandover, $this->returnHandover);

    expect($result->incidentDeductions)->toHaveCount(1)
        ->and($result->incidentDeductions[0]['incident_id'])->toBe($incident->id)
        ->and($result->incidentDeductions[0]['amount_minor_units'])->toBe(12000);
});
