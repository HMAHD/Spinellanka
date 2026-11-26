<?php

declare(strict_types=1);

namespace App\Domain\Payment\DTOs;

readonly class PaymentVerificationResult
{
    /**
     * @param  array<string, mixed>  $rawPayload
     */
    public function __construct(
        public bool $isValid,
        public string $provider,
        public ?string $providerEventId,
        public ?string $providerTransactionId,
        public ?string $orderReference,
        public int $amountMinorUnits,
        public string $currency,
        public string $statusCode,
        public array $rawPayload = [],
        public ?string $errorMessage = null,
    ) {}

    public function isSuccessful(): bool
    {
        return $this->isValid && $this->statusCode === '2';
    }
}
