<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Booking;
use App\Models\GuestAccessToken;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<GuestAccessToken>
 */
class GuestAccessTokenFactory extends Factory
{
    protected $model = GuestAccessToken::class;

    public function definition(): array
    {
        $token = Str::random(64);

        return [
            'booking_id' => Booking::factory(),
            'token_hash' => GuestAccessToken::hashToken($token),
            'purpose' => 'guest_access',
            'expires_at' => Carbon::now('Asia/Colombo')->addDays(30),
            'revoked_at' => null,
            'last_used_at' => null,
        ];
    }
}
