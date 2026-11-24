<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Booking;
use App\Models\BookingPolicyAcceptance;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<BookingPolicyAcceptance>
 */
class BookingPolicyAcceptanceFactory extends Factory
{
    protected $model = BookingPolicyAcceptance::class;

    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory(),
            'policy_type' => 'terms_of_service',
            'version' => '2026-11-23',
            'text_hash' => hash('sha256', Str::random(32)),
            'accepted_at' => Carbon::now('Asia/Colombo'),
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Mozilla/5.0 Testing',
        ];
    }
}

