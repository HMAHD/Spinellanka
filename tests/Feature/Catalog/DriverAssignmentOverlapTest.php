<?php

use App\Domain\Catalog\Enums\DriverStatus;
use App\Domain\Catalog\Exceptions\DriverAssignmentConflictException;
use App\Domain\Catalog\Services\DriverAssignmentService;
use App\Models\Driver;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

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
});

test('can assign permanent driver to vehicle and auto-closes prior permanent driver', function (): void {
    $service = app(DriverAssignmentService::class);
    $vehicle = Vehicle::factory()->create();
    $driver1 = Driver::factory()->create();
    $driver2 = Driver::factory()->create();

    $start1 = Carbon::parse('2026-12-01 08:00:00', 'Asia/Colombo');
    $assignment1 = $service->assignPermanentDriver($vehicle, $driver1, $start1, 'Initial driver', $this->owner);

    expect($assignment1->is_permanent)->toBeTrue();
    expect($assignment1->ends_at)->toBeNull();

    // Reassign permanent driver to driver2 at a later date
    $start2 = Carbon::parse('2026-12-10 08:00:00', 'Asia/Colombo');
    $assignment2 = $service->assignPermanentDriver($vehicle, $driver2, $start2, 'New tied driver', $this->owner);

    expect($assignment2->is_permanent)->toBeTrue();
    expect($assignment2->ends_at)->toBeNull();

    // Assignment 1 should now be closed at start2
    $assignment1->refresh();
    expect($assignment1->ends_at->toDateTimeString())->toBe($start2->toDateTimeString());
});

test('universal physical driver exclusivity rejects overlapping assignment across vehicles', function (): void {
    $service = app(DriverAssignmentService::class);
    $vehicle1 = Vehicle::factory()->create();
    $vehicle2 = Vehicle::factory()->create();
    $driver = Driver::factory()->create();

    $start = Carbon::parse('2026-12-01 08:00:00', 'Asia/Colombo');
    $end = Carbon::parse('2026-12-05 18:00:00', 'Asia/Colombo');

    // Assign to Vehicle 1
    $service->assignTemporaryDriver($vehicle1, $driver, $start, $end, 'Trip cover', $this->owner);

    // Attempt to assign the same driver to Vehicle 2 during overlapping window
    $overlapStart = Carbon::parse('2026-12-03 10:00:00', 'Asia/Colombo');
    $overlapEnd = Carbon::parse('2026-12-07 18:00:00', 'Asia/Colombo');

    expect(fn () => $service->assignTemporaryDriver($vehicle2, $driver, $overlapStart, $overlapEnd, 'Conflict trip', $this->owner))
        ->toThrow(DriverAssignmentConflictException::class);
});

test('vehicle cannot have two overlapping temporary replacement drivers', function (): void {
    $service = app(DriverAssignmentService::class);
    $vehicle = Vehicle::factory()->create();
    $driver1 = Driver::factory()->create();
    $driver2 = Driver::factory()->create();

    $start = Carbon::parse('2026-12-01 08:00:00', 'Asia/Colombo');
    $end = Carbon::parse('2026-12-05 18:00:00', 'Asia/Colombo');

    $service->assignTemporaryDriver($vehicle, $driver1, $start, $end, 'Cover driver 1', $this->owner);

    // Second driver attempting to cover overlapping window on same vehicle
    $overlapStart = Carbon::parse('2026-12-03 08:00:00', 'Asia/Colombo');
    $overlapEnd = Carbon::parse('2026-12-06 18:00:00', 'Asia/Colombo');

    expect(fn () => $service->assignTemporaryDriver($vehicle, $driver2, $overlapStart, $overlapEnd, 'Cover driver 2', $this->owner))
        ->toThrow(DriverAssignmentConflictException::class);
});

test('temporary driver coexists with permanent base driver and takes precedence in getEffectiveDriver', function (): void {
    $service = app(DriverAssignmentService::class);
    $vehicle = Vehicle::factory()->create();
    $baseDriver = Driver::factory()->create(['public_name' => 'Base Driver']);
    $coverDriver = Driver::factory()->create(['public_name' => 'Cover Driver']);

    $permStart = Carbon::parse('2026-11-01 08:00:00', 'Asia/Colombo');
    $service->assignPermanentDriver($vehicle, $baseDriver, $permStart, 'Permanent base', $this->owner);

    $tempStart = Carbon::parse('2026-12-01 00:00:00', 'Asia/Colombo');
    $tempEnd = Carbon::parse('2026-12-05 23:59:59', 'Asia/Colombo');
    $service->assignTemporaryDriver($vehicle, $coverDriver, $tempStart, $tempEnd, 'Vacation coverage', $this->owner);

    // Before temporary window -> Base driver
    $beforeDate = Carbon::parse('2026-11-20 12:00:00', 'Asia/Colombo');
    expect($service->getEffectiveDriver($vehicle, $beforeDate)?->id)->toBe($baseDriver->id);

    // Inside temporary window -> Cover driver
    $duringDate = Carbon::parse('2026-12-03 12:00:00', 'Asia/Colombo');
    expect($service->getEffectiveDriver($vehicle, $duringDate)?->id)->toBe($coverDriver->id);

    // After temporary window -> Base driver
    $afterDate = Carbon::parse('2026-12-07 12:00:00', 'Asia/Colombo');
    expect($service->getEffectiveDriver($vehicle, $afterDate)?->id)->toBe($baseDriver->id);
});

test('cannot assign inactive driver to a vehicle', function (): void {
    $service = app(DriverAssignmentService::class);
    $vehicle = Vehicle::factory()->create();
    $inactiveDriver = Driver::factory()->create(['status' => DriverStatus::Inactive]);

    $start = Carbon::parse('2026-12-01 08:00:00', 'Asia/Colombo');

    expect(fn () => $service->assignPermanentDriver($vehicle, $inactiveDriver, $start, null, $this->owner))
        ->toThrow(DriverAssignmentConflictException::class);
});

