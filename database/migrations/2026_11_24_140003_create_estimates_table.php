<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('estimates', function (Blueprint $table): void {
            $table->id();
            $table->string('reference', 32)->unique();
            $table->string('service_type', 30);
            $table->foreignId('vehicle_id')->nullable()->constrained('vehicles')->nullOnDelete();
            $table->foreignId('pricing_rule_id')->nullable()->constrained('pricing_rules')->nullOnDelete();
            $table->foreignId('exchange_rate_basis_id')->nullable()->constrained('exchange_rate_bases')->nullOnDelete();
            $table->string('status', 30)->default('calculated');
            $table->boolean('is_custom_quote')->default(false);
            $table->string('custom_quote_reason', 255)->nullable();
            $table->bigInteger('subtotal_minor_units')->nullable();
            $table->bigInteger('tax_minor_units')->nullable();
            $table->bigInteger('deposit_minor_units')->nullable();
            $table->bigInteger('total_amount_minor_units')->nullable();
            $table->string('currency', 3)->default('USD');
            $table->bigInteger('approximate_lkr_amount_minor_units')->nullable();
            $table->unsignedInteger('rule_version')->default(1);
            $table->json('assumptions')->nullable();
            $table->dateTime('expires_at');
            $table->timestamps();

            $table->index(['reference', 'status'], 'idx_estimates_lookup');
            $table->index('expires_at', 'idx_estimates_expires_at');
        });

        // Ensure custom quotes do not hold non-null total amounts (preventing free $0.00 checkouts)
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE estimates ADD CONSTRAINT chk_est_custom_quote_null_amounts CHECK ((is_custom_quote = 1 AND total_amount_minor_units IS NULL) OR (is_custom_quote = 0 AND total_amount_minor_units IS NOT NULL))');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('estimates');
    }
};
