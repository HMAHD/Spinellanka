<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Database\Factories\AirportPickupDetailFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $booking_id
 * @property string $airport
 * @property string $flight_number
 * @property Carbon $arrival_time
 * @property string $destination_text
 * @property string|null $destination_place_id
 * @property float|null $destination_lat
 * @property float|null $destination_lng
 * @property string|null $destination_notes
 * @property int $passenger_count
 * @property int $luggage_count
 * @property bool $meet_and_greet
 * @property string|null $name_board_text
 * @property string|null $waiting_instructions
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Booking $booking
 */
class AirportPickupDetail extends Model
{
    /** @use HasFactory<AirportPickupDetailFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'arrival_time' => 'datetime',
            'destination_lat' => 'float',
            'destination_lng' => 'float',
            'passenger_count' => 'integer',
            'luggage_count' => 'integer',
            'meet_and_greet' => 'boolean',
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

