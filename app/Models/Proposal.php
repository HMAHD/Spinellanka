<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Booking\Enums\PaymentOption;
use App\Domain\Booking\Enums\ProposalPaymentChoice;
use App\Domain\Booking\Enums\ProposalStatus;
use App\Domain\Booking\ServiceType;
use App\Domain\Pricing\ValueObjects\Money;
use Carbon\Carbon;
use Database\Factories\ProposalFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $booking_id
 * @property int $version
 * @property string $reference
 * @property ProposalStatus $status
 * @property int $vehicle_id
 * @property ServiceType $service_type
 * @property int|null $schedule_block_id
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property string $currency
 * @property int $total_amount_minor_units
 * @property int $approximate_lkr_amount_minor_units
 * @property bool $deposit_required
 * @property int $deposit_amount_minor_units
 * @property ProposalPaymentChoice $payment_choice
 * @property PaymentOption|null $selected_payment_choice
 * @property Carbon $expires_at
 * @property string|null $staff_notes
 * @property string|null $internal_notes
 * @property int|null $sent_by_user_id
 * @property Carbon|null $sent_at
 * @property Carbon|null $responded_at
 * @property string|null $response_action
 * @property string|null $response_notes
 * @property array<string, mixed> $snapshot_data
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Booking $booking
 * @property-read Vehicle $vehicle
 * @property-read VehicleScheduleBlock|null $scheduleBlock
 * @property-read User|null $sentBy
 * @property-read Collection<int, ProposalItem> $items
 */
class Proposal extends Model
{
    /** @use HasFactory<ProposalFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::updating(function (Proposal $proposal): void {
            $originalStatus = $proposal->getOriginal('status');
            $originalStatusValue = $originalStatus instanceof ProposalStatus ? $originalStatus->value : (string) $originalStatus;

            // Once sent, only response actions or status transitions are allowed
            if ($originalStatusValue === ProposalStatus::Sent->value) {
                $allowedDirty = ['status', 'selected_payment_choice', 'responded_at', 'response_action', 'response_notes', 'updated_at'];
                $dirtyKeys = array_keys($proposal->getDirty());
                $unallowedDirty = array_diff($dirtyKeys, $allowedDirty);

                if (! empty($unallowedDirty)) {
                    throw new \LogicException("Proposal {$proposal->reference} is immutable once sent. Cannot modify: ".implode(', ', $unallowedDirty));
                }
            } elseif (in_array($originalStatusValue, [ProposalStatus::Accepted->value, ProposalStatus::Declined->value, ProposalStatus::Expired->value], true)) {
                throw new \LogicException("Proposal {$proposal->reference} is in a terminal state ({$originalStatusValue}) and cannot be modified.");
            }
        });
    }

    /**
     * Critical privacy guard: internal staff notes must never be serialized
     * to JSON or exposed to guest-facing surfaces.
     *
     * @var list<string>
     */
    protected $hidden = [
        'internal_notes',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'status' => ProposalStatus::class,
            'service_type' => ServiceType::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'total_amount_minor_units' => 'integer',
            'approximate_lkr_amount_minor_units' => 'integer',
            'deposit_required' => 'boolean',
            'deposit_amount_minor_units' => 'integer',
            'payment_choice' => ProposalPaymentChoice::class,
            'selected_payment_choice' => PaymentOption::class,
            'expires_at' => 'datetime',
            'sent_at' => 'datetime',
            'responded_at' => 'datetime',
            'snapshot_data' => 'array',
        ];
    }

    public static function generateReference(Booking $booking, int $version): string
    {
        return sprintf('PROP-%s-V%d', $booking->reference, $version);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isActionable(): bool
    {
        return $this->status === ProposalStatus::Sent && ! $this->isExpired();
    }

    public function totalMoney(): Money
    {
        return Money::of($this->total_amount_minor_units, $this->currency);
    }

    public function depositMoney(): Money
    {
        return Money::of($this->deposit_amount_minor_units, $this->currency);
    }

    public function approximateLkrMoney(): Money
    {
        return Money::of($this->approximate_lkr_amount_minor_units, 'LKR');
    }

    /**
     * @return BelongsTo<Booking, $this>
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * @return BelongsTo<Vehicle, $this>
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * @return BelongsTo<Deal, $this>
     */
    public function appliedDeal(): BelongsTo
    {
        return $this->belongsTo(Deal::class, 'applied_deal_id');
    }

    /**
     * @return BelongsTo<Promotion, $this>
     */
    public function appliedPromotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class, 'applied_promotion_id');
    }

    /**
     * @return BelongsTo<VehicleScheduleBlock, $this>
     */
    public function scheduleBlock(): BelongsTo
    {
        return $this->belongsTo(VehicleScheduleBlock::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function sentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by_user_id');
    }

    /**
     * @return HasMany<ProposalItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(ProposalItem::class)->orderBy('sort_order');
    }

    /**
     * @return HasMany<PaymentTransaction, $this>
     */
    public function paymentTransactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class);
    }
}
