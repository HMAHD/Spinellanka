<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Availability\Enums\HoldStatus;
use App\Domain\Availability\Enums\ScheduleBlockKind;
use App\Domain\Booking\ServiceType;
use App\Models\BookingHold;
use App\Models\Vehicle;
use App\Models\VehicleScheduleBlock;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<BookingHold>
 */
class BookingHoldFactory extends Factory
{
    protected $model = BookingHold::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsAt = Carbon::now('Asia/Colombo')->addDay();
        $endsAt = (clone $startsAt)->addHours(4);
        $token = Str::random(32);

        return [
            'hold_token_hash' => BookingHold::hashToken($token),
            'vehicle_id' => Vehicle::factory(),
            'schedule_block_id' => VehicleScheduleBlock::factory()->state([
                'kind' => ScheduleBlockKind::CheckoutHold,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'effective_start' => $startsAt,
                'effective_end' => $endsAt,
            ]),
            'group_id' => null,
            'session_id' => Str::random(40),
            'service_type' => fake()->randomElement(ServiceType::cases()),
            'hold_duration_minutes' => 15,
            'status' => HoldStatus::Active,
            'expires_at' => Carbon::now('Asia/Colombo')->addMinutes(15),
            'released_at' => null,
            'release_reason' => null,
        ];
    }
}
