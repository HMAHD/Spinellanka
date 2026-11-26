<?php

declare(strict_types=1);

namespace App\Domain\Booking\DTOs;

use App\Domain\Booking\Enums\ProposalPaymentChoice;
use App\Domain\Booking\ServiceType;
use App\Domain\Pricing\DTOs\EstimateItemData;
use Carbon\Carbon;

final readonly class CreateProposalData
{
    /**
     * @param  array<int, EstimateItemData>  $items
     * @param  array<string, mixed>  $snapshotData
     */
    public function __construct(
        public int $vehicleId,
        public ServiceType $serviceType,
        public Carbon $startsAt,
        public Carbon $endsAt,
        public string $currency,
        public int $totalAmountMinorUnits,
        public int $approximateLkrAmountMinorUnits,
        public bool $depositRequired,
        public int $depositAmountMinorUnits,
        public ProposalPaymentChoice $paymentChoice,
        public Carbon $expiresAt,
        public array $items,
        public array $snapshotData,
        public ?string $staffNotes = null,
        public ?string $internalNotes = null,
    ) {}
}
