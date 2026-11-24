<?php

declare(strict_types=1);

namespace App\Domain\Booking\DTOs;

final class SubmitRideData
{
    public function __construct(
        public readonly RideCriteria $criteria,
        public readonly int $vehicleId,
        public readonly ?string $estimateReference,
        public readonly string $holdPlainToken,
        public readonly string $bookerFirstName,
        public readonly string $bookerLastName,
        public readonly string $bookerPhone,
        public readonly bool $bookerIsWhatsapp = true,
        public readonly ?string $bookerEmail = null,
        public readonly ?string $leadTravelerFirstName = null,
        public readonly ?string $leadTravelerLastName = null,
        public readonly ?string $leadTravelerPhone = null,
        public readonly ?bool $leadTravelerIsWhatsapp = null,
        public readonly ?string $leadTravelerEmail = null,
        public readonly bool $termsAccepted = true,
        public readonly bool $privacyAccepted = true,
        public readonly bool $marketingConsent = false,
        public readonly ?string $specialInstructions = null,
    ) {}
}
