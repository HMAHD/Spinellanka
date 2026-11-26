<?php

declare(strict_types=1);

namespace App\Domain\Payment\DTOs;

use App\Domain\Payment\Enums\PaymentMethod;
use App\Domain\Payment\Enums\PaymentType;
use App\Models\Proposal;

readonly class OfflinePaymentData
{
    public function __construct(
        public int $amountMinorUnits,
        public string $currency,
        public PaymentMethod $paymentMethod,
        public PaymentType $paymentType = PaymentType::Full,
        public string $notes = '',
        public ?string $externalReference = null,
        public ?Proposal $proposal = null,
    ) {}
}
