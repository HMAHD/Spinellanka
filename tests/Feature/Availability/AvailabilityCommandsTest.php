<?php

declare(strict_types=1);

use App\Domain\Availability\Enums\HoldStatus;
use App\Domain\Availability\Enums\ScheduleBlockKind;
use App\Domain\Availability\Enums\ScheduleBlockStatus;
use App\Domain\Availability\Services\AvailabilityService;
use App\Domain\Booking\ServiceType;
use App\Models\BookingHold;
use App\Models\Vehicle;
use App\Models\VehicleScheduleBlock;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('availability:expire-holds expires stale holds and reports count', function (): void {
    $vehicle = Vehicle::factory()->create();
    $service = app(AvailabilityService::class);

    $startsAt = Carbon::now('Asia/Colombo')->addDays(1);
    $endsAt = (clone $startsAt)->addHours(2);

    $hold = $service->createHold($vehicle, $startsAt, $endsAt, ServiceType::Rent);

    // Backdate the hold's expires_at to 5 minutes ago
    BookingHold::where('id', $hold->hold->id)->update([
        'expires_at' => Carbon::now('Asia/Colombo')->subMinutes(5),
    ]);

    $this->artisan('availability:expire-holds')
        ->expectsOutputToContain('Scanning for stale booking holds...')
        ->expectsOutputToContain('Expired 1 stale hold(s).')
        ->assertExitCode(0);

    $hold->hold->refresh();
    expect($hold->hold->status)->toBe(HoldStatus::Expired);
});

test('availability:cleanup-stale purges expired blocks older than retention days', function (): void {
    $vehicle = Vehicle::factory()->create();

    // Expired block from 40 days ago
    $pastDate = Carbon::now('Asia/Colombo')->subDays(40);
    $oldBlock = VehicleScheduleBlock::factory()->create([
        'vehicle_id' => $vehicle->id,
        'kind' => ScheduleBlockKind::CheckoutHold,
        'status' => ScheduleBlockStatus::Expired,
        'effective_start' => $pastDate,
        'effective_end' => (clone $pastDate)->addHours(2),
        'blocks_availability' => false,
        'created_at' => $pastDate,
        'updated_at' => $pastDate,
    ]);

    // Active block should never be purged even if effective_start is past
    $activeBlock = VehicleScheduleBlock::factory()->create([
        'vehicle_id' => $vehicle->id,
        'kind' => ScheduleBlockKind::Maintenance,
        'status' => ScheduleBlockStatus::Active,
        'effective_start' => $pastDate,
        'effective_end' => (clone $pastDate)->addHours(2),
        'blocks_availability' => true,
    ]);

    $this->artisan('availability:cleanup-stale', ['--days' => 30])
        ->expectsOutputToContain('Cleaned up 1 stale block(s).')
        ->assertExitCode(0);

    expect(VehicleScheduleBlock::where('id', $oldBlock->id)->exists())->toBeFalse()
        ->and(VehicleScheduleBlock::where('id', $activeBlock->id)->exists())->toBeTrue();
});
