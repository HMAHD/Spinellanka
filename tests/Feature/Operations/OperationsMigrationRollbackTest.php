<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

test('driver operations migrations can be rolled back and re-applied cleanly without foreign key deadlocks', function (): void {
    // Ensure all tables are migrated
    Artisan::call('migrate');

    // Assert Phase 14 tables and columns exist
    expect(Schema::hasTable('driver_assignments'))->toBeTrue();
    expect(Schema::hasTable('driver_access_tokens'))->toBeTrue();
    expect(Schema::hasTable('driver_trip_updates'))->toBeTrue();
    expect(Schema::hasColumn('bookings', 'assigned_driver_id'))->toBeTrue();
    expect(Schema::hasColumn('bookings', 'current_driver_assignment_id'))->toBeTrue();

    // Rollback the 2 operations migration steps
    $exitCode = Artisan::call('migrate:rollback', ['--step' => 2]);
    expect($exitCode)->toBe(0);

    // Assert Phase 14 tables and columns are dropped cleanly
    expect(Schema::hasTable('driver_trip_updates'))->toBeFalse();
    expect(Schema::hasTable('driver_access_tokens'))->toBeFalse();
    expect(Schema::hasTable('driver_assignments'))->toBeFalse();
    expect(Schema::hasColumn('bookings', 'assigned_driver_id'))->toBeFalse();
    expect(Schema::hasColumn('bookings', 'current_driver_assignment_id'))->toBeFalse();

    // Assert parent booking and fleet tables remain intact
    expect(Schema::hasTable('bookings'))->toBeTrue();
    expect(Schema::hasTable('drivers'))->toBeTrue();
    expect(Schema::hasTable('vehicles'))->toBeTrue();

    // Re-migrate cleanly
    $reMigrateCode = Artisan::call('migrate');
    expect($reMigrateCode)->toBe(0);

    expect(Schema::hasTable('driver_assignments'))->toBeTrue();
    expect(Schema::hasTable('driver_access_tokens'))->toBeTrue();
    expect(Schema::hasTable('driver_trip_updates'))->toBeTrue();
});
