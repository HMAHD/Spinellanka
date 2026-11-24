<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Booking\ServiceType;
use App\Domain\Pricing\Enums\EstimateStatus;
use App\Domain\Pricing\ValueObjects\Money;
use Carbon\Carbon;
use Database\Factories\EstimateFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @use HasFactory<EstimateFactory>
 *
 * @property int $id
 * @property string $reference
 * @property ServiceType $service_type
 * @property int|null $vehicle_id
 * @property int|null $pricing_rule_id
 * @property int|null $exchange_rate_basis_id
 * @property EstimateStatus $status
 * @property bool $is_custom_quote
 * @property string|null $custom_quote_reason
 * @property int|null $subtotal_minor_units
 * @property int|null $tax_minor_units
 * @property int|null $deposit_minor_units
 * @property int|null $total_amount_minor_units
 * @property string $currency
 * @property int|null $approximate_lkr_amount_minor_units
 * @property int $rule_version
 * @property array<string, mixed>|null $assumptions
 * @property Carbon $expires_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Vehicle|null $vehicle
 * @property-read PricingRule|null $pricingRule
 * @property-read ExchangeRateBasis|null $exchangeRateBasis
 * @property-read Collection<int, EstimateItem> $items
 * @property-read Money|null $subtotal
 * @property-read Money|null $taxTotal
 * @property-read Money|null $depositTotal
 * @property-read Money|null $grandTotal
 * @property-read Money|null $approximateLkr
 */
class Estimate extends Model
{
    /** @use HasFactory<EstimateFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'service_type' => ServiceType::class,
            'status' => EstimateStatus::class,
            'is_custom_quote' => 'boolean',
            'subtotal_minor_units' => 'integer',
            'tax_minor_units' => 'integer',
            'deposit_minor_units' => 'integer',
            'total_amount_minor_units' => 'integer',
            'approximate_lkr_amount_minor_units' => 'integer',
            'rule_version' => 'integer',
            'assumptions' => 'array',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Vehicle, $this>
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * @return BelongsTo<PricingRule, $this>
     */
    public function pricingRule(): BelongsTo
    {
        return $this->belongsTo(PricingRule::class);
    }

    /**
     * @return BelongsTo<ExchangeRateBasis, $this>
     */
    public function exchangeRateBasis(): BelongsTo
    {
        return $this->belongsTo(ExchangeRateBasis::class);
    }

    /**
     * @return HasMany<EstimateItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(EstimateItem::class)->orderBy('sort_order');
    }

    public function getSubtotalAttribute(): ?Money
    {
        return $this->subtotal_minor_units !== null
            ? Money::of($this->subtotal_minor_units, $this->currency)
            : null;
    }

    public function getTaxTotalAttribute(): ?Money
    {
        return $this->tax_minor_units !== null
            ? Money::of($this->tax_minor_units, $this->currency)
            : null;
    }

    public function getDepositTotalAttribute(): ?Money
    {
        return $this->deposit_minor_units !== null
            ? Money::of($this->deposit_minor_units, $this->currency)
            : null;
    }

    public function getGrandTotalAttribute(): ?Money
    {
        return $this->total_amount_minor_units !== null
            ? Money::of($this->total_amount_minor_units, $this->currency)
            : null;
    }

    public function getApproximateLkrAttribute(): ?Money
    {
        return $this->approximate_lkr_amount_minor_units !== null
            ? Money::of($this->approximate_lkr_amount_minor_units, 'LKR')
            : null;
    }
}
