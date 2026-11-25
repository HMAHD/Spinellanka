<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\GuestAccessToken;
use Carbon\Carbon;
use Illuminate\Http\Response;

class BookingStatusController extends Controller
{
    public function show(string $reference, string $token): Response
    {
        $tokenHash = GuestAccessToken::hashToken($token);

        /** @var GuestAccessToken|null $accessToken */
        $accessToken = GuestAccessToken::where('token_hash', $tokenHash)->first();

        // Generic 404 to prevent enumeration and existence leakage
        if (! $accessToken || ! $accessToken->isValid()) {
            abort(404);
        }

        /** @var Booking|null $booking */
        $booking = Booking::where('id', $accessToken->booking_id)
            ->where('reference', $reference)
            ->with([
                'vehicle.publicApprovedMedia',
                'estimate.items',
                'airportPickupDetail',
                'airportTransferDetail',
                'rentDetail',
                'contacts',
                'policyAcceptances',
            ])
            ->first();

        if (! $booking) {
            abort(404);
        }

        // Update token usage timestamp
        $accessToken->update([
            'last_used_at' => Carbon::now('Asia/Colombo'),
        ]);

        return response()
            ->view('booking.status', [
                'booking' => $booking,
                'token' => $token,
                'leadTraveler' => $booking->leadTraveler,
                'booker' => $booking->booker,
                'detail' => $booking->airportPickupDetail,
                'transferDetail' => $booking->airportTransferDetail,
                'rentDetail' => $booking->rentDetail,
            ])
            ->header('X-Robots-Tag', 'noindex, nofollow')
            ->header('Referrer-Policy', 'no-referrer')
            ->header('Cache-Control', 'no-store, private');
    }
}
