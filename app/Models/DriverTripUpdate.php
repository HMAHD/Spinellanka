<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $driver_assignment_id
 * @property string $status
 * @property string|null $notes
 * @property string|null $client_device_timestamp
 * @property Carbon $recorded_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read DriverAssignment $driverAssignment
 */
class DriverTripUpdate extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'recorded_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<DriverAssignment, $this>
     */
    public function driverAssignment(): BelongsTo
    {
        return $this->belongsTo(DriverAssignment::class);
    }
}
