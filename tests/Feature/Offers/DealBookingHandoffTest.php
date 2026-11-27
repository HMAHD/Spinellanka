<?php

declare(strict_types=1);

use App\Domain\Booking\Enums\DealStatus;
use App\Domain\Booking\ServiceType;
use App\Models\Deal;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('booking a deal validates travel date and redirects to service-specific booking flow with session preset', function (): void {
    $now = Carbon::parse('2026-11-26 12:00:00', 'Asia/Colombo');
    Carbon::setTestNow($now);

    $deal = Deal::factory()->create([
        'title' => 'Galle Fort Heritage Chauffeured Tour',
        'slug' => 'galle-fort-tour',
        'service_type' => ServiceType::Ride,
        'status' => DealStatus::Published,
        'preset_payload' => [
            'origin' => 'Colombo Fort',
            'destination' => 'Galle Fort Lighthouse',
            'passenger_count' => 3,
            'luggage_count' => 2,
        ],
    ]);

    // Validation fails if date is missing or past
    $invalidResponse = $this->post(route('deals.book', $deal->slug), [
        'travel_date' => $now->copy()->subDay()->format('Y-m-d'),
    ]);
    $invalidResponse->assertSessionHasErrors('travel_date');

    // Valid booking request
    $validDate = $now->copy()->addDays(3)->format('Y-m-d');
    $response = $this->post(route('deals.book', $deal->slug), [
        'travel_date' => $validDate,
        'travel_time' => '09:30',
    ]);

    $response->assertRedirect(route('booking.ride'));
    $response->assertSessionHas('booking_deal_preset');

    $preset = session('booking_deal_preset');
    expect($preset['deal_id'])->toBe($deal->id)
        ->and($preset['origin'])->toBe('Colombo Fort')
        ->and($preset['destination'])->toBe('Galle Fort Lighthouse')
        ->and($preset['travel_date'])->toBe($validDate)
        ->and($preset['travel_time'])->toBe('09:30');

    // Visiting booking flow initializes the form inputs
    $bookingPage = $this->get(route('booking.ride'));
    $bookingPage->assertOk()
        ->assertSee('Colombo Fort')
        ->assertSee('Galle Fort Lighthouse');
});

test('booking an airport pickup deal redirects to airport-pickup booking flow and pre-fills criteria', function (): void {
    $now = Carbon::parse('2026-11-26 12:00:00', 'Asia/Colombo');
    Carbon::setTestNow($now);

    $deal = Deal::factory()->create([
        'title' => 'CMB to Kandy Direct Pickup',
        'slug' => 'cmb-kandy-pickup',
        'service_type' => ServiceType::AirportPickup,
        'status' => DealStatus::Published,
        'preset_payload' => [
            'airport' => 'CMB',
            'flight_number' => 'UL504',
            'destination_text' => 'Grand Hotel, Kandy',
            'passenger_count' => 2,
            'luggage_count' => 2,
        ],
    ]);

    $validDate = $now->copy()->addDays(2)->format('Y-m-d');
    $response = $this->post(route('deals.book', $deal->slug), [
        'travel_date' => $validDate,
        'travel_time' => '15:45',
    ]);

    $response->assertRedirect(route('booking.airport-pickup'));

    $pickupPage = $this->get(route('booking.airport-pickup'));
    $pickupPage->assertOk()
        ->assertSee('UL504')
        ->assertSee('Grand Hotel, Kandy');
});

test('booking a rent deal redirects to rent booking flow and pre-fills criteria', function (): void {
    $now = Carbon::parse('2026-11-26 12:00:00', 'Asia/Colombo');
    Carbon::setTestNow($now);

    $deal = Deal::factory()->create([
        'title' => 'South Coast Self-Drive Weekend',
        'slug' => 'south-coast-weekend',
        'service_type' => ServiceType::Rent,
        'status' => DealStatus::Published,
        'preset_payload' => [
            'duration_days' => 4,
            'pickup_location_text' => 'Colombo Hub',
            'return_location_text' => 'Colombo Hub',
            'driver_age' => 32,
        ],
    ]);

    $validDate = $now->copy()->addDays(5)->format('Y-m-d');
    $response = $this->post(route('deals.book', $deal->slug), [
        'travel_date' => $validDate,
        'travel_time' => '11:00',
    ]);

    $response->assertRedirect(route('booking.rent'));

    $rentPage = $this->get(route('booking.rent'));
    $rentPage->assertOk()
        ->assertSee('Colombo Hub');
});
