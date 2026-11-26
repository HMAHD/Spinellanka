<?php

declare(strict_types=1);

namespace App\Domain\Payment\DTOs;

readonly class RefundData
{
    public function __construct(
        public int $amountMinorUnits,
        public string $reason,
        public ?string $staffPasswordConfirmation = null,
    ) {}
}
