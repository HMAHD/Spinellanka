<?php

declare(strict_types=1);

use App\Domain\Availability\Enums\ScheduleBlockKind;
use App\Domain\Availability\Enums\ScheduleBlockStatus;
use App\Domain\Availability\Services\AvailabilityService;
use App\Domain\Booking\Enums\BookingStatus;
use App\Domain\Booking\Enums\ConfirmationChannel;
use App\Domain\Booking\Enums\PaymentOption;
use App\Domain\Booking\Enums\ProposalPaymentChoice;
use App\Domain\Booking\Enums\ProposalStatus;
use App\Domain\Booking\Services\GuestControlService;
use App\Domain\Booking\Services\ProposalService;
use App\Domain\Booking\ServiceType;
use App\Domain\Catalog\Enums\VehicleStatus;
use App\Domain\Pricing\Enums\PricingComponentType;
use App\Domain\Pricing\Enums\PricingUnit;
use App\Models\Booking;
use App\Models\GuestAccessToken;
use App\Models\Proposal;
use App\Models\ProposalItem;
use App\Models\RentDetail;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed();
    $this->guestControlService = app(GuestControlService::class);
    $this->availabilityService = app(AvailabilityService::class);
    $this->proposalService = app(ProposalService::class);
});

test('guest status page displays proposal details, comparison, and enforces security headers', function (): void {
    $vehicle = Vehicle::factory()->create(['status' => VehicleStatus::Active]);
    $booking = Booking::factory()->create([
        'status' => BookingStatus::ProposalSent,
        'vehicle_id' => $vehicle->id,
        'service_type' => ServiceType::AirportPickup,
        'total_amount_minor_units' => 7500,
    ]);

    $proposal = Proposal::factory()->create([
        'booking_id' => $booking->id,
        'status' => ProposalStatus::Sent,
        'vehicle_id' => $vehicle->id,
        'total_amount_minor_units' => 8500,
        'deposit_required' => true,
        'deposit_amount_minor_units' => 2000,
        'payment_choice' => ProposalPaymentChoice::FullOrDeposit,
        'staff_notes' => 'Airport toll fees and greeting included.',
    ]);

    ProposalItem::create([
        'proposal_id' => $proposal->id,
        'component_type' => PricingComponentType::Base,
        'description' => 'Base Pickup Transfer',
        'quantity' => 1.0,
        'unit' => PricingUnit::Flat,
        'unit_price_minor_units' => 8500,
        'total_amount_minor_units' => 8500,
        'is_deposit' => false,
        'is_tax' => false,
        'is_discount' => false,
        'sort_order' => 1,
    ]);

    $booking->update(['current_proposal_id' => $proposal->id]);

    $plainToken = GuestAccessToken::generateToken();
    GuestAccessToken::create([
        'booking_id' => $booking->id,
        'token_hash' => GuestAccessToken::hashToken($plainToken),
        'purpose' => 'guest_access',
        'expires_at' => Carbon::now('Asia/Colombo')->addDays(30),
    ]);

    $response = $this->get(route('booking.status', [
        'reference' => $booking->reference,
        'token' => $plainToken,
    ]));

    $response->assertStatus(200);
    $response->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    $response->assertHeader('Referrer-Policy', 'no-referrer');
    $response->assertHeader('Cache-Control', 'no-store, private');

    $response->assertSee('Official Service &amp; Rate Proposal', false);
    $response->assertSee('Airport toll fees and greeting included.');
    $response->assertSee('Base Pickup Transfer');
    $response->assertSee('Accept Proposal');
    $response->assertSee('Request Adjustments');
    $response->assertSee('Decline Proposal');
});

test('invalid, expired, or revoked token returns neutral 404', function (): void {
    $booking = Booking::factory()->create([
        'status' => BookingStatus::Submitted,
    ]);

    // Invalid random token
    $this->get(route('booking.status', [
        'reference' => $booking->reference,
        'token' => 'invalid-random-token-xyz',
    ]))->assertStatus(404);

    // Revoked token
    $revokedToken = GuestAccessToken::generateToken();
    GuestAccessToken::create([
        'booking_id' => $booking->id,
        'token_hash' => GuestAccessToken::hashToken($revokedToken),
        'purpose' => 'guest_access',
        'revoked_at' => Carbon::now('Asia/Colombo')->subHour(),
        'expires_at' => Carbon::now('Asia/Colombo')->addDays(30),
    ]);

    $this->get(route('booking.status', [
        'reference' => $booking->reference,
        'token' => $revokedToken,
    ]))->assertStatus(404);

    // Expired token
    $expiredToken = GuestAccessToken::generateToken();
    GuestAccessToken::create([
        'booking_id' => $booking->id,
        'token_hash' => GuestAccessToken::hashToken($expiredToken),
        'purpose' => 'guest_access',
        'expires_at' => Carbon::now('Asia/Colombo')->subMinute(),
    ]);

    $this->get(route('booking.status', [
        'reference' => $booking->reference,
        'token' => $expiredToken,
    ]))->assertStatus(404);
});

test('accepting proposal with pay_later confirms booking directly with GuestPrivateLink', function (): void {
    $vehicle = Vehicle::factory()->create(['status' => VehicleStatus::Active]);
    $booking = Booking::factory()->create([
        'status' => BookingStatus::ProposalSent,
        'vehicle_id' => $vehicle->id,
    ]);

    $hold = $this->availabilityService->createProposalHold(
        vehicle: $vehicle,
        start: Carbon::now('Asia/Colombo')->addDays(3),
        end: Carbon::now('Asia/Colombo')->addDays(3)->addHours(2),
        service: ServiceType::AirportPickup,
        expiresAt: Carbon::now('Asia/Colombo')->addHours(48)
    );

    $proposal = Proposal::factory()->create([
        'booking_id' => $booking->id,
        'status' => ProposalStatus::Sent,
        'vehicle_id' => $vehicle->id,
        'schedule_block_id' => $hold->id,
        'payment_choice' => ProposalPaymentChoice::FullOrPayLater,
    ]);
    $booking->update(['current_proposal_id' => $proposal->id]);

    $plainToken = GuestAccessToken::generateToken();
    GuestAccessToken::create([
        'booking_id' => $booking->id,
        'token_hash' => GuestAccessToken::hashToken($plainToken),
        'purpose' => 'guest_access',
        'expires_at' => Carbon::now('Asia/Colombo')->addDays(30),
    ]);

    $response = $this->post(route('booking.status.accept', [
        'reference' => $booking->reference,
        'token' => $plainToken,
    ]), [
        'payment_option' => 'pay_later',
        'notes' => 'Will pay cash in EUR to driver',
    ]);

    $response->assertRedirect(route('booking.status', ['reference' => $booking->reference, 'token' => $plainToken]));

    $booking->refresh();
    $proposal->refresh();
    $hold->refresh();

    expect($booking->status)->toBe(BookingStatus::Confirmed)
        ->and($booking->confirmation_channel)->toBe(ConfirmationChannel::GuestPrivateLink)
        ->and($proposal->status)->toBe(ProposalStatus::Accepted)
        ->and($proposal->selected_payment_choice)->toBe(PaymentOption::PayLater)
        ->and($hold->kind)->toBe(ScheduleBlockKind::ConfirmedBooking)
        ->and($hold->status)->toBe(ScheduleBlockStatus::Active);
});

test('accepting proposal requiring online payment transitions to AwaitingPayment (Phase 13 boundary)', function (): void {
    $vehicle = Vehicle::factory()->create(['status' => VehicleStatus::Active]);
    $booking = Booking::factory()->create([
        'status' => BookingStatus::ProposalSent,
        'vehicle_id' => $vehicle->id,
    ]);

    $hold = $this->availabilityService->createProposalHold(
        vehicle: $vehicle,
        start: Carbon::now('Asia/Colombo')->addDays(3),
        end: Carbon::now('Asia/Colombo')->addDays(3)->addHours(2),
        service: ServiceType::AirportPickup,
        expiresAt: Carbon::now('Asia/Colombo')->addHours(48)
    );

    $proposal = Proposal::factory()->create([
        'booking_id' => $booking->id,
        'status' => ProposalStatus::Sent,
        'vehicle_id' => $vehicle->id,
        'schedule_block_id' => $hold->id,
        'deposit_required' => true,
        'deposit_amount_minor_units' => 2000,
        'payment_choice' => ProposalPaymentChoice::DepositOnly,
    ]);
    $booking->update(['current_proposal_id' => $proposal->id]);

    $plainToken = GuestAccessToken::generateToken();
    GuestAccessToken::create([
        'booking_id' => $booking->id,
        'token_hash' => GuestAccessToken::hashToken($plainToken),
        'purpose' => 'guest_access',
        'expires_at' => Carbon::now('Asia/Colombo')->addDays(30),
    ]);

    $response = $this->post(route('booking.status.accept', [
        'reference' => $booking->reference,
        'token' => $plainToken,
    ]), [
        'payment_option' => 'deposit',
    ]);

    $response->assertRedirect(route('booking.status', ['reference' => $booking->reference, 'token' => $plainToken]));

    $booking->refresh();
    $proposal->refresh();

    // Stops at AwaitingPayment until Phase 13 checkout
    expect($booking->status)->toBe(BookingStatus::AwaitingPayment)
        ->and($proposal->status)->toBe(ProposalStatus::Accepted)
        ->and($proposal->selected_payment_choice)->toBe(PaymentOption::Deposit);
});

test('payment choice tampering protection rejects unauthorized payment choice', function (): void {
    $vehicle = Vehicle::factory()->create(['status' => VehicleStatus::Active]);
    $booking = Booking::factory()->create([
        'status' => BookingStatus::ProposalSent,
        'vehicle_id' => $vehicle->id,
    ]);

    $proposal = Proposal::factory()->create([
        'booking_id' => $booking->id,
        'status' => ProposalStatus::Sent,
        'vehicle_id' => $vehicle->id,
        'payment_choice' => ProposalPaymentChoice::DepositOnly, // Strictly deposit only
    ]);
    $booking->update(['current_proposal_id' => $proposal->id]);

    $plainToken = GuestAccessToken::generateToken();
    GuestAccessToken::create([
        'booking_id' => $booking->id,
        'token_hash' => GuestAccessToken::hashToken($plainToken),
        'purpose' => 'guest_access',
        'expires_at' => Carbon::now('Asia/Colombo')->addDays(30),
    ]);

    // Attacker attempts to bypass deposit by sending pay_later
    $response = $this->post(route('booking.status.accept', [
        'reference' => $booking->reference,
        'token' => $plainToken,
    ]), [
        'payment_option' => 'pay_later',
    ]);

    $response->assertSessionHas('error');

    $booking->refresh();
    $proposal->refresh();
    expect($proposal->status)->toBe(ProposalStatus::Sent)
        ->and($booking->status)->toBe(BookingStatus::ProposalSent);
});

test('declining revision proposal keeps confirmed booking intact (Pillar B)', function (): void {
    $vehicle = Vehicle::factory()->create(['status' => VehicleStatus::Active]);
    $confirmedBlock = $this->availabilityService->createBlock(
        vehicle: $vehicle,
        kind: ScheduleBlockKind::ConfirmedBooking,
        start: Carbon::now('Asia/Colombo')->addDays(4),
        end: Carbon::now('Asia/Colombo')->addDays(4)->addHours(4)
    );

    $booking = Booking::factory()->create([
        'status' => BookingStatus::Confirmed,
        'vehicle_id' => $vehicle->id,
        'schedule_block_id' => $confirmedBlock->id,
        'pending_revision' => true,
    ]);

    $hold = $this->availabilityService->createProposalHold(
        vehicle: $vehicle,
        start: Carbon::now('Asia/Colombo')->addDays(4)->addHour(),
        end: Carbon::now('Asia/Colombo')->addDays(4)->addHours(5),
        service: ServiceType::AirportPickup,
        expiresAt: Carbon::now('Asia/Colombo')->addHours(48),
        ignoreBlockId: $confirmedBlock->id
    );

    $proposal = Proposal::factory()->create([
        'booking_id' => $booking->id,
        'status' => ProposalStatus::Sent,
        'vehicle_id' => $vehicle->id,
        'schedule_block_id' => $hold->id,
    ]);
    $booking->update(['current_proposal_id' => $proposal->id]);

    $plainToken = GuestAccessToken::generateToken();
    GuestAccessToken::create([
        'booking_id' => $booking->id,
        'token_hash' => GuestAccessToken::hashToken($plainToken),
        'purpose' => 'guest_access',
        'expires_at' => Carbon::now('Asia/Colombo')->addDays(30),
    ]);

    $response = $this->post(route('booking.status.decline', [
        'reference' => $booking->reference,
        'token' => $plainToken,
    ]), [
        'reason' => 'Rate is higher than expected',
    ]);

    $response->assertRedirect(route('booking.status', ['reference' => $booking->reference, 'token' => $plainToken]));

    $booking->refresh();
    $proposal->refresh();
    $hold->refresh();
    $confirmedBlock->refresh();

    expect($proposal->status)->toBe(ProposalStatus::Declined);
    expect($hold->status)->toBe(ScheduleBlockStatus::Released);

    // PILLAR B INVARIANT: Confirmed booking remains Confirmed!
    expect($booking->status)->toBe(BookingStatus::Confirmed)
        ->and($booking->pending_revision)->toBeFalse()
        ->and($booking->schedule_block_id)->toBe($confirmedBlock->id);

    expect($confirmedBlock->status)->toBe(ScheduleBlockStatus::Active);
});

test('guest can request changes and submit cancellation', function (): void {
    $booking = Booking::factory()->create([
        'status' => BookingStatus::Submitted,
    ]);

    $plainToken = GuestAccessToken::generateToken();
    GuestAccessToken::create([
        'booking_id' => $booking->id,
        'token_hash' => GuestAccessToken::hashToken($plainToken),
        'purpose' => 'guest_access',
        'expires_at' => Carbon::now('Asia/Colombo')->addDays(30),
    ]);

    // Request Change
    $this->post(route('booking.status.change', [
        'reference' => $booking->reference,
        'token' => $plainToken,
    ]), [
        'notes' => 'Flight delayed by 3 hours. New ETA is 14:30.',
    ])->assertRedirect(route('booking.status', ['reference' => $booking->reference, 'token' => $plainToken]));

    $booking->refresh();
    expect($booking->pending_revision)->toBeTrue();

    // Cancel Booking
    $this->post(route('booking.status.cancel', [
        'reference' => $booking->reference,
        'token' => $plainToken,
    ]), [
        'reason' => 'Trip cancelled due to weather.',
    ])->assertRedirect(route('booking.status', ['reference' => $booking->reference, 'token' => $plainToken]));

    $booking->refresh();
    expect($booking->status)->toBe(BookingStatus::Cancelled);
});

test('rent extension request updates rent_details extension_state and flags pending revision', function (): void {
    $vehicle = Vehicle::factory()->create(['status' => VehicleStatus::Active]);
    $booking = Booking::factory()->create([
        'status' => BookingStatus::Confirmed,
        'service_type' => ServiceType::Rent,
        'vehicle_id' => $vehicle->id,
    ]);

    $rentDetail = RentDetail::factory()->create([
        'booking_id' => $booking->id,
        'extension_state' => [],
    ]);

    $plainToken = GuestAccessToken::generateToken();
    GuestAccessToken::create([
        'booking_id' => $booking->id,
        'token_hash' => GuestAccessToken::hashToken($plainToken),
        'purpose' => 'guest_access',
        'expires_at' => Carbon::now('Asia/Colombo')->addDays(30),
    ]);

    $response = $this->post(route('booking.status.extension', [
        'reference' => $booking->reference,
        'token' => $plainToken,
    ]), [
        'extra_days' => 3,
        'notes' => 'Would like to extend road trip around Ella.',
    ]);

    $response->assertRedirect(route('booking.status', ['reference' => $booking->reference, 'token' => $plainToken]));

    $booking->refresh();
    $rentDetail->refresh();

    expect($booking->pending_revision)->toBeTrue();
    expect($rentDetail->extension_state)->toHaveCount(1)
        ->and($rentDetail->extension_state[0]['status'])->toBe('requested')
        ->and($rentDetail->extension_state[0]['extra_days'])->toBe(3);
});

test('terminal booking blocks proposal acceptance, change requests, and rent extensions', function (): void {
    $vehicle = Vehicle::factory()->create(['status' => VehicleStatus::Active]);
    $booking = Booking::factory()->create([
        'status' => BookingStatus::Cancelled,
        'service_type' => ServiceType::Rent,
        'vehicle_id' => $vehicle->id,
    ]);

    $proposal = Proposal::factory()->create([
        'booking_id' => $booking->id,
        'status' => ProposalStatus::Sent,
        'vehicle_id' => $vehicle->id,
        'payment_choice' => ProposalPaymentChoice::FullOrPayLater,
    ]);
    $booking->update(['current_proposal_id' => $proposal->id]);

    $plainToken = GuestAccessToken::generateToken();
    GuestAccessToken::create([
        'booking_id' => $booking->id,
        'token_hash' => GuestAccessToken::hashToken($plainToken),
        'purpose' => 'guest_access',
        'expires_at' => Carbon::now('Asia/Colombo')->addDays(30),
    ]);

    // 1. Accept proposal on terminal booking fails gracefully
    $acceptResponse = $this->post(route('booking.status.accept', [
        'reference' => $booking->reference,
        'token' => $plainToken,
    ]), [
        'payment_option' => 'pay_later',
    ]);
    $acceptResponse->assertRedirect(route('booking.status', ['reference' => $booking->reference, 'token' => $plainToken]));
    $acceptResponse->assertSessionHas('error');

    // 2. Request change on terminal booking fails gracefully
    $changeResponse = $this->post(route('booking.status.change', [
        'reference' => $booking->reference,
        'token' => $plainToken,
    ]), [
        'notes' => 'Attempting to change a cancelled booking.',
    ]);
    $changeResponse->assertRedirect(route('booking.status', ['reference' => $booking->reference, 'token' => $plainToken]));
    $changeResponse->assertSessionHas('error');

    // 3. Rent extension on terminal booking fails gracefully
    $extResponse = $this->post(route('booking.status.extension', [
        'reference' => $booking->reference,
        'token' => $plainToken,
    ]), [
        'extra_days' => 2,
    ]);
    $extResponse->assertRedirect(route('booking.status', ['reference' => $booking->reference, 'token' => $plainToken]));
    $extResponse->assertSessionHas('error');
});

test('accepting revision proposal with online payment preserves Confirmed status on already-confirmed booking', function (): void {
    $vehicle = Vehicle::factory()->create(['status' => VehicleStatus::Active]);
    $booking = Booking::factory()->create([
        'status' => BookingStatus::Confirmed,
        'service_type' => ServiceType::AirportPickup,
        'vehicle_id' => $vehicle->id,
        'pending_revision' => true,
    ]);

    $proposal = Proposal::factory()->create([
        'booking_id' => $booking->id,
        'status' => ProposalStatus::Sent,
        'vehicle_id' => $vehicle->id,
        'payment_choice' => ProposalPaymentChoice::FullOnly,
        'total_amount_minor_units' => 12000,
    ]);
    $booking->update(['current_proposal_id' => $proposal->id]);

    $plainToken = GuestAccessToken::generateToken();
    GuestAccessToken::create([
        'booking_id' => $booking->id,
        'token_hash' => GuestAccessToken::hashToken($plainToken),
        'purpose' => 'guest_access',
        'expires_at' => Carbon::now('Asia/Colombo')->addDays(30),
    ]);

    $response = $this->post(route('booking.status.accept', [
        'reference' => $booking->reference,
        'token' => $plainToken,
    ]), [
        'payment_option' => 'full',
    ]);

    $response->assertRedirect(route('booking.status', ['reference' => $booking->reference, 'token' => $plainToken]));

    $booking->refresh();
    $proposal->refresh();

    // Invariant: Confirmed booking must stay Confirmed (not prematurely AwaitingPayment)
    expect($booking->status)->toBe(BookingStatus::Confirmed)
        ->and($booking->pending_revision)->toBeTrue()
        ->and($proposal->status)->toBe(ProposalStatus::Accepted);
});
