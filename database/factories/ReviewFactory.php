<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Booking\Enums\ReviewSource;
use App\Domain\Booking\Enums\ReviewStatus;
use App\Domain\Booking\ServiceType;
use App\Models\Booking;
use App\Models\Review;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Review>
 */
class ReviewFactory extends Factory
{
    protected $model = Review::class;

    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory(),
            'traveler_name' => fake()->name(),
            'traveler_country' => fake()->countryCode(),
            'service_type' => ServiceType::AirportPickup,
            'rating' => 5,
            'title' => 'Exceptional service and pristine vehicle',
            'review_text' => 'Driver was on time with a name board at CMB airport. Highly recommended for traveling in Sri Lanka!',
            'source' => ReviewSource::Trustpilot,
            'external_url' => 'https://www.trustpilot.com/reviews/sample123',
            'status' => ReviewStatus::Published,
            'is_featured' => false,
            'verified_trip' => true,
            'invitation_token' => Str::random(40),
            'invited_at' => Carbon::now()->subDays(3),
            'submitted_at' => Carbon::now()->subDays(2),
            'moderated_by_user_id' => null,
            'moderated_at' => Carbon::now()->subDay(),
            'moderation_notes' => 'Verified against completed booking.',
        ];
    }

    public function featured(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_featured' => true,
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ReviewStatus::PendingModeration,
            'moderated_at' => null,
        ]);
    }
}
