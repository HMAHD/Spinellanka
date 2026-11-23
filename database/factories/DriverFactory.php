<?php

namespace Database\Factories;

use App\Domain\Catalog\Enums\DriverStatus;
use App\Models\Driver;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Driver> */
class DriverFactory extends Factory
{
    protected $model = Driver::class;

    public function definition(): array
    {
        return [
            'public_name' => 'Test Driver '.fake()->firstName(),
            'full_name' => 'Test Full Driver Name',
            'phone' => '+9477000'.fake()->unique()->numerify('####'),
            'whatsapp' => '+9477000'.fake()->unique()->numerify('####'),
            'status' => DriverStatus::Active,
            'public_trust_facts' => ['10+ years experience', 'Tourist board certified'],
        ];
    }
}
