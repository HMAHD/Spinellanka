<?php

namespace Database\Factories;

use App\Domain\Catalog\Enums\ExtraPricingType;
use App\Models\Extra;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Extra> */
class ExtraFactory extends Factory
{
    protected $model = Extra::class;

    public function definition(): array
    {
        return [
            'name' => 'Test Extra '.fake()->word(),
            'slug' => 'test-extra-'.fake()->unique()->slug(),
            'price_minor_units' => 1500,
            'currency' => 'USD',
            'pricing_type' => ExtraPricingType::PerBooking,
            'max_quantity' => 2,
            'is_active' => true,
        ];
    }
}
