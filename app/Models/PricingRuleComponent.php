<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Pricing\Enums\PricingComponentType;
use App\Domain\Pricing\Enums\PricingUnit;
use App\Domain\Pricing\ValueObjects\Money;
use Carbon\Carbon;
use Database\Factories\PricingRuleComponentFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @use HasFactory<PricingRuleComponentFactory>
 *
 * @property int $id
 * @property int $pricing_rule_id
 * @property PricingComponentType $component_type
 * @property PricingUnit $unit
 * @property int $amount_minor_units
 * @property string $currency
 * @property string|null $min_units
 * @property string|null $max_units
 * @property bool $is_taxable
 * @property bool $is_deposit
 * @property array<string, mixed>|null $metadata
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read PricingRule $pricingRule
 * @property-read Money $money
 * @property float|int $amount_display
 */
class PricingRuleComponent extends Model
{
    /** @use HasFactory<PricingRuleComponentFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'component_type' => PricingComponentType::class,
            'unit' => PricingUnit::class,
            'amount_minor_units' => 'integer',
            'min_units' => 'decimal:2',
            'max_units' => 'decimal:2',
            'is_taxable' => 'boolean',
            'is_deposit' => 'boolean',
            'metadata' => 'array',
        ];
    }

    /**
     * @return BelongsTo<PricingRule, $this>
     */
    public function pricingRule(): BelongsTo
    {
        return $this->belongsTo(PricingRule::class);
    }

    public function getMoneyAttribute(): Money
    {
        return Money::of($this->amount_minor_units, $this->currency ?? 'USD');
    }

    /**
     * Filament display accessor/mutator:
     * When unit is percentage, amount_display is percentage points (e.g. 15.00 for 1500 bps).
     * When unit is flat/fiat, amount_display is dollars (e.g. 50.00 for 5000 cents).
     *
     * @return Attribute<float, float|int|string>
     */
    protected function amountDisplay(): Attribute
    {
        return Attribute::make(
            get: fn (): float => $this->amount_minor_units / 100,
            set: fn (float|int|string $value): array => [
                'amount_minor_units' => is_string($value)
                    ? Money::ofDollars($value)->amountMinorUnits
                    : (int) round(((float) $value) * 100),
            ]
        );
    }
}
