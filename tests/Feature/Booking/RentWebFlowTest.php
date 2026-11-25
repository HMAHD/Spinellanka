<?php

declare(strict_types=1);

use App\Domain\Availability\Enums\ScheduleBlockStatus;
use App\Domain\Booking\Enums\BookingSource;
use App\Domain\Booking\Enums\BookingStatus;
use App\Domain\Booking\ServiceType;
use App\Domain\Catalog\Enums\VehicleStatus;
use App\Domain\Pricing\Enums\PricingComponentType;
use App\Domain\Pricing\Enums\PricingUnit;
use App\Models\Booking;
use App\Models\PricingRule;
use App\Models\PricingRuleComponent;
use App\Models\RentPolicy;
use App\Models\Vehicle;
use App\Models\VehicleScheduleBlock;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->policy = RentPolicy::factory()->create([
        'min_driver_age' => 23,
        'max_driver_age' => 70,
        'min_licence_years' => 2,
        'idp_required' => true,
        'security_deposit_minor_units' => 50000,
        'insurance_excess_minor_units' => 50000,
        'daily_mileage_km' => 100,
        'grace_period_minutes' => 59,
        'extra_hours_threshold' => 5,
    ]);

    $this->vehicle = Vehicle::factory()->create([
        'public_name' => 'Toyota Land Cruiser Prado',
        'make' => 'Toyota',
        'model' => 'Land Cruiser Prado',
        'year' => 2024,
        'license_plate' => 'WP-CB-9901',
        'status' => VehicleStatus::Active,
        'eligible_for_rent' => true,
        'turnaround_buffer_minutes' => 60,
    ]);

    // Set up basic pricing rule for Rent
    $rule = PricingRule::factory()->create([
        'service_type' => ServiceType::Rent,
        'vehicle_id' => $this->vehicle->id,
        'priority' => 1,
        'is_active' => true,
        'effective_from' => Carbon::now('Asia/Colombo')->subDays(10),
        'version' => 1,
    ]);

    // Daily rate component ($120.00 / day)
    PricingRuleComponent::create([
        'pricing_rule_id' => $rule->id,
        'component_type' => PricingComponentType::Base,
        'unit' => PricingUnit::PerDay,
        'amount_minor_units' => 12000,
    ]);

    // Refundable deposit component ($500.00)
    PricingRuleComponent::create([
        'pricing_rule_id' => $rule->id,
        'component_type' => PricingComponentType::Deposit,
        'unit' => PricingUnit::Flat,
        'amount_minor_units' => 50000,
    ]);
});

test('step 1 create page renders successfully', function (): void {
    $response = $this->get(route('booking.rent'));

    $response->assertOk()
        ->assertSee('Self-Drive Vehicle Hire')
        ->assertSee('Rental Period (24-Hour Cycles)')
        ->assertSee('Lead Driver Eligibility Verification');
});

test('step 1 validation rejects return date before pickup date', function (): void {
    $pickupDate = Carbon::now('Asia/Colombo')->addDays(3)->format('Y-m-d');
    $returnDate = Carbon::now('Asia/Colombo')->addDays(2)->format('Y-m-d'); // Invalid!

    $response = $this->post(route('booking.rent.search'), [
        'pickup_date' => $pickupDate,
        'pickup_time' => '10:00',
        'return_date' => $returnDate,
        'return_time' => '10:00',
        'pickup_location_type' => 'office',
        'pickup_location_text' => 'Spinellanka Head Office, Colombo 03',
        'return_location_type' => 'office',
        'return_location_text' => 'Spinellanka Head Office, Colombo 03',
        'driver_age' => 30,
        'driver_licence_country' => 'United Kingdom',
        'driver_licence_years' => 5,
        'has_idp' => 1,
    ]);

    $response->assertSessionHasErrors(['return_date']);
});

test('step 1 validation rejects pickup notice violation', function (): void {
    $pickupDate = Carbon::now('Asia/Colombo')->addHours(2)->format('Y-m-d'); // Less than 12 hours notice
    $pickupTime = Carbon::now('Asia/Colombo')->addHours(2)->format('H:i');

    $response = $this->post(route('booking.rent.search'), [
        'pickup_date' => $pickupDate,
        'pickup_time' => $pickupTime,
        'return_date' => Carbon::now('Asia/Colombo')->addDays(3)->format('Y-m-d'),
        'return_time' => '10:00',
        'pickup_location_type' => 'office',
        'pickup_location_text' => 'Office',
        'return_location_type' => 'office',
        'return_location_text' => 'Office',
        'driver_age' => 30,
        'driver_licence_country' => 'UK',
        'driver_licence_years' => 5,
        'has_idp' => 1,
    ]);

    $response->assertSessionHasErrors(['pickup_date']);
});

test('full multi-step web flow completes advance rental submission and redirects to status page', function (): void {
    $pickup = Carbon::now('Asia/Colombo')->addDays(3)->setHour(10)->setMinute(0)->setSecond(0);
    $return = (clone $pickup)->addDays(3); // 3 days

    // 1. Submit Search (Step 1 -> Step 2)
    $searchResponse = $this->post(route('booking.rent.search'), [
        'pickup_date' => $pickup->format('Y-m-d'),
        'pickup_time' => $pickup->format('H:i'),
        'return_date' => $return->format('Y-m-d'),
        'return_time' => $return->format('H:i'),
        'pickup_location_type' => 'office',
        'pickup_location_text' => 'Spinellanka Head Office, Colombo 03',
        'return_location_type' => 'office',
        'return_location_text' => 'Spinellanka Head Office, Colombo 03',
        'driver_age' => 35,
        'driver_licence_country' => 'United Kingdom',
        'driver_licence_years' => 10,
        'has_idp' => 1,
        'has_additional_driver' => 0,
        'expected_distance_km' => 350,
    ]);

    $searchResponse->assertRedirect(route('booking.rent.vehicles'));

    // 2. View Vehicles Page (Step 2)
    $vehiclesResponse = $this->get(route('booking.rent.vehicles'));
    $vehiclesResponse->assertOk()
        ->assertSee('Toyota Land Cruiser Prado')
        ->assertSee('WP-CB-9901')
        ->assertSee('Self-Drive Hire:')
        ->assertSee('300 km allowance included') // 3 days * 100 km
        ->assertDontSee('Chauffeured English-speaking driver included');

    // 3. Hold Vehicle (Step 2 -> Step 3)
    $holdResponse = $this->post(route('booking.rent.hold'), [
        'vehicle_id' => $this->vehicle->id,
    ]);
    $holdResponse->assertRedirect(route('booking.rent.contact'));

    // 4. View Contact Form (Step 3)
    $contactResponse = $this->get(route('booking.rent.contact'));
    $contactResponse->assertOk()
        ->assertSee('Vehicle Hold Active:')
        ->assertSee('WP-CB-9901')
        ->assertSee('Physical Original Documents Requirement');

    // 5. Submit Reservation (Step 3 -> Private Status Page)
    $submitResponse = $this->post(route('booking.rent.submit'), [
        'booker_first_name' => 'Alexander',
        'booker_last_name' => 'Wright',
        'booker_phone' => '+44 7700 900123',
        'booker_is_whatsapp' => 1,
        'booker_email' => 'alex@example.co.uk',
        'lead_driver_same_as_booker' => 1,
        'terms_accepted' => 1,
        'privacy_accepted' => 1,
        'original_documents_acknowledged' => 1,
    ]);

    $submitResponse->assertRedirect();
    $redirectUrl = $submitResponse->headers->get('Location');
    expect($redirectUrl)->toContain('/booking/BKG-');

    // 6. Access Private Status Page
    $statusResponse = $this->get($redirectUrl);
    $statusResponse->assertOk()
        ->assertSee('Request Received — Under Review')
        ->assertSee('Self-Drive Rental &amp; Handover Details', false)
        ->assertSee('Toyota Land Cruiser Prado')
        ->assertSee('WP-CB-9901')
        ->assertSee('3 Days')
        ->assertSee('300 km included cumulative')
        ->assertSee('USD $500.00')
        ->assertSee('Mandatory In-Person Document Inspection at Handover:')
        ->assertSee('Self-drive vehicle hire (no driver assigned)')
        ->assertDontSee('Chauffeured English-speaking driver included');

    // Verify database booking record
    $booking = Booking::where('source', BookingSource::Web)
        ->where('service_type', ServiceType::Rent)
        ->latest('id')
        ->first();

    expect($booking)->not->toBeNull()
        ->and($booking->status)->toBe(BookingStatus::Submitted)
        ->and($booking->rentDetail)->not->toBeNull()
        ->and($booking->rentDetail->duration_days)->toBe(3)
        ->and($booking->rentDetail->included_distance_km)->toBe(300)
        ->and($booking->rentDetail->original_documents_acknowledged)->toBeTrue();
});

test('rent hold releases previous session hold upon selecting a different vehicle', function (): void {
    $pickup = Carbon::now('Asia/Colombo')->addDays(3)->setHour(10)->setMinute(0);
    $return = (clone $pickup)->addDays(3);

    $vehicle2 = Vehicle::factory()->create([
        'status' => VehicleStatus::Active,
        'eligible_for_rent' => true,
        'turnaround_buffer_minutes' => 60,
    ]);

    $rule2 = PricingRule::factory()->create([
        'service_type' => ServiceType::Rent,
        'vehicle_id' => $vehicle2->id,
        'priority' => 1,
        'is_active' => true,
        'effective_from' => Carbon::now('Asia/Colombo')->subDays(10),
    ]);

    PricingRuleComponent::create([
        'pricing_rule_id' => $rule2->id,
        'component_type' => PricingComponentType::Base,
        'unit' => PricingUnit::PerDay,
        'amount_minor_units' => 15000,
    ]);

    $this->post(route('booking.rent.search'), [
        'pickup_date' => $pickup->format('Y-m-d'),
        'pickup_time' => $pickup->format('H:i'),
        'return_date' => $return->format('Y-m-d'),
        'return_time' => $return->format('H:i'),
        'pickup_location_type' => 'office',
        'pickup_location_text' => 'Spinellanka Colombo Office',
        'return_location_type' => 'office',
        'return_location_text' => 'Spinellanka Colombo Office',
        'driver_age' => 28,
        'driver_licence_country' => 'United Kingdom',
        'driver_licence_years' => 5,
        'has_idp' => 1,
    ])->assertRedirect(route('booking.rent.vehicles'));

    // Select Vehicle 1
    $this->post(route('booking.rent.hold'), [
        'vehicle_id' => $this->vehicle->id,
    ])->assertRedirect(route('booking.rent.contact'));

    $hold1 = session('rent_hold');
    expect($hold1)->not->toBeNull();

    // Now re-select Vehicle 2
    $this->post(route('booking.rent.hold'), [
        'vehicle_id' => $vehicle2->id,
    ])->assertRedirect(route('booking.rent.contact'));

    $hold2 = session('rent_hold');
    expect($hold2)->not->toBeNull()
        ->and($hold2['vehicle_id'])->toBe($vehicle2->id)
        ->and($hold2['plain_token'])->not->toBe($hold1['plain_token']);

    // Check old hold block on Vehicle 1 is released
    $oldBlock = VehicleScheduleBlock::where('vehicle_id', $this->vehicle->id)->latest('id')->first();
    expect($oldBlock->status)->toBe(ScheduleBlockStatus::Released);
});

test('web form contains zero file inputs preserving strict data minimization', function (): void {
    $this->get(route('booking.rent'))
        ->assertOk()
        ->assertDontSee('type="file"', false)
        ->assertSee('Original physical documents verified in person at handover');
});
