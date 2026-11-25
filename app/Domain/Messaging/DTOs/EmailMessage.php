<?php

declare(strict_types=1);

namespace App\Domain\Messaging\DTOs;

final readonly class EmailMessage
{
    /**
     * @param  array<string, mixed>  $templateParams
     */
    public function __construct(
        public string $recipientEmail,
        public string $subject,
        public string $templateName,
        public array $templateParams,
        public ?string $idempotencyKey = null,
    ) {}
}
