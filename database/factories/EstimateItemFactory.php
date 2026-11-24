<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Pricing\Enums\PricingComponentType;
use App\Domain\Pricing\Enums\PricingUnit;
use App\Models\Estimate;
use App\Models\EstimateItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EstimateItem>
 */
class EstimateItemFactory extends Factory
{
    protected $model = EstimateItem::class;

    public function definition(): array
    {
        return [
            'estimate_id' => Estimate::factory(),
            'rule_component_id' => null,
            'sort_order' => 1,
            'component_type' => PricingComponentType::Base,
            'description' => 'Airport Pickup Base Rate',
            'unit' => PricingUnit::Flat,
            'quantity' => 1.00,
            'unit_price_minor_units' => 5000,
            'total_amount_minor_units' => 5000,
            'currency' => 'USD',
            'is_deposit' => false,
            'is_tax' => false,
        ];
    }
}
