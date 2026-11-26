<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Booking\DTOs\GuestProposalViewDTO;
use App\Domain\Booking\Enums\PaymentOption;
use App\Domain\Booking\Exceptions\InvalidPaymentChoiceException;
use App\Domain\Booking\Exceptions\ProposalNotActionableException;
use App\Domain\Booking\Services\GuestControlService;
use App\Domain\Booking\Services\ProposalService;
use App\Models\Booking;
use App\Models\GuestAccessToken;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class BookingStatusController extends Controller
{
    public function __construct(
        protected GuestControlService $guestControlService,
        protected ProposalService $proposalService,
    ) {}

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
                'currentProposal.items',
                'currentProposal.vehicle',
                'proposals.items',
                'airportPickupDetail',
                'airportTransferDetail',
                'rideDetail.stops',
                'rentDetail',
                'contacts',
                'policyAcceptances',
                'paymentTransactions',
                'financialDocuments',
                'assignedDriver',
                'currentDriverAssignment',
            ])
            ->first();

        if (! $booking) {
            abort(404);
        }

        // Update token usage timestamp
        $accessToken->update([
            'last_used_at' => Carbon::now('Asia/Colombo'),
        ]);

        $proposalDTO = null;
        if ($booking->currentProposal) {
            $proposalDTO = GuestProposalViewDTO::fromProposal($booking->currentProposal);
        }

        // Compute comparison between original request and current proposal
        $comparison = $this->proposalService->computeComparison($booking);

        return response()
            ->view('booking.status', [
                'booking' => $booking,
                'token' => $token,
                'proposal' => $proposalDTO,
                'comparison' => $comparison,
                'leadTraveler' => $booking->leadTraveler,
                'booker' => $booking->booker,
                'detail' => $booking->airportPickupDetail,
                'transferDetail' => $booking->airportTransferDetail,
                'rideDetail' => $booking->rideDetail,
                'rentDetail' => $booking->rentDetail,
                'assignedDriver' => $booking->assignedDriver,
                'currentDriverAssignment' => $booking->currentDriverAssignment,
            ])
            ->header('X-Robots-Tag', 'noindex, nofollow')
            ->header('Referrer-Policy', 'no-referrer')
            ->header('Cache-Control', 'no-store, private');
    }

    public function accept(Request $request, string $reference, string $token): RedirectResponse
    {
        $booking = $this->resolveAuthorizedBooking($reference, $token);

        if (! $booking->currentProposal) {
            return redirect()->route('booking.status', ['reference' => $reference, 'token' => $token])
                ->with('error', 'No active proposal found for this booking.');
        }

        $validated = $request->validate([
            'payment_option' => ['required', 'string', 'in:full,deposit,pay_later'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $paymentOption = PaymentOption::from($validated['payment_option']);
            $result = $this->guestControlService->acceptProposal(
                booking: $booking,
                proposal: $booking->currentProposal,
                choice: $paymentOption,
                notes: $validated['notes'] ?? null
            );

            return redirect()->route('booking.status', ['reference' => $reference, 'token' => $token])
                ->with('status_success', $result->message);
        } catch (ProposalNotActionableException|InvalidPaymentChoiceException $e) {
            return redirect()->route('booking.status', ['reference' => $reference, 'token' => $token])
                ->with('error', $e->getMessage());
        }
    }

    public function decline(Request $request, string $reference, string $token): RedirectResponse
    {
        $booking = $this->resolveAuthorizedBooking($reference, $token);

        if (! $booking->currentProposal) {
            return redirect()->route('booking.status', ['reference' => $reference, 'token' => $token])
                ->with('error', 'No active proposal found for this booking.');
        }

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $this->guestControlService->declineProposal(
                booking: $booking,
                proposal: $booking->currentProposal,
                reason: $validated['reason'] ?? null
            );

            return redirect()->route('booking.status', ['reference' => $reference, 'token' => $token])
                ->with('status_success', 'The proposal has been declined.');
        } catch (ProposalNotActionableException $e) {
            return redirect()->route('booking.status', ['reference' => $reference, 'token' => $token])
                ->with('error', $e->getMessage());
        }
    }

    public function change(Request $request, string $reference, string $token): RedirectResponse
    {
        $booking = $this->resolveAuthorizedBooking($reference, $token);

        $validated = $request->validate([
            'notes' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        try {
            $this->guestControlService->requestChange($booking, $validated['notes']);

            return redirect()->route('booking.status', ['reference' => $reference, 'token' => $token])
                ->with('status_success', 'Your change request has been submitted. Our operations desk will review and dispatch an amended proposal shortly.');
        } catch (\Throwable $e) {
            return redirect()->route('booking.status', ['reference' => $reference, 'token' => $token])
                ->with('error', $e->getMessage());
        }
    }

    public function cancel(Request $request, string $reference, string $token): RedirectResponse
    {
        $booking = $this->resolveAuthorizedBooking($reference, $token);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        try {
            $this->guestControlService->cancelBooking($booking, $validated['reason']);

            return redirect()->route('booking.status', ['reference' => $reference, 'token' => $token])
                ->with('status_success', 'Your booking reservation has been cancelled.');
        } catch (\Exception $e) {
            return redirect()->route('booking.status', ['reference' => $reference, 'token' => $token])
                ->with('error', $e->getMessage());
        }
    }

    public function extension(Request $request, string $reference, string $token): RedirectResponse
    {
        $booking = $this->resolveAuthorizedBooking($reference, $token);

        $validated = $request->validate([
            'extra_days' => ['required', 'integer', 'min:1', 'max:30'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $this->guestControlService->requestRentExtension(
                booking: $booking,
                extraDays: (int) $validated['extra_days'],
                notes: $validated['notes'] ?? null
            );

            return redirect()->route('booking.status', ['reference' => $reference, 'token' => $token])
                ->with('status_success', 'Rental extension request submitted. Our team will verify vehicle schedule availability and prepare an extension quote.');
        } catch (\Exception $e) {
            return redirect()->route('booking.status', ['reference' => $reference, 'token' => $token])
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Resolve booking matching reference and token or abort 404.
     */
    protected function resolveAuthorizedBooking(string $reference, string $token): Booking
    {
        $tokenHash = GuestAccessToken::hashToken($token);

        /** @var GuestAccessToken|null $accessToken */
        $accessToken = GuestAccessToken::where('token_hash', $tokenHash)->first();

        if (! $accessToken || ! $accessToken->isValid()) {
            abort(404);
        }

        /** @var Booking|null $booking */
        $booking = Booking::where('id', $accessToken->booking_id)
            ->where('reference', $reference)
            ->with(['currentProposal.vehicle', 'currentProposal.items', 'rentDetail'])
            ->first();

        if (! $booking) {
            abort(404);
        }

        return $booking;
    }
}
