<?php

namespace App\Models;

use App\Domain\Catalog\Enums\DriverStatus;
use Carbon\Carbon;
use Database\Factories\DriverFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @use HasFactory<DriverFactory>
 *
 * @property int $id
 * @property string $public_name
 * @property string $full_name
 * @property string $phone
 * @property string|null $whatsapp
 * @property string|null $portrait_path
 * @property array<string>|null $public_trust_facts
 * @property string|null $license_number
 * @property Carbon|null $license_expiry
 * @property DriverStatus $status
 * @property string|null $internal_notes
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Driver extends Model
{
    /** @use HasFactory<DriverFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected $attributes = [
        'status' => 'active',
        'whatsapp' => null,
        'portrait_path' => null,
        'public_trust_facts' => '[]',
        'license_number' => null,
        'license_expiry' => null,
        'internal_notes' => null,
    ];

    protected $hidden = [
        'full_name',
        'phone',
        'whatsapp',
        'license_number',
        'license_expiry',
        'internal_notes',
    ];

    protected function casts(): array
    {
        return [
            'license_expiry' => 'date',
            'public_trust_facts' => 'array',
            'status' => DriverStatus::class,
        ];
    }

    /**
     * @return HasMany<VehicleDriverAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(VehicleDriverAssignment::class);
    }
}
