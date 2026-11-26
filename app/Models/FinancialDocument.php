<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Payment\Enums\FinancialDocumentType;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $booking_id
 * @property int|null $proposal_id
 * @property int|null $payment_transaction_id
 * @property FinancialDocumentType $document_type
 * @property string $document_number
 * @property int $version
 * @property int $amount_minor_units
 * @property string $currency
 * @property string|null $file_path
 * @property string|null $checksum
 * @property array<string, mixed>|null $metadata
 * @property Carbon $issued_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Booking $booking
 * @property-read Proposal|null $proposal
 * @property-read PaymentTransaction|null $transaction
 */
class FinancialDocument extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'document_type' => FinancialDocumentType::class,
            'metadata' => 'array',
            'issued_at' => 'datetime',
        ];
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
     * @return BelongsTo<PaymentTransaction, $this>
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(PaymentTransaction::class, 'payment_transaction_id');
    }
}
