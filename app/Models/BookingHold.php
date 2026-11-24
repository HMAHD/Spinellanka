<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Availability\Enums\HoldStatus;
use App\Domain\Booking\ServiceType;
use Carbon\Carbon;
use Database\Factories\BookingHoldFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @use HasFactory<BookingHoldFactory>
 *
 * @property int $id
 * @property string $hold_token_hash
 * @property int $vehicle_id
 * @property int $schedule_block_id
 * @property string|null $group_id
 * @property string|null $session_id
 * @property ServiceType $service_type
 * @property int $hold_duration_minutes
 * @property HoldStatus $status
 * @property Carbon $expires_at
 * @property Carbon|null $released_at
 * @property string|null $release_reason
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Vehicle $vehicle
 * @property-read VehicleScheduleBlock $scheduleBlock
 */
class BookingHold extends Model
{
    /** @use HasFactory<BookingHoldFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected $attributes = [
        'hold_duration_minutes' => 15,
        'status' => 'active',
        'released_at' => null,
        'release_reason' => null,
    ];

    protected function casts(): array
    {
        return [
            'service_type' => ServiceType::class,
            'status' => HoldStatus::class,
            'hold_duration_minutes' => 'integer',
            'expires_at' => 'datetime',
            'released_at' => 'datetime',
        ];
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * @param  Builder<BookingHold>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', HoldStatus::Active->value);
    }

    /**
     * @param  Builder<BookingHold>  $query
     */
    public function scopeExpired(Builder $query, Carbon $asOf): void
    {
        $query->where('status', HoldStatus::Active->value)
            ->where('expires_at', '<=', $asOf);
    }

    /**
     * @param  Builder<BookingHold>  $query
     */
    public function scopeInGroup(Builder $query, string $groupId): void
    {
        $query->where('group_id', $groupId);
    }

    /**
     * @return BelongsTo<Vehicle, $this>
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * @return BelongsTo<VehicleScheduleBlock, $this>
     */
    public function scheduleBlock(): BelongsTo
    {
        return $this->belongsTo(VehicleScheduleBlock::class, 'schedule_block_id');
    }
}
