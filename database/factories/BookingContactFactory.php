<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Booking\Enums\ContactType;
use App\Models\Booking;
use App\Models\BookingContact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BookingContact>
 */
class BookingContactFactory extends Factory
{
    protected $model = BookingContact::class;

    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory(),
            'type' => ContactType::Booker,
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'phone' => '+447700900077',
            'is_whatsapp' => true,
            'email' => fake()->safeEmail(),
            'marketing_consent' => false,
        ];
    }

    public function leadTraveler(): static
    {
        return $this->state(fn () => [
            'type' => ContactType::LeadTraveler,
            'marketing_consent' => false,
        ]);
    }
}
