<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Booking\ServiceType;
use App\Models\PricingRule;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PricingRule>
 */
class PricingRuleFactory extends Factory
{
    protected $model = PricingRule::class;

    public function definition(): array
    {
        return [
            'service_type' => ServiceType::AirportPickup,
            'vehicle_id' => null,
            'version' => 1,
            'priority' => 0,
            'is_active' => true,
            'effective_from' => Carbon::now('Asia/Colombo')->subDays(1),
            'effective_to' => null,
            'notes' => 'Standard baseline rate rule',
            'metadata' => null,
            'created_by' => User::factory(),
        ];
    }
}
