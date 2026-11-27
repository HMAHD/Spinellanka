<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

test('booking migrations can be completely rolled back and re-applied without constraint failures', function (): void {
    // Ensure all tables are migrated in this connection
    Artisan::call('migrate');

    // Assert offers, booking & proposal tables exist
    expect(Schema::hasTable('reviews'))->toBeTrue();
    expect(Schema::hasTable('deals'))->toBeTrue();
    expect(Schema::hasTable('promotions'))->toBeTrue();
    expect(Schema::hasTable('proposal_items'))->toBeTrue();
    expect(Schema::hasTable('proposals'))->toBeTrue();
    expect(Schema::hasTable('message_deliveries'))->toBeTrue();
    expect(Schema::hasTable('outbox_events'))->toBeTrue();
    expect(Schema::hasTable('rent_details'))->toBeTrue();
    expect(Schema::hasTable('rent_policies'))->toBeTrue();
    expect(Schema::hasTable('ride_stops'))->toBeTrue();
    expect(Schema::hasTable('ride_details'))->toBeTrue();
    expect(Schema::hasTable('guest_access_tokens'))->toBeTrue();
    expect(Schema::hasTable('booking_policy_acceptances'))->toBeTrue();
    expect(Schema::hasTable('airport_transfer_details'))->toBeTrue();
    expect(Schema::hasTable('airport_pickup_details'))->toBeTrue();
    expect(Schema::hasTable('booking_contacts'))->toBeTrue();
    expect(Schema::hasTable('bookings'))->toBeTrue();

    // Rollback the 24 offers, config-snapshot, booking, proposal, ride, rent, outbox, payment, driver operations, handover, incident & privacy migration steps
    $exitCode = Artisan::call('migrate:rollback', ['--step' => 24]);
    expect($exitCode)->toBe(0);

    // Assert all tables dropped cleanly
    expect(Schema::hasTable('reviews'))->toBeFalse();
    expect(Schema::hasTable('deals'))->toBeFalse();
    expect(Schema::hasTable('promotions'))->toBeFalse();
    expect(Schema::hasTable('proposal_items'))->toBeFalse();
    expect(Schema::hasTable('proposals'))->toBeFalse();
    expect(Schema::hasTable('message_deliveries'))->toBeFalse();
    expect(Schema::hasTable('outbox_events'))->toBeFalse();
    expect(Schema::hasTable('rent_details'))->toBeFalse();
    expect(Schema::hasTable('rent_policies'))->toBeFalse();
    expect(Schema::hasTable('ride_stops'))->toBeFalse();
    expect(Schema::hasTable('ride_details'))->toBeFalse();
    expect(Schema::hasTable('guest_access_tokens'))->toBeFalse();
    expect(Schema::hasTable('booking_policy_acceptances'))->toBeFalse();
    expect(Schema::hasTable('airport_transfer_details'))->toBeFalse();
    expect(Schema::hasTable('airport_pickup_details'))->toBeFalse();
    expect(Schema::hasTable('booking_contacts'))->toBeFalse();
    expect(Schema::hasTable('bookings'))->toBeFalse();

    // Assert pricing and availability tables still exist
    expect(Schema::hasTable('estimates'))->toBeTrue();
    expect(Schema::hasTable('vehicle_schedule_blocks'))->toBeTrue();

    // Re-apply migrations
    $migrateExit = Artisan::call('migrate');
    expect($migrateExit)->toBe(0);

    expect(Schema::hasTable('reviews'))->toBeTrue();
    expect(Schema::hasTable('deals'))->toBeTrue();
    expect(Schema::hasTable('promotions'))->toBeTrue();

    expect(Schema::hasTable('proposal_items'))->toBeTrue();
    expect(Schema::hasTable('proposals'))->toBeTrue();
    expect(Schema::hasTable('message_deliveries'))->toBeTrue();
    expect(Schema::hasTable('outbox_events'))->toBeTrue();

    expect(Schema::hasTable('rent_details'))->toBeTrue();
    expect(Schema::hasTable('rent_policies'))->toBeTrue();
    expect(Schema::hasTable('ride_stops'))->toBeTrue();
    expect(Schema::hasTable('ride_details'))->toBeTrue();
    expect(Schema::hasTable('guest_access_tokens'))->toBeTrue();
    expect(Schema::hasTable('booking_policy_acceptances'))->toBeTrue();
    expect(Schema::hasTable('airport_transfer_details'))->toBeTrue();
    expect(Schema::hasTable('airport_pickup_details'))->toBeTrue();
    expect(Schema::hasTable('booking_contacts'))->toBeTrue();
    expect(Schema::hasTable('bookings'))->toBeTrue();
});
