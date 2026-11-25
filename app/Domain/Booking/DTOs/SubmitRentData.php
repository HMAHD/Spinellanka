<?php

declare(strict_types=1);

namespace App\Domain\Booking\DTOs;

final class SubmitRentData
{
    public function __construct(
        public readonly RentCriteria $criteria,
        public readonly int $vehicleId,
        public readonly string $holdPlainToken,
        public readonly string $estimateReference,
        public readonly string $bookerFirstName,
        public readonly string $bookerLastName,
        public readonly string $bookerPhone,
        public readonly bool $bookerIsWhatsapp = true,
        public readonly ?string $bookerEmail = null,
        public readonly bool $bookerMarketingConsent = false,
        public readonly bool $leadDriverSameAsBooker = true,
        public readonly ?string $leadDriverFirstName = null,
        public readonly ?string $leadDriverLastName = null,
        public readonly ?string $leadDriverPhone = null,
        public readonly bool $leadDriverIsWhatsapp = true,
        public readonly ?string $leadDriverEmail = null,
        public readonly bool $termsAccepted = false,
        public readonly bool $privacyAccepted = false,
        public readonly bool $originalDocumentsAcknowledged = false,
        public readonly ?string $ipAddress = null,
        public readonly ?string $userAgent = null,
    ) {}
}
