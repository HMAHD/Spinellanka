<?php

declare(strict_types=1);

use App\Domain\Availability\Enums\ScheduleBlockKind;
use App\Domain\Availability\Enums\ScheduleBlockStatus;
use App\Domain\Identity\Permission;
use App\Domain\Identity\Role;
use App\Filament\Resources\Operations\ScheduleBlocks\Pages\ManageScheduleBlocks;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleScheduleBlock;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

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
    $this->owner->givePermissionTo(Permission::FleetManage->value);
    $this->owner->givePermissionTo(Permission::BookingOverrideConflict->value);

    $this->staff = User::create([
        'name' => 'Operational Staff',
        'email' => 'staff@spinellanka.test',
        'password' => Hash::make('Password123456!'),
        'is_active' => true,
        'token_version' => 1,
    ]);
    $this->staff->assignRole(Role::Staff->value);
    $this->staff->givePermissionTo(Permission::FleetManage->value);
});

test('staff with fleet.manage can render schedule block page and list records', function (): void {
    $vehicle = Vehicle::factory()->create();
    $block = VehicleScheduleBlock::factory()->create([
        'vehicle_id' => $vehicle->id,
        'kind' => ScheduleBlockKind::Maintenance,
        'notes' => 'Scheduled oil change',
    ]);

    $this->actingAs($this->staff);

    Livewire::test(ManageScheduleBlocks::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$block]);
});

test('user without fleet.manage permission cannot access schedule blocks', function (): void {
    $unauthorizedUser = User::create([
        'name' => 'Regular User',
        'email' => 'regular@spinellanka.test',
        'password' => Hash::make('Password123456!'),
        'is_active' => true,
        'token_version' => 1,
    ]);
    $this->actingAs($unauthorizedUser);

    Livewire::test(ManageScheduleBlocks::class)
        ->assertForbidden();
});

test('staff can create a maintenance block via manage records page', function (): void {
    $vehicle = Vehicle::factory()->create(['turnaround_buffer_minutes' => 60]);
    $this->actingAs($this->owner);

    $startsAt = Carbon::now('Asia/Colombo')->addDays(2)->startOfHour();
    $endsAt = (clone $startsAt)->addHours(4);

    Livewire::test(ManageScheduleBlocks::class)
        ->callAction('create', data: [
            'vehicle_id' => $vehicle->id,
            'kind' => ScheduleBlockKind::Maintenance->value,
            'starts_at' => $startsAt->toDateTimeString(),
            'ends_at' => $endsAt->toDateTimeString(),
            'buffer_minutes_before' => 0,
            'buffer_minutes_after' => 60,
            'notes' => 'Tire rotation & alignment',
            'is_override' => false,
        ])
        ->assertHasNoActionErrors();

    $block = VehicleScheduleBlock::where('vehicle_id', $vehicle->id)
        ->where('kind', ScheduleBlockKind::Maintenance->value)
        ->first();

    expect($block)->not->toBeNull()
        ->and($block->status)->toBe(ScheduleBlockStatus::Active)
        ->and($block->notes)->toBe('Tire rotation & alignment')
        ->and($block->created_by)->toBe($this->owner->id);
});

test('explicit 0 buffer minutes is preserved on block creation', function (): void {
    $vehicle = Vehicle::factory()->create(['turnaround_buffer_minutes' => 60]);
    $this->actingAs($this->owner);

    $startsAt = Carbon::now('Asia/Colombo')->addDays(5)->startOfHour();
    $endsAt = (clone $startsAt)->addHours(4);

    Livewire::test(ManageScheduleBlocks::class)
        ->callAction('create', data: [
            'vehicle_id' => $vehicle->id,
            'kind' => ScheduleBlockKind::ManualBlock->value,
            'starts_at' => $startsAt->toDateTimeString(),
            'ends_at' => $endsAt->toDateTimeString(),
            'buffer_minutes_before' => 0,
            'buffer_minutes_after' => 0,
            'notes' => 'Zero buffer block',
            'is_override' => false,
        ])
        ->assertHasNoActionErrors();

    $block = VehicleScheduleBlock::where('notes', 'Zero buffer block')->firstOrFail();
    expect($block->buffer_minutes_before)->toBe(0)
        ->and($block->buffer_minutes_after)->toBe(0)
        ->and($block->effective_start->toDateTimeString())->toBe($startsAt->toDateTimeString())
        ->and($block->effective_end->toDateTimeString())->toBe($endsAt->toDateTimeString());
});

test('creating conflicting block without override sends danger notification and halts', function (): void {
    $vehicle = Vehicle::factory()->create(['turnaround_buffer_minutes' => 60]);
    $this->actingAs($this->staff);

    $startsAt = Carbon::now('Asia/Colombo')->addDays(3)->startOfHour();
    $endsAt = (clone $startsAt)->addHours(4);

    VehicleScheduleBlock::factory()->create([
        'vehicle_id' => $vehicle->id,
        'kind' => ScheduleBlockKind::ConfirmedBooking,
        'starts_at' => $startsAt,
        'ends_at' => $endsAt,
        'effective_start' => $startsAt,
        'effective_end' => (clone $endsAt)->addMinutes(60),
        'blocks_availability' => true,
        'status' => ScheduleBlockStatus::Active,
    ]);

    Livewire::test(ManageScheduleBlocks::class)
        ->callAction('create', data: [
            'vehicle_id' => $vehicle->id,
            'kind' => ScheduleBlockKind::Maintenance->value,
            'starts_at' => $startsAt->toDateTimeString(),
            'ends_at' => $endsAt->toDateTimeString(),
            'buffer_minutes_before' => 0,
            'buffer_minutes_after' => 60,
            'notes' => 'Attempt conflicting block',
            'is_override' => false,
        ])
        ->assertNotified();

    expect(VehicleScheduleBlock::where('notes', 'Attempt conflicting block')->exists())->toBeFalse();
});

test('staff without override permission attempting override sends danger notification and halts', function (): void {
    $vehicle = Vehicle::factory()->create(['turnaround_buffer_minutes' => 60]);
    $this->actingAs($this->staff); // staff does not have BookingOverrideConflict

    $startsAt = Carbon::now('Asia/Colombo')->addDays(3)->startOfHour();
    $endsAt = (clone $startsAt)->addHours(4);

    VehicleScheduleBlock::factory()->create([
        'vehicle_id' => $vehicle->id,
        'kind' => ScheduleBlockKind::ConfirmedBooking,
        'starts_at' => $startsAt,
        'ends_at' => $endsAt,
        'effective_start' => $startsAt,
        'effective_end' => (clone $endsAt)->addMinutes(60),
        'blocks_availability' => true,
        'status' => ScheduleBlockStatus::Active,
    ]);

    Livewire::test(ManageScheduleBlocks::class)
        ->callAction('create', data: [
            'vehicle_id' => $vehicle->id,
            'kind' => ScheduleBlockKind::Maintenance->value,
            'starts_at' => $startsAt->toDateTimeString(),
            'ends_at' => $endsAt->toDateTimeString(),
            'buffer_minutes_before' => 0,
            'buffer_minutes_after' => 60,
            'notes' => 'Attempt unauthorized override',
            'is_override' => true,
            'override_reason' => 'Unauthorized attempt by staff',
        ])
        ->assertNotified();

    expect(VehicleScheduleBlock::where('notes', 'Attempt unauthorized override')->exists())->toBeFalse();
});

test('staff with booking.override_conflict can authorize override via manage records page', function (): void {
    $vehicle = Vehicle::factory()->create(['turnaround_buffer_minutes' => 60]);
    $this->actingAs($this->owner);

    $startsAt = Carbon::now('Asia/Colombo')->addDays(4)->startOfHour();
    $endsAt = (clone $startsAt)->addHours(4);

    VehicleScheduleBlock::factory()->create([
        'vehicle_id' => $vehicle->id,
        'kind' => ScheduleBlockKind::ConfirmedBooking,
        'starts_at' => $startsAt,
        'ends_at' => $endsAt,
        'effective_start' => $startsAt,
        'effective_end' => (clone $endsAt)->addMinutes(60),
        'blocks_availability' => true,
        'status' => ScheduleBlockStatus::Active,
    ]);

    Livewire::test(ManageScheduleBlocks::class)
        ->callAction('create', data: [
            'vehicle_id' => $vehicle->id,
            'kind' => ScheduleBlockKind::Maintenance->value,
            'starts_at' => $startsAt->toDateTimeString(),
            'ends_at' => $endsAt->toDateTimeString(),
            'buffer_minutes_before' => 0,
            'buffer_minutes_after' => 60,
            'notes' => 'Emergency brake replacement',
            'is_override' => true,
            'override_reason' => 'Brake warning lamp activated; fleet manager authorized priority repair',
        ])
        ->assertHasNoActionErrors();

    $overrideBlock = VehicleScheduleBlock::where('vehicle_id', $vehicle->id)
        ->where('kind', ScheduleBlockKind::Maintenance->value)
        ->first();

    expect($overrideBlock)->not->toBeNull()
        ->and($overrideBlock->is_override)->toBeTrue()
        ->and($overrideBlock->override_authorized_by)->toBe($this->owner->id)
        ->and($overrideBlock->override_reason)->toContain('Brake warning lamp');
});

test('staff can release an active block via table release action', function (): void {
    $vehicle = Vehicle::factory()->create();
    $block = VehicleScheduleBlock::factory()->create([
        'vehicle_id' => $vehicle->id,
        'kind' => ScheduleBlockKind::Maintenance,
        'status' => ScheduleBlockStatus::Active,
        'blocks_availability' => true,
    ]);

    $this->actingAs($this->staff);

    Livewire::test(ManageScheduleBlocks::class)
        ->callTableAction('release', $block, data: [
            'reason' => 'Maintenance completed early',
        ])
        ->assertHasNoTableActionErrors();

    $block->refresh();
    expect($block->status)->toBe(ScheduleBlockStatus::Released)
        ->and($block->blocks_availability)->toBeFalse()
        ->and($block->notes)->toContain('Released: Maintenance completed early');
});

test('table release action is hidden on confirmed booking schedule blocks', function (): void {
    $vehicle = Vehicle::factory()->create();
    $bookingBlock = VehicleScheduleBlock::factory()->create([
        'vehicle_id' => $vehicle->id,
        'kind' => ScheduleBlockKind::ConfirmedBooking,
        'status' => ScheduleBlockStatus::Active,
        'blocks_availability' => true,
    ]);

    $this->actingAs($this->staff);

    Livewire::test(ManageScheduleBlocks::class)
        ->assertTableActionHidden('release', $bookingBlock);
});
