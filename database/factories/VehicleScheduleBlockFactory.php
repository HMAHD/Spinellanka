<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Availability\Enums\ScheduleBlockKind;
use App\Domain\Availability\Enums\ScheduleBlockStatus;
use App\Models\Vehicle;
use App\Models\VehicleScheduleBlock;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VehicleScheduleBlock>
 */
class VehicleScheduleBlockFactory extends Factory
{
    protected $model = VehicleScheduleBlock::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsAt = Carbon::now('Asia/Colombo')->addDays(fake()->numberBetween(1, 10))->startOfHour();
        $endsAt = (clone $startsAt)->addHours(fake()->numberBetween(2, 8));

        return [
            'vehicle_id' => Vehicle::factory(),
            'kind' => fake()->randomElement(ScheduleBlockKind::cases()),
            'source_type' => null,
            'source_id' => null,
            'group_id' => null,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'buffer_minutes_before' => 0,
            'buffer_minutes_after' => 0,
            'effective_start' => $startsAt,
            'effective_end' => $endsAt,
            'blocks_availability' => true,
            'status' => ScheduleBlockStatus::Active,
            'is_chauffeured_only' => false,
            'driver_id' => null,
            'is_override' => false,
            'override_reason' => null,
            'override_authorized_by' => null,
            'created_by' => null,
            'expires_at' => null,
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
