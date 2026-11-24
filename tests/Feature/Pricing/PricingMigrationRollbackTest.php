<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

test('pricing migrations can be completely rolled back and re-applied without constraint failures', function (): void {
    // Ensure all tables are migrated in this connection
    Artisan::call('migrate');

    // If downstream booking tables exist, roll them back first so pricing can be tested in isolation
    while (Schema::hasTable('bookings')) {
        Artisan::call('migrate:rollback', ['--step' => 1]);
    }

    // Assert pricing tables exist
    expect(Schema::hasTable('estimate_items'))->toBeTrue();
    expect(Schema::hasTable('estimates'))->toBeTrue();
    expect(Schema::hasTable('pricing_rule_components'))->toBeTrue();
    expect(Schema::hasTable('pricing_rules'))->toBeTrue();
    expect(Schema::hasTable('exchange_rate_bases'))->toBeTrue();

    // Rollback the 5 pricing migration steps
    Artisan::call('migrate:rollback', ['--step' => 5]);

    // Assert all 5 tables dropped cleanly
    expect(Schema::hasTable('estimate_items'))->toBeFalse();
    expect(Schema::hasTable('estimates'))->toBeFalse();
    expect(Schema::hasTable('pricing_rule_components'))->toBeFalse();
    expect(Schema::hasTable('pricing_rules'))->toBeFalse();
    expect(Schema::hasTable('exchange_rate_bases'))->toBeFalse();

    // Re-apply migrations
    Artisan::call('migrate');

    expect(Schema::hasTable('estimate_items'))->toBeTrue();
    expect(Schema::hasTable('estimates'))->toBeTrue();
    expect(Schema::hasTable('pricing_rule_components'))->toBeTrue();
    expect(Schema::hasTable('pricing_rules'))->toBeTrue();
    expect(Schema::hasTable('exchange_rate_bases'))->toBeTrue();
});
