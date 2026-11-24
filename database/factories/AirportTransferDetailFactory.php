<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AirportTransferDetail;
use App\Models\Booking;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AirportTransferDetail>
 */
class AirportTransferDetailFactory extends Factory
{
    protected $model = AirportTransferDetail::class;

    public function definition(): array
    {
        $departure = Carbon::now('Asia/Colombo')->addDays(2)->setHour(20)->setMinute(0);
        $recommended = (clone $departure)->subHours(5); // e.g., 2h drive + 3h airport buffer
        $chosen = clone $recommended;

        return [
            'booking_id' => Booking::factory(),
            'airport' => 'CMB',
            'flight_number' => 'EK651',
            'departure_time' => $departure,
            'origin_text' => 'Galle Fort Heritage Villa, Galle',
            'origin_place_id' => null,
            'origin_lat' => 6.0329,
            'origin_lng' => 80.2168,
            'origin_notes' => 'Pickup at the villa reception entrance.',
            'recommended_pickup_time' => $recommended,
            'chosen_pickup_time' => $chosen,
            'is_unsafe_time' => false,
            'warning_accepted' => false,
            'passenger_count' => 2,
            'luggage_count' => 2,
            'special_instructions' => null,
        ];
    }
}
