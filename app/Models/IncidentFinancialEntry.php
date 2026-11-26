<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Operations\Enums\IncidentFinancialEntryType;
use App\Domain\Operations\Enums\IncidentFinancialStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $incident_id
 * @property int|null $payment_transaction_id
 * @property IncidentFinancialEntryType $entry_type
 * @property string $description
 * @property int $amount_minor_units
 * @property string $currency
 * @property string|null $original_currency
 * @property int|null $original_amount_minor_units
 * @property float|null $exchange_rate
 * @property IncidentFinancialStatus $status
 * @property int|null $created_by_user_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Incident $incident
 * @property-read PaymentTransaction|null $paymentTransaction
 * @property-read User|null $createdByUser
 */
class IncidentFinancialEntry extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'entry_type' => IncidentFinancialEntryType::class,
            'amount_minor_units' => 'integer',
            'original_amount_minor_units' => 'integer',
            'exchange_rate' => 'float',
            'status' => IncidentFinancialStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Incident, $this>
     */
    public function incident(): BelongsTo
    {
        return $this->belongsTo(Incident::class);
    }

    /**
     * @return BelongsTo<PaymentTransaction, $this>
     */
    public function paymentTransaction(): BelongsTo
    {
        return $this->belongsTo(PaymentTransaction::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
