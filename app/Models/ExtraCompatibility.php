<?php

namespace App\Models;

use App\Domain\Booking\ServiceType;
use App\Domain\Catalog\Enums\VehicleType;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $extra_id
 * @property ServiceType|null $service_type
 * @property VehicleType|null $vehicle_type
 * @property int|null $vehicle_id
 * @property bool $is_compatible
 * @property string $scope_key
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Extra $extra
 * @property-read Vehicle|null $vehicle
 */
class ExtraCompatibility extends Model
{
    protected $table = 'extra_compatibility';

    protected $guarded = ['id'];

    protected $attributes = [
        'is_compatible' => true,
        'service_type' => null,
        'vehicle_type' => null,
        'vehicle_id' => null,
    ];

    protected function casts(): array
    {
        return [
            'is_compatible' => 'boolean',
            'service_type' => ServiceType::class,
            'vehicle_type' => VehicleType::class,
        ];
    }

    /**
     * @return BelongsTo<Extra, $this>
     */
    public function extra(): BelongsTo
    {
        return $this->belongsTo(Extra::class);
    }

    /**
     * @return BelongsTo<Vehicle, $this>
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }
}
