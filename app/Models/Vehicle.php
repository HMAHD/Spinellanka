<?php

namespace App\Models;

use App\Domain\Catalog\Enums\TransmissionType;
use App\Domain\Catalog\Enums\VehicleStatus;
use App\Domain\Catalog\Enums\VehicleType;
use Carbon\Carbon;
use Database\Factories\VehicleFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @use HasFactory<VehicleFactory>
 *
 * @property int $id
 * @property string $public_name
 * @property string $internal_fleet_id
 * @property string $make
 * @property string $model
 * @property int $year
 * @property string $license_plate
 * @property string|null $vin
 * @property Carbon|null $registration_expiry
 * @property Carbon|null $insurance_expiry
 * @property VehicleType $vehicle_type
 * @property int $passenger_capacity
 * @property int $luggage_capacity
 * @property TransmissionType $transmission
 * @property bool $has_ac
 * @property array<string>|null $accessibility_features
 * @property array<string>|null $amenities
 * @property bool $eligible_for_pickup
 * @property bool $eligible_for_transfer
 * @property bool $eligible_for_ride
 * @property bool $eligible_for_rent
 * @property int $turnaround_buffer_minutes
 * @property VehicleStatus $status
 * @property string|null $internal_notes
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Collection<int, VehicleMedia> $media
 * @property-read Collection<int, VehicleDriverAssignment> $driverAssignments
 * @property-read Collection<int, VehicleScheduleBlock> $scheduleBlocks
 * @property-read Collection<int, BookingHold> $holds
 * @property-read \Illuminate\Database\Eloquent\Collection<int, VehicleMedia> $media
 * @property-read \Illuminate\Database\Eloquent\Collection<int, VehicleDriverAssignment> $driverAssignments
 * @property-read \Illuminate\Database\Eloquent\Collection<int, VehicleScheduleBlock> $scheduleBlocks
 * @property-read \Illuminate\Database\Eloquent\Collection<int, BookingHold> $holds
 */
class Vehicle extends Model
{
    /** @use HasFactory<VehicleFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected $attributes = [
        'has_ac' => true,
        'eligible_for_pickup' => false,
        'eligible_for_transfer' => false,
        'eligible_for_ride' => false,
        'eligible_for_rent' => false,
        'turnaround_buffer_minutes' => 90,
        'status' => 'active',
        'accessibility_features' => '[]',
        'amenities' => '[]',
        'vin' => null,
        'registration_expiry' => null,
        'insurance_expiry' => null,
        'internal_notes' => null,
    ];

    protected $hidden = [
        'license_plate',
        'vin',
        'internal_fleet_id',
        'registration_expiry',
        'insurance_expiry',
        'internal_notes',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'passenger_capacity' => 'integer',
            'luggage_capacity' => 'integer',
            'has_ac' => 'boolean',
            'eligible_for_pickup' => 'boolean',
            'eligible_for_transfer' => 'boolean',
            'eligible_for_ride' => 'boolean',
            'eligible_for_rent' => 'boolean',
            'turnaround_buffer_minutes' => 'integer',
            'registration_expiry' => 'date',
            'insurance_expiry' => 'date',
            'accessibility_features' => 'array',
            'amenities' => 'array',
            'status' => VehicleStatus::class,
            'vehicle_type' => VehicleType::class,
            'transmission' => TransmissionType::class,
        ];
    }

    /**
     * @return HasMany<VehicleMedia, $this>
     */
    public function media(): HasMany
    {
        return $this->hasMany(VehicleMedia::class)->orderBy('sort_order');
    }

    /**
     * @return HasMany<VehicleDriverAssignment, $this>
     */
    public function driverAssignments(): HasMany
    {
        return $this->hasMany(VehicleDriverAssignment::class);
    }

    /**
     * @return HasMany<VehicleScheduleBlock, $this>
     */
    public function scheduleBlocks(): HasMany
    {
        return $this->hasMany(VehicleScheduleBlock::class);
    }

    /**
     * @return HasMany<BookingHold, $this>
     */
    public function holds(): HasMany
    {
        return $this->hasMany(BookingHold::class);
    }
}
