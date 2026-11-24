<?php

declare(strict_types=1);

namespace App\Domain\Pricing\Services;

use App\Domain\Audit\Services\AuditService;
use App\Domain\Pricing\DTOs\EstimateRequestData;
use App\Domain\Pricing\DTOs\EstimateResult;
use App\Models\Estimate;
use App\Models\EstimateItem;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EstimateService
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * Persist an immutable Estimate record and itemized lines from an EstimateResult.
     */
    public function snapshotEstimate(
        EstimateResult $result,
        EstimateRequestData $request,
        int $ttlHours = 24
    ): Estimate {
        return DB::transaction(function () use ($result, $request, $ttlHours) {
            $reference = 'EST-'.strtoupper(Str::random(8));
            $expiresAt = Carbon::now('Asia/Colombo')->addHours($ttlHours);

            $estimate = Estimate::create([
                'reference' => $reference,
                'service_type' => $request->serviceType,
                'vehicle_id' => $request->vehicleId,
                'pricing_rule_id' => $result->pricingRuleId,
                'exchange_rate_basis_id' => $result->exchangeRateBasisId,
                'status' => $result->status,
                'is_custom_quote' => $result->isCustomQuote,
                'custom_quote_reason' => $result->customQuoteReason,
                'subtotal_minor_units' => $result->subtotal?->amountMinorUnits,
                'tax_minor_units' => $result->taxTotal?->amountMinorUnits,
                'deposit_minor_units' => $result->depositTotal?->amountMinorUnits,
                'total_amount_minor_units' => $result->grandTotal?->amountMinorUnits,
                'currency' => $result->grandTotal !== null ? $result->grandTotal->currency : 'USD',
                'approximate_lkr_amount_minor_units' => $result->approximateLkr?->amountMinorUnits,
                'rule_version' => $result->ruleVersion ?? 1,
                'assumptions' => $request->assumptions ?: null,
                'expires_at' => $expiresAt,
            ]);

            foreach ($result->items as $itemData) {
                EstimateItem::create([
                    'estimate_id' => $estimate->id,
                    'rule_component_id' => $itemData->ruleComponentId,
                    'sort_order' => $itemData->sortOrder,
                    'component_type' => $itemData->componentType,
                    'description' => $itemData->description,
                    'unit' => $itemData->unit,
                    'quantity' => $itemData->quantity,
                    'unit_price_minor_units' => $itemData->unitPrice->amountMinorUnits,
                    'total_amount_minor_units' => $itemData->totalAmount->amountMinorUnits,
                    'currency' => $itemData->totalAmount->currency,
                    'is_deposit' => $itemData->isDeposit,
                    'is_tax' => $itemData->isTax,
                ]);
            }

            $this->auditService->record(
                action: 'pricing.estimate.created',
                actor: null,
                summary: "Created estimate snapshot {$reference} ({$request->serviceType->value})",
                metadata: [
                    'estimate_id' => $estimate->id,
                    'reference' => $reference,
                    'service_type' => $request->serviceType->value,
                    'vehicle_id' => $request->vehicleId,
                    'is_custom_quote' => $result->isCustomQuote,
                    'total_usd_minor_units' => $result->grandTotal?->amountMinorUnits,
                    'approximate_lkr_minor_units' => $result->approximateLkr?->amountMinorUnits,
                ],
                auditable: $estimate,
                actorType: 'guest'
            );

            return $estimate->load('items');
        });
    }

    /**
     * Retrieve an estimate by public reference string.
     */
    public function findByReference(string $reference): ?Estimate
    {
        return Estimate::with(['items', 'pricingRule', 'exchangeRateBasis', 'vehicle'])
            ->where('reference', $reference)
            ->first();
    }
}
