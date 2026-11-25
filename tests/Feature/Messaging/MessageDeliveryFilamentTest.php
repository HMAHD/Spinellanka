<?php

declare(strict_types=1);

namespace Tests\Feature\Messaging;

use App\Domain\Identity\Permission;
use App\Domain\Identity\Role;
use App\Domain\Messaging\Enums\MessageChannel;
use App\Domain\Messaging\Enums\MessageDeliveryStatus;
use App\Filament\Resources\Operations\Bookings\Pages\ViewBooking;
use App\Filament\Resources\Operations\Bookings\RelationManagers\MessageDeliveriesRelationManager;
use App\Filament\Resources\Operations\MessageDeliveries\MessageDeliveryResource;
use App\Filament\Resources\Operations\MessageDeliveries\Pages\ManageMessageDeliveries;
use App\Jobs\SendMessageDeliveryJob;
use App\Models\AuditEvent;
use App\Models\Booking;
use App\Models\MessageDelivery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed();

    $this->authorizedStaff = User::create([
        'name' => 'Ops Staff',
        'email' => 'ops_user@spinellanka.test',
        'password' => Hash::make('Password123456!'),
        'is_active' => true,
        'token_version' => 1,
    ]);
    $this->authorizedStaff->assignRole(Role::Staff->value);
    $this->authorizedStaff->givePermissionTo(Permission::BookingReview->value);
    $this->authorizedStaff->givePermissionTo(Permission::BookingConfirm->value);

    $this->unauthorizedUser = User::create([
        'name' => 'Regular User',
        'email' => 'regular@spinellanka.test',
        'password' => Hash::make('Password123456!'),
        'is_active' => true,
        'token_version' => 1,
    ]);
});

test('authorized staff can view message deliveries table with masked recipients', function (): void {
    $booking = Booking::factory()->create(['reference' => 'BKG-VIEW-123']);

    $waDelivery = MessageDelivery::factory()->create([
        'booking_id' => $booking->id,
        'channel' => MessageChannel::WhatsApp,
        'recipient_identifier' => '+447700900123',
        'status' => MessageDeliveryStatus::Sent,
    ]);

    $emailDelivery = MessageDelivery::factory()->email()->create([
        'booking_id' => $booking->id,
        'recipient_identifier' => 'john.doe@example.com',
        'status' => MessageDeliveryStatus::Sent,
    ]);

    $this->actingAs($this->authorizedStaff);

    Livewire::test(ManageMessageDeliveries::class)
        ->assertCanSeeTableRecords([$waDelivery, $emailDelivery])
        ->assertSee($waDelivery->maskedRecipient())
        ->assertSee($emailDelivery->maskedRecipient());
});

test('staff can manually retry a failed or delivery_unknown message with audit logging', function (): void {
    Queue::fake([SendMessageDeliveryJob::class]);

    $booking = Booking::factory()->create(['reference' => 'BKG-RETRY-456']);

    $failedDelivery = MessageDelivery::factory()->create([
        'booking_id' => $booking->id,
        'status' => MessageDeliveryStatus::Failed,
        'attempts' => 3,
        'max_attempts' => 3,
    ]);

    $this->actingAs($this->authorizedStaff);

    Livewire::test(ManageMessageDeliveries::class)
        ->callTableAction('retry', $failedDelivery);

    $failedDelivery->refresh();
    expect($failedDelivery->status)->toBe(MessageDeliveryStatus::Pending)
        ->and($failedDelivery->attempts)->toBe(0)
        ->and($failedDelivery->max_attempts)->toBe(5);

    Queue::assertPushed(SendMessageDeliveryJob::class, function ($job) use ($failedDelivery) {
        return $job->messageDeliveryId === $failedDelivery->id;
    });

    // Check audit event
    $audit = AuditEvent::where('action', 'message_delivery.manually_retried')
        ->where('metadata->message_delivery_id', $failedDelivery->id)
        ->first();

    expect($audit)->not->toBeNull()
        ->and($audit->actor_id)->toBe($this->authorizedStaff->id);
});

test('retry action is hidden for sent deliveries', function (): void {
    $sentDelivery = MessageDelivery::factory()->sent()->create();

    $this->actingAs($this->authorizedStaff);

    Livewire::test(ManageMessageDeliveries::class)
        ->assertTableActionHidden('retry', $sentDelivery);
});

test('unauthorized user cannot view message deliveries resource', function (): void {
    $this->actingAs($this->unauthorizedUser);

    expect(MessageDeliveryResource::canViewAny())->toBeFalse();
});

test('authorized staff can view message deliveries relation manager on booking', function (): void {
    $booking = Booking::factory()->create(['reference' => 'BKG-REL-999']);
    $delivery = MessageDelivery::factory()->create([
        'booking_id' => $booking->id,
        'channel' => MessageChannel::WhatsApp,
        'recipient_identifier' => '+94770009999',
        'status' => MessageDeliveryStatus::Sent,
    ]);

    $this->actingAs($this->authorizedStaff);

    Livewire::test(MessageDeliveriesRelationManager::class, [
        'ownerRecord' => $booking,
        'pageClass' => ViewBooking::class,
    ])
        ->assertCanSeeTableRecords([$delivery])
        ->assertSee($delivery->maskedRecipient());
});
