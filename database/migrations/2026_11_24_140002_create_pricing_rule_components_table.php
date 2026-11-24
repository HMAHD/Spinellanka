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
        Schema::create('pricing_rule_components', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pricing_rule_id')->constrained('pricing_rules')->cascadeOnDelete();
            $table->string('component_type', 30);
            $table->string('unit', 30);
            $table->bigInteger('amount_minor_units');
            $table->string('currency', 3)->default('USD');
            $table->decimal('min_units', 10, 2)->unsigned()->nullable();
            $table->decimal('max_units', 10, 2)->unsigned()->nullable();
            $table->boolean('is_taxable')->default(false);
            $table->boolean('is_deposit')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['pricing_rule_id', 'component_type'], 'idx_components_rule');
        });

        // MariaDB and MySQL CHECK constraint ensuring security deposits are not taxable trust liabilities
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE pricing_rule_components ADD CONSTRAINT chk_prc_deposit_not_taxable CHECK (NOT (is_deposit = 1 AND is_taxable = 1))');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pricing_rule_components');
    }
};
