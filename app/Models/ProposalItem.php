<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Booking\Enums\ProposalStatus;
use App\Domain\Pricing\Enums\PricingComponentType;
use App\Domain\Pricing\Enums\PricingUnit;
use App\Domain\Pricing\ValueObjects\Money;
use Carbon\Carbon;
use Database\Factories\ProposalItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $proposal_id
 * @property PricingComponentType $component_type
 * @property string $description
 * @property string $quantity
 * @property PricingUnit $unit
 * @property int $unit_price_minor_units
 * @property int $total_amount_minor_units
 * @property bool $is_deposit
 * @property bool $is_tax
 * @property bool $is_discount
 * @property int $sort_order
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Proposal $proposal
 * @property-read Money $unitPrice
 * @property-read Money $totalAmount
 */
class ProposalItem extends Model
{
    /** @use HasFactory<ProposalItemFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::updating(function (ProposalItem $item): void {
            if ($item->proposal && $item->proposal->status !== ProposalStatus::Draft) {
                throw new \LogicException("Cannot update line item of non-draft proposal {$item->proposal->reference}.");
            }
        });

        static::deleting(function (ProposalItem $item): void {
            if ($item->proposal && $item->proposal->status !== ProposalStatus::Draft) {
                throw new \LogicException("Cannot delete line item of non-draft proposal {$item->proposal->reference}.");
            }
        });
    }

    protected function casts(): array
    {
        return [
            'component_type' => PricingComponentType::class,
            'unit' => PricingUnit::class,
            'quantity' => 'decimal:2',
            'unit_price_minor_units' => 'integer',
            'total_amount_minor_units' => 'integer',
            'is_deposit' => 'boolean',
            'is_tax' => 'boolean',
            'is_discount' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Proposal, $this>
     */
    public function proposal(): BelongsTo
    {
        return $this->belongsTo(Proposal::class);
    }

    public function getUnitPriceAttribute(): Money
    {
        return Money::of($this->unit_price_minor_units, $this->proposal->currency);
    }

    public function getTotalAmountAttribute(): Money
    {
        return Money::of($this->total_amount_minor_units, $this->proposal->currency);
    }
}
