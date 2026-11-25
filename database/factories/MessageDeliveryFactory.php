<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Messaging\Enums\MessageChannel;
use App\Domain\Messaging\Enums\MessageDeliveryStatus;
use App\Domain\Messaging\Enums\RecipientType;
use App\Models\Booking;
use App\Models\MessageDelivery;
use App\Models\OutboxEvent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MessageDelivery>
 */
class MessageDeliveryFactory extends Factory
{
    protected $model = MessageDelivery::class;

    public function definition(): array
    {
        return [
            'outbox_event_id' => OutboxEvent::factory(),
            'booking_id' => Booking::factory(),
            'channel' => MessageChannel::WhatsApp,
            'recipient_type' => RecipientType::Booker,
            'recipient_identifier' => '+447700900077',
            'template_name' => 'booking_submitted_booker_whatsapp',
            'template_version' => '1.0',
            'template_params' => [
                'reference' => 'SPL-TEST',
                'status_url' => 'https://spinellanka.test/booking/status/test-token',
            ],
            'idempotency_key' => (string) Str::uuid(),
            'provider' => 'fake',
            'provider_message_id' => null,
            'status' => MessageDeliveryStatus::Pending,
            'attempts' => 0,
            'max_attempts' => 3,
            'last_attempted_at' => null,
            'sent_at' => null,
            'delivered_at' => null,
            'failed_at' => null,
            'last_error_code' => null,
            'last_error_message' => null,
        ];
    }

    public function email(): static
    {
        return $this->state(fn () => [
            'channel' => MessageChannel::Email,
            'recipient_identifier' => fake()->safeEmail(),
            'template_name' => 'booking_submitted_booker_email',
        ]);
    }

    public function sent(): static
    {
        return $this->state(fn () => [
            'status' => MessageDeliveryStatus::Sent,
            'sent_at' => now(),
            'provider_message_id' => (string) Str::uuid(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn () => [
            'status' => MessageDeliveryStatus::Failed,
            'failed_at' => now(),
            'attempts' => 3,
            'last_error_code' => 'DELIVERY_FAILED',
            'last_error_message' => 'Simulated delivery failure',
        ]);
    }

    public function deliveryUnknown(): static
    {
        return $this->state(fn () => [
            'status' => MessageDeliveryStatus::DeliveryUnknown,
            'last_attempted_at' => now(),
            'attempts' => 1,
            'last_error_code' => 'TIMEOUT',
            'last_error_message' => 'HTTP timeout waiting for gateway acceptance',
        ]);
    }
}
