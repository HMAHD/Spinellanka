<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Operations\Enums\IncidentSeverity;
use App\Domain\Operations\Enums\IncidentStatus;
use App\Domain\Operations\Enums\IncidentType;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $incident_number
 * @property int|null $booking_id
 * @property int|null $vehicle_id
 * @property int|null $driver_id
 * @property IncidentType $type
 * @property IncidentSeverity $severity
 * @property IncidentStatus $status
 * @property string|null $location_text
 * @property Carbon $occurred_at
 * @property Carbon $reported_at
 * @property string|null $reported_by_name
 * @property string|null $reported_by_role
 * @property string $description
 * @property bool $police_report_filed
 * @property string|null $police_station
 * @property string|null $police_report_number
 * @property bool $insurance_claim_filed
 * @property string|null $insurance_claim_reference
 * @property int $total_financial_impact_minor_units
 * @property string $currency
 * @property int|null $assigned_to_user_id
 * @property string|null $resolution_summary
 * @property Carbon|null $resolved_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Booking|null $booking
 * @property-read Vehicle|null $vehicle
 * @property-read Driver|null $driver
 * @property-read User|null $assignedToUser
 * @property-read \Illuminate\Database\Eloquent\Collection<int, IncidentAction> $actions
 * @property-read \Illuminate\Database\Eloquent\Collection<int, IncidentFinancialEntry> $financialEntries
 * @property-read \Illuminate\Database\Eloquent\Collection<int, IncidentAttachment> $attachments
 */
class Incident extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'type' => IncidentType::class,
            'severity' => IncidentSeverity::class,
            'status' => IncidentStatus::class,
            'occurred_at' => 'datetime',
            'reported_at' => 'datetime',
            'police_report_filed' => 'boolean',
            'insurance_claim_filed' => 'boolean',
            'total_financial_impact_minor_units' => 'integer',
            'resolved_at' => 'datetime',
        ];
    }

    public static function generateIncidentNumber(): string
    {
        $date = Carbon::now('Asia/Colombo')->format('Ymd');
        $random = strtoupper(bin2hex(random_bytes(3)));

        return sprintf('INC-%s-%s', $date, $random);
    }

    public static function generateReference(): string
    {
        return self::generateIncidentNumber();
    }

    public function getReferenceAttribute(): string
    {
        return $this->incident_number;
    }

    /**
     * @return BelongsTo<Booking, $this>
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
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
    public function assignedToUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    /**
     * @return HasMany<IncidentAction, $this>
     */
    public function actions(): HasMany
    {
        return $this->hasMany(IncidentAction::class);
    }

    /**
     * @return HasMany<IncidentFinancialEntry, $this>
     */
    public function financialEntries(): HasMany
    {
        return $this->hasMany(IncidentFinancialEntry::class);
    }

    /**
     * @return HasMany<IncidentAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(IncidentAttachment::class);
    }
}
