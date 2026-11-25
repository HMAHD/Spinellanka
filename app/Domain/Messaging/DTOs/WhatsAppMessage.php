<?php

declare(strict_types=1);

namespace App\Domain\Messaging\DTOs;

final readonly class WhatsAppMessage
{
    /**
     * @param  array<string, mixed>  $templateParams
     */
    public function __construct(
        public string $recipientPhone,
        public string $templateName,
        public array $templateParams,
        public ?string $idempotencyKey = null,
        public string $language = 'en_US',
    ) {}
}
