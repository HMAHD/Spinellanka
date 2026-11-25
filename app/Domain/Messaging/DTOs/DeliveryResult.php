<?php

declare(strict_types=1);

namespace App\Domain\Messaging\DTOs;

use App\Domain\Messaging\Enums\DeliveryResultStatus;

final readonly class DeliveryResult
{
    /**
     * @param  array<string, mixed>|null  $rawResponse
     */
    public function __construct(
        public DeliveryResultStatus $status,
        public ?string $providerMessageId = null,
        public ?string $errorCode = null,
        public ?string $errorMessage = null,
        public bool $isRetryable = false,
        public ?array $rawResponse = null,
    ) {}

    /**
     * @param  array<string, mixed>|null  $rawResponse
     */
    public static function accepted(string $providerMessageId, ?array $rawResponse = null): self
    {
        return new self(
            status: DeliveryResultStatus::Accepted,
            providerMessageId: $providerMessageId,
            rawResponse: $rawResponse,
        );
    }

    /**
     * @param  array<string, mixed>|null  $rawResponse
     */
    public static function failed(
        string $errorCode,
        string $errorMessage,
        bool $isRetryable = false,
        ?array $rawResponse = null,
    ): self {
        return new self(
            status: DeliveryResultStatus::Failed,
            errorCode: $errorCode,
            errorMessage: $errorMessage,
            isRetryable: $isRetryable,
            rawResponse: $rawResponse,
        );
    }

    /**
     * @param  array<string, mixed>|null  $rawResponse
     */
    public static function unknown(
        string $errorCode,
        string $errorMessage,
        ?array $rawResponse = null,
    ): self {
        return new self(
            status: DeliveryResultStatus::Unknown,
            errorCode: $errorCode,
            errorMessage: $errorMessage,
            isRetryable: false,
            rawResponse: $rawResponse,
        );
    }
}
