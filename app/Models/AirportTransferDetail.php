<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Database\Factories\AirportTransferDetailFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $booking_id
 * @property string $airport
 * @property string $flight_number
 * @property Carbon $departure_time
 * @property string $origin_text
 * @property string|null $origin_place_id
 * @property float|null $origin_lat
 * @property float|null $origin_lng
 * @property string|null $origin_notes
 * @property Carbon $recommended_pickup_time
 * @property Carbon $chosen_pickup_time
 * @property bool $is_unsafe_time
 * @property bool $warning_accepted
 * @property int $passenger_count
 * @property int $luggage_count
 * @property string|null $special_instructions
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Booking $booking
 */
class AirportTransferDetail extends Model
{
    /** @use HasFactory<AirportTransferDetailFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'departure_time' => 'datetime',
            'recommended_pickup_time' => 'datetime',
            'chosen_pickup_time' => 'datetime',
            'origin_lat' => 'float',
            'origin_lng' => 'float',
            'is_unsafe_time' => 'boolean',
            'warning_accepted' => 'boolean',
            'passenger_count' => 'integer',
            'luggage_count' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Booking, $this>
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
