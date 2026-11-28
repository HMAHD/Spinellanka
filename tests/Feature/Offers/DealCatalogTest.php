<?php

declare(strict_types=1);

use App\Domain\Booking\Enums\DealStatus;
use App\Domain\Booking\ServiceType;
use App\Domain\Catalog\Services\DriverAssignmentService;
use App\Domain\Catalog\Services\DriverService;
use App\Domain\Catalog\Services\ExtraService;
use App\Domain\Catalog\Services\VehicleService;
use App\Domain\Pricing\Services\ExchangeRateService;
use App\Domain\Pricing\Services\PricingRuleService;
use App\Domain\Pricing\Services\RentPolicyService;
use App\Models\Deal;
use Carbon\Carbon;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\StagingSyntheticFixturesSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('public deals index displays published deals and excludes drafts and expired deals', function (): void {
    $now = Carbon::parse('2026-11-26 12:00:00', 'Asia/Colombo');
    Carbon::setTestNow($now);

    $publishedDeal = Deal::factory()->create([
        'title' => 'Galle Heritage Coastal Express',
        'slug' => 'galle-heritage-coastal-express',
        'status' => DealStatus::Published,
        'published_at' => $now->copy()->subDays(2),
        'expires_at' => $now->copy()->addDays(10),
    ]);

    $draftDeal = Deal::factory()->create([
        'title' => 'Secret Draft Deal',
        'slug' => 'secret-draft-deal',
        'status' => DealStatus::Draft,
    ]);

    $expiredDeal = Deal::factory()->create([
        'title' => 'Expired Summer Deal',
        'slug' => 'expired-summer-deal',
        'status' => DealStatus::Published,
        'published_at' => $now->copy()->subDays(10),
        'expires_at' => $now->copy()->subDay(),
    ]);

    $response = $this->get(route('deals.index'));

    $response->assertOk()
        ->assertSee('Galle Heritage Coastal Express')
        ->assertDontSee('Secret Draft Deal')
        ->assertDontSee('Expired Summer Deal');
});

test('deals index filters deals by service type', function (): void {
    Deal::factory()->create([
        'title' => 'CMB VIP Airport Welcome',
        'service_type' => ServiceType::AirportPickup,
        'status' => DealStatus::Published,
    ]);

    Deal::factory()->create([
        'title' => 'Ella Scenic Self-Drive 3-Day',
        'service_type' => ServiceType::Rent,
        'status' => DealStatus::Published,
    ]);

    $responsePickup = $this->get(route('deals.index', ['service' => 'airport_pickup']));
    $responsePickup->assertOk()
        ->assertSee('CMB VIP Airport Welcome')
        ->assertDontSee('Ella Scenic Self-Drive 3-Day');

    $responseRent = $this->get(route('deals.index', ['service' => 'rent']));
    $responseRent->assertOk()
        ->assertSee('Ella Scenic Self-Drive 3-Day')
        ->assertDontSee('CMB VIP Airport Welcome');
});

test('deals index filters deals by destination keyword in title, summary, or preset payload', function (): void {
    Deal::factory()->create([
        'title' => 'Bentota Golden Beach Shuttle',
        'summary' => 'Direct transfer to Bentota resort strip',
        'preset_payload' => ['destination' => 'Bentota'],
        'status' => DealStatus::Published,
    ]);

    Deal::factory()->create([
        'title' => 'Nuwara Eliya Tea Country Explorer',
        'summary' => 'Highland journeys through mist-shrouded tea plantations',
        'preset_payload' => ['destination' => 'Nuwara Eliya'],
        'status' => DealStatus::Published,
    ]);

    $response = $this->get(route('deals.index', ['destination' => 'Bentota']));

    $response->assertOk()
        ->assertSee('Bentota Golden Beach Shuttle')
        ->assertDontSee('Nuwara Eliya Tea Country Explorer');
});

test('public deal show page displays details for published deal and returns 404 for draft', function (): void {
    $deal = Deal::factory()->create([
        'title' => 'Mirissa Whale Safari Transfer',
        'slug' => 'mirissa-whale-safari-transfer',
        'summary' => 'Chauffeured day expedition to Mirissa harbor',
        'description_markdown' => "Full day private expedition including highway tolls and flexible harbor pickup.\n\nRelax in luxury.",
        'status' => DealStatus::Published,
    ]);

    $draft = Deal::factory()->create([
        'title' => 'Unpublished Package',
        'slug' => 'unpublished-package',
        'status' => DealStatus::Draft,
    ]);

    $this->get(route('deals.show', $deal->slug))
        ->assertOk()
        ->assertSee('Mirissa Whale Safari Transfer')
        ->assertSee('Chauffeured day expedition to Mirissa harbor')
        ->assertSee('Full day private expedition');

    $this->get(route('deals.show', $draft->slug))
        ->assertNotFound();
});

test('synthetic staging fixtures seed deals across all four services rendered on home and service pages', function (): void {
    $this->seed(RoleAndPermissionSeeder::class);

    app(StagingSyntheticFixturesSeeder::class)->run(
        app(VehicleService::class),
        app(DriverService::class),
        app(DriverAssignmentService::class),
        app(ExtraService::class),
        app(PricingRuleService::class),
        app(ExchangeRateService::class),
        app(RentPolicyService::class),
    );

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('CMB Airport to Colombo Fort Direct Arrival (Synthetic)')
        ->assertSee('CMB Airport to Galle Fort &amp; Unawatuna Arrival (Synthetic)', false)
        ->assertSee('Galle Fort to CMB Departure Transfer (Synthetic)')
        ->assertSee('Kandy to CMB Departure Transfer (Synthetic)')
        ->assertSee('Colombo to Galle Coast Chauffeured Charter (Synthetic)')
        ->assertSee('Colombo to Ella Scenic Hill Country Charter (Synthetic)')
        ->assertSee('Colombo Hub 3-Day Self-Drive Package (Synthetic)')
        ->assertSee('Coast &amp; Highlands 7-Day Self-Drive Package (Synthetic)', false)
        ->assertSee('Airport Pickup')
        ->assertSee('Airport Transfer')
        ->assertSee('Ride')
        ->assertSee('Rent')
        ->assertSee('Deals')
        ->assertSee('More');

    $this->get(route('services.show', ['service' => ServiceType::AirportPickup->value]))
        ->assertOk()
        ->assertSee('CMB Airport to Colombo Fort Direct Arrival (Synthetic)')
        ->assertSee('CMB Airport to Galle Fort &amp; Unawatuna Arrival (Synthetic)', false);

    $this->get(route('services.show', ['service' => ServiceType::AirportTransfer->value]))
        ->assertOk()
        ->assertSee('Galle Fort to CMB Departure Transfer (Synthetic)')
        ->assertSee('Kandy to CMB Departure Transfer (Synthetic)');

    $this->get(route('services.show', ['service' => ServiceType::Ride->value]))
        ->assertOk()
        ->assertSee('Colombo to Galle Coast Chauffeured Charter (Synthetic)')
        ->assertSee('Colombo to Ella Scenic Hill Country Charter (Synthetic)');

    $this->get(route('services.show', ['service' => ServiceType::Rent->value]))
        ->assertOk()
        ->assertSee('Colombo Hub 3-Day Self-Drive Package (Synthetic)')
        ->assertSee('Coast &amp; Highlands 7-Day Self-Drive Package (Synthetic)', false);
});

test('admin panel configures collapsible desktop sidebar', function (): void {
    expect(Filament::getPanel('admin')->isSidebarCollapsibleOnDesktop())->toBeTrue();
});
