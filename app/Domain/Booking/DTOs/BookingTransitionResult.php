<?php

declare(strict_types=1);

namespace App\Domain\Booking\DTOs;

use App\Domain\Booking\Enums\BookingStatus;
use App\Domain\Booking\Enums\PaymentOption;
use App\Models\Booking;
use App\Models\Proposal;

final readonly class BookingTransitionResult
{
    public function __construct(
        public Booking $booking,
        public Proposal $proposal,
        public bool $requiresPayment,
        public PaymentOption $paymentOption,
        public int $amountDueMinorUnits,
        public string $currency,
        public BookingStatus $targetStatus,
        public string $message,
    ) {}
}
