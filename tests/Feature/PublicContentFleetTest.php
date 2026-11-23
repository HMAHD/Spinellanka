<?php

use App\Domain\Catalog\Enums\MediaType;
use App\Domain\Catalog\Enums\TransmissionType;
use App\Domain\Catalog\Enums\VehicleStatus;
use App\Domain\Catalog\Enums\VehicleType;
use App\Models\Vehicle;
use App\Models\VehicleMedia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('renders the public fleet index page with active vehicles and approved media', function (): void {
    Storage::fake('public');

    $vehicle = Vehicle::factory()->create([
        'public_name' => 'Spinel Premier Van',
        'internal_fleet_id' => 'FLEET-SECRET-01',
        'make' => 'Toyota',
        'model' => 'KDH High Roof',
        'year' => 2023,
        'license_plate' => 'WP-CAD-1234',
        'vin' => 'VIN-SECRET-1234567890',
        'vehicle_type' => VehicleType::LuxuryVan,
        'passenger_capacity' => 7,
        'luggage_capacity' => 6,
        'transmission' => TransmissionType::Automatic,
        'has_ac' => true,
        'eligible_for_pickup' => true,
        'eligible_for_transfer' => true,
        'eligible_for_ride' => true,
        'eligible_for_rent' => false,
        'status' => VehicleStatus::Active,
        'internal_notes' => 'Internal maintenance note that should never be shown publicly.',
    ]);

    VehicleMedia::factory()->create([
        'vehicle_id' => $vehicle->id,
        'disk' => 'public',
        'file_path' => 'vehicles/exterior-front.webp',
        'media_type' => MediaType::ExteriorFront,
        'alt_text' => 'Front three-quarter view of Spinel Premier Van',
        'sort_order' => 1,
        'is_approved' => true,
    ]);

    $response = $this->get(route('fleet.index'));

    $response->assertOk()
        ->assertSee('Our Fleet')
        ->assertSee('Spinel Premier Van')
        ->assertSee('Toyota KDH High Roof (2023)')
        ->assertSee('Luxury Van')
        ->assertSee('Up to 7')
        ->assertSee('6 bags')
        ->assertSee('Automatic')
        ->assertSee('Airport Pickup')
        ->assertSee('Airport Transfer')
        ->assertSee('Ride')
        ->assertDontSee('Rent (Self-Drive)')
        ->assertSee('Front three-quarter view of Spinel Premier Van')
        // Ensure sensitive internal fields are NEVER exposed
        ->assertDontSee('FLEET-SECRET-01')
        ->assertDontSee('WP-CAD-1234')
        ->assertDontSee('VIN-SECRET-1234567890')
        ->assertDontSee('Internal maintenance note');
});

it('renders calm factual empty state when no vehicles are active', function (): void {
    $response = $this->get(route('fleet.index'));

    $response->assertOk()
        ->assertSee('Our Fleet')
        ->assertSee('Fleet verification in progress')
        ->assertSee('Vehicle records are being verified with the operator')
        ->assertDontSee('Approved photography pending');
});

it('excludes inactive, maintenance, or retired vehicles from public fleet index', function (): void {
    Vehicle::factory()->create([
        'public_name' => 'Active Van',
        'status' => VehicleStatus::Active,
    ]);

    Vehicle::factory()->create([
        'public_name' => 'Maintenance Sedan',
        'status' => VehicleStatus::Maintenance,
    ]);

    Vehicle::factory()->create([
        'public_name' => 'Retired SUV',
        'status' => VehicleStatus::Retired,
    ]);

    $response = $this->get(route('fleet.index'));

    $response->assertOk()
        ->assertSee('Active Van')
        ->assertDontSee('Maintenance Sedan')
        ->assertDontSee('Retired SUV');
});

it('renders single active vehicle specification page with verified facts and approved media', function (): void {
    Storage::fake('public');

    $vehicle = Vehicle::factory()->create([
        'public_name' => 'Spinel Executive Sedan',
        'internal_fleet_id' => 'FLEET-HIDDEN-02',
        'make' => 'Honda',
        'model' => 'Grace Hybrid',
        'year' => 2022,
        'license_plate' => 'WP-CBD-5678',
        'vin' => 'VIN-HIDDEN-0987654321',
        'vehicle_type' => VehicleType::Sedan,
        'passenger_capacity' => 3,
        'luggage_capacity' => 3,
        'transmission' => TransmissionType::Automatic,
        'has_ac' => true,
        'accessibility_features' => ['Low step-in height'],
        'amenities' => ['Bottled water', 'USB phone charging'],
        'eligible_for_pickup' => true,
        'eligible_for_transfer' => true,
        'eligible_for_ride' => true,
        'eligible_for_rent' => false,
        'status' => VehicleStatus::Active,
        'internal_notes' => 'Confidential driver assignment notes.',
    ]);

    VehicleMedia::factory()->create([
        'vehicle_id' => $vehicle->id,
        'disk' => 'public',
        'file_path' => 'vehicles/sedan-exterior.webp',
        'media_type' => MediaType::ExteriorFront,
        'alt_text' => 'Front view of Spinel Executive Sedan',
        'sort_order' => 1,
        'is_approved' => true,
    ]);

    $response = $this->get(route('fleet.show', $vehicle));

    $response->assertOk()
        ->assertSee('Spinel Executive Sedan')
        ->assertSee('Honda Grace Hybrid')
        ->assertSee('2022')
        ->assertSee('Sedan')
        ->assertSee('Up to 3 passengers')
        ->assertSee('3 standard bags')
        ->assertSee('Automatic')
        ->assertSee('Equipped with climate control')
        ->assertSee('Low step-in height')
        ->assertSee('Bottled water')
        ->assertSee('USB phone charging')
        ->assertSee('Airport Pickup')
        ->assertSee('Front view of Spinel Executive Sedan')
        // Strict boundary: hidden / private fields must NOT appear
        ->assertDontSee('FLEET-HIDDEN-02')
        ->assertDontSee('WP-CBD-5678')
        ->assertDontSee('VIN-HIDDEN-0987654321')
        ->assertDontSee('Confidential driver assignment notes');
});

it('returns 404 when viewing an inactive, maintenance, or retired vehicle', function (): void {
    $maintenanceVehicle = Vehicle::factory()->create([
        'public_name' => 'Under Repair Van',
        'status' => VehicleStatus::Maintenance,
    ]);

    $retiredVehicle = Vehicle::factory()->create([
        'public_name' => 'Old Retired Van',
        'status' => VehicleStatus::Retired,
    ]);

    $this->get(route('fleet.show', $maintenanceVehicle))->assertNotFound();
    $this->get(route('fleet.show', $retiredVehicle))->assertNotFound();
    $this->get('/fleet/999999')->assertNotFound();
});

it('renders calm photography placeholder when vehicle has no approved media', function (): void {
    $vehicle = Vehicle::factory()->create([
        'public_name' => 'Vehicle Without Photos',
        'status' => VehicleStatus::Active,
    ]);

    $response = $this->get(route('fleet.show', $vehicle));

    $response->assertOk()
        ->assertSee('Vehicle Without Photos')
        ->assertSee('Approved photography for Vehicle Without Photos is currently being prepared');
});

it('renders how-it-works page with 4-step workflow, Sri Lanka timezone, and advance booking guarantees', function (): void {
    $response = $this->get(route('how-it-works'));

    $response->assertOk()
        ->assertSee('How It Works')
        ->assertSee('1. Choose a service')
        ->assertSee('2. Select your exact vehicle')
        ->assertSee('3. Submit an advance request')
        ->assertSee('4. Staff confirmation &amp; proposal', false)
        ->assertSee('Sri Lanka Standard Time (UTC+5:30)')
        ->assertSee('Turnaround &amp; Preparation Buffers', false)
        ->assertSee('In-Person Handover for Rent');
});

it('renders help and FAQ page with accessible accordion details', function (): void {
    $response = $this->get(route('help'));

    $response->assertOk()
        ->assertSee('Help &amp; Frequently Asked Questions', false)
        ->assertSee('How far in advance should I book?')
        ->assertSee('What does exact vehicle selection mean?')
        ->assertSee('What time zone are bookings scheduled in?')
        ->assertSee('What is included with Airport Pickup?')
        ->assertSee('What is required for self-drive Rent?')
        ->assertSee('details class="faq-item"', false)
        ->assertSee('summary class="faq-question"', false);
});

it('redirects /faq to /help via 301 redirect', function (): void {
    $this->get('/faq')->assertRedirect(route('help'))->assertStatus(301);
});

it('renders contact page with phased service launch timeline and checklist verification notice', function (): void {
    $response = $this->get(route('contact'));

    $response->assertOk()
        ->assertSee('Contact &amp; Inquiries', false)
        ->assertSee('Phase 07:')
        ->assertSee('Airport Pickup')
        ->assertSee('Phase 08:')
        ->assertSee('Airport Transfer')
        ->assertSee('Phase 09:')
        ->assertSee('Ride')
        ->assertSee('Phase 10:')
        ->assertSee('Rent')
        ->assertSee('Checklist Section 1');
});

it('renders terms and privacy pages with noindex metadata and truthful legal pending status', function (): void {
    $termsResponse = $this->get(route('legal.terms'));
    $termsResponse->assertOk()
        ->assertSee('Terms of Service')
        ->assertSee('Legal Verification in Progress')
        ->assertSee('name="robots" content="noindex, nofollow"', false);

    $privacyResponse = $this->get(route('legal.privacy'));
    $privacyResponse->assertOk()
        ->assertSee('Privacy Policy')
        ->assertSee('Policy Finalization in Progress')
        ->assertSee('Data Minimization Standards')
        ->assertSee('name="robots" content="noindex, nofollow"', false);
});

it('renders standard indexable metadata on public marketing pages and enforces noindex boundaries on legal/admin', function (): void {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('name="robots" content="index, follow"', false);

    $this->get(route('fleet.index'))
        ->assertOk()
        ->assertSee('name="robots" content="index, follow"', false);

    $this->get(route('how-it-works'))
        ->assertOk()
        ->assertSee('name="robots" content="index, follow"', false);

    $this->get(route('legal.terms'))
        ->assertOk()
        ->assertSee('name="robots" content="noindex, nofollow"', false);

    $this->get(route('legal.privacy'))
        ->assertOk()
        ->assertSee('name="robots" content="noindex, nofollow"', false);
});

it('ensures all public navigation links and footer exist across all pages', function (): void {
    $urls = [
        route('home'),
        route('fleet.index'),
        route('how-it-works'),
        route('help'),
        route('contact'),
        route('legal.terms'),
        route('legal.privacy'),
    ];

    foreach ($urls as $url) {
        $response = $this->get($url);
        $response->assertOk()
            ->assertSee(route('fleet.index'))
            ->assertSee(route('how-it-works'))
            ->assertSee(route('help'))
            ->assertSee(route('contact'))
            ->assertSee(route('legal.terms'))
            ->assertSee(route('legal.privacy'));
    }
});

it('verifies that self-hosted Allo 1.0.3 font files and licenses exist in public assets', function (): void {
    expect(file_exists(public_path('fonts/allo/AlloVariable.woff2')))->toBeTrue();
    expect(file_exists(public_path('fonts/allo/OFL.txt')))->toBeTrue();
    expect(file_exists(public_path('fonts/allo/LICENSE.txt')))->toBeTrue();
    expect(filesize(public_path('fonts/allo/AlloVariable.woff2')))->toBeGreaterThan(400000);
});
