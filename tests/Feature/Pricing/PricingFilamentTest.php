<?php

declare(strict_types=1);

use App\Domain\Booking\ServiceType;
use App\Domain\Identity\Permission;
use App\Domain\Identity\Role;
use App\Domain\Pricing\Enums\PricingComponentType;
use App\Domain\Pricing\Enums\PricingUnit;
use App\Filament\Resources\Operations\ExchangeRateBases\Pages\ManageExchangeRateBases;
use App\Filament\Resources\Operations\PricingRules\Pages\ManagePricingRules;
use App\Models\ExchangeRateBasis;
use App\Models\PricingRule;
use App\Models\User;
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
    $this->owner->givePermissionTo(Permission::ConfigManage->value);

    $this->staff = User::create([
        'name' => 'Staff Member',
        'email' => 'staff@spinellanka.test',
        'password' => Hash::make('Password123456!'),
        'is_active' => true,
        'token_version' => 1,
    ]);
    $this->staff->assignRole(Role::Staff->value);
    // staff lacks config.manage
});

test('unprivileged staff cannot access pricing rule management', function (): void {
    $this->actingAs($this->staff);

    Livewire::test(ManagePricingRules::class)
        ->assertForbidden();
});

test('unprivileged staff cannot access exchange rate basis management', function (): void {
    $this->actingAs($this->staff);

    Livewire::test(ManageExchangeRateBases::class)
        ->assertForbidden();
});

test('authorized owner can create pricing rule via filament manage page', function (): void {
    $this->actingAs($this->owner);

    $startsAt = Carbon::now('Asia/Colombo')->addDays(1)->startOfHour();

    Livewire::test(ManagePricingRules::class)
        ->callAction('create', data: [
            'service_type' => ServiceType::AirportPickup->value,
            'vehicle_id' => null,
            'priority' => 5,
            'is_active' => true,
            'effective_from' => $startsAt->toDateTimeString(),
            'notes' => 'Airport pickup standard rule',
            'components' => [
                [
                    'component_type' => PricingComponentType::Base->value,
                    'unit' => PricingUnit::Flat->value,
                    'amount_display' => 55.00,
                    'is_taxable' => false,
                    'is_deposit' => false,
                ],
            ],
        ])
        ->assertHasNoActionErrors();

    $rule = PricingRule::where('service_type', ServiceType::AirportPickup->value)->first();
    expect($rule)->not->toBeNull()
        ->and($rule->priority)->toBe(5)
        ->and($rule->components)->toHaveCount(1)
        ->and($rule->components->first()->amount_minor_units)->toBe(5500);
});

test('authorized owner can record exchange rate basis via filament', function (): void {
    $this->actingAs($this->owner);

    Livewire::test(ManageExchangeRateBases::class)
        ->callAction('create', data: [
            'rate' => 312.5000,
            'source_note' => 'CBSL Morning Rate',
            'effective_at' => Carbon::now('Asia/Colombo')->toDateTimeString(),
        ])
        ->assertHasNoActionErrors();

    $basis = ExchangeRateBasis::where('source_note', 'CBSL Morning Rate')->first();
    expect($basis)->not->toBeNull()
        ->and((float) $basis->rate)->toBe(312.5);
});
