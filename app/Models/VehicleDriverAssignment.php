<?php

namespace App\Models;

use Carbon\Carbon;
use Database\Factories\VehicleDriverAssignmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @use HasFactory<VehicleDriverAssignmentFactory>
 *
 * @property int $id
 * @property int $vehicle_id
 * @property int $driver_id
 * @property Carbon $starts_at
 * @property Carbon|null $ends_at
 * @property bool $is_permanent
 * @property string|null $reason
 * @property int|null $assigned_by_user_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Vehicle $vehicle
 * @property-read Driver $driver
 * @property-read User|null $assignedByUser
 */
class VehicleDriverAssignment extends Model
{
    /** @use HasFactory<VehicleDriverAssignmentFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected $attributes = [
        'is_permanent' => false,
        'ends_at' => null,
        'reason' => null,
        'assigned_by_user_id' => null,
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_permanent' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Vehicle, $this>
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * @return BelongsTo<Driver, $this>
     */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by_user_id');
    }
}
