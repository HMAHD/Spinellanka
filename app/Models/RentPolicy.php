<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Database\Factories\RentPolicyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $min_driver_age
 * @property int $max_driver_age
 * @property int $min_licence_years
 * @property bool $idp_required
 * @property int $max_additional_drivers
 * @property int $security_deposit_minor_units
 * @property int $insurance_excess_minor_units
 * @property int $daily_mileage_km
 * @property int $excess_mileage_rate_minor_units
 * @property int $grace_period_minutes
 * @property int $extra_hours_threshold
 * @property int $full_day_threshold_hours
 * @property bool $office_handover_available
 * @property bool $airport_handover_available
 * @property bool $hotel_delivery_available
 * @property int $hotel_delivery_fee_minor_units
 * @property string|null $prohibited_roads_text
 * @property int $smoking_penalty_minor_units
 * @property bool $is_active
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class RentPolicy extends Model
{
    /** @use HasFactory<RentPolicyFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'min_driver_age' => 'integer',
            'max_driver_age' => 'integer',
            'min_licence_years' => 'integer',
            'idp_required' => 'boolean',
            'max_additional_drivers' => 'integer',
            'security_deposit_minor_units' => 'integer',
            'insurance_excess_minor_units' => 'integer',
            'daily_mileage_km' => 'integer',
            'excess_mileage_rate_minor_units' => 'integer',
            'grace_period_minutes' => 'integer',
            'extra_hours_threshold' => 'integer',
            'full_day_threshold_hours' => 'integer',
            'office_handover_available' => 'boolean',
            'airport_handover_available' => 'boolean',
            'hotel_delivery_available' => 'boolean',
            'hotel_delivery_fee_minor_units' => 'integer',
            'smoking_penalty_minor_units' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public static function current(): self
    {
        /** @var self|null $policy */
        $policy = static::query()->where('is_active', true)->latest('id')->first();

        if ($policy !== null) {
            return $policy;
        }

        return static::create([
            'min_driver_age' => (int) config('booking.rent.min_driver_age', 23),
            'max_driver_age' => (int) config('booking.rent.max_driver_age', 70),
            'min_licence_years' => (int) config('booking.rent.min_licence_years', 2),
            'idp_required' => (bool) config('booking.rent.idp_required', true),
            'max_additional_drivers' => (int) config('booking.rent.max_additional_drivers', 1),
            'security_deposit_minor_units' => (int) config('booking.rent.security_deposit_minor_units', 50000),
            'insurance_excess_minor_units' => (int) config('booking.rent.insurance_excess_minor_units', 50000),
            'daily_mileage_km' => (int) config('booking.rent.daily_mileage_km', 100),
            'excess_mileage_rate_minor_units' => (int) config('booking.rent.excess_mileage_rate_minor_units', 30),
            'grace_period_minutes' => (int) config('booking.rent.grace_period_minutes', 59),
            'extra_hours_threshold' => (int) config('booking.rent.extra_hours_threshold', 5),
            'full_day_threshold_hours' => (int) config('booking.rent.full_day_threshold_hours', 6),
            'office_handover_available' => true,
            'airport_handover_available' => true,
            'hotel_delivery_available' => true,
            'hotel_delivery_fee_minor_units' => (int) config('booking.rent.hotel_delivery_fee_minor_units', 2000),
            'prohibited_roads_text' => (string) config('booking.rent.prohibited_roads_text', 'Paved public roads only; off-road driving strictly prohibited.'),
            'smoking_penalty_minor_units' => (int) config('booking.rent.smoking_penalty_minor_units', 15000),
            'is_active' => true,
        ]);
    }
}
