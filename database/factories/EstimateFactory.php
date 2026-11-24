<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Booking\ServiceType;
use App\Domain\Pricing\Enums\EstimateStatus;
use App\Models\Estimate;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Estimate>
 */
class EstimateFactory extends Factory
{
    protected $model = Estimate::class;

    public function definition(): array
    {
        return [
            'reference' => 'EST-'.strtoupper(Str::random(8)),
            'service_type' => ServiceType::AirportPickup,
            'vehicle_id' => null,
            'pricing_rule_id' => null,
            'exchange_rate_basis_id' => null,
            'status' => EstimateStatus::Calculated,
            'is_custom_quote' => false,
            'custom_quote_reason' => null,
            'subtotal_minor_units' => 6500,
            'tax_minor_units' => 0,
            'deposit_minor_units' => 0,
            'total_amount_minor_units' => 6500,
            'currency' => 'USD',
            'approximate_lkr_amount_minor_units' => 2050750,
            'rule_version' => 1,
            'assumptions' => ['distance_km' => 35],
            'expires_at' => Carbon::now('Asia/Colombo')->addHours(24),
        ];
    }
}
