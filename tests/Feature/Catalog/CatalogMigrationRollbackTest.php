<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

test('catalog migrations can be completely rolled back and re-applied without constraint failures', function (): void {
    // Ensure all tables are migrated in this connection
    Artisan::call('migrate');

    // Assert all 6 tables exist initially
    expect(Schema::hasTable('vehicles'))->toBeTrue();
    expect(Schema::hasTable('vehicle_media'))->toBeTrue();
    expect(Schema::hasTable('drivers'))->toBeTrue();
    expect(Schema::hasTable('vehicle_driver_assignments'))->toBeTrue();
    expect(Schema::hasTable('extras'))->toBeTrue();
    expect(Schema::hasTable('extra_compatibility'))->toBeTrue();

    // Rollback any dependent downstream migrations (booking, pricing, and availability) first if present
    while (Schema::hasTable('bookings')) {
        Artisan::call('migrate:rollback', ['--step' => 1]);
    }
    if (Schema::hasTable('estimates')) {
        Artisan::call('migrate:rollback', ['--step' => 5]);
    }
    if (Schema::hasTable('booking_holds') || Schema::hasTable('vehicle_schedule_blocks')) {
        Artisan::call('migrate:rollback', ['--step' => 2]);
    }

    // Rollback the 6 catalog migration steps
    $exitCode = Artisan::call('migrate:rollback', ['--step' => 6]);
    expect($exitCode)->toBe(0);

    // Verify all 6 catalog tables are dropped cleanly
    expect(Schema::hasTable('extra_compatibility'))->toBeFalse();
    expect(Schema::hasTable('extras'))->toBeFalse();
    expect(Schema::hasTable('vehicle_driver_assignments'))->toBeFalse();
    expect(Schema::hasTable('drivers'))->toBeFalse();
    expect(Schema::hasTable('vehicle_media'))->toBeFalse();
    expect(Schema::hasTable('vehicles'))->toBeFalse();

    // Re-run migrations
    $migrateExit = Artisan::call('migrate');
    expect($migrateExit)->toBe(0);

    // Verify all 6 catalog tables exist again
    expect(Schema::hasTable('vehicles'))->toBeTrue();
    expect(Schema::hasTable('vehicle_media'))->toBeTrue();
    expect(Schema::hasTable('drivers'))->toBeTrue();
    expect(Schema::hasTable('vehicle_driver_assignments'))->toBeTrue();
    expect(Schema::hasTable('extras'))->toBeTrue();
    expect(Schema::hasTable('extra_compatibility'))->toBeTrue();
});
