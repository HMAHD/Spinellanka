<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Operations\Enums\FuelLevel;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $handover_id
 * @property string $record_type
 * @property int $odometer_km
 * @property FuelLevel $fuel_level
 * @property array<string, bool>|null $accessories_checklist
 * @property bool $is_clean
 * @property bool $smoking_detected
 * @property bool $is_zero_km_verified
 * @property string|null $zero_km_reason
 * @property string|null $exterior_condition_notes
 * @property string|null $interior_condition_notes
 * @property Carbon $recorded_at
 * @property int|null $recorded_by_user_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Handover $handover
 * @property-read User|null $recordedByUser
 * @property-read Collection<int, ConditionAttachment> $attachments
 */
class VehicleConditionRecord extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'odometer_km' => 'integer',
            'fuel_level' => FuelLevel::class,
            'accessories_checklist' => 'array',
            'is_clean' => 'boolean',
            'smoking_detected' => 'boolean',
            'is_zero_km_verified' => 'boolean',
            'recorded_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Handover, $this>
     */
    public function handover(): BelongsTo
    {
        return $this->belongsTo(Handover::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recordedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }

    /**
     * @return HasMany<ConditionAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(ConditionAttachment::class);
    }
}
