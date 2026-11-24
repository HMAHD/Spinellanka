<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

test('availability migrations can be completely rolled back and re-applied without constraint failures', function (): void {
    // Ensure all tables are migrated in this connection
    Artisan::call('migrate');

    // Assert availability and catalog tables exist
    expect(Schema::hasTable('booking_holds'))->toBeTrue();
    expect(Schema::hasTable('vehicle_schedule_blocks'))->toBeTrue();
    expect(Schema::hasTable('vehicles'))->toBeTrue();

    // If downstream booking tables exist, roll them back first so availability can be tested in isolation
    if (Schema::hasTable('bookings')) {
        Artisan::call('migrate:rollback', ['--step' => 5]);
    }

    // If downstream pricing tables exist, roll them back first so availability can be tested in isolation
    if (Schema::hasTable('estimates')) {
        Artisan::call('migrate:rollback', ['--step' => 5]);
    }

    // Rollback the 2 availability migration steps
    $exitCode = Artisan::call('migrate:rollback', ['--step' => 2]);
    expect($exitCode)->toBe(0);

    // Verify availability tables dropped while fleet/catalog remains intact
    expect(Schema::hasTable('booking_holds'))->toBeFalse();
    expect(Schema::hasTable('vehicle_schedule_blocks'))->toBeFalse();
    expect(Schema::hasTable('vehicles'))->toBeTrue();

    // Re-run migrations
    $migrateExit = Artisan::call('migrate');
    expect($migrateExit)->toBe(0);

    // Verify availability tables exist again
    expect(Schema::hasTable('booking_holds'))->toBeTrue();
    expect(Schema::hasTable('vehicle_schedule_blocks'))->toBeTrue();
});
