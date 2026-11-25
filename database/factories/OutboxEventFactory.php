<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Messaging\Enums\OutboxEventStatus;
use App\Models\Booking;
use App\Models\OutboxEvent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<OutboxEvent>
 */
class OutboxEventFactory extends Factory
{
    protected $model = OutboxEvent::class;

    public function definition(): array
    {
        return [
            'event_type' => 'booking.submitted',
            'aggregate_type' => Booking::class,
            'aggregate_id' => Booking::factory(),
            'idempotency_key' => (string) Str::uuid(),
            'payload_version' => 1,
            'payload' => [
                'booking_id' => 1,
                'reference' => 'SPL-TEST',
                'status_url' => 'https://spinellanka.test/booking/status/test-token',
            ],
            'status' => OutboxEventStatus::Pending,
            'attempts' => 0,
            'last_error' => null,
            'processed_at' => null,
        ];
    }

    public function processed(): static
    {
        return $this->state(fn () => [
            'status' => OutboxEventStatus::Processed,
            'processed_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn () => [
            'status' => OutboxEventStatus::Failed,
            'attempts' => 3,
            'last_error' => 'Processing exception',
        ]);
    }
}
