<?php

namespace App\Models;

use App\Domain\Catalog\Enums\MediaType;
use Carbon\Carbon;
use Database\Factories\VehicleMediaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * @use HasFactory<VehicleMediaFactory>
 *
 * @property int $id
 * @property int $vehicle_id
 * @property string $file_path
 * @property string $disk
 * @property MediaType $media_type
 * @property string $alt_text
 * @property string|null $photographer
 * @property string|null $usage_rights
 * @property int $sort_order
 * @property bool $is_approved
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Vehicle $vehicle
 */
class VehicleMedia extends Model
{
    /** @use HasFactory<VehicleMediaFactory> */
    use HasFactory;

    protected $table = 'vehicle_media';

    protected $guarded = ['id'];

    protected $attributes = [
        'disk' => 'public',
        'sort_order' => 0,
        'is_approved' => false,
        'photographer' => null,
        'usage_rights' => null,
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_approved' => 'boolean',
            'media_type' => MediaType::class,
        ];
    }

    protected static function booted(): void
    {
        static::deleted(function (VehicleMedia $media): void {
            if (Storage::disk($media->disk)->exists($media->file_path)) {
                Storage::disk($media->disk)->delete($media->file_path);
            }
        });
    }

    /**
     * @return BelongsTo<Vehicle, $this>
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }
}
