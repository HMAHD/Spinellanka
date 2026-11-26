<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Payment\Enums\RefundStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $payment_transaction_id
 * @property int $booking_id
 * @property string $reference
 * @property int $amount_minor_units
 * @property string $currency
 * @property string $reason
 * @property RefundStatus $status
 * @property string|null $gateway_refund_id
 * @property int $approved_by_user_id
 * @property Carbon|null $processed_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read PaymentTransaction $transaction
 * @property-read Booking $booking
 * @property-read User $approvedBy
 */
class Refund extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => RefundStatus::class,
            'processed_at' => 'datetime',
        ];
    }

    public static function generateReference(PaymentTransaction $transaction): string
    {
        return sprintf('REF-%s-%s', $transaction->reference, strtoupper(bin2hex(random_bytes(3))));
    }

    /**
     * @return BelongsTo<PaymentTransaction, $this>
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(PaymentTransaction::class, 'payment_transaction_id');
    }

    /**
     * @return BelongsTo<Booking, $this>
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }
}
