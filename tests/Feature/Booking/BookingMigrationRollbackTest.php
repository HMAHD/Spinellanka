<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

test('booking migrations can be completely rolled back and re-applied without constraint failures', function (): void {
    // Ensure all tables are migrated in this connection
    Artisan::call('migrate');

    // Assert booking tables exist
    expect(Schema::hasTable('guest_access_tokens'))->toBeTrue();
    expect(Schema::hasTable('booking_policy_acceptances'))->toBeTrue();
    expect(Schema::hasTable('airport_transfer_details'))->toBeTrue();
    expect(Schema::hasTable('airport_pickup_details'))->toBeTrue();
    expect(Schema::hasTable('booking_contacts'))->toBeTrue();
    expect(Schema::hasTable('bookings'))->toBeTrue();

    // Rollback the 6 booking migration steps
    $exitCode = Artisan::call('migrate:rollback', ['--step' => 6]);
    expect($exitCode)->toBe(0);

    // Assert all 6 tables dropped cleanly
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

    expect(Schema::hasTable('guest_access_tokens'))->toBeTrue();
    expect(Schema::hasTable('booking_policy_acceptances'))->toBeTrue();
    expect(Schema::hasTable('airport_transfer_details'))->toBeTrue();
    expect(Schema::hasTable('airport_pickup_details'))->toBeTrue();
    expect(Schema::hasTable('booking_contacts'))->toBeTrue();
    expect(Schema::hasTable('bookings'))->toBeTrue();
});
