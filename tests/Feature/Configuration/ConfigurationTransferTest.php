<?php

declare(strict_types=1);

use App\Domain\Booking\Enums\BookingStatus;
use App\Domain\Booking\ServiceType;
use App\Domain\Catalog\Enums\MediaType;
use App\Domain\Catalog\Enums\VehicleStatus;
use App\Domain\Catalog\Services\DriverAssignmentService;
use App\Domain\Catalog\Services\DriverService;
use App\Domain\Catalog\Services\ExtraService;
use App\Domain\Catalog\Services\VehicleService;
use App\Domain\Configuration\Enums\PackageClassification;
use App\Domain\Configuration\Exceptions\ConfigurationRollbackException;
use App\Domain\Configuration\Exceptions\ConfigurationSecurityException;
use App\Domain\Configuration\Exceptions\ConfigurationValidationException;
use App\Domain\Configuration\Services\ConfigurationCanonicalizer;
use App\Domain\Configuration\Services\ConfigurationTransferService;
use App\Domain\Identity\Role;
use App\Domain\Pricing\DTOs\EstimateRequestData;
use App\Domain\Pricing\Services\EstimateService;
use App\Domain\Pricing\Services\ExchangeRateService;
use App\Domain\Pricing\Services\PricingRuleService;
use App\Domain\Pricing\Services\PricingService;
use App\Domain\Pricing\Services\RentPolicyService;
use App\Filament\Pages\ConfigurationManagement;
use App\Models\AuditEvent;
use App\Models\Booking;
use App\Models\ConfigurationSnapshot;
use App\Models\EstimateItem;
use App\Models\PricingRule;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\Carbon;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\StagingSyntheticFixturesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RoleAndPermissionSeeder::class);
});

it('exports, dry-runs, idempotently imports, and rolls back versioned 5-vehicle configuration with audit history', function (): void {
    $owner = User::factory()->create(['is_active' => true]);
    $owner->assignRole(Role::OwnerAdmin->value);

    app(StagingSyntheticFixturesSeeder::class)->run(
        app(VehicleService::class),
        app(DriverService::class),
        app(DriverAssignmentService::class),
        app(ExtraService::class),
        app(PricingRuleService::class),
        app(ExchangeRateService::class),
        app(RentPolicyService::class),
    );

    /** @var ConfigurationTransferService $service */
    $service = app(ConfigurationTransferService::class);
    /** @var ConfigurationCanonicalizer $canonicalizer */
    $canonicalizer = app(ConfigurationCanonicalizer::class);

    $packageV1 = $service->export(PackageClassification::StagingSynthetic, $owner, ['is_synthetic_staging' => true], 'Baseline v1');

    expect($packageV1['schema_version'])->toBe('1.0')
        ->and($packageV1['record_counts']['vehicles'])->toBe(5)
        ->and($packageV1['exported_by_role'])->not->toContain('@')
        ->and($packageV1['checksum_sha256'])->toBe($canonicalizer->computeChecksum($packageV1));

    // Dry-run validation reports zero changes against current DB and makes no snapshot
    $dryResult = $service->import($packageV1, $owner, dryRun: true);
    expect($dryResult->dryRun)->toBeTrue()
        ->and($dryResult->snapshot)->toBeNull()
        ->and($dryResult->driftReport->hasChanges())->toBeFalse();
    expect(ConfigurationSnapshot::count())->toBe(0);

    // Apply v1 -> captures baseline + v1 active snapshot
    $applyV1 = $service->import($packageV1, $owner, dryRun: false, notes: 'Apply v1');
    expect($applyV1->snapshot)->not->toBeNull()
        ->and($applyV1->snapshot?->is_active)->toBeTrue()
        ->and(Vehicle::where('status', '!=', 'retired')->count())->toBe(5);

    $snapshotCountAfterV1 = ConfigurationSnapshot::count();

    // Repeat identical import is an idempotent no-op (no duplicate vehicles, no version bump)
    $repeatResult = $service->import($packageV1, $owner, dryRun: false, notes: 'Repeat v1');
    expect($repeatResult->wasNoOp)->toBeTrue()
        ->and(ConfigurationSnapshot::count())->toBe($snapshotCountAfterV1)
        ->and(Vehicle::where('status', '!=', 'retired')->count())->toBe(5);

    // Create historical Estimate linked to current pricing rule to prove FK preservation on rule change
    $estimateService = app(EstimateService::class);
    $pricingService = app(PricingService::class);
    $pickupVehicle = Vehicle::where('internal_fleet_id', 'SYNTH-VEH-01')->firstOrFail();
    $req = new EstimateRequestData(
        serviceType: ServiceType::AirportPickup,
        vehicleId: $pickupVehicle->id,
        serviceDate: Carbon::now('Asia/Colombo')->addDays(5),
        distanceKm: 35.0,
    );
    $estResult = $pricingService->calculateEstimate($req);
    $estimate = $estimateService->snapshotEstimate($estResult, $req);
    $linkedRuleId = $estimate->pricing_rule_id;
    expect($linkedRuleId)->not->toBeNull();
    expect(EstimateItem::where('estimate_id', $estimate->id)->count())->toBeGreaterThan(0);

    // Modify package v2: swap plates between vehicles 01 & 02, update rate & pricing rule
    $packageV2 = $packageV1;
    $plate0 = $packageV2['payload']['vehicles'][0]['license_plate'];
    $plate1 = $packageV2['payload']['vehicles'][1]['license_plate'];
    $packageV2['payload']['vehicles'][0]['license_plate'] = $plate1;
    $packageV2['payload']['vehicles'][1]['license_plate'] = $plate0;
    $packageV2['payload']['vehicles'][0]['public_name'] = 'SYNTHETIC Sedan 01 Updated';
    $packageV2['payload']['exchange_rate_bases'][0]['rate'] = '312.0000';
    $packageV2['payload']['exchange_rate_bases'][0]['source_note'] = 'SYNTHETIC RATE V2';
    $packageV2['payload']['pricing_rules'][0]['components'][0]['amount_minor_units'] = 3500;
    $packageV2['checksum_sha256'] = $canonicalizer->computeChecksum($packageV2);

    $dryV2 = $service->validateAndDryRun($packageV2);
    expect($dryV2->hasChanges())->toBeTrue()
        ->and($dryV2->updated['vehicles'])->toContain('SYNTH-VEH-01', 'SYNTH-VEH-02');

    $applyV2 = $service->import($packageV2, $owner, dryRun: false, notes: 'Apply v2');
    expect($applyV2->wasNoOp)->toBeFalse()
        ->and(Vehicle::where('internal_fleet_id', 'SYNTH-VEH-01')->value('license_plate'))->toBe($plate1)
        ->and(Vehicle::where('internal_fleet_id', 'SYNTH-VEH-02')->value('license_plate'))->toBe($plate0)
        ->and((string) app(ExchangeRateService::class)->getLatestRate()?->rate)->toBe('312.0000')
        ->and(PricingRule::find($linkedRuleId)?->is_active)->toBeFalse();

    // Historical estimate & items remain intact!
    expect($estimate->fresh()?->pricing_rule_id)->toBe($linkedRuleId);

    // Roll back to v1 snapshot
    $rollbackResult = $service->rollback($applyV1->snapshot?->id, $owner, 'Revert v2 plate & rate change');
    expect($rollbackResult->snapshot?->rolled_back_from_id)->toBe($applyV1->snapshot?->id)
        ->and(Vehicle::where('internal_fleet_id', 'SYNTH-VEH-01')->value('license_plate'))->toBe($plate0)
        ->and(Vehicle::where('internal_fleet_id', 'SYNTH-VEH-01')->value('public_name'))->toBe('SYNTHETIC Sedan 01 (Staging Fixture)')
        ->and((string) app(ExchangeRateService::class)->getLatestRate()?->rate)->toBe('308.5000');

    expect(AuditEvent::where('action', 'configuration.exported')->exists())->toBeTrue()
        ->and(AuditEvent::where('action', 'configuration.dry_run_validated')->exists())->toBeTrue()
        ->and(AuditEvent::where('action', 'configuration.imported')->count())->toBe(2)
        ->and(AuditEvent::where('action', 'configuration.rolled_back')->exists())->toBeTrue();
});

it('rejects schema mismatch, checksum mismatch, corrupt/partial packages, duplicate vehicles, and secret or PII leakage', function (): void {
    $owner = User::factory()->create(['is_active' => true]);
    $owner->assignRole(Role::OwnerAdmin->value);

    app(StagingSyntheticFixturesSeeder::class)->run(
        app(VehicleService::class),
        app(DriverService::class),
        app(DriverAssignmentService::class),
        app(ExtraService::class),
        app(PricingRuleService::class),
        app(ExchangeRateService::class),
        app(RentPolicyService::class),
    );

    $service = app(ConfigurationTransferService::class);
    $canonicalizer = app(ConfigurationCanonicalizer::class);
    $valid = $service->export(PackageClassification::StagingSynthetic, $owner, ['is_synthetic_staging' => true]);

    // 1. Schema mismatch
    $badSchema = $valid;
    $badSchema['schema_version'] = '9.9';
    expect(fn () => $service->validateAndDryRun($badSchema))
        ->toThrow(ConfigurationValidationException::class, 'schema_version');

    // 2. Checksum mismatch (tamper without recomputing checksum)
    $tampered = $valid;
    $tampered['payload']['vehicles'][0]['public_name'] = 'Tampered Name';
    expect(fn () => $service->validateAndDryRun($tampered))
        ->toThrow(ConfigurationValidationException::class, 'checksum_sha256');

    // 3. Partial / missing payload section
    $partial = $valid;
    unset($partial['payload']['rent_policy']);
    expect(fn () => $service->validateAndDryRun($partial))
        ->toThrow(ConfigurationValidationException::class);

    // 4. Record count mismatch
    $badCount = $valid;
    $badCount['record_counts']['vehicles'] = 4;
    $badCount['checksum_sha256'] = $canonicalizer->computeChecksum($badCount);
    expect(fn () => $service->validateAndDryRun($badCount))
        ->toThrow(ConfigurationValidationException::class, 'Record count mismatch');

    // 5. Duplicate vehicle license plate
    $dupPlate = $valid;
    $dupPlate['payload']['vehicles'][1]['license_plate'] = $dupPlate['payload']['vehicles'][0]['license_plate'];
    $dupPlate['checksum_sha256'] = $canonicalizer->computeChecksum($dupPlate);
    expect(fn () => $service->validateAndDryRun($dupPlate))
        ->toThrow(ConfigurationValidationException::class, 'Duplicate license_plate');

    // 6. Fewer than 5 vehicles
    $fourVehicles = $valid;
    array_pop($fourVehicles['payload']['vehicles']);
    $fourVehicles['record_counts']['vehicles'] = 4;
    $fourVehicles['checksum_sha256'] = $canonicalizer->computeChecksum($fourVehicles);
    expect(fn () => $service->validateAndDryRun($fourVehicles))
        ->toThrow(ConfigurationValidationException::class, 'exactly 5');

    // 7. Forbidden secret/PII keys
    foreach (['phone', 'whatsapp', 'email', 'license_number', 'vin', 'api_key', 'secret', 'drivers'] as $forbiddenKey) {
        $leakedKey = $valid;
        $leakedKey['payload']['vehicles'][0][$forbiddenKey] = 'leaked-value';
        $leakedKey['checksum_sha256'] = $canonicalizer->computeChecksum($leakedKey);
        expect(fn () => $service->validateAndDryRun($leakedKey))
            ->toThrow(ConfigurationSecurityException::class);
    }

    // 8. Forbidden secret/PII string values (Google API key, raw email, E.164 phone)
    foreach (['AIzaSyDummyLeakedKey123456789012345', 'driver@realperson.com', '+94771234567'] as $secretVal) {
        $leakedVal = $valid;
        $leakedVal['payload']['vehicles'][0]['public_name'] = "Van {$secretVal}";
        $leakedVal['checksum_sha256'] = $canonicalizer->computeChecksum($leakedVal);
        expect(fn () => $service->validateAndDryRun($leakedVal))
            ->toThrow(ConfigurationSecurityException::class);
    }

    // 9. Legitimate English words like "WhatsApp" or "email" in RentPolicy text must NOT trigger false-positive rejection
    $proseSafe = $valid;
    $proseSafe['payload']['rent_policy']['prohibited_roads_text'] = 'Paved roads only; emergency WhatsApp and email support active.';
    $proseSafe['checksum_sha256'] = $canonicalizer->computeChecksum($proseSafe);
    expect($service->validateAndDryRun($proseSafe)->isValid)->toBeTrue();
});

it('blocks production export when synthetic fixtures are present and blocks staging_synthetic in production', function (): void {
    $owner = User::factory()->create(['is_active' => true]);
    $owner->assignRole(Role::OwnerAdmin->value);

    app(StagingSyntheticFixturesSeeder::class)->run(
        app(VehicleService::class),
        app(DriverService::class),
        app(DriverAssignmentService::class),
        app(ExtraService::class),
        app(PricingRuleService::class),
        app(ExchangeRateService::class),
        app(RentPolicyService::class),
    );

    $service = app(ConfigurationTransferService::class);

    // ProductionApproved export rejects synthetic DB records
    expect(fn () => $service->export(
        PackageClassification::ProductionApproved,
        $owner,
        ['owner' => 'Lead', 'approved_by' => 'Lead', 'approved_at' => '2026-11-27', 'effective_date' => '2026-11-27']
    ))->toThrow(ConfigurationSecurityException::class, 'synthetic');

    // StagingSynthetic blocked when environment is production
    $stagingPkg = $service->export(PackageClassification::StagingSynthetic, $owner, ['is_synthetic_staging' => true]);
    app()->detectEnvironment(fn (): string => 'production');
    try {
        expect(fn () => $service->validateAndDryRun($stagingPkg))
            ->toThrow(ConfigurationSecurityException::class, 'production');
    } finally {
        app()->detectEnvironment(fn (): string => 'testing');
    }
});

it('supports CLI export, dry-run/apply import, rollback, and Filament page access control', function (): void {
    $owner = User::factory()->create(['is_active' => true]);
    $owner->assignRole(Role::OwnerAdmin->value);
    $staff = User::factory()->create(['is_active' => true]);
    $staff->assignRole(Role::Staff->value);

    app(StagingSyntheticFixturesSeeder::class)->run(
        app(VehicleService::class),
        app(DriverService::class),
        app(DriverAssignmentService::class),
        app(ExtraService::class),
        app(PricingRuleService::class),
        app(ExchangeRateService::class),
        app(RentPolicyService::class),
    );

    $path = storage_path('framework/testing/phase16_config_export_test.json');
    if (File::exists($path)) {
        File::delete($path);
    }

    try {
        $exitExport = Artisan::call('config:export-package', [
            'path' => $path,
            '--classification' => 'staging_synthetic',
            '--actor' => $owner->email,
            '--force' => true,
        ]);
        expect($exitExport)->toBe(0)
            ->and(File::exists($path))->toBeTrue();

        $exitDry = Artisan::call('config:import-package', [
            'path' => $path,
            '--actor' => $owner->email,
        ]);
        expect($exitDry)->toBe(0)
            ->and(ConfigurationSnapshot::count())->toBe(0);

        $exitApply = Artisan::call('config:import-package', [
            'path' => $path,
            '--apply' => true,
            '--force' => true,
            '--actor' => $owner->email,
        ]);
        expect($exitApply)->toBe(0)
            ->and(ConfigurationSnapshot::where('is_active', true)->count())->toBe(1);

        $exitRollback = Artisan::call('config:rollback-package', [
            '--force' => true,
            '--actor' => $owner->email,
            '--reason' => 'CLI rollback test',
        ]);
        expect($exitRollback)->toBe(0)
            ->and(ConfigurationSnapshot::where('is_active', true)->first()?->rolled_back_from_id)->not->toBeNull();
    } finally {
        if (File::exists($path)) {
            File::delete($path);
        }
    }

    // Rollback fails cleanly when no prior snapshot exists after truncation
    ConfigurationSnapshot::query()->delete();
    expect(fn () => app(ConfigurationTransferService::class)->rollback(null, $owner))
        ->toThrow(ConfigurationRollbackException::class);

    // Filament page permission gating
    $this->actingAs($staff);
    expect(ConfigurationManagement::canAccess())->toBeFalse();

    $this->actingAs($owner);
    expect(ConfigurationManagement::canAccess())->toBeTrue();
});

it('rolls back configuration_snapshots migration cleanly', function (): void {
    expect(Schema::hasTable('configuration_snapshots'))->toBeTrue();
    $migration = require database_path('migrations/2026_11_27_100000_create_configuration_snapshots_table.php');
    $migration->down();
    expect(Schema::hasTable('configuration_snapshots'))->toBeFalse();
    $migration->up();
    expect(Schema::hasTable('configuration_snapshots'))->toBeTrue();
});

it('allows owner_admin to trigger password-confirmed rollback on ConfigurationManagement Filament page', function (): void {
    $owner = User::factory()->create([
        'is_active' => true,
        'password' => bcrypt('Password123456!'),
    ]);
    $owner->assignRole(Role::OwnerAdmin->value);

    app(StagingSyntheticFixturesSeeder::class)->run(
        app(VehicleService::class),
        app(DriverService::class),
        app(DriverAssignmentService::class),
        app(ExtraService::class),
        app(PricingRuleService::class),
        app(ExchangeRateService::class),
        app(RentPolicyService::class),
    );

    /** @var ConfigurationTransferService $service */
    $service = app(ConfigurationTransferService::class);
    /** @var ConfigurationCanonicalizer $canonicalizer */
    $canonicalizer = app(ConfigurationCanonicalizer::class);

    $v1Payload = $service->export(PackageClassification::StagingSynthetic, $owner, ['is_synthetic_staging' => true], 'v1');
    $v1Result = $service->import($v1Payload, $owner, dryRun: false, notes: 'v1');

    $v2Payload = $v1Payload;
    $v2Payload['source_revision'] = 'phase16-filament-v2';
    $v2Payload['payload']['vehicles'][0]['public_name'] = 'Updated Name Before Rollback';
    $v2Payload['checksum_sha256'] = $canonicalizer->computeChecksum($v2Payload);
    $service->import($v2Payload, $owner, dryRun: false, notes: 'v2');

    $v1Snapshot = $v1Result->snapshot;
    expect($v1Snapshot)->not->toBeNull();

    Livewire::actingAs($owner)
        ->test(ConfigurationManagement::class)
        ->assertOk()
        ->callAction('rollbackConfiguration', [
            'snapshot_id' => $v1Snapshot->id,
            'reason' => 'Filament revert test',
            'reauth_password' => 'WrongPassword!',
        ])
        ->assertHasActionErrors(['reauth_password']);

    Livewire::actingAs($owner)
        ->test(ConfigurationManagement::class)
        ->callAction('rollbackConfiguration', [
            'snapshot_id' => $v1Snapshot->id,
            'reason' => 'Filament revert test',
            'reauth_password' => 'Password123456!',
        ])
        ->assertHasNoActionErrors();

    expect(Vehicle::where('internal_fleet_id', 'SYNTH-VEH-01')->value('public_name'))
        ->toBe('SYNTHETIC Sedan 01 (Staging Fixture)');
});

it('detects vehicle_media and extra_compatibility drift and blocks retiring a vehicle with active bookings', function (): void {
    $owner = User::factory()->create(['is_active' => true]);
    $owner->assignRole(Role::OwnerAdmin->value);

    app(StagingSyntheticFixturesSeeder::class)->run(
        app(VehicleService::class),
        app(DriverService::class),
        app(DriverAssignmentService::class),
        app(ExtraService::class),
        app(PricingRuleService::class),
        app(ExchangeRateService::class),
        app(RentPolicyService::class),
    );

    /** @var ConfigurationTransferService $service */
    $service = app(ConfigurationTransferService::class);
    /** @var ConfigurationCanonicalizer $canonicalizer */
    $canonicalizer = app(ConfigurationCanonicalizer::class);

    $base = $service->export(PackageClassification::StagingSynthetic, $owner, ['is_synthetic_staging' => true], 'v1');
    $service->import($base, $owner, dryRun: false, notes: 'v1');

    // Add media + change compatibility -> dryRun must detect drift (not no-op) and import must audit media sync
    $mediaType = MediaType::cases()[0]->value;
    $withMedia = $base;
    $withMedia['payload']['vehicle_media'] = [[
        'vehicle_internal_fleet_id' => 'SYNTH-VEH-01',
        'file_path' => 'vehicles/synth/01-front.jpg',
        'disk' => 'public',
        'media_type' => $mediaType,
        'alt_text' => 'Synthetic sedan front angle',
        'photographer' => 'Synthetic Studio',
        'usage_rights' => 'Staging Synthetic Only',
        'sort_order' => 0,
        'is_approved' => true,
    ]];
    $withMedia['payload']['extra_compatibility'][0]['is_compatible'] = false;
    $withMedia['record_counts']['vehicle_media'] = 1;
    $withMedia['checksum_sha256'] = $canonicalizer->computeChecksum($withMedia);

    $drift = $service->validateAndDryRun($withMedia);
    expect($drift->hasChanges())->toBeTrue()
        ->and($drift->added['vehicle_media'])->not->toBeEmpty()
        ->and($drift->updated['extra_compatibility'])->not->toBeEmpty();

    $applied = $service->import($withMedia, $owner, dryRun: false, notes: 'media+compat');
    expect($applied->wasNoOp)->toBeFalse()
        ->and(AuditEvent::where('action', 'catalog.vehicle_media.synced')->exists())->toBeTrue();

    // Active Confirmed booking blocks retiring a vehicle replaced in package
    $vehicleToReplace = Vehicle::where('internal_fleet_id', 'SYNTH-VEH-05')->firstOrFail();
    Booking::factory()->create([
        'vehicle_id' => $vehicleToReplace->id,
        'service_type' => ServiceType::Rent,
        'status' => BookingStatus::Confirmed,
    ]);

    $replaced = $withMedia;
    $replaced['payload']['vehicles'][4]['internal_fleet_id'] = 'SYNTH-VEH-06';
    $replaced['payload']['vehicles'][4]['license_plate'] = 'SYNTH-0006';
    $replaced['checksum_sha256'] = $canonicalizer->computeChecksum($replaced);

    expect(fn () => $service->import($replaced, $owner, dryRun: false, notes: 'replace booked vehicle'))
        ->toThrow(ConfigurationValidationException::class, 'active bookings');
});

it('rejects unknown vehicle_internal_fleet_id/extra_slug or malformed extras/rates and updates tank capacity and reused plate cleanly', function (): void {
    $owner = User::factory()->create(['is_active' => true]);
    $owner->assignRole(Role::OwnerAdmin->value);

    app(StagingSyntheticFixturesSeeder::class)->run(
        app(VehicleService::class),
        app(DriverService::class),
        app(DriverAssignmentService::class),
        app(ExtraService::class),
        app(PricingRuleService::class),
        app(ExchangeRateService::class),
        app(RentPolicyService::class),
    );

    /** @var ConfigurationTransferService $service */
    $service = app(ConfigurationTransferService::class);
    /** @var ConfigurationCanonicalizer $canonicalizer */
    $canonicalizer = app(ConfigurationCanonicalizer::class);

    $base = $service->export(PackageClassification::StagingSynthetic, $owner, ['is_synthetic_staging' => true], 'v1');
    $service->import($base, $owner, dryRun: false, notes: 'v1');

    // Unknown vehicle_internal_fleet_id in pricing rule must fail validation instead of silently creating global rule
    $badRuleFleet = $base;
    $badRuleFleet['payload']['pricing_rules'][0]['vehicle_internal_fleet_id'] = 'UNKNOWN-FLEET-99';
    $badRuleFleet['checksum_sha256'] = $canonicalizer->computeChecksum($badRuleFleet);
    expect(fn () => $service->validateAndDryRun($badRuleFleet))
        ->toThrow(ConfigurationValidationException::class, 'unknown vehicle_internal_fleet_id');

    // Unknown extra_slug in compatibility must fail validation
    $badCompatExtra = $base;
    $badCompatExtra['payload']['extra_compatibility'][0]['extra_slug'] = 'missing-extra-slug';
    $badCompatExtra['checksum_sha256'] = $canonicalizer->computeChecksum($badCompatExtra);
    expect(fn () => $service->validateAndDryRun($badCompatExtra))
        ->toThrow(ConfigurationValidationException::class, 'unknown extra_slug');

    // Malformed rent_policy missing required field fails validation cleanly
    $badRentPolicy = $base;
    unset($badRentPolicy['payload']['rent_policy']['min_driver_age']);
    $badRentPolicy['checksum_sha256'] = $canonicalizer->computeChecksum($badRentPolicy);
    expect(fn () => $service->validateAndDryRun($badRentPolicy))
        ->toThrow(ConfigurationValidationException::class, 'min_driver_age');

    // Fuel tank capacity drift update + replacing a retired vehicle while reusing its exact license_plate
    $updated = $base;
    $updated['payload']['vehicles'][4]['internal_fleet_id'] = 'SYNTH-VEH-06'; // reuses plate SYNTH-0005 of unbooked SYNTH-VEH-05
    $updated['payload']['vehicles'][0]['fuel_tank_capacity_litres'] = 60;
    $updated['checksum_sha256'] = $canonicalizer->computeChecksum($updated);

    $drift = $service->validateAndDryRun($updated);
    expect($drift->hasChanges())->toBeTrue()
        ->and($drift->updated['vehicles'])->toContain('SYNTH-VEH-01');

    $service->import($updated, $owner, dryRun: false, notes: 'tank+plate-reuse');
    expect(Vehicle::where('internal_fleet_id', 'SYNTH-VEH-01')->value('fuel_tank_capacity_litres'))->toBe(60)
        ->and(Vehicle::where('internal_fleet_id', 'SYNTH-VEH-06')->value('license_plate'))->toBe('SYNTH-0005')
        ->and(Vehicle::where('internal_fleet_id', 'SYNTH-VEH-05')->value('status'))->toBe(VehicleStatus::Retired);
});
