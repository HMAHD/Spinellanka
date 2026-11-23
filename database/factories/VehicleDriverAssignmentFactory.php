<?php

namespace Database\Factories;

use App\Models\Driver;
use App\Models\Vehicle;
use App\Models\VehicleDriverAssignment;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<VehicleDriverAssignment> */
class VehicleDriverAssignmentFactory extends Factory
{
    protected $model = VehicleDriverAssignment::class;

    public function definition(): array
    {
        return [
            'vehicle_id' => Vehicle::factory(),
            'driver_id' => Driver::factory(),
            'starts_at' => now(),
            'ends_at' => null,
            'is_permanent' => true,
            'reason' => 'Primary tied driver',
        ];
    }
}
