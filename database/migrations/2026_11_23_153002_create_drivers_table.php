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
        Schema::create('drivers', function (Blueprint $table) {
            $table->id();
            $table->string('public_name');
            $table->string('full_name');
            $table->string('phone', 50);
            $table->string('whatsapp', 50)->nullable();
            $table->string('portrait_path')->nullable();
            $table->json('public_trust_facts')->nullable();
            $table->string('license_number', 100)->nullable();
            $table->date('license_expiry')->nullable();
            $table->string('status', 30)->default('active');
            $table->text('internal_notes')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('status', 'idx_drivers_status');
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'])) {
            DB::statement("ALTER TABLE drivers ADD CONSTRAINT chk_drivers_status CHECK (status IN ('active', 'inactive', 'on_leave'))");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('drivers');
    }
};

