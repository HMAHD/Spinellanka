<?php

declare(strict_types=1);

namespace App\Domain\Payment\DTOs;

readonly class CheckoutSession
{
    /**
     * @param  array<string, mixed>  $formFields
     */
    public function __construct(
        public string $gatewayProvider,
        public string $transactionReference,
        public string $checkoutUrl,
        public string $httpMethod,
        public array $formFields = [],
    ) {}
}
