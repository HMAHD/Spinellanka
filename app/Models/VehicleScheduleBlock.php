<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Availability\Enums\ScheduleBlockKind;
use App\Domain\Availability\Enums\ScheduleBlockStatus;
use Carbon\Carbon;
use Database\Factories\VehicleScheduleBlockFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @use HasFactory<VehicleScheduleBlockFactory>
 *
 * @property int $id
 * @property int $vehicle_id
 * @property ScheduleBlockKind $kind
 * @property string|null $source_type
 * @property int|null $source_id
 * @property string|null $group_id
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property int $buffer_minutes_before
 * @property int $buffer_minutes_after
 * @property Carbon $effective_start
 * @property Carbon $effective_end
 * @property bool $blocks_availability
 * @property ScheduleBlockStatus $status
 * @property bool $is_chauffeured_only
 * @property int|null $driver_id
 * @property bool $is_override
 * @property string|null $override_reason
 * @property int|null $override_authorized_by
 * @property int|null $created_by
 * @property Carbon|null $expires_at
 * @property string|null $notes
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Vehicle $vehicle
 * @property-read Driver|null $driver
 * @property-read User|null $overrideAuthorizedBy
 * @property-read User|null $createdBy
 * @property-read BookingHold|null $hold
 * @property-read Model|null $source
 */
class VehicleScheduleBlock extends Model
{
    /** @use HasFactory<VehicleScheduleBlockFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected $attributes = [
        'buffer_minutes_before' => 0,
        'buffer_minutes_after' => 0,
        'blocks_availability' => true,
        'status' => 'active',
        'is_chauffeured_only' => false,
        'is_override' => false,
        'override_reason' => null,
        'override_authorized_by' => null,
        'created_by' => null,
        'expires_at' => null,
        'notes' => null,
    ];

    protected function casts(): array
    {
        return [
            'kind' => ScheduleBlockKind::class,
            'status' => ScheduleBlockStatus::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'effective_start' => 'datetime',
            'effective_end' => 'datetime',
            'expires_at' => 'datetime',
            'buffer_minutes_before' => 'integer',
            'buffer_minutes_after' => 'integer',
            'blocks_availability' => 'boolean',
            'is_chauffeured_only' => 'boolean',
            'is_override' => 'boolean',
        ];
    }

    /**
     * @param  Builder<VehicleScheduleBlock>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', ScheduleBlockStatus::Active->value);
    }

    /**
     * @param  Builder<VehicleScheduleBlock>  $query
     */
    public function scopeBlocking(Builder $query): void
    {
        $query->where('blocks_availability', true);
    }

    /**
     * Half-open interval overlap check: starts_at < $requestedEnd AND ends_at > $requestedStart
     *
     * @param  Builder<VehicleScheduleBlock>  $query
     */
    public function scopeOverlapping(Builder $query, Carbon $start, Carbon $end): void
    {
        $query->where('effective_start', '<', $end)
            ->where('effective_end', '>', $start);
    }

    /**
     * @param  Builder<VehicleScheduleBlock>  $query
     */
    public function scopeExpired(Builder $query, Carbon $asOf): void
    {
        $query->where('status', ScheduleBlockStatus::Active->value)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $asOf);
    }

    /**
     * @param  Builder<VehicleScheduleBlock>  $query
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
     * @return BelongsTo<Driver, $this>
     */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function overrideAuthorizedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'override_authorized_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return HasOne<BookingHold, $this>
     */
    public function hold(): HasOne
    {
        return $this->hasOne(BookingHold::class, 'schedule_block_id');
    }
}
