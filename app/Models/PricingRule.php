<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Booking\ServiceType;
use Carbon\Carbon;
use Database\Factories\PricingRuleFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @use HasFactory<PricingRuleFactory>
 *
 * @property int $id
 * @property ServiceType $service_type
 * @property int|null $vehicle_id
 * @property int $version
 * @property int $priority
 * @property bool $is_active
 * @property Carbon $effective_from
 * @property Carbon|null $effective_to
 * @property string|null $notes
 * @property array<string, mixed>|null $metadata
 * @property int|null $created_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Vehicle|null $vehicle
 * @property-read User|null $creator
 * @property-read Collection<int, PricingRuleComponent> $components
 * @property-read Collection<int, Estimate> $estimates
 */
class PricingRule extends Model
{
    /** @use HasFactory<PricingRuleFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'service_type' => ServiceType::class,
            'is_active' => 'boolean',
            'version' => 'integer',
            'priority' => 'integer',
            'effective_from' => 'datetime',
            'effective_to' => 'datetime',
            'metadata' => 'array',
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
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<PricingRuleComponent, $this>
     */
    public function components(): HasMany
    {
        return $this->hasMany(PricingRuleComponent::class);
    }

    /**
     * @return HasMany<Estimate, $this>
     */
    public function estimates(): HasMany
    {
        return $this->hasMany(Estimate::class);
    }
}
