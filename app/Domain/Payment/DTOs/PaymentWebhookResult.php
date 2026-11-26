<?php

declare(strict_types=1);

namespace App\Domain\Payment\DTOs;

readonly class PaymentWebhookResult
{
    public function __construct(
        public bool $success,
        public string $status,
        public string $message,
        public ?int $transactionId = null,
    ) {}

    public static function success(int $transactionId, string $message = 'Payment settled and booking advanced successfully.'): self
    {
        return new self(success: true, status: 'success', message: $message, transactionId: $transactionId);
    }

    public static function alreadyProcessed(string $message = 'Event already processed.'): self
    {
        return new self(success: true, status: 'already_processed', message: $message);
    }

    public static function failed(string $message = 'Payment was declined or cancelled by gateway.'): self
    {
        return new self(success: false, status: 'failed', message: $message);
    }

    public static function discrepancy(string $message = 'Settlement amount discrepancy detected.'): self
    {
        return new self(success: false, status: 'discrepancy', message: $message);
    }

    public static function settledWithoutConfirmation(string $message = 'Payment captured but proposal is expired/cancelled. Flagged for staff review.'): self
    {
        return new self(success: true, status: 'settled_unassigned', message: $message);
    }
}
