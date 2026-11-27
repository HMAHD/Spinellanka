<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

test('handover, incident, and privacy migrations can be rolled back and re-applied cleanly without foreign key deadlocks', function (): void {
    // Ensure all tables are migrated
    Artisan::call('migrate');

    // Assert Phase 15 tables and columns exist
    expect(Schema::hasTable('handovers'))->toBeTrue()
        ->and(Schema::hasTable('vehicle_condition_records'))->toBeTrue()
        ->and(Schema::hasTable('condition_attachments'))->toBeTrue()
        ->and(Schema::hasTable('incidents'))->toBeTrue()
        ->and(Schema::hasTable('incident_actions'))->toBeTrue()
        ->and(Schema::hasTable('incident_financial_entries'))->toBeTrue()
        ->and(Schema::hasTable('incident_attachments'))->toBeTrue()
        ->and(Schema::hasTable('legal_holds'))->toBeTrue()
        ->and(Schema::hasTable('data_requests'))->toBeTrue()
        ->and(Schema::hasTable('data_exports'))->toBeTrue()
        ->and(Schema::hasColumn('vehicles', 'fuel_tank_capacity_litres'))->toBeTrue()
        ->and(Schema::hasColumn('bookings', 'anonymized_at'))->toBeTrue();

    // Rollback the 3 Phase 15 migration steps
    $exitCode = Artisan::call('migrate:rollback', ['--step' => 3]);
    expect($exitCode)->toBe(0);

    // Assert Phase 15 tables and added columns are dropped cleanly in reverse dependency order
    expect(Schema::hasTable('condition_attachments'))->toBeFalse()
        ->and(Schema::hasTable('vehicle_condition_records'))->toBeFalse()
        ->and(Schema::hasTable('handovers'))->toBeFalse()
        ->and(Schema::hasTable('incident_attachments'))->toBeFalse()
        ->and(Schema::hasTable('incident_financial_entries'))->toBeFalse()
        ->and(Schema::hasTable('incident_actions'))->toBeFalse()
        ->and(Schema::hasTable('incidents'))->toBeFalse()
        ->and(Schema::hasTable('data_exports'))->toBeFalse()
        ->and(Schema::hasTable('data_requests'))->toBeFalse()
        ->and(Schema::hasTable('legal_holds'))->toBeFalse()
        ->and(Schema::hasColumn('vehicles', 'fuel_tank_capacity_litres'))->toBeFalse()
        ->and(Schema::hasColumn('bookings', 'anonymized_at'))->toBeFalse();

    // Assert parent tables remain intact
    expect(Schema::hasTable('bookings'))->toBeTrue()
        ->and(Schema::hasTable('vehicles'))->toBeTrue()
        ->and(Schema::hasTable('users'))->toBeTrue();

    // Re-migrate cleanly
    $reMigrateCode = Artisan::call('migrate');
    expect($reMigrateCode)->toBe(0);

    expect(Schema::hasTable('handovers'))->toBeTrue()
        ->and(Schema::hasTable('incidents'))->toBeTrue()
        ->and(Schema::hasTable('legal_holds'))->toBeTrue()
        ->and(Schema::hasColumn('vehicles', 'fuel_tank_capacity_litres'))->toBeTrue()
        ->and(Schema::hasColumn('bookings', 'anonymized_at'))->toBeTrue();
});
