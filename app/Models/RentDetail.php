<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Database\Factories\RentDetailFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $booking_id
 * @property int|null $rent_policy_id
 * @property Carbon $pickup_datetime
 * @property Carbon $return_datetime
 * @property int $duration_days
 * @property int $extra_hours
 * @property string $pickup_location_type
 * @property string $pickup_location_text
 * @property string|null $pickup_place_id
 * @property float|null $pickup_lat
 * @property float|null $pickup_lng
 * @property string $return_location_type
 * @property string $return_location_text
 * @property string|null $return_place_id
 * @property float|null $return_lat
 * @property float|null $return_lng
 * @property int $driver_age
 * @property string $driver_licence_country
 * @property int $driver_licence_years
 * @property bool $has_idp
 * @property bool $has_additional_driver
 * @property string|null $additional_driver_name
 * @property int|null $additional_driver_age
 * @property string|null $additional_driver_licence_country
 * @property int|null $additional_driver_licence_years
 * @property bool $additional_driver_has_idp
 * @property int|null $expected_distance_km
 * @property int $included_distance_km
 * @property int $excess_mileage_rate_minor_units
 * @property string $fuel_policy
 * @property int $delivery_fee_minor_units
 * @property int $security_deposit_minor_units
 * @property int $insurance_excess_minor_units
 * @property bool $original_documents_acknowledged
 * @property bool $original_documents_inspected
 * @property Carbon|null $original_documents_inspected_at
 * @property int|null $original_documents_inspected_by
 * @property string|null $original_documents_inspection_notes
 * @property array<string, mixed>|null $extension_state
 * @property string|null $special_instructions
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Booking $booking
 * @property-read RentPolicy|null $rentPolicy
 * @property-read User|null $inspectedBy
 */
class RentDetail extends Model
{
    /** @use HasFactory<RentDetailFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'pickup_datetime' => 'datetime',
            'return_datetime' => 'datetime',
            'duration_days' => 'integer',
            'extra_hours' => 'integer',
            'pickup_lat' => 'float',
            'pickup_lng' => 'float',
            'return_lat' => 'float',
            'return_lng' => 'float',
            'driver_age' => 'integer',
            'driver_licence_years' => 'integer',
            'has_idp' => 'boolean',
            'has_additional_driver' => 'boolean',
            'additional_driver_age' => 'integer',
            'additional_driver_licence_years' => 'integer',
            'additional_driver_has_idp' => 'boolean',
            'expected_distance_km' => 'integer',
            'included_distance_km' => 'integer',
            'excess_mileage_rate_minor_units' => 'integer',
            'delivery_fee_minor_units' => 'integer',
            'security_deposit_minor_units' => 'integer',
            'insurance_excess_minor_units' => 'integer',
            'original_documents_acknowledged' => 'boolean',
            'original_documents_inspected' => 'boolean',
            'original_documents_inspected_at' => 'datetime',
            'extension_state' => 'array',
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
     * @return BelongsTo<RentPolicy, $this>
     */
    public function rentPolicy(): BelongsTo
    {
        return $this->belongsTo(RentPolicy::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function inspectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'original_documents_inspected_by');
    }
}
