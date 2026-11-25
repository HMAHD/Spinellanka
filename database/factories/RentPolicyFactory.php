<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\RentPolicy;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RentPolicy>
 */
class RentPolicyFactory extends Factory
{
    protected $model = RentPolicy::class;

    public function definition(): array
    {
        return [
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
            'is_active' => true,
        ];
    }
}
