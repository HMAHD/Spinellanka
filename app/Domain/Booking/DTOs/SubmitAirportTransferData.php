<?php

declare(strict_types=1);

namespace App\Domain\Booking\DTOs;

final class SubmitAirportTransferData
{
    public function __construct(
        public readonly AirportTransferCriteria $criteria,
        public readonly int $vehicleId,
        public readonly string $holdPlainToken,
        public readonly string $estimateReference,
        public readonly string $bookerFirstName,
        public readonly string $bookerLastName,
        public readonly string $bookerPhone,
        public readonly bool $bookerIsWhatsapp = true,
        public readonly ?string $bookerEmail = null,
        public readonly bool $bookerMarketingConsent = false,
        public readonly bool $leadTravelerSameAsBooker = true,
        public readonly ?string $leadTravelerFirstName = null,
        public readonly ?string $leadTravelerLastName = null,
        public readonly ?string $leadTravelerPhone = null,
        public readonly bool $leadTravelerIsWhatsapp = true,
        public readonly ?string $leadTravelerEmail = null,
        public readonly bool $termsAccepted = false,
        public readonly bool $privacyAccepted = false,
        public readonly ?string $ipAddress = null,
        public readonly ?string $userAgent = null,
    ) {}
}
