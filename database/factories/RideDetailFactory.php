<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Booking;
use App\Models\RideDetail;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RideDetail>
 */
class RideDetailFactory extends Factory
{
    protected $model = RideDetail::class;

    public function definition(): array
    {
        $pickup = Carbon::now('Asia/Colombo')->addDays(2)->setHour(9)->setMinute(0);

        return [
            'booking_id' => Booking::factory(),
            'pickup_time' => $pickup,
            'origin_text' => 'Cinnamon Grand, Colombo',
            'origin_latitude' => 6.9175,
            'origin_longitude' => 79.8484,
            'origin_notes' => 'Hotel main lobby',
            'destination_text' => 'Galle Fort Lighthouse, Galle',
            'destination_latitude' => 6.0269,
            'destination_longitude' => 80.2176,
            'destination_notes' => 'Near lighthouse parking',
            'has_return' => false,
            'return_pickup_time' => null,
            'return_origin_text' => null,
            'return_destination_text' => null,
            'return_notes' => null,
            'passenger_count' => 2,
            'luggage_count' => 2,
            'total_waiting_minutes' => 60,
            'estimated_outbound_distance_km' => 125.5,
            'estimated_outbound_duration_minutes' => 120,
            'estimated_return_distance_km' => null,
            'estimated_return_duration_minutes' => null,
            'route_map_data' => null,
        ];
    }

    public function withReturn(): self
    {
        return $this->state(function (array $attributes) {
            $pickup = Carbon::parse($attributes['pickup_time']);
            $returnPickup = (clone $pickup)->addHours(8);

            return [
                'has_return' => true,
                'return_pickup_time' => $returnPickup,
                'return_origin_text' => $attributes['destination_text'],
                'return_destination_text' => $attributes['origin_text'],
                'return_notes' => 'Return pickup at same drop location',
                'estimated_return_distance_km' => 125.5,
                'estimated_return_duration_minutes' => 120,
            ];
        });
    }
}
