<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AirportPickupDetail;
use App\Models\Booking;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AirportPickupDetail>
 */
class AirportPickupDetailFactory extends Factory
{
    protected $model = AirportPickupDetail::class;

    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory(),
            'airport' => 'CMB',
            'flight_number' => 'UL504',
            'arrival_time' => Carbon::now('Asia/Colombo')->addDays(2)->setHour(14)->setMinute(30),
            'destination_text' => 'Galle Fort Heritage Villa, Galle',
            'destination_place_id' => null,
            'destination_lat' => 6.0329,
            'destination_lng' => 80.2168,
            'destination_notes' => 'Drop off near main lighthouse entrance.',
            'passenger_count' => 2,
            'luggage_count' => 2,
            'meet_and_greet' => true,
            'name_board_text' => 'Mr. Smith',
            'waiting_instructions' => null,
        ];
    }
}

