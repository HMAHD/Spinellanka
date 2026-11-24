<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Booking\Enums\BookingSource;
use App\Domain\Booking\Enums\BookingStatus;
use App\Domain\Booking\Enums\ConfirmationChannel;
use App\Domain\Booking\ServiceType;
use App\Models\Booking;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    protected $model = Booking::class;

    public function definition(): array
    {
        return [
            'reference' => Booking::generateReference(),
            'service_type' => ServiceType::AirportPickup,
            'status' => BookingStatus::Submitted,
            'vehicle_id' => Vehicle::factory(),
            'estimate_id' => null,
            'schedule_block_id' => null,
            'source' => BookingSource::Web,
            'assigned_staff_id' => null,
            'currency' => 'USD',
            'total_amount_minor_units' => 6500,
            'approximate_lkr_amount_minor_units' => 2000000,
            'request_hash' => hash('sha256', Str::random(32)),
            'confirmation_channel' => null,
            'confirmation_notes' => null,
            'cancellation_reason' => null,
            'decline_reason' => null,
            'confirmed_at' => null,
            'cancelled_at' => null,
            'declined_at' => null,
            'expires_at' => Carbon::now('Asia/Colombo')->addHours(24),
        ];
    }

    public function confirmed(): static
    {
        return $this->state(fn () => [
            'status' => BookingStatus::Confirmed,
            'confirmed_at' => Carbon::now('Asia/Colombo'),
            'confirmation_channel' => ConfirmationChannel::WhatsApp,
            'confirmation_notes' => 'Confirmed via WhatsApp with guest.',
        ]);
    }
}
