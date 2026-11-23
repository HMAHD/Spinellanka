<?php

namespace Database\Factories;

use App\Domain\Catalog\Enums\TransmissionType;
use App\Domain\Catalog\Enums\VehicleStatus;
use App\Domain\Catalog\Enums\VehicleType;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Vehicle> */
class VehicleFactory extends Factory
{
    protected $model = Vehicle::class;

    public function definition(): array
    {
        return [
            'public_name' => 'Test Vehicle '.fake()->word(),
            'internal_fleet_id' => 'TEST-FLEET-'.fake()->unique()->numerify('###'),
            'make' => 'Toyota',
            'model' => 'Alphard',
            'year' => 2024,
            'license_plate' => 'TEST-PLATE-'.fake()->unique()->numerify('####'),
            'vin' => 'TESTVIN'.fake()->unique()->numerify('##########'),
            'vehicle_type' => VehicleType::LuxuryVan,
            'passenger_capacity' => 5,
            'luggage_capacity' => 4,
            'transmission' => TransmissionType::Automatic,
            'has_ac' => true,
            'eligible_for_pickup' => true,
            'eligible_for_transfer' => true,
            'eligible_for_ride' => true,
            'eligible_for_rent' => false,
            'turnaround_buffer_minutes' => 90,
            'status' => VehicleStatus::Active,
        ];
    }
}
