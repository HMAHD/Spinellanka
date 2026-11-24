<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Pricing\Enums\PricingComponentType;
use App\Domain\Pricing\Enums\PricingUnit;
use App\Models\PricingRule;
use App\Models\PricingRuleComponent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PricingRuleComponent>
 */
class PricingRuleComponentFactory extends Factory
{
    protected $model = PricingRuleComponent::class;

    public function definition(): array
    {
        return [
            'pricing_rule_id' => PricingRule::factory(),
            'component_type' => PricingComponentType::Base,
            'unit' => PricingUnit::Flat,
            'amount_minor_units' => 5000, // $50.00
            'currency' => 'USD',
            'min_units' => null,
            'max_units' => null,
            'is_taxable' => false,
            'is_deposit' => false,
            'metadata' => null,
        ];
    }
}
