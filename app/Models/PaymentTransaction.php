<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Payment\Enums\PaymentMethod;
use App\Domain\Payment\Enums\PaymentStatus;
use App\Domain\Payment\Enums\PaymentType;
use App\Domain\Payment\Enums\RefundStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $booking_id
 * @property int|null $proposal_id
 * @property string $reference
 * @property string $gateway_provider
 * @property string|null $gateway_transaction_id
 * @property PaymentMethod $payment_method
 * @property PaymentType $payment_type
 * @property PaymentStatus $status
 * @property int $amount_minor_units
 * @property string $currency
 * @property string $idempotency_key
 * @property int|null $recorded_by_user_id
 * @property string|null $notes
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $settled_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Booking $booking
 * @property-read Proposal|null $proposal
 * @property-read User|null $recordedBy
 */
class PaymentTransaction extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'payment_method' => PaymentMethod::class,
            'payment_type' => PaymentType::class,
            'status' => PaymentStatus::class,
            'metadata' => 'array',
            'settled_at' => 'datetime',
        ];
    }

    public static function generateReference(Booking $booking): string
    {
        return sprintf('TXN-%s-%s', $booking->reference, strtoupper(bin2hex(random_bytes(4))));
    }

    protected static function booted(): void
    {
        static::creating(function (PaymentTransaction $transaction): void {
            if (empty($transaction->reference)) {
                $booking = $transaction->booking ?? Booking::find($transaction->booking_id);
                $ref = $booking ? $booking->reference : strtoupper(bin2hex(random_bytes(4)));
                $transaction->reference = sprintf('TXN-%s-%s', $ref, strtoupper(bin2hex(random_bytes(4))));
            }
            if (empty($transaction->gateway_provider)) {
                $transaction->gateway_provider = (string) config('services.payment.gateway', 'fake');
            }
        });
    }

    public function remainingRefundableAmount(): int
    {
        $refunded = (int) $this->refunds()
            ->where('status', RefundStatus::Completed->value)
            ->sum('amount_minor_units');

        return max(0, $this->amount_minor_units - $refunded);
    }

    public function isSettled(): bool
    {
        return $this->status->isSettled();
    }

    /**
     * @return BelongsTo<Booking, $this>
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * @return BelongsTo<Proposal, $this>
     */
    public function proposal(): BelongsTo
    {
        return $this->belongsTo(Proposal::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }

    /**
     * @return HasMany<Refund, $this>
     */
    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    /**
     * @return HasMany<PaymentEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(PaymentEvent::class);
    }

    /**
     * @return HasMany<FinancialDocument, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(FinancialDocument::class);
    }
}
