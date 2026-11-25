<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\RideDetail;
use App\Models\RideStop;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RideStop>
 */
class RideStopFactory extends Factory
{
    protected $model = RideStop::class;

    public function definition(): array
    {
        return [
            'ride_detail_id' => RideDetail::factory(),
            'stop_order' => 1,
            'location_text' => 'Bentota Beach, Bentota',
            'latitude' => 6.4214,
            'longitude' => 79.9984,
            'planned_waiting_minutes' => 60,
            'notes' => 'Beach photo stop',
        ];
    }
}
