<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Booking;
use App\Models\RentDetail;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RentDetail>
 */
class RentDetailFactory extends Factory
{
    protected $model = RentDetail::class;

    public function definition(): array
    {
        $pickup = Carbon::now('Asia/Colombo')->addDays(2)->setHour(10)->setMinute(0)->setSecond(0);
        $return = (clone $pickup)->addDays(3);

        return [
            'booking_id' => Booking::factory(),
            'pickup_datetime' => $pickup,
            'return_datetime' => $return,
            'duration_days' => 3,
            'extra_hours' => 0,
            'pickup_location_type' => 'office',
            'pickup_location_text' => 'Spinellanka Head Office, Colombo 03',
            'pickup_place_id' => null,
            'pickup_lat' => 6.9271,
            'pickup_lng' => 79.8612,
            'return_location_type' => 'office',
            'return_location_text' => 'Spinellanka Head Office, Colombo 03',
            'return_place_id' => null,
            'return_lat' => 6.9271,
            'return_lng' => 79.8612,
            'driver_age' => 32,
            'driver_licence_country' => 'United Kingdom',
            'driver_licence_years' => 10,
            'has_idp' => true,
            'has_additional_driver' => false,
            'additional_driver_name' => null,
            'additional_driver_age' => null,
            'additional_driver_licence_country' => null,
            'additional_driver_licence_years' => null,
            'additional_driver_has_idp' => false,
            'expected_distance_km' => 300,
            'included_distance_km' => 300,
            'excess_mileage_rate_minor_units' => 30,
            'fuel_policy' => 'same_to_same',
            'delivery_fee_minor_units' => 0,
            'security_deposit_minor_units' => 50000,
            'insurance_excess_minor_units' => 50000,
            'original_documents_acknowledged' => true,
            'original_documents_inspected' => false,
            'original_documents_inspected_at' => null,
            'original_documents_inspected_by' => null,
            'original_documents_inspection_notes' => null,
            'extension_state' => null,
            'special_instructions' => null,
        ];
    }
}
