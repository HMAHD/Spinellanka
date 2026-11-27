<?php

declare(strict_types=1);

namespace Tests\Feature\Payment;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

test('payment and document tables migration rolls back cleanly without foreign key errors', function (): void {
    // Assert all tables exist initially
    expect(Schema::hasTable('payment_transactions'))->toBeTrue()
        ->and(Schema::hasTable('payment_events'))->toBeTrue()
        ->and(Schema::hasTable('webhook_receipts'))->toBeTrue()
        ->and(Schema::hasTable('refunds'))->toBeTrue()
        ->and(Schema::hasTable('financial_documents'))->toBeTrue()
        ->and(Schema::hasTable('document_sequences'))->toBeTrue();

    // Rollback through the payment migration step (7 steps back: 1 from Phase 16 + 3 from Phase 15 + 2 from Phase 14 + 1 payment)
    $this->artisan('migrate:rollback --step=7')->assertSuccessful();

    // Assert all tables dropped in reverse dependency order
    expect(Schema::hasTable('document_sequences'))->toBeFalse()
        ->and(Schema::hasTable('financial_documents'))->toBeFalse()
        ->and(Schema::hasTable('refunds'))->toBeFalse()
        ->and(Schema::hasTable('webhook_receipts'))->toBeFalse()
        ->and(Schema::hasTable('payment_events'))->toBeFalse()
        ->and(Schema::hasTable('payment_transactions'))->toBeFalse();

    // Re-run migrate to restore test database state
    $this->artisan('migrate')->assertSuccessful();
});
