<?php

declare(strict_types=1);

namespace App\Domain\Booking\DTOs;

use App\Domain\Booking\Enums\PaymentOption;
use App\Domain\Booking\Enums\ProposalPaymentChoice;
use App\Domain\Booking\Enums\ProposalStatus;
use App\Domain\Booking\ServiceType;
use App\Models\Proposal;
use Carbon\Carbon;
use Illuminate\Support\Str;

final readonly class GuestProposalViewDTO
{
    /**
     * @param  array<int, array{
     *     description: string,
     *     component_type: string,
     *     quantity: string,
     *     unit: string,
     *     unit_price_minor_units: int,
     *     total_amount_minor_units: int,
     *     is_deposit: bool,
     *     is_tax: bool,
     *     is_discount: bool
     * }>  $items
     * @param  array<string, mixed>  $snapshotData
     * @param  array<int, PaymentOption>  $allowedPaymentOptions
     */
    public function __construct(
        public int $id,
        public int $version,
        public string $reference,
        public ProposalStatus $status,
        public string $vehicleName,
        public ServiceType $serviceType,
        public Carbon $startsAt,
        public Carbon $endsAt,
        public string $currency,
        public int $totalAmountMinorUnits,
        public int $approximateLkrAmountMinorUnits,
        public bool $depositRequired,
        public int $depositAmountMinorUnits,
        public ProposalPaymentChoice $paymentChoice,
        public array $allowedPaymentOptions,
        public ?PaymentOption $selectedPaymentChoice,
        public Carbon $expiresAt,
        public bool $isExpired,
        public bool $isActionable,
        public ?string $staffNotes,
        public ?Carbon $sentAt,
        public ?Carbon $respondedAt,
        public array $items,
        public array $snapshotData,
    ) {}

    public function __get(string $name): mixed
    {
        $camel = Str::camel($name);
        if (property_exists($this, $camel)) {
            return $this->{$camel};
        }

        return null;
    }

    public static function fromProposal(Proposal $proposal): self
    {
        $proposal->loadMissing(['items', 'vehicle']);

        $items = $proposal->items->map(fn ($item) => [
            'description' => $item->description,
            'component_type' => $item->component_type->value,
            'quantity' => (string) $item->quantity,
            'unit' => $item->unit->value,
            'unit_price_minor_units' => $item->unit_price_minor_units,
            'total_amount_minor_units' => $item->total_amount_minor_units,
            'is_deposit' => $item->is_deposit,
            'is_tax' => $item->is_tax,
            'is_discount' => $item->is_discount,
        ])->all();

        return new self(
            id: $proposal->id,
            version: $proposal->version,
            reference: $proposal->reference,
            status: $proposal->status,
            vehicleName: $proposal->vehicle->public_name ?: "{$proposal->vehicle->make} {$proposal->vehicle->model}",
            serviceType: $proposal->service_type,
            startsAt: $proposal->starts_at,
            endsAt: $proposal->ends_at,
            currency: $proposal->currency,
            totalAmountMinorUnits: $proposal->total_amount_minor_units,
            approximateLkrAmountMinorUnits: (int) $proposal->approximate_lkr_amount_minor_units,
            depositRequired: $proposal->deposit_required,
            depositAmountMinorUnits: $proposal->deposit_amount_minor_units,
            paymentChoice: $proposal->payment_choice,
            allowedPaymentOptions: $proposal->payment_choice->allowedOptions(),
            selectedPaymentChoice: $proposal->selected_payment_choice,
            expiresAt: $proposal->expires_at,
            isExpired: $proposal->isExpired(),
            isActionable: $proposal->isActionable(),
            staffNotes: $proposal->staff_notes,
            sentAt: $proposal->sent_at,
            respondedAt: $proposal->responded_at,
            items: $items,
            snapshotData: $proposal->snapshot_data ?? [],
        );
    }
}
