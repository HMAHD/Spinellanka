<?php

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
        Schema::create('extras', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug', 100)->unique();
            $table->text('description')->nullable();
            $table->unsignedInteger('price_minor_units')->default(0);
            $table->string('currency', 3)->default('USD');
            $table->string('pricing_type', 30)->default('per_booking');
            $table->unsignedTinyInteger('max_quantity')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Indexes
            $table->index('is_active', 'idx_extras_active');
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'])) {
            DB::statement("ALTER TABLE extras ADD CONSTRAINT chk_extras_currency CHECK (currency IN ('USD', 'LKR'))");
            DB::statement("ALTER TABLE extras ADD CONSTRAINT chk_extras_pricing_type CHECK (pricing_type IN ('per_booking', 'per_day'))");
            DB::statement('ALTER TABLE extras ADD CONSTRAINT chk_extras_max_quantity CHECK (max_quantity >= 1)');
            DB::statement('ALTER TABLE extras ADD CONSTRAINT chk_extras_price_non_negative CHECK (price_minor_units >= 0)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('extras');
    }
};
