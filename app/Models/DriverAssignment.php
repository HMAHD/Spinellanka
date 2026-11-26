<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Operations\Enums\DriverTripStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $booking_id
 * @property int $driver_id
 * @property int $vehicle_id
 * @property bool $is_replacement
 * @property string|null $replacement_reason
 * @property int|null $replacement_authorized_by_user_id
 * @property int|null $assigned_by_user_id
 * @property DriverTripStatus $status
 * @property string|null $issue_reported_notes
 * @property Carbon|null $issue_reported_at
 * @property Carbon $assigned_at
 * @property Carbon|null $acknowledged_at
 * @property Carbon|null $en_route_at
 * @property Carbon|null $arrived_at
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Booking $booking
 * @property-read Driver $driver
 * @property-read Vehicle $vehicle
 * @property-read User|null $replacementAuthorizedBy
 * @property-read User|null $assignedByUser
 * @property-read Collection<int, DriverAccessToken> $accessTokens
 * @property-read Collection<int, DriverTripUpdate> $tripUpdates
 */
class DriverAssignment extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_replacement' => 'boolean',
            'status' => DriverTripStatus::class,
            'issue_reported_at' => 'datetime',
            'assigned_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'en_route_at' => 'datetime',
            'arrived_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
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
     * @return BelongsTo<Driver, $this>
     */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    /**
     * @return BelongsTo<Vehicle, $this>
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function replacementAuthorizedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'replacement_authorized_by_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by_user_id');
    }

    /**
     * @return HasMany<DriverAccessToken, $this>
     */
    public function accessTokens(): HasMany
    {
        return $this->hasMany(DriverAccessToken::class);
    }

    /**
     * @return HasMany<DriverTripUpdate, $this>
     */
    public function tripUpdates(): HasMany
    {
        return $this->hasMany(DriverTripUpdate::class)->orderBy('id');
    }
}
