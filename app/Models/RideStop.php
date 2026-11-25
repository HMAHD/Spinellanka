<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Database\Factories\RideStopFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $ride_detail_id
 * @property int $stop_order
 * @property string $location_text
 * @property float|null $latitude
 * @property float|null $longitude
 * @property int $planned_waiting_minutes
 * @property string|null $notes
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read RideDetail $rideDetail
 */
class RideStop extends Model
{
    /** @use HasFactory<RideStopFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'stop_order' => 'integer',
            'latitude' => 'float',
            'longitude' => 'float',
            'planned_waiting_minutes' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<RideDetail, $this>
     */
    public function rideDetail(): BelongsTo
    {
        return $this->belongsTo(RideDetail::class);
    }
}
