<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Pricing\Enums\PricingComponentType;
use App\Domain\Pricing\Enums\PricingUnit;
use App\Models\Proposal;
use App\Models\ProposalItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProposalItem>
 */
class ProposalItemFactory extends Factory
{
    protected $model = ProposalItem::class;

    public function definition(): array
    {
        return [
            'proposal_id' => Proposal::factory(),
            'component_type' => PricingComponentType::Base,
            'description' => 'Standard base journey fare',
            'quantity' => '1.00',
            'unit' => PricingUnit::Flat,
            'unit_price_minor_units' => 6500,
            'total_amount_minor_units' => 6500,
            'is_deposit' => false,
            'is_tax' => false,
            'is_discount' => false,
            'sort_order' => 1,
        ];
    }
}
