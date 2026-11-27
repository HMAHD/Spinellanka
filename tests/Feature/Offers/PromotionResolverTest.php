<?php

declare(strict_types=1);

use App\Domain\Booking\Enums\DealStatus;
use App\Domain\Booking\Enums\PromotionDiscountType;
use App\Domain\Booking\Enums\PromotionScope;
use App\Domain\Booking\Enums\PromotionStatus;
use App\Domain\Booking\ServiceType;
use App\Domain\Pricing\Services\PromotionResolver;
use App\Models\Deal;
use App\Models\Promotion;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->resolver = new PromotionResolver;
});

test('deal-scoped promotion wins over service-scoped and global-scoped promotions', function (): void {
    $deal = Deal::factory()->create([
        'status' => DealStatus::Published,
        'service_type' => ServiceType::AirportPickup,
    ]);

    // Create Global promotion
    Promotion::factory()->create([
        'name' => 'Global Promo',
        'scope' => PromotionScope::Global,
        'status' => PromotionStatus::Active,
        'priority' => 10,
    ]);

    // Create Service promotion
    Promotion::factory()->create([
        'name' => 'Service Promo',
        'scope' => PromotionScope::Service,
        'service_type' => ServiceType::AirportPickup,
        'status' => PromotionStatus::Active,
        'priority' => 10,
    ]);

    // Create Deal promotion
    $dealPromo = Promotion::factory()->create([
        'name' => 'Deal Promo',
        'scope' => PromotionScope::Deal,
        'deal_id' => $deal->id,
        'status' => PromotionStatus::Active,
        'priority' => 5, // Even with lower priority than global/service, deal scope wins!
    ]);

    $result = $this->resolver->resolve(
        serviceType: ServiceType::AirportPickup,
        deal: $deal,
    );

    expect($result)->not->toBeNull()
        ->and($result->name)->toBe('Deal Promo')
        ->and($result->promotion->id)->toBe($dealPromo->id)
        ->and($result->scope)->toBe(PromotionScope::Deal);
});

test('service-scoped promotion wins over global-scoped promotion when no deal is provided', function (): void {
    Promotion::factory()->create([
        'name' => 'Global Promo',
        'scope' => PromotionScope::Global,
        'status' => PromotionStatus::Active,
        'priority' => 100,
    ]);

    $servicePromo = Promotion::factory()->create([
        'name' => 'Service Promo',
        'scope' => PromotionScope::Service,
        'service_type' => ServiceType::Ride,
        'status' => PromotionStatus::Active,
        'priority' => 10,
    ]);

    $result = $this->resolver->resolve(
        serviceType: ServiceType::Ride,
        deal: null,
    );

    expect($result)->not->toBeNull()
        ->and($result->name)->toBe('Service Promo')
        ->and($result->promotion->id)->toBe($servicePromo->id);
});

test('global-scoped promotion resolves when no deal or service promotion exists', function (): void {
    $globalPromo = Promotion::factory()->create([
        'name' => 'Global Discount',
        'scope' => PromotionScope::Global,
        'status' => PromotionStatus::Active,
        'priority' => 10,
    ]);

    $result = $this->resolver->resolve(
        serviceType: ServiceType::Rent,
        deal: null,
    );

    expect($result)->not->toBeNull()
        ->and($result->name)->toBe('Global Discount')
        ->and($result->promotion->id)->toBe($globalPromo->id);
});

test('ambiguity fail-safe returns null and logs warning when equal priority promotions collide in winning scope', function (): void {
    Log::shouldReceive('warning')
        ->once()
        ->withArgs(function (string $message): bool {
            return str_contains($message, 'Ambiguous promotion resolution in Global')
                && str_contains($message, 'Overlapping promotions with priority 20');
        });

    Promotion::factory()->create([
        'name' => 'Global Promo A',
        'scope' => PromotionScope::Global,
        'status' => PromotionStatus::Active,
        'priority' => 20,
    ]);

    Promotion::factory()->create([
        'name' => 'Global Promo B',
        'scope' => PromotionScope::Global,
        'status' => PromotionStatus::Active,
        'priority' => 20,
    ]);

    $result = $this->resolver->resolve(
        serviceType: ServiceType::AirportTransfer,
        deal: null,
    );

    expect($result)->toBeNull();
});

test('promotions outside active validity window in Asia/Colombo are excluded', function (): void {
    $now = Carbon::parse('2026-11-26 12:00:00', 'Asia/Colombo');

    // Expired promotion
    Promotion::factory()->create([
        'name' => 'Past Promo',
        'scope' => PromotionScope::Global,
        'status' => PromotionStatus::Active,
        'starts_at' => $now->copy()->subDays(5),
        'ends_at' => $now->copy()->subDay(),
    ]);

    // Future promotion
    Promotion::factory()->create([
        'name' => 'Future Promo',
        'scope' => PromotionScope::Global,
        'status' => PromotionStatus::Active,
        'starts_at' => $now->copy()->addDay(),
        'ends_at' => $now->copy()->addDays(5),
    ]);

    $result = $this->resolver->resolve(
        serviceType: ServiceType::Ride,
        deal: null,
        travelDate: $now,
    );

    expect($result)->toBeNull();
});

test('fixed amount promotion is skipped if currency does not match', function (): void {
    Promotion::factory()->create([
        'name' => 'EUR Discount',
        'scope' => PromotionScope::Global,
        'discount_type' => PromotionDiscountType::FixedAmount,
        'discount_value' => 2000,
        'currency' => 'EUR',
        'status' => PromotionStatus::Active,
    ]);

    $resultUsd = $this->resolver->resolve(
        serviceType: ServiceType::Ride,
        deal: null,
        currency: 'USD',
    );

    expect($resultUsd)->toBeNull();

    $resultEur = $this->resolver->resolve(
        serviceType: ServiceType::Ride,
        deal: null,
        currency: 'EUR',
    );

    expect($resultEur)->not->toBeNull()
        ->and($resultEur->name)->toBe('EUR Discount');
});
