<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Booking\ServiceType;
use App\Domain\Catalog\Enums\DriverStatus;
use App\Domain\Catalog\Enums\ExtraPricingType;
use App\Domain\Catalog\Enums\TransmissionType;
use App\Domain\Catalog\Enums\VehicleStatus;
use App\Domain\Catalog\Enums\VehicleType;
use App\Domain\Catalog\Services\DriverAssignmentService;
use App\Domain\Catalog\Services\DriverService;
use App\Domain\Catalog\Services\ExtraService;
use App\Domain\Catalog\Services\VehicleService;
use App\Domain\Pricing\Enums\PricingComponentType;
use App\Domain\Pricing\Enums\PricingUnit;
use App\Domain\Pricing\Services\ExchangeRateService;
use App\Domain\Pricing\Services\PricingRuleService;
use App\Domain\Pricing\Services\RentPolicyService;
use App\Models\Driver;
use App\Models\Extra;
use App\Models\PricingRule;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * CHG-003: Staging-only synthetic fixtures for Phase 16 reconciliation and journey verification.
 * Strictly prohibited in production environments and blocked from production_approved exports.
 */
class StagingSyntheticFixturesSeeder extends Seeder
{
    public function run(
        VehicleService $vehicleService,
        DriverService $driverService,
        DriverAssignmentService $driverAssignmentService,
        ExtraService $extraService,
        PricingRuleService $pricingRuleService,
        ExchangeRateService $exchangeRateService,
        RentPolicyService $rentPolicyService,
    ): void {
        if (app()->isProduction()) {
            throw new RuntimeException('StagingSyntheticFixturesSeeder cannot execute in production environment.');
        }

        // 1. Ensure active RentPolicy matches approved Checklist §8 defaults
        $rentPolicyService->updateActivePolicy([
            'min_driver_age' => 23,
            'max_driver_age' => 70,
            'min_licence_years' => 2,
            'idp_required' => true,
            'max_additional_drivers' => 1,
            'security_deposit_minor_units' => 50000,
            'insurance_excess_minor_units' => 50000,
            'daily_mileage_km' => 100,
            'excess_mileage_rate_minor_units' => 30,
            'grace_period_minutes' => 59,
            'extra_hours_threshold' => 5,
            'full_day_threshold_hours' => 6,
            'office_handover_available' => true,
            'airport_handover_available' => true,
            'hotel_delivery_available' => true,
            'hotel_delivery_fee_minor_units' => 2000,
            'prohibited_roads_text' => 'Paved public roads only; off-road driving strictly prohibited.',
            'smoking_penalty_minor_units' => 15000,
        ]);

        // 2. Ensure synthetic USD/LKR approximation basis
        $latestRate = $exchangeRateService->getLatestRate();
        if ($latestRate === null || (string) $latestRate->rate !== '308.5000') {
            $exchangeRateService->recordRate(
                '308.5000',
                'SYNTHETIC STAGING BASIS — CBSL Indicative Reference (CHG-003)',
                Carbon::parse('2026-11-27 00:00:00', 'Asia/Colombo'),
            );
        }

        // 3. Exactly 5 synthetic fleet vehicles (CHG-003)
        $vehicleDefinitions = [
            [
                'internal_fleet_id' => 'SYNTH-VEH-01',
                'public_name' => 'SYNTHETIC Sedan 01 (Staging Fixture)',
                'make' => 'Toyota',
                'model' => 'Axio Hybrid (Synthetic)',
                'year' => 2022,
                'license_plate' => 'SYNTH-0001',
                'vehicle_type' => VehicleType::Sedan->value,
                'passenger_capacity' => 3,
                'luggage_capacity' => 3,
                'transmission' => TransmissionType::Automatic->value,
                'has_ac' => true,
                'accessibility_features' => ['Low step-in height'],
                'amenities' => ['Air conditioning', 'USB charging'],
                'eligible_for_pickup' => true,
                'eligible_for_transfer' => true,
                'eligible_for_ride' => true,
                'eligible_for_rent' => false,
                'turnaround_buffer_minutes' => 90,
                'status' => VehicleStatus::Active->value,
            ],
            [
                'internal_fleet_id' => 'SYNTH-VEH-02',
                'public_name' => 'SYNTHETIC Touring Van 02 (Staging Fixture)',
                'make' => 'Toyota',
                'model' => 'KDH High Roof (Synthetic)',
                'year' => 2023,
                'license_plate' => 'SYNTH-0002',
                'vehicle_type' => VehicleType::LuxuryVan->value,
                'passenger_capacity' => 7,
                'luggage_capacity' => 7,
                'transmission' => TransmissionType::Automatic->value,
                'has_ac' => true,
                'accessibility_features' => ['Wide sliding door'],
                'amenities' => ['Dual-zone A/C', 'Reclining seats'],
                'eligible_for_pickup' => true,
                'eligible_for_transfer' => true,
                'eligible_for_ride' => true,
                'eligible_for_rent' => false,
                'turnaround_buffer_minutes' => 120,
                'status' => VehicleStatus::Active->value,
            ],
            [
                'internal_fleet_id' => 'SYNTH-VEH-03',
                'public_name' => 'SYNTHETIC SUV 03 (Staging Fixture)',
                'make' => 'Mitsubishi',
                'model' => 'Outlander (Synthetic)',
                'year' => 2023,
                'license_plate' => 'SYNTH-0003',
                'vehicle_type' => VehicleType::Suv->value,
                'passenger_capacity' => 4,
                'luggage_capacity' => 4,
                'transmission' => TransmissionType::Automatic->value,
                'has_ac' => true,
                'accessibility_features' => [],
                'amenities' => ['Air conditioning', 'Luggage cover'],
                'eligible_for_pickup' => true,
                'eligible_for_transfer' => true,
                'eligible_for_ride' => true,
                'eligible_for_rent' => true,
                'turnaround_buffer_minutes' => 120,
                'status' => VehicleStatus::Active->value,
            ],
            [
                'internal_fleet_id' => 'SYNTH-VEH-04',
                'public_name' => 'SYNTHETIC Minivan 04 (Staging Fixture)',
                'make' => 'Suzuki',
                'model' => 'Every Wagon (Synthetic)',
                'year' => 2021,
                'license_plate' => 'SYNTH-0004',
                'vehicle_type' => VehicleType::Minivan->value,
                'passenger_capacity' => 5,
                'luggage_capacity' => 4,
                'transmission' => TransmissionType::Automatic->value,
                'has_ac' => true,
                'accessibility_features' => [],
                'amenities' => ['Air conditioning'],
                'eligible_for_pickup' => true,
                'eligible_for_transfer' => false,
                'eligible_for_ride' => true,
                'eligible_for_rent' => false,
                'turnaround_buffer_minutes' => 90,
                'status' => VehicleStatus::Active->value,
            ],
            [
                'internal_fleet_id' => 'SYNTH-VEH-05',
                'public_name' => 'SYNTHETIC Self-Drive Compact 05 (Staging Fixture)',
                'make' => 'Suzuki',
                'model' => 'Swift (Synthetic)',
                'year' => 2022,
                'license_plate' => 'SYNTH-0005',
                'vehicle_type' => VehicleType::Compact->value,
                'passenger_capacity' => 4,
                'luggage_capacity' => 2,
                'transmission' => TransmissionType::Automatic->value,
                'has_ac' => true,
                'accessibility_features' => [],
                'amenities' => ['Air conditioning', 'Bluetooth audio'],
                'eligible_for_pickup' => false,
                'eligible_for_transfer' => false,
                'eligible_for_ride' => false,
                'eligible_for_rent' => true,
                'turnaround_buffer_minutes' => 120,
                'status' => VehicleStatus::Active->value,
            ],
        ];

        $vehicles = [];
        foreach ($vehicleDefinitions as $def) {
            $existing = Vehicle::where('internal_fleet_id', $def['internal_fleet_id'])->first();
            $vehicles[$def['internal_fleet_id']] = $existing === null
                ? $vehicleService->createVehicle($def)
                : $vehicleService->updateVehicle($existing, $def);
        }

        // 4. Synthetic public-safe permanent drivers for chauffeured vehicles (zero real PII)
        $driverMap = [
            'SYNTH-VEH-01' => ['public_name' => 'Synthetic Chauffeur 01', 'full_name' => 'SYNTH-DRIVER-01', 'phone' => '+94000000001'],
            'SYNTH-VEH-02' => ['public_name' => 'Synthetic Chauffeur 02', 'full_name' => 'SYNTH-DRIVER-02', 'phone' => '+94000000002'],
            'SYNTH-VEH-03' => ['public_name' => 'Synthetic Chauffeur 03', 'full_name' => 'SYNTH-DRIVER-03', 'phone' => '+94000000003'],
            'SYNTH-VEH-04' => ['public_name' => 'Synthetic Chauffeur 04', 'full_name' => 'SYNTH-DRIVER-04', 'phone' => '+94000000004'],
        ];

        foreach ($driverMap as $fleetId => $dData) {
            $vehicle = $vehicles[$fleetId];
            $driver = Driver::where('full_name', $dData['full_name'])->first();
            if ($driver === null) {
                $driver = $driverService->createDriver([
                    'public_name' => $dData['public_name'],
                    'full_name' => $dData['full_name'],
                    'phone' => $dData['phone'],
                    'status' => DriverStatus::Active->value,
                    'public_trust_facts' => ['Staging synthetic driver fixture'],
                ]);
            }

            if (! $vehicle->driverAssignments()->where('is_permanent', true)->whereNull('ends_at')->exists()) {
                $driverAssignmentService->assignPermanentDriver(
                    $vehicle,
                    $driver,
                    Carbon::parse('2026-11-27 00:00:00', 'Asia/Colombo'),
                    'SYNTHETIC STAGING PERMANENT TIE',
                );
            }
        }

        // 5. Synthetic Extras
        $extras = [
            [
                'name' => 'Child Safety Seat (Synthetic)',
                'slug' => 'synth-child-seat',
                'description' => 'Synthetic staging fixture child seat.',
                'price_minor_units' => 1000,
                'currency' => 'USD',
                'pricing_type' => ExtraPricingType::PerBooking->value,
                'max_quantity' => 2,
                'is_active' => true,
            ],
            [
                'name' => 'Booster Seat (Synthetic)',
                'slug' => 'synth-booster-seat',
                'description' => 'Synthetic staging fixture booster seat.',
                'price_minor_units' => 800,
                'currency' => 'USD',
                'pricing_type' => ExtraPricingType::PerBooking->value,
                'max_quantity' => 2,
                'is_active' => true,
            ],
        ];
        foreach ($extras as $eDef) {
            $existingExtra = Extra::where('slug', $eDef['slug'])->first();
            $extra = $existingExtra === null
                ? $extraService->createExtra($eDef)
                : $extraService->updateExtra($existingExtra, $eDef);

            if (! $extra->compatibilities()->exists()) {
                $extraService->createCompatibilityRule($extra, [
                    'service_type' => null,
                    'vehicle_type' => null,
                    'vehicle_id' => null,
                    'is_compatible' => true,
                ]);
            }
        }

        // 6. Synthetic Pricing Rules for all 4 services (deterministic config_key)
        $effectiveFrom = Carbon::parse('2026-11-01 00:00:00', 'Asia/Colombo');
        $rules = [
            [
                'config_key' => 'synth_pickup_default_v1',
                'service_type' => ServiceType::AirportPickup,
                'priority' => 10,
                'notes' => 'SYNTHETIC STAGING RULE — Airport Pickup Default',
                'components' => [
                    ['component_type' => PricingComponentType::Base->value, 'unit' => PricingUnit::Flat->value, 'amount_minor_units' => 2500, 'currency' => 'USD', 'is_taxable' => false, 'is_deposit' => false],
                    ['component_type' => PricingComponentType::Distance->value, 'unit' => PricingUnit::PerKm->value, 'amount_minor_units' => 80, 'currency' => 'USD', 'is_taxable' => false, 'is_deposit' => false],
                    ['component_type' => PricingComponentType::AirportFee->value, 'unit' => PricingUnit::Flat->value, 'amount_minor_units' => 500, 'currency' => 'USD', 'is_taxable' => false, 'is_deposit' => false],
                ],
            ],
            [
                'config_key' => 'synth_transfer_default_v1',
                'service_type' => ServiceType::AirportTransfer,
                'priority' => 10,
                'notes' => 'SYNTHETIC STAGING RULE — Airport Transfer Default',
                'components' => [
                    ['component_type' => PricingComponentType::Base->value, 'unit' => PricingUnit::Flat->value, 'amount_minor_units' => 2200, 'currency' => 'USD', 'is_taxable' => false, 'is_deposit' => false],
                    ['component_type' => PricingComponentType::Distance->value, 'unit' => PricingUnit::PerKm->value, 'amount_minor_units' => 80, 'currency' => 'USD', 'is_taxable' => false, 'is_deposit' => false],
                ],
            ],
            [
                'config_key' => 'synth_ride_default_v1',
                'service_type' => ServiceType::Ride,
                'priority' => 10,
                'notes' => 'SYNTHETIC STAGING RULE — Chauffeured Ride Default',
                'components' => [
                    ['component_type' => PricingComponentType::Base->value, 'unit' => PricingUnit::Flat->value, 'amount_minor_units' => 3000, 'currency' => 'USD', 'is_taxable' => false, 'is_deposit' => false],
                    ['component_type' => PricingComponentType::Distance->value, 'unit' => PricingUnit::PerKm->value, 'amount_minor_units' => 90, 'currency' => 'USD', 'is_taxable' => false, 'is_deposit' => false],
                    ['component_type' => PricingComponentType::Stop->value, 'unit' => PricingUnit::PerStop->value, 'amount_minor_units' => 500, 'currency' => 'USD', 'is_taxable' => false, 'is_deposit' => false],
                    ['component_type' => PricingComponentType::Waiting->value, 'unit' => PricingUnit::PerHour->value, 'amount_minor_units' => 600, 'currency' => 'USD', 'is_taxable' => false, 'is_deposit' => false],
                ],
            ],
            [
                'config_key' => 'synth_rent_default_v1',
                'service_type' => ServiceType::Rent,
                'priority' => 10,
                'notes' => 'SYNTHETIC STAGING RULE — Self-Drive Rent Default',
                'components' => [
                    ['component_type' => PricingComponentType::Duration->value, 'unit' => PricingUnit::PerDay->value, 'amount_minor_units' => 5500, 'currency' => 'USD', 'is_taxable' => false, 'is_deposit' => false],
                    ['component_type' => PricingComponentType::Deposit->value, 'unit' => PricingUnit::Flat->value, 'amount_minor_units' => 50000, 'currency' => 'USD', 'is_taxable' => false, 'is_deposit' => true],
                ],
            ],
        ];

        $existingActiveRules = PricingRule::where('is_active', true)->get();
        foreach ($rules as $ruleDef) {
            $alreadyExists = $existingActiveRules->first(
                fn (PricingRule $r): bool => ($r->metadata['config_key'] ?? null) === $ruleDef['config_key']
            );
            if ($alreadyExists === null) {
                $pricingRuleService->createRule([
                    'service_type' => $ruleDef['service_type'],
                    'vehicle_id' => null,
                    'priority' => $ruleDef['priority'],
                    'is_active' => true,
                    'effective_from' => $effectiveFrom,
                    'notes' => $ruleDef['notes'],
                    'metadata' => ['config_key' => $ruleDef['config_key'], 'synthetic_staging' => true],
                    'components' => $ruleDef['components'],
                ]);
            }
        }
    }
}
