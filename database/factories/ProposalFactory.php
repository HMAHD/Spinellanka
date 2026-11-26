<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Booking\Enums\PaymentOption;
use App\Domain\Booking\Enums\ProposalPaymentChoice;
use App\Domain\Booking\Enums\ProposalStatus;
use App\Domain\Booking\ServiceType;
use App\Models\Booking;
use App\Models\Proposal;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Proposal>
 */
class ProposalFactory extends Factory
{
    protected $model = Proposal::class;

    public function definition(): array
    {
        $startsAt = Carbon::now('Asia/Colombo')->addDays(3)->setTime(10, 0);
        $endsAt = (clone $startsAt)->addHours(4);

        return [
            'booking_id' => Booking::factory(),
            'version' => 1,
            'reference' => 'PROP-BKG-'.fake()->unique()->numerify('########').'-V1',
            'status' => ProposalStatus::Draft,
            'vehicle_id' => Vehicle::factory(),
            'service_type' => ServiceType::AirportPickup,
            'schedule_block_id' => null,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'currency' => 'EUR',
            'total_amount_minor_units' => 6500,
            'approximate_lkr_amount_minor_units' => 2100000,
            'deposit_required' => false,
            'deposit_amount_minor_units' => 0,
            'payment_choice' => ProposalPaymentChoice::FullOrPayLater,
            'selected_payment_choice' => null,
            'expires_at' => Carbon::now('Asia/Colombo')->addHours(48),
            'staff_notes' => 'Direct transfer with English-speaking chauffeur.',
            'internal_notes' => 'Margin healthy at standard rate.',
            'sent_by_user_id' => null,
            'sent_at' => null,
            'responded_at' => null,
            'response_action' => null,
            'response_notes' => null,
            'snapshot_data' => [
                'service_type' => 'airport_pickup',
                'vehicle_name' => 'Toyota Prius Hybrid',
                'pickup_location' => 'Bandaranaike International Airport (CMB)',
                'destination' => 'Colombo City Center',
                'passenger_count' => 2,
                'luggage_count' => 2,
            ],
        ];
    }

    public function sent(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ProposalStatus::Sent,
            'sent_at' => Carbon::now('Asia/Colombo'),
        ]);
    }

    public function accepted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ProposalStatus::Accepted,
            'sent_at' => Carbon::now('Asia/Colombo')->subHours(2),
            'responded_at' => Carbon::now('Asia/Colombo'),
            'selected_payment_choice' => PaymentOption::PayLater,
            'response_action' => 'accepted',
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ProposalStatus::Expired,
            'expires_at' => Carbon::now('Asia/Colombo')->subHour(),
        ]);
    }
}
