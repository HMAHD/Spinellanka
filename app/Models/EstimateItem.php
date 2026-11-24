<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Pricing\Enums\PricingComponentType;
use App\Domain\Pricing\Enums\PricingUnit;
use App\Domain\Pricing\ValueObjects\Money;
use Carbon\Carbon;
use Database\Factories\EstimateItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @use HasFactory<EstimateItemFactory>
 *
 * @property int $id
 * @property int $estimate_id
 * @property int|null $rule_component_id
 * @property int $sort_order
 * @property PricingComponentType $component_type
 * @property string $description
 * @property PricingUnit $unit
 * @property string $quantity
 * @property int $unit_price_minor_units
 * @property int $total_amount_minor_units
 * @property string $currency
 * @property bool $is_deposit
 * @property bool $is_tax
 * @property bool $is_discount
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Estimate $estimate
 * @property-read PricingRuleComponent|null $ruleComponent
 * @property-read Money $unitPrice
 * @property-read Money $totalAmount
 */
class EstimateItem extends Model
{
    /** @use HasFactory<EstimateItemFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'component_type' => PricingComponentType::class,
            'unit' => PricingUnit::class,
            'quantity' => 'decimal:2',
            'unit_price_minor_units' => 'integer',
            'total_amount_minor_units' => 'integer',
            'is_deposit' => 'boolean',
            'is_tax' => 'boolean',
            'is_discount' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Estimate, $this>
     */
    public function estimate(): BelongsTo
    {
        return $this->belongsTo(Estimate::class);
    }

    /**
     * @return BelongsTo<PricingRuleComponent, $this>
     */
    public function ruleComponent(): BelongsTo
    {
        return $this->belongsTo(PricingRuleComponent::class);
    }

    public function getUnitPriceAttribute(): Money
    {
        return Money::of($this->unit_price_minor_units, $this->currency);
    }

    public function getTotalAmountAttribute(): Money
    {
        return Money::of($this->total_amount_minor_units, $this->currency);
    }
}
