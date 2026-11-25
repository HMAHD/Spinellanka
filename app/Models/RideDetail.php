<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Database\Factories\RideDetailFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $booking_id
 * @property Carbon $pickup_time
 * @property string $origin_text
 * @property float|null $origin_latitude
 * @property float|null $origin_longitude
 * @property string|null $origin_notes
 * @property string $destination_text
 * @property float|null $destination_latitude
 * @property float|null $destination_longitude
 * @property string|null $destination_notes
 * @property bool $has_return
 * @property Carbon|null $return_pickup_time
 * @property string|null $return_origin_text
 * @property string|null $return_destination_text
 * @property string|null $return_notes
 * @property int $passenger_count
 * @property int $luggage_count
 * @property int $total_waiting_minutes
 * @property float|null $estimated_outbound_distance_km
 * @property int|null $estimated_outbound_duration_minutes
 * @property float|null $estimated_return_distance_km
 * @property int|null $estimated_return_duration_minutes
 * @property array<string, mixed>|null $route_map_data
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Booking $booking
 * @property-read Collection<int, RideStop> $stops
 */
class RideDetail extends Model
{
    /** @use HasFactory<RideDetailFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'pickup_time' => 'datetime',
            'return_pickup_time' => 'datetime',
            'origin_latitude' => 'float',
            'origin_longitude' => 'float',
            'destination_latitude' => 'float',
            'destination_longitude' => 'float',
            'has_return' => 'boolean',
            'passenger_count' => 'integer',
            'luggage_count' => 'integer',
            'total_waiting_minutes' => 'integer',
            'estimated_outbound_distance_km' => 'float',
            'estimated_outbound_duration_minutes' => 'integer',
            'estimated_return_distance_km' => 'float',
            'estimated_return_duration_minutes' => 'integer',
            'route_map_data' => 'array',
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
     * @return HasMany<RideStop, $this>
     */
    public function stops(): HasMany
    {
        return $this->hasMany(RideStop::class)->orderBy('stop_order');
    }
}
