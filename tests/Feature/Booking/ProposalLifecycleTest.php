<?php

declare(strict_types=1);

use App\Domain\Availability\Enums\ScheduleBlockKind;
use App\Domain\Availability\Enums\ScheduleBlockStatus;
use App\Domain\Availability\Services\AvailabilityService;
use App\Domain\Booking\DTOs\CreateProposalData;
use App\Domain\Booking\Enums\BookingStatus;
use App\Domain\Booking\Enums\ConfirmationChannel;
use App\Domain\Booking\Enums\ProposalPaymentChoice;
use App\Domain\Booking\Enums\ProposalStatus;
use App\Domain\Booking\Services\BookingLifecycleService;
use App\Domain\Booking\Services\ProposalService;
use App\Domain\Booking\ServiceType;
use App\Domain\Catalog\Enums\VehicleStatus;
use App\Domain\Identity\Permission;
use App\Domain\Pricing\DTOs\EstimateItemData;
use App\Domain\Pricing\Enums\PricingComponentType;
use App\Domain\Pricing\Enums\PricingUnit;
use App\Domain\Pricing\ValueObjects\Money;
use App\Models\Booking;
use App\Models\Proposal;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed();
    $this->proposalService = app(ProposalService::class);
    $this->lifecycleService = app(BookingLifecycleService::class);
    $this->availabilityService = app(AvailabilityService::class);

    $this->staff = User::create([
        'name' => 'Dispatch Lead',
        'email' => 'lead@spinellanka.test',
        'password' => Hash::make('Password123456!'),
        'is_active' => true,
        'token_version' => 1,
    ]);
    $this->staff->givePermissionTo(Permission::BookingReview->value);
    $this->staff->givePermissionTo(Permission::BookingConfirm->value);
});

test('proposal creation, versioning, reference generation, and draft state', function (): void {
    $vehicle = Vehicle::factory()->create(['status' => VehicleStatus::Active]);
    $booking = Booking::factory()->create([
        'status' => BookingStatus::UnderReview,
        'vehicle_id' => $vehicle->id,
        'service_type' => ServiceType::AirportPickup,
    ]);

    $startsAt = Carbon::now('Asia/Colombo')->addDays(3)->setTime(10, 0);
    $endsAt = (clone $startsAt)->addHours(3);

    $data = new CreateProposalData(
        vehicleId: $vehicle->id,
        serviceType: ServiceType::AirportPickup,
        startsAt: $startsAt,
        endsAt: $endsAt,
        currency: 'EUR',
        totalAmountMinorUnits: 8500,
        approximateLkrAmountMinorUnits: 2750000,
        depositRequired: false,
        depositAmountMinorUnits: 0,
        paymentChoice: ProposalPaymentChoice::FullOrPayLater,
        expiresAt: Carbon::now('Asia/Colombo')->addHours(48),
        items: [
            new EstimateItemData(
                componentType: PricingComponentType::Base,
                description: 'Airport Meet & Greet Base Transfer',
                unit: PricingUnit::Flat,
                quantity: 1.0,
                unitPrice: Money::of(8500, 'EUR'),
                totalAmount: Money::of(8500, 'EUR'),
                sortOrder: 1
            ),
        ],
        snapshotData: [
            'service_type' => 'airport_pickup',
            'flight_number' => 'UL-504',
        ],
        staffNotes: 'English-speaking executive chauffeur confirmed.',
        internalNotes: 'Standard 20% margin preserved.'
    );

    $proposal = $this->proposalService->createDraft($booking, $data, $this->staff);

    expect($proposal)->toBeInstanceOf(Proposal::class)
        ->and($proposal->version)->toBe(1)
        ->and($proposal->reference)->toBe("PROP-{$booking->reference}-V1")
        ->and($proposal->status)->toBe(ProposalStatus::Draft)
        ->and($proposal->items)->toHaveCount(1)
        ->and($proposal->items->first()->description)->toBe('Airport Meet & Greet Base Transfer')
        ->and($proposal->items->first()->sort_order)->toBe(1);

    // Verify privacy: internal_notes is hidden from array serialization
    $serialized = $proposal->toArray();
    expect(array_key_exists('internal_notes', $serialized))->toBeFalse();
});

test('sending proposal creates ProposalHold block and transitions booking to ProposalSent', function (): void {
    $vehicle = Vehicle::factory()->create(['status' => VehicleStatus::Active]);
    $booking = Booking::factory()->create([
        'status' => BookingStatus::UnderReview,
        'vehicle_id' => $vehicle->id,
        'service_type' => ServiceType::AirportPickup,
    ]);

    $startsAt = Carbon::now('Asia/Colombo')->addDays(3)->setTime(10, 0);
    $endsAt = (clone $startsAt)->addHours(3);

    $data = new CreateProposalData(
        vehicleId: $vehicle->id,
        serviceType: ServiceType::AirportPickup,
        startsAt: $startsAt,
        endsAt: $endsAt,
        currency: 'EUR',
        totalAmountMinorUnits: 8500,
        approximateLkrAmountMinorUnits: 2750000,
        depositRequired: false,
        depositAmountMinorUnits: 0,
        paymentChoice: ProposalPaymentChoice::FullOrPayLater,
        expiresAt: Carbon::now('Asia/Colombo')->addHours(48),
        items: [
            new EstimateItemData(
                componentType: PricingComponentType::Base,
                description: 'Airport Transfer Fare',
                unit: PricingUnit::Flat,
                quantity: 1.0,
                unitPrice: Money::of(8500, 'EUR'),
                totalAmount: Money::of(8500, 'EUR'),
                sortOrder: 1
            ),
        ],
        snapshotData: ['service_type' => 'airport_pickup'],
        staffNotes: 'All tolls included.'
    );

    $draft = $this->proposalService->createDraft($booking, $data, $this->staff);
    $sent = $this->proposalService->sendProposal($draft, $this->staff);

    expect($sent->status)->toBe(ProposalStatus::Sent)
        ->and($sent->schedule_block_id)->not->toBeNull()
        ->and($sent->scheduleBlock->kind)->toBe(ScheduleBlockKind::ProposalHold)
        ->and($sent->scheduleBlock->status)->toBe(ScheduleBlockStatus::Active);

    $booking->refresh();
    expect($booking->status)->toBe(BookingStatus::ProposalSent)
        ->and($booking->current_proposal_id)->toBe($sent->id);
});

test('self-conflict prevention on same-vehicle revision allows proposal hold over confirmed block', function (): void {
    $vehicle = Vehicle::factory()->create(['status' => VehicleStatus::Active]);

    $start = Carbon::now('Asia/Colombo')->addDays(4)->setTime(10, 0);
    $end = (clone $start)->addHours(4);

    // Initial confirmed booking with an active confirmed schedule block
    $confirmedBlock = $this->availabilityService->createBlock(
        vehicle: $vehicle,
        kind: ScheduleBlockKind::ConfirmedBooking,
        start: $start,
        end: $end,
        actor: $this->staff
    );

    $booking = Booking::factory()->create([
        'status' => BookingStatus::Confirmed,
        'vehicle_id' => $vehicle->id,
        'schedule_block_id' => $confirmedBlock->id,
        'service_type' => ServiceType::AirportPickup,
    ]);

    // Guest requests moving pickup time by 1 hour (11:00 - 15:00) on the SAME vehicle
    $revisedStart = (clone $start)->addHour();
    $revisedEnd = (clone $end)->addHour();

    $revisionData = new CreateProposalData(
        vehicleId: $vehicle->id,
        serviceType: ServiceType::AirportPickup,
        startsAt: $revisedStart,
        endsAt: $revisedEnd,
        currency: 'EUR',
        totalAmountMinorUnits: 9000,
        approximateLkrAmountMinorUnits: 2900000,
        depositRequired: false,
        depositAmountMinorUnits: 0,
        paymentChoice: ProposalPaymentChoice::FullOrPayLater,
        expiresAt: Carbon::now('Asia/Colombo')->addHours(48),
        items: [
            new EstimateItemData(
                componentType: PricingComponentType::Base,
                description: 'Amended Time Base Transfer',
                unit: PricingUnit::Flat,
                quantity: 1.0,
                unitPrice: Money::of(9000, 'EUR'),
                totalAmount: Money::of(9000, 'EUR'),
                sortOrder: 1
            ),
        ],
        snapshotData: ['service_type' => 'airport_pickup'],
        staffNotes: 'Amended pickup time by +1hr.'
    );

    // Should NOT throw ScheduleConflictException because the confirmed booking schedule block is ignored!
    $revisionProposal = $this->proposalService->reviseProposal($booking, $revisionData, $this->staff);

    expect($revisionProposal)->toBeInstanceOf(Proposal::class)
        ->and($revisionProposal->status)->toBe(ProposalStatus::Sent)
        ->and($revisionProposal->version)->toBe(1)
        ->and($revisionProposal->schedule_block_id)->not->toBeNull();

    $booking->refresh();
    // Pillar B: Booking remains Confirmed with pending_revision = true!
    expect($booking->status)->toBe(BookingStatus::Confirmed)
        ->and($booking->pending_revision)->toBeTrue()
        ->and($booking->schedule_block_id)->toBe($confirmedBlock->id);
});

test('expiring revision proposal releases hold while preserving confirmed booking (Pillar B)', function (): void {
    $vehicle = Vehicle::factory()->create(['status' => VehicleStatus::Active]);
    $start = Carbon::now('Asia/Colombo')->addDays(5)->setTime(10, 0);
    $end = (clone $start)->addHours(3);

    $confirmedBlock = $this->availabilityService->createBlock(
        vehicle: $vehicle,
        kind: ScheduleBlockKind::ConfirmedBooking,
        start: $start,
        end: $end,
        actor: $this->staff
    );

    $booking = Booking::factory()->create([
        'status' => BookingStatus::Confirmed,
        'vehicle_id' => $vehicle->id,
        'schedule_block_id' => $confirmedBlock->id,
        'pending_revision' => true,
    ]);

    $proposalHold = $this->availabilityService->createProposalHold(
        vehicle: $vehicle,
        start: (clone $start)->addHour(),
        end: (clone $end)->addHour(),
        service: ServiceType::AirportPickup,
        expiresAt: Carbon::now('Asia/Colombo')->subHour(), // Stale
        ignoreBlockId: $confirmedBlock->id,
        actor: $this->staff
    );

    $proposal = Proposal::factory()->create([
        'booking_id' => $booking->id,
        'status' => ProposalStatus::Sent,
        'vehicle_id' => $vehicle->id,
        'schedule_block_id' => $proposalHold->id,
        'expires_at' => Carbon::now('Asia/Colombo')->subHour(),
    ]);

    $booking->update(['current_proposal_id' => $proposal->id]);

    $this->proposalService->expireProposal($proposal);

    $proposal->refresh();
    $booking->refresh();
    $confirmedBlock->refresh();
    $proposalHold->refresh();

    // Proposal is expired
    expect($proposal->status)->toBe(ProposalStatus::Expired);
    // Temporary hold block is released
    expect($proposalHold->status)->toBe(ScheduleBlockStatus::Released);

    // PILLAR B GUARANTEE: Confirmed booking remains Confirmed!
    expect($booking->status)->toBe(BookingStatus::Confirmed)
        ->and($booking->pending_revision)->toBeFalse()
        ->and($booking->schedule_block_id)->toBe($confirmedBlock->id);

    // Confirmed schedule block remains Active
    expect($confirmedBlock->status)->toBe(ScheduleBlockStatus::Active);
});

test('expire stale proposals scheduled command cleans up expired proposals and holds', function (): void {
    $vehicle = Vehicle::factory()->create(['status' => VehicleStatus::Active]);
    $booking = Booking::factory()->create([
        'status' => BookingStatus::ProposalSent,
        'vehicle_id' => $vehicle->id,
    ]);

    $hold = $this->availabilityService->createProposalHold(
        vehicle: $vehicle,
        start: Carbon::now('Asia/Colombo')->addDays(2),
        end: Carbon::now('Asia/Colombo')->addDays(2)->addHours(3),
        service: ServiceType::AirportPickup,
        expiresAt: Carbon::now('Asia/Colombo')->subMinutes(30),
        actor: $this->staff
    );

    $proposal = Proposal::factory()->create([
        'booking_id' => $booking->id,
        'status' => ProposalStatus::Sent,
        'vehicle_id' => $vehicle->id,
        'schedule_block_id' => $hold->id,
        'expires_at' => Carbon::now('Asia/Colombo')->subMinutes(30),
    ]);
    $booking->update(['current_proposal_id' => $proposal->id]);

    Artisan::call('proposals:expire-stale');

    $proposal->refresh();
    $booking->refresh();
    expect($proposal->status)->toBe(ProposalStatus::Expired)
        ->and($booking->status)->toBe(BookingStatus::Expired);
});

test('sent proposals are strictly immutable against price or vehicle tampering', function (): void {
    $vehicle = Vehicle::factory()->create(['status' => VehicleStatus::Active]);
    $booking = Booking::factory()->create([
        'status' => BookingStatus::ProposalSent,
        'vehicle_id' => $vehicle->id,
    ]);

    $proposal = Proposal::factory()->create([
        'booking_id' => $booking->id,
        'status' => ProposalStatus::Sent,
        'vehicle_id' => $vehicle->id,
        'total_amount_minor_units' => 10000,
    ]);

    expect(fn () => $proposal->update(['total_amount_minor_units' => 12000]))
        ->toThrow(LogicException::class);
});

test('confirming a confirmed booking with accepted revision applies changes and confirms (Pillar B acceptance)', function (): void {
    $vehicle = Vehicle::factory()->create(['status' => VehicleStatus::Active]);
    $start = Carbon::now('Asia/Colombo')->addDays(4)->setTime(9, 0);
    $end = (clone $start)->addHours(4);

    $confirmedBlock = $this->availabilityService->createBlock(
        vehicle: $vehicle,
        kind: ScheduleBlockKind::ConfirmedBooking,
        start: $start,
        end: $end,
        actor: $this->staff
    );

    $booking = Booking::factory()->create([
        'status' => BookingStatus::Confirmed,
        'vehicle_id' => $vehicle->id,
        'schedule_block_id' => $confirmedBlock->id,
        'pending_revision' => true,
    ]);

    $proposalHold = $this->availabilityService->createProposalHold(
        vehicle: $vehicle,
        start: (clone $start)->addHours(2),
        end: (clone $end)->addHours(2),
        service: ServiceType::AirportPickup,
        expiresAt: Carbon::now('Asia/Colombo')->addHours(24),
        ignoreBlockId: $confirmedBlock->id,
        actor: $this->staff
    );

    $proposal = Proposal::factory()->create([
        'booking_id' => $booking->id,
        'status' => ProposalStatus::Accepted,
        'vehicle_id' => $vehicle->id,
        'schedule_block_id' => $proposalHold->id,
        'total_amount_minor_units' => 15000,
        'snapshot_data' => [
            'service_type' => 'airport_pickup',
            'service_details' => [],
        ],
    ]);
    $booking->update(['current_proposal_id' => $proposal->id]);

    $confirmed = app(BookingLifecycleService::class)->confirm(
        booking: $booking,
        staff: null,
        channel: ConfirmationChannel::GuestPrivateLink,
        notes: 'Guest accepted amendment via private link with Pay Later',
        proposal: $proposal
    );

    expect($confirmed->status)->toBe(BookingStatus::Confirmed)
        ->and($confirmed->pending_revision)->toBeFalse()
        ->and($confirmed->total_amount_minor_units)->toBe(15000)
        ->and($confirmed->schedule_block_id)->toBe($proposalHold->id);

    $confirmedBlock->refresh();
    $proposalHold->refresh();

    // Old confirmed block is released, proposal hold is promoted to ConfirmedBooking
    expect($confirmedBlock->status)->toBe(ScheduleBlockStatus::Released);
    expect($proposalHold->kind)->toBe(ScheduleBlockKind::ConfirmedBooking);
    expect($proposalHold->status)->toBe(ScheduleBlockStatus::Active);
});

test('confirming booking from expired proposal throws exception', function (): void {
    $vehicle = Vehicle::factory()->create(['status' => VehicleStatus::Active]);
    $booking = Booking::factory()->create([
        'status' => BookingStatus::ProposalSent,
        'vehicle_id' => $vehicle->id,
    ]);

    $proposal = Proposal::factory()->create([
        'booking_id' => $booking->id,
        'status' => ProposalStatus::Sent,
        'vehicle_id' => $vehicle->id,
        'expires_at' => Carbon::now('Asia/Colombo')->subHour(),
    ]);

    expect(fn () => app(BookingLifecycleService::class)->confirm(
        booking: $booking,
        staff: $this->staff,
        channel: ConfirmationChannel::WhatsApp,
        notes: 'Confirming expired proposal',
        proposal: $proposal
    ))->toThrow(InvalidArgumentException::class);
});

test('sending proposal for under review booking on same vehicle ignores submitted hold self-conflict', function (): void {
    $vehicle = Vehicle::factory()->create(['status' => VehicleStatus::Active]);
    $start = Carbon::now('Asia/Colombo')->addDays(3)->setTime(10, 0);
    $end = (clone $start)->addHours(3);

    $submittedHold = $this->availabilityService->createBlock(
        vehicle: $vehicle,
        kind: ScheduleBlockKind::SubmittedRequest,
        start: $start,
        end: $end,
        actor: $this->staff,
    );

    $booking = Booking::factory()->create([
        'status' => BookingStatus::UnderReview,
        'vehicle_id' => $vehicle->id,
        'service_type' => ServiceType::AirportPickup,
        'schedule_block_id' => $submittedHold->id,
    ]);

    $data = new CreateProposalData(
        vehicleId: $vehicle->id,
        serviceType: ServiceType::AirportPickup,
        startsAt: $start,
        endsAt: $end,
        currency: 'USD',
        totalAmountMinorUnits: 9500,
        approximateLkrAmountMinorUnits: 3000000,
        depositRequired: false,
        depositAmountMinorUnits: 0,
        paymentChoice: ProposalPaymentChoice::FullOrPayLater,
        expiresAt: Carbon::now('Asia/Colombo')->addHours(24),
        items: [
            new EstimateItemData(
                componentType: PricingComponentType::Base,
                description: 'Airport Meet & Greet Base Transfer',
                unit: PricingUnit::Flat,
                quantity: 1.0,
                unitPrice: Money::of(9500, 'USD'),
                totalAmount: Money::of(9500, 'USD'),
                sortOrder: 1
            ),
        ],
        snapshotData: ['service_type' => 'airport_pickup'],
        staffNotes: 'VIP greeting.'
    );

    $draft = $this->proposalService->createDraft($booking, $data, $this->staff);
    $sent = $this->proposalService->sendProposal($draft, $this->staff);

    expect($sent->status)->toBe(ProposalStatus::Sent)
        ->and($sent->schedule_block_id)->not->toBeNull()
        ->and($sent->scheduleBlock->kind)->toBe(ScheduleBlockKind::ProposalHold);
});

test('cancelling booking voids active proposal', function (): void {
    $vehicle = Vehicle::factory()->create(['status' => VehicleStatus::Active]);
    $booking = Booking::factory()->create([
        'status' => BookingStatus::ProposalSent,
        'vehicle_id' => $vehicle->id,
    ]);

    $proposal = Proposal::factory()->create([
        'booking_id' => $booking->id,
        'status' => ProposalStatus::Sent,
        'vehicle_id' => $vehicle->id,
    ]);
    $booking->update(['current_proposal_id' => $proposal->id]);

    $this->lifecycleService->cancel($booking, 'Travel plans changed', null);

    $booking->refresh();
    $proposal->refresh();

    expect($booking->status)->toBe(BookingStatus::Cancelled);
    expect($proposal->status)->toBe(ProposalStatus::Superseded);
});

test('declining booking voids active proposal', function (): void {
    $vehicle = Vehicle::factory()->create(['status' => VehicleStatus::Active]);
    $booking = Booking::factory()->create([
        'status' => BookingStatus::ProposalSent,
        'vehicle_id' => $vehicle->id,
    ]);

    $proposal = Proposal::factory()->create([
        'booking_id' => $booking->id,
        'status' => ProposalStatus::Sent,
        'vehicle_id' => $vehicle->id,
    ]);
    $booking->update(['current_proposal_id' => $proposal->id]);

    $this->lifecycleService->decline($booking, $this->staff, 'Requested dates are fully booked');

    $booking->refresh();
    $proposal->refresh();

    expect($booking->status)->toBe(BookingStatus::Declined);
    expect($proposal->status)->toBe(ProposalStatus::Declined);
});
