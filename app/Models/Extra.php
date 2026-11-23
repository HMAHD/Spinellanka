<?php

namespace App\Models;

use App\Domain\Catalog\Enums\ExtraPricingType;
use Carbon\Carbon;
use Database\Factories\ExtraFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @use HasFactory<ExtraFactory>
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property int $price_minor_units
 * @property string $currency
 * @property ExtraPricingType $pricing_type
 * @property int $max_quantity
 * @property bool $is_active
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Extra extends Model
{
    /** @use HasFactory<ExtraFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected $attributes = [
        'price_minor_units' => 0,
        'currency' => 'USD',
        'pricing_type' => 'per_booking',
        'max_quantity' => 1,
        'is_active' => true,
        'description' => null,
    ];

    protected function casts(): array
    {
        return [
            'price_minor_units' => 'integer',
            'pricing_type' => ExtraPricingType::class,
            'max_quantity' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<ExtraCompatibility, $this>
     */
    public function compatibilities(): HasMany
    {
        return $this->hasMany(ExtraCompatibility::class);
    }
}
