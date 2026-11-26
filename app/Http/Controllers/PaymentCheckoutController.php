<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Booking\Enums\BookingStatus;
use App\Domain\Booking\Enums\PaymentOption;
use App\Domain\Payment\Enums\PaymentStatus;
use App\Domain\Payment\Enums\PaymentType;
use App\Domain\Payment\Services\PaymentService;
use App\Models\Booking;
use App\Models\GuestAccessToken;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PaymentCheckoutController extends Controller
{
    public function __construct(
        protected PaymentService $paymentService,
    ) {}

    /**
     * Initiate online payment checkout for an accepted proposal.
     */
    public function checkout(Request $request, string $reference, string $token): Response|RedirectResponse
    {
        $booking = $this->resolveAuthorizedBooking($reference, $token);

        $proposal = $booking->currentProposal;
        if (! $proposal) {
            return redirect()->route('booking.status', ['reference' => $reference, 'token' => $token])
                ->with('error', 'No active proposal found for payment.');
        }

        try {
            $paymentOption = $proposal->deposit_required ? PaymentOption::Deposit : PaymentOption::Full;
            $returnUrl = route('booking.payment.return', ['reference' => $reference, 'token' => $token]);
            $cancelUrl = route('booking.payment.cancel', ['reference' => $reference, 'token' => $token]);

            $session = $this->paymentService->initiateCheckout(
                booking: $booking,
                proposal: $proposal,
                paymentOption: $paymentOption,
                returnUrl: $returnUrl,
                cancelUrl: $cancelUrl
            );

            if (strtoupper($session->httpMethod) === 'GET' || empty($session->formFields)) {
                return redirect()->away($session->checkoutUrl);
            }

            return response()->view('guest.payment.checkout-form', [
                'session' => $session,
                'booking' => $booking,
                'proposal' => $proposal,
                'token' => $token,
            ])
                ->header('X-Robots-Tag', 'noindex, nofollow')
                ->header('Referrer-Policy', 'no-referrer')
                ->header('Cache-Control', 'no-store, private');
        } catch (\Throwable $e) {
            return redirect()->route('booking.status', ['reference' => $reference, 'token' => $token])
                ->with('error', 'Unable to initiate payment: '.$e->getMessage());
        }
    }

    /**
     * Browser return landing endpoint from payment gateway.
     *
     * IMPORTANT: Decoupled display only! Never confirms booking or settles payment
     * from browser return query parameters.
     */
    public function return(Request $request, string $reference, string $token): RedirectResponse
    {
        $booking = $this->resolveAuthorizedBooking($reference, $token);

        // Inspect latest transaction status recorded by webhook
        $latestTransaction = $booking->paymentTransactions()
            ->where('amount_minor_units', '>', 0)
            ->where('payment_type', '!=', PaymentType::Refund->value)
            ->latest('id')
            ->first();

        if ($latestTransaction && $latestTransaction->status === PaymentStatus::Completed) {
            if ($booking->status === BookingStatus::Confirmed) {
                return redirect()->route('booking.status', ['reference' => $reference, 'token' => $token])
                    ->with('status_success', 'Your online payment was successfully completed and your booking reservation is confirmed. Official receipt issued.');
            }

            return redirect()->route('booking.status', ['reference' => $reference, 'token' => $token])
                ->with('status_warning', 'Payment captured, but proposal hold had expired prior to settlement. A staff operator will verify and assign vehicle schedule.');
        }

        if ($latestTransaction && $latestTransaction->status === PaymentStatus::Failed) {
            return redirect()->route('booking.status', ['reference' => $reference, 'token' => $token])
                ->with('error', 'Your payment attempt could not be processed by the payment provider. Please try again or contact our desk.');
        }

        // Return when webhook has not yet posted or is in-flight
        return redirect()->route('booking.status', ['reference' => $reference, 'token' => $token])
            ->with('payment_pending', 'Your payment is currently being confirmed by the payment gateway. This status page will update automatically once settlement is recorded.');
    }

    /**
     * Browser cancel landing endpoint from payment gateway.
     */
    public function cancel(Request $request, string $reference, string $token): RedirectResponse
    {
        $this->resolveAuthorizedBooking($reference, $token);

        return redirect()->route('booking.status', ['reference' => $reference, 'token' => $token])
            ->with('error', 'Payment checkout was cancelled. You may retry when you are ready.');
    }

    /**
     * Resolve booking by reference and valid guest access token.
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
            ->with(['currentProposal.items', 'paymentTransactions'])
            ->first();

        if (! $booking) {
            abort(404);
        }

        return $booking;
    }
}
