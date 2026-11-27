<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Booking\Enums\DealStatus;
use App\Domain\Booking\ServiceType;
use App\Models\Deal;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Deal>
 */
class DealFactory extends Factory
{
    protected $model = Deal::class;

    public function definition(): array
    {
        $title = fake()->sentence(3);

        return [
            'slug' => Str::slug($title).'-'.Str::random(5),
            'service_type' => ServiceType::AirportPickup,
            'title' => $title,
            'summary' => fake()->paragraph(1),
            'description_markdown' => fake()->paragraphs(3, true),
            'preset_payload' => [
                'pickup_location' => 'Bandaranaike International Airport (CMB)',
                'destination_town' => 'Colombo Fort',
                'passengers' => 2,
                'luggage' => 2,
            ],
            'image_path' => null,
            'image_alt' => null,
            'image_source' => null,
            'image_approver' => null,
            'image_approved_at' => null,
            'status' => DealStatus::Published,
            'is_featured' => false,
            'priority' => 0,
            'published_at' => Carbon::now('Asia/Colombo')->subDay(),
            'expires_at' => Carbon::now('Asia/Colombo')->addMonth(),
            'promotion_id' => null,
            'created_by_user_id' => null,
            'updated_by_user_id' => null,
        ];
    }

    public function featured(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_featured' => true,
            'priority' => 10,
        ]);
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DealStatus::Draft,
        ]);
    }

    public function withApprovedMedia(): static
    {
        return $this->state(fn (array $attributes) => [
            'image_path' => 'deals/sample-route.jpg',
            'image_alt' => 'Colombo Airport VIP Transfer route scenic highway',
            'image_source' => 'Client verified fleet photo library 2026',
            'image_approver' => 'Marketing Lead',
            'image_approved_at' => Carbon::now('Asia/Colombo'),
        ]);
    }
}
