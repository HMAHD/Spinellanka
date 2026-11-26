<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Operations\Enums\HandoverStatus;
use App\Domain\Operations\Enums\HandoverType;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property int $booking_id
 * @property int $rent_detail_id
 * @property int $vehicle_id
 * @property HandoverType $type
 * @property HandoverStatus $status
 * @property Carbon $scheduled_at
 * @property Carbon|null $completed_at
 * @property int|null $staff_user_id
 * @property string $location_type
 * @property string $location_text
 * @property bool $documents_inspected
 * @property string|null $inspected_doc_types
 * @property string|null $driver_licence_country
 * @property Carbon|null $driver_licence_expiry
 * @property bool $idp_or_aac_verified
 * @property string|null $document_inspection_notes
 * @property string|null $customer_signature_path
 * @property string|null $customer_signer_name
 * @property Carbon|null $customer_signed_at
 * @property string|null $staff_signature_path
 * @property Carbon|null $staff_signed_at
 * @property bool $is_released
 * @property bool $has_exception
 * @property string|null $exception_reason
 * @property int|null $exception_authorized_by_user_id
 * @property Carbon|null $exception_authorized_at
 * @property string|null $operational_notes
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Booking $booking
 * @property-read RentDetail $rentDetail
 * @property-read Vehicle $vehicle
 * @property-read User|null $staffUser
 * @property-read User|null $exceptionAuthorizedBy
 * @property-read VehicleConditionRecord|null $conditionRecord
 */
class Handover extends Model
{
    protected $guarded = ['id'];

    protected $attributes = [
        'status' => 'scheduled',
        'documents_inspected' => false,
        'idp_or_aac_verified' => false,
        'is_released' => false,
        'has_exception' => false,
    ];

    protected function casts(): array
    {
        return [
            'type' => HandoverType::class,
            'status' => HandoverStatus::class,
            'scheduled_at' => 'datetime',
            'completed_at' => 'datetime',
            'documents_inspected' => 'boolean',
            'driver_licence_expiry' => 'datetime',
            'idp_or_aac_verified' => 'boolean',
            'customer_signed_at' => 'datetime',
            'staff_signed_at' => 'datetime',
            'is_released' => 'boolean',
            'has_exception' => 'boolean',
            'exception_authorized_at' => 'datetime',
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
     * @return BelongsTo<RentDetail, $this>
     */
    public function rentDetail(): BelongsTo
    {
        return $this->belongsTo(RentDetail::class);
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
    public function staffUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function exceptionAuthorizedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'exception_authorized_by_user_id');
    }

    /**
     * @return HasOne<VehicleConditionRecord, $this>
     */
    public function conditionRecord(): HasOne
    {
        return $this->hasOne(VehicleConditionRecord::class);
    }
}
