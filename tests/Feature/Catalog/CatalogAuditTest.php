<?php

use App\Domain\Catalog\Enums\VehicleStatus;
use App\Domain\Catalog\Enums\VehicleType;
use App\Domain\Catalog\Services\DriverAssignmentService;
use App\Domain\Catalog\Services\VehicleService;
use App\Models\AuditEvent;
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

test('vehicle mutations record structured audit events', function (): void {
    $service = app(VehicleService::class);

    // Create
    $vehicle = $service->createVehicle([
        'public_name' => 'Toyota Alphard Executive',
        'internal_fleet_id' => 'FLEET-AUDIT-01',
        'make' => 'Toyota',
        'model' => 'Alphard',
        'year' => 2024,
        'license_plate' => 'WP-AUD-1111',
        'vehicle_type' => VehicleType::LuxuryVan->value,
        'passenger_capacity' => 5,
        'luggage_capacity' => 4,
        'transmission' => 'automatic',
        'turnaround_buffer_minutes' => 90,
    ], $this->owner);

    $createEvent = AuditEvent::where('action', 'fleet.vehicle.created')->first();
    expect($createEvent)->not->toBeNull();
    expect($createEvent->actor_id)->toBe($this->owner->id);
    expect($createEvent->auditable_id)->toBe($vehicle->id);

    // Status change
    $service->updateStatus($vehicle, VehicleStatus::Maintenance, 'Scheduled 50k service', $this->owner);

    $statusEvent = AuditEvent::where('action', 'fleet.vehicle.status_changed')->first();
    expect($statusEvent)->not->toBeNull();
    expect($statusEvent->metadata['old_status'])->toBe('active');
    expect($statusEvent->metadata['new_status'])->toBe('maintenance');
    expect($statusEvent->metadata['reason'])->toBe('Scheduled 50k service');
});

test('driver assignment mutations record structured audit events', function (): void {
    $service = app(DriverAssignmentService::class);
    $vehicle = Vehicle::factory()->create();
    $driver = Driver::factory()->create();

    $start = Carbon::parse('2026-12-01 08:00:00', 'Asia/Colombo');
    $assignment = $service->assignPermanentDriver($vehicle, $driver, $start, 'Initial tie', $this->owner);

    $createEvent = AuditEvent::where('action', 'fleet.driver_assignment.created')->first();
    expect($createEvent)->not->toBeNull();
    expect($createEvent->metadata['vehicle_id'])->toBe($vehicle->id);
    expect($createEvent->metadata['driver_id'])->toBe($driver->id);

    // End assignment
    $end = Carbon::parse('2026-12-15 18:00:00', 'Asia/Colombo');
    $service->endAssignment($assignment, $end, $this->owner);

    $closedEvent = AuditEvent::where('action', 'fleet.driver_assignment.closed')->first();
    expect($closedEvent)->not->toBeNull();
    expect($closedEvent->metadata['assignment_id'])->toBe($assignment->id);
});
