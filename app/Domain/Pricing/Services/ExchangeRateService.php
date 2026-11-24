<?php

declare(strict_types=1);

namespace App\Domain\Pricing\Services;

use App\Domain\Audit\Services\AuditService;
use App\Domain\Pricing\ValueObjects\Money;
use App\Models\ExchangeRateBasis;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ExchangeRateService
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * Record a new manual USD/LKR approximation rate basis with audit logging.
     */
    public function recordRate(
        float|string $rate,
        string $sourceNote,
        ?Carbon $effectiveAt = null,
        ?User $actor = null
    ): ExchangeRateBasis {
        $effectiveAt = ($effectiveAt ?? Carbon::now('Asia/Colombo'))->setTimezone('Asia/Colombo');
        $rateDecimal = number_format((float) $rate, 4, '.', '');

        return DB::transaction(function () use ($rateDecimal, $sourceNote, $effectiveAt, $actor) {
            $basis = ExchangeRateBasis::create([
                'rate' => $rateDecimal,
                'source_note' => $sourceNote,
                'effective_at' => $effectiveAt,
                'created_by' => $actor !== null ? $actor->id : auth()->id(),
            ]);

            $this->auditService->record(
                action: 'pricing.exchange_rate.created',
                actor: $actor,
                summary: "Recorded manual USD/LKR approximation rate {$rateDecimal} ({$sourceNote})",
                metadata: [
                    'exchange_rate_basis_id' => $basis->id,
                    'rate' => $rateDecimal,
                    'source_note' => $sourceNote,
                    'effective_at' => $effectiveAt->toIso8601String(),
                ],
                auditable: $basis
            );

            return $basis;
        });
    }

    /**
     * Retrieve the latest effective rate basis as of a specific date in Asia/Colombo.
     */
    public function getLatestRate(?Carbon $asOf = null): ?ExchangeRateBasis
    {
        $asOf = ($asOf ?? Carbon::now('Asia/Colombo'))->setTimezone('Asia/Colombo');

        return ExchangeRateBasis::query()
            ->where('effective_at', '<=', $asOf)
            ->orderByDesc('effective_at')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Convert USD money to approximate LKR minor units (cents equivalent) using latest basis,
     * returning both converted Money and matched ExchangeRateBasis atomically.
     *
     * @return array{approximate_lkr: ?Money, basis: ?ExchangeRateBasis}
     */
    public function approximateLkrWithBasis(Money $usdAmount, ?Carbon $asOf = null): array
    {
        $basis = $this->getLatestRate($asOf);
        if ($basis === null) {
            return [
                'approximate_lkr' => null,
                'basis' => null,
            ];
        }

        // Rate is stored with 4 decimal places (e.g. 308.5000)
        // Scaled integer arithmetic avoids IEEE-754 precision issues
        $rateScaled = (int) round(((float) $basis->rate) * 10000);
        $lkrMinorUnits = (int) round(($usdAmount->amountMinorUnits * $rateScaled) / 10000);

        return [
            'approximate_lkr' => Money::of($lkrMinorUnits, 'LKR'),
            'basis' => $basis,
        ];
    }

    /**
     * Convert USD money to approximate LKR minor units (cents equivalent) using latest basis.
     */
    public function approximateLkr(Money $usdAmount, ?Carbon $asOf = null): ?Money
    {
        return $this->approximateLkrWithBasis($usdAmount, $asOf)['approximate_lkr'];
    }
}
