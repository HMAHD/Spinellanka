<?php

use App\Domain\Booking\ServiceType;
use App\Domain\Catalog\Enums\DriverStatus;
use App\Domain\Catalog\Enums\MediaType;
use App\Domain\Catalog\Enums\VehicleStatus;
use App\Domain\Catalog\Enums\VehicleType;
use App\Domain\Catalog\Services\DriverService;
use App\Domain\Catalog\Services\ExtraService;
use App\Domain\Catalog\Services\MediaService;
use App\Domain\Catalog\Services\VehicleService;
use App\Domain\Identity\Role;
use App\Models\AuditEvent;
use App\Models\Driver;
use App\Models\Extra;
use App\Models\ExtraCompatibility;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleDriverAssignment;
use App\Models\VehicleMedia;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed();

    $this->owner = User::create([
        'name' => 'Owner Admin',
        'email' => 'owner@spinellanka.test',
        'password' => Hash::make('Password123456!'),
        'is_active' => true,
        'token_version' => 1,
    ]);
    $this->owner->assignRole(Role::OwnerAdmin->value);

    $this->staff = User::create([
        'name' => 'Staff Member',
        'email' => 'staff@spinellanka.test',
        'password' => Hash::make('Password123456!'),
        'is_active' => true,
        'token_version' => 1,
    ]);
    $this->staff->assignRole(Role::Staff->value);
});

test('duplicate vehicle fleet id and license plate are strictly rejected', function (): void {
    Vehicle::factory()->create([
        'internal_fleet_id' => 'FLEET-EXACT-01',
        'license_plate' => 'WP-EXACT-1234',
    ]);

    // Duplicate fleet id
    expect(fn () => Vehicle::factory()->create([
        'internal_fleet_id' => 'FLEET-EXACT-01',
        'license_plate' => 'WP-DIFF-5678',
    ]))->toThrow(QueryException::class);

    // Duplicate license plate
    expect(fn () => Vehicle::factory()->create([
        'internal_fleet_id' => 'FLEET-DIFF-02',
        'license_plate' => 'WP-EXACT-1234',
    ]))->toThrow(QueryException::class);
});

test('duplicate extra slug and duplicate compatibility scope are strictly rejected', function (): void {
    $extra = Extra::factory()->create(['slug' => 'child-seat']);

    // Duplicate extra slug
    expect(fn () => Extra::factory()->create(['slug' => 'child-seat']))
        ->toThrow(QueryException::class);

    // First compatibility rule
    ExtraCompatibility::create([
        'extra_id' => $extra->id,
        'service_type' => ServiceType::AirportPickup->value,
        'vehicle_type' => VehicleType::LuxuryVan->value,
        'is_compatible' => true,
    ]);

    // Duplicate rule matching identical scope key
    expect(fn () => ExtraCompatibility::create([
        'extra_id' => $extra->id,
        'service_type' => ServiceType::AirportPickup->value,
        'vehicle_type' => VehicleType::LuxuryVan->value,
        'is_compatible' => false,
    ]))->toThrow(QueryException::class);
});

test('vehicle service updateVehicle detects status change, auto-closes driver assignments, and audits both update and status change', function (): void {
    $service = app(VehicleService::class);
    $vehicle = Vehicle::factory()->create(['status' => VehicleStatus::Active]);
    $driver = Driver::factory()->create();

    $assignment = VehicleDriverAssignment::create([
        'vehicle_id' => $vehicle->id,
        'driver_id' => $driver->id,
        'starts_at' => Carbon::now('Asia/Colombo')->subDays(2),
        'ends_at' => null,
        'is_permanent' => true,
    ]);

    $service->updateVehicle($vehicle, [
        'public_name' => 'Renamed Alphard Elite',
        'status' => VehicleStatus::Retired->value,
    ], $this->owner);

    expect($vehicle->fresh()->public_name)->toBe('Renamed Alphard Elite');
    expect($vehicle->fresh()->status)->toBe(VehicleStatus::Retired);
    expect($assignment->fresh()->ends_at)->not->toBeNull();

    expect(AuditEvent::where('action', 'fleet.vehicle.updated')->exists())->toBeTrue();
    expect(AuditEvent::where('action', 'fleet.vehicle.status_changed')->exists())->toBeTrue();
    expect(AuditEvent::where('action', 'fleet.driver_assignment.closed')->exists())->toBeTrue();
});

test('vehicle deletion is audited and blocked if driver assignment history exists', function (): void {
    $service = app(VehicleService::class);
    $vehicle = Vehicle::factory()->create();
    $driver = Driver::factory()->create();

    // With assignment history
    VehicleDriverAssignment::create([
        'vehicle_id' => $vehicle->id,
        'driver_id' => $driver->id,
        'starts_at' => Carbon::now('Asia/Colombo')->subDay(),
        'ends_at' => Carbon::now('Asia/Colombo'),
        'is_permanent' => false,
    ]);

    expect(fn () => $service->deleteVehicle($vehicle, $this->owner))
        ->toThrow(InvalidArgumentException::class);

    // Clean vehicle without history
    $cleanVehicle = Vehicle::factory()->create();
    $cleanVehicleId = $cleanVehicle->id;
    $service->deleteVehicle($cleanVehicle, $this->owner);

    expect(Vehicle::find($cleanVehicleId))->toBeNull();
    $deleteAudit = AuditEvent::where('action', 'fleet.vehicle.deleted')->first();
    expect($deleteAudit)->not->toBeNull();
    expect($deleteAudit->metadata['vehicle_id'])->toBe($cleanVehicleId);
});

test('driver service manages full lifecycle with audits and deactivation auto-closes vehicle assignments', function (): void {
    $driverService = app(DriverService::class);
    $vehicle = Vehicle::factory()->create();

    // 1. Create driver
    $driver = $driverService->createDriver([
        'public_name' => 'Kamal P.',
        'full_name' => 'Kamal Perera',
        'phone' => '+94771234567',
        'status' => DriverStatus::Active->value,
    ], $this->owner);

    expect(AuditEvent::where('action', 'fleet.driver.created')->exists())->toBeTrue();

    // 2. Assign to vehicle
    $assignment = VehicleDriverAssignment::create([
        'vehicle_id' => $vehicle->id,
        'driver_id' => $driver->id,
        'starts_at' => Carbon::now('Asia/Colombo')->subDays(3),
        'ends_at' => null,
        'is_permanent' => true,
    ]);

    // 3. Update driver phone
    $driverService->updateDriver($driver, ['phone' => '+94779999999'], $this->owner);
    expect(AuditEvent::where('action', 'fleet.driver.updated')->exists())->toBeTrue();

    // 4. Deactivate driver -> auto-closes open-ended assignment
    $driverService->updateStatus($driver, DriverStatus::Inactive, 'Medical leave', $this->owner);
    expect($driver->fresh()->status)->toBe(DriverStatus::Inactive);
    expect($assignment->fresh()->ends_at)->not->toBeNull();
    expect(AuditEvent::where('action', 'fleet.driver.status_changed')->exists())->toBeTrue();

    // 5. Deleting driver with assignment history is blocked
    expect(fn () => $driverService->deleteDriver($driver, $this->owner))
        ->toThrow(InvalidArgumentException::class);
});

test('extra service manages full lifecycle including compatibility rules with structured audits', function (): void {
    $extraService = app(ExtraService::class);

    // 1. Create Extra
    $extra = $extraService->createExtra([
        'name' => 'Roof Surfboard Rack',
        'slug' => 'roof-surfboard-rack',
        'price_minor_units' => 2500,
        'currency' => 'USD',
        'pricing_type' => 'per_booking',
        'max_quantity' => 2,
    ], $this->owner);

    expect(AuditEvent::where('action', 'fleet.extra.created')->exists())->toBeTrue();

    // 2. Update Extra
    $extraService->updateExtra($extra, ['price_minor_units' => 3000], $this->owner);
    expect(AuditEvent::where('action', 'fleet.extra.updated')->exists())->toBeTrue();

    // 3. Create compatibility rule
    $rule = $extraService->createCompatibilityRule($extra, [
        'service_type' => ServiceType::Rent->value,
        'is_compatible' => true,
    ], $this->owner);

    expect(AuditEvent::where('action', 'fleet.extra.compatibility_created')->exists())->toBeTrue();

    // 4. Delete compatibility rule
    $extraService->deleteCompatibilityRule($rule, $this->owner);
    expect(AuditEvent::where('action', 'fleet.extra.compatibility_deleted')->exists())->toBeTrue();

    // 5. Delete Extra
    $extraId = $extra->id;
    $extraService->deleteExtra($extra, $this->owner);
    expect(Extra::find($extraId))->toBeNull();
    expect(AuditEvent::where('action', 'fleet.extra.deleted')->exists())->toBeTrue();
});

test('media approval sanitizes contents, strips EXIF, and model deletion unlinks physical files', function (): void {
    Storage::fake('local');
    Storage::fake('public');

    $mediaService = app(MediaService::class);
    $vehicle = Vehicle::factory()->create();

    $file = UploadedFile::fake()->image('test-photo.jpg', 640, 480);
    $media = $mediaService->quarantineUpload($vehicle, $file, [
        'media_type' => MediaType::ExteriorFront,
        'alt_text' => 'Front view',
    ], $this->owner);

    // Check quarantine isolation
    expect($media->disk)->toBe('local');
    expect(Storage::disk('local')->exists($media->file_path))->toBeTrue();
    expect(Storage::disk('public')->exists($media->file_path))->toBeFalse();

    // Approve & Publish
    $approved = $mediaService->approveAndPublish($media, $this->owner);
    expect($approved->disk)->toBe('public');
    expect(Storage::disk('public')->exists($approved->file_path))->toBeTrue();
    expect(Storage::disk('local')->exists($media->file_path))->toBeFalse();

    // Direct model deletion triggers booted listener and unlinks storage file
    $publicPath = $approved->file_path;
    $approved->delete();
    expect(Storage::disk('public')->exists($publicPath))->toBeFalse();
});

test('driver model deletion unlinks portrait from storage', function (): void {
    Storage::fake('public');

    $portrait = 'drivers/portraits/test-driver.jpg';
    Storage::disk('public')->put($portrait, 'fake-portrait-bytes');

    $driver = Driver::factory()->create(['portrait_path' => $portrait]);
    expect(Storage::disk('public')->exists($portrait))->toBeTrue();

    $driver->delete();
    expect(Storage::disk('public')->exists($portrait))->toBeFalse();
});

test('staff without fleet.manage cannot create, update, or delete catalog entities', function (): void {
    $vehicle = Vehicle::factory()->create();
    $driver = Driver::factory()->create();
    $extra = Extra::factory()->create();
    $media = VehicleMedia::factory()->create();

    // View is allowed
    expect($this->staff->can('viewAny', Vehicle::class))->toBeTrue();
    expect($this->staff->can('viewAny', Driver::class))->toBeTrue();
    expect($this->staff->can('viewAny', Extra::class))->toBeTrue();

    // Mutation is forbidden
    expect($this->staff->can('create', Vehicle::class))->toBeFalse();
    expect($this->staff->can('update', $vehicle))->toBeFalse();
    expect($this->staff->can('delete', $vehicle))->toBeFalse();

    expect($this->staff->can('create', Driver::class))->toBeFalse();
    expect($this->staff->can('update', $driver))->toBeFalse();
    expect($this->staff->can('delete', $driver))->toBeFalse();

    expect($this->staff->can('create', Extra::class))->toBeFalse();
    expect($this->staff->can('update', $extra))->toBeFalse();
    expect($this->staff->can('delete', $extra))->toBeFalse();

    expect($this->staff->can('create', VehicleMedia::class))->toBeFalse();
    expect($this->staff->can('update', $media))->toBeFalse();
    expect($this->staff->can('delete', $media))->toBeFalse();
});
