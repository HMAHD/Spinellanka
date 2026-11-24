<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Booking\Enums\BookingSource;
use App\Domain\Booking\Enums\BookingStatus;
use App\Domain\Booking\Enums\ConfirmationChannel;
use App\Domain\Booking\Enums\ContactType;
use App\Domain\Booking\ServiceType;
use App\Domain\Pricing\ValueObjects\Money;
use Carbon\Carbon;
use Database\Factories\BookingFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $reference
 * @property ServiceType $service_type
 * @property BookingStatus $status
 * @property int $vehicle_id
 * @property int|null $estimate_id
 * @property int|null $schedule_block_id
 * @property BookingSource $source
 * @property int|null $assigned_staff_id
 * @property string $currency
 * @property int|null $total_amount_minor_units
 * @property int|null $approximate_lkr_amount_minor_units
 * @property string $request_hash
 * @property ConfirmationChannel|null $confirmation_channel
 * @property string|null $confirmation_notes
 * @property string|null $cancellation_reason
 * @property string|null $decline_reason
 * @property Carbon|null $confirmed_at
 * @property Carbon|null $cancelled_at
 * @property Carbon|null $declined_at
 * @property Carbon|null $expires_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Vehicle $vehicle
 * @property-read Estimate|null $estimate
 * @property-read VehicleScheduleBlock|null $scheduleBlock
 * @property-read User|null $assignedStaff
 * @property-read AirportPickupDetail|null $airportPickupDetail
 * @property-read AirportTransferDetail|null $airportTransferDetail
 */
class Booking extends Model
{
    /** @use HasFactory<BookingFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'service_type' => ServiceType::class,
            'status' => BookingStatus::class,
            'source' => BookingSource::class,
            'confirmation_channel' => ConfirmationChannel::class,
            'total_amount_minor_units' => 'integer',
            'approximate_lkr_amount_minor_units' => 'integer',
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'declined_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public static function generateReference(): string
    {
        do {
            $reference = 'BKG-'.strtoupper(Str::random(8));
        } while (static::where('reference', $reference)->exists());

        return $reference;
    }

    public function totalMoney(): ?Money
    {
        if ($this->total_amount_minor_units === null) {
            return null;
        }

        return new Money($this->total_amount_minor_units, $this->currency ?: 'USD');
    }

    public function approximateLkrMoney(): ?Money
    {
        if ($this->approximate_lkr_amount_minor_units === null) {
            return null;
        }

        return new Money($this->approximate_lkr_amount_minor_units, 'LKR');
    }

    public function computeRequestHash(): string
    {
        if ($this->airportPickupDetail) {
            $detail = $this->airportPickupDetail;
            $payloadData = [
                'type' => 'airport_pickup',
                'airport' => $detail->airport,
                'flight_number' => $detail->flight_number,
                'arrival_time' => $detail->arrival_time ? $detail->arrival_time->setTimezone('Asia/Colombo')->format('Y-m-d H:i:s') : '',
                'destination_text' => $detail->destination_text,
                'passenger_count' => $detail->passenger_count,
                'luggage_count' => $detail->luggage_count,
                'vehicle_id' => $this->vehicle_id,
                'total_amount_minor_units' => $this->total_amount_minor_units,
            ];

            return hash('sha256', serialize($payloadData));
        }

        if ($this->airportTransferDetail) {
            $detail = $this->airportTransferDetail;
            $payloadData = [
                'type' => 'airport_transfer',
                'airport' => $detail->airport,
                'flight_number' => $detail->flight_number,
                'departure_time' => $detail->departure_time ? $detail->departure_time->setTimezone('Asia/Colombo')->format('Y-m-d H:i:s') : '',
                'origin_text' => $detail->origin_text,
                'chosen_pickup_time' => $detail->chosen_pickup_time ? $detail->chosen_pickup_time->setTimezone('Asia/Colombo')->format('Y-m-d H:i:s') : '',
                'passenger_count' => $detail->passenger_count,
                'luggage_count' => $detail->luggage_count,
                'vehicle_id' => $this->vehicle_id,
                'total_amount_minor_units' => $this->total_amount_minor_units,
            ];

            return hash('sha256', serialize($payloadData));
        }

        return hash('sha256', serialize(['vehicle_id' => $this->vehicle_id]));
    }

    public function isPending(): bool
    {
        return $this->status->isPending();
    }

    public function canBeConfirmed(): bool
    {
        return $this->status->canBeConfirmed();
    }

    public function canBeDeclined(): bool
    {
        return $this->status->canBeDeclined();
    }

    public function canBeCancelled(): bool
    {
        return $this->status->canBeCancelled();
    }

    /**
     * @return BelongsTo<Vehicle, $this>
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * @return BelongsTo<Estimate, $this>
     */
    public function estimate(): BelongsTo
    {
        return $this->belongsTo(Estimate::class);
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
    public function assignedStaff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_staff_id');
    }

    /**
     * @return HasMany<BookingContact, $this>
     */
    public function contacts(): HasMany
    {
        return $this->hasMany(BookingContact::class);
    }

    /**
     * @return HasOne<BookingContact, $this>
     */
    public function booker(): HasOne
    {
        return $this->hasOne(BookingContact::class)->where('type', ContactType::Booker->value);
    }

    /**
     * @return HasOne<BookingContact, $this>
     */
    public function leadTraveler(): HasOne
    {
        return $this->hasOne(BookingContact::class)->where('type', ContactType::LeadTraveler->value);
    }

    /**
     * @return HasOne<AirportPickupDetail, $this>
     */
    public function airportPickupDetail(): HasOne
    {
        return $this->hasOne(AirportPickupDetail::class);
    }

    /**
     * @return HasOne<AirportTransferDetail, $this>
     */
    public function airportTransferDetail(): HasOne
    {
        return $this->hasOne(AirportTransferDetail::class);
    }

    /**
     * @return HasMany<BookingPolicyAcceptance, $this>
     */
    public function policyAcceptances(): HasMany
    {
        return $this->hasMany(BookingPolicyAcceptance::class);
    }

    /**
     * @return HasMany<GuestAccessToken, $this>
     */
    public function guestAccessTokens(): HasMany
    {
        return $this->hasMany(GuestAccessToken::class);
    }

    /**
     * @param  Builder<Booking>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->whereIn('status', [BookingStatus::Submitted->value, BookingStatus::UnderReview->value]);
    }

    /**
     * @param  Builder<Booking>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereNotIn('status', [
            BookingStatus::Declined->value,
            BookingStatus::Cancelled->value,
            BookingStatus::Expired->value,
        ]);
    }
}
