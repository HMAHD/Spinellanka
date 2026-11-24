<?php

declare(strict_types=1);

use App\Domain\Pricing\Services\ExchangeRateService;
use App\Domain\Pricing\ValueObjects\Money;
use App\Models\AuditEvent;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->service = app(ExchangeRateService::class);
});

test('recordRate stores new exchange rate basis and logs audit event', function (): void {
    $date = Carbon::parse('2026-11-24 10:00:00', 'Asia/Colombo');
    $basis = $this->service->recordRate(310.2500, 'CBSL indicative rate', $date);

    expect($basis->id)->not->toBeNull()
        ->and((float) $basis->rate)->toBe(310.25)
        ->and($basis->source_note)->toBe('CBSL indicative rate');

    $audit = AuditEvent::where('action', 'pricing.exchange_rate.created')->first();
    expect($audit)->not->toBeNull()
        ->and($audit->metadata['rate'])->toBe('310.2500');
});

test('approximateLkr converts USD money accurately based on latest effective rate', function (): void {
    // Record rate from 2 days ago: 300 LKR
    $this->service->recordRate(300.0000, 'Older rate', Carbon::now('Asia/Colombo')->subDays(2));

    // Record rate from today: 320 LKR
    $this->service->recordRate(320.0000, 'Newer rate', Carbon::now('Asia/Colombo'));

    $usd = Money::of(10000, 'USD'); // $100.00
    $lkr = $this->service->approximateLkr($usd);

    // 10000 * 320 = 3,200,000 cents
    expect($lkr)->not->toBeNull()
        ->and($lkr->amountMinorUnits)->toBe(3200000)
        ->and($lkr->currency)->toBe('LKR')
        ->and($lkr->format())->toBe('LKR 32,000.00');
});
