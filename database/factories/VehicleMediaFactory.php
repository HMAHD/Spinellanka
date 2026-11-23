<?php

namespace Database\Factories;

use App\Domain\Catalog\Enums\MediaType;
use App\Models\Vehicle;
use App\Models\VehicleMedia;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<VehicleMedia> */
class VehicleMediaFactory extends Factory
{
    protected $model = VehicleMedia::class;

    public function definition(): array
    {
        return [
            'vehicle_id' => Vehicle::factory(),
            'file_path' => 'vehicles/test-photo.jpg',
            'disk' => 'public',
            'media_type' => MediaType::ExteriorFront,
            'alt_text' => 'Test vehicle photo',
            'sort_order' => 0,
            'is_approved' => true,
        ];
    }
}
