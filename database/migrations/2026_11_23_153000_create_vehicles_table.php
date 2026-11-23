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
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->string('public_name');
            $table->string('internal_fleet_id', 50)->unique();
            $table->string('make', 100);
            $table->string('model', 100);
            $table->unsignedSmallInteger('year');
            $table->string('license_plate', 50)->unique();
            $table->string('vin', 100)->nullable();
            $table->date('registration_expiry')->nullable();
            $table->date('insurance_expiry')->nullable();
            $table->string('vehicle_type', 50);
            $table->unsignedTinyInteger('passenger_capacity');
            $table->unsignedTinyInteger('luggage_capacity');
            $table->string('transmission', 20);
            $table->boolean('has_ac')->default(true);
            $table->json('accessibility_features')->nullable();
            $table->json('amenities')->nullable();
            $table->boolean('eligible_for_pickup')->default(false);
            $table->boolean('eligible_for_transfer')->default(false);
            $table->boolean('eligible_for_ride')->default(false);
            $table->boolean('eligible_for_rent')->default(false);
            $table->unsignedSmallInteger('turnaround_buffer_minutes')->default(90);
            $table->string('status', 30)->default('active');
            $table->text('internal_notes')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('status', 'idx_vehicles_status');
            $table->index(['vehicle_type', 'status'], 'idx_vehicles_type_status');
            $table->index(
                ['status', 'eligible_for_pickup', 'eligible_for_transfer', 'eligible_for_ride', 'eligible_for_rent'],
                'idx_vehicles_eligibility'
            );
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'])) {
            DB::statement('ALTER TABLE vehicles ADD CONSTRAINT chk_vehicles_year CHECK (year BETWEEN 2000 AND 2100)');
            DB::statement('ALTER TABLE vehicles ADD CONSTRAINT chk_vehicles_passenger_cap CHECK (passenger_capacity BETWEEN 1 AND 50)');
            DB::statement('ALTER TABLE vehicles ADD CONSTRAINT chk_vehicles_luggage_cap CHECK (luggage_capacity BETWEEN 0 AND 50)');
            DB::statement('ALTER TABLE vehicles ADD CONSTRAINT chk_vehicles_turnaround_buffer CHECK (turnaround_buffer_minutes BETWEEN 30 AND 720)');
            DB::statement("ALTER TABLE vehicles ADD CONSTRAINT chk_vehicles_status CHECK (status IN ('active', 'maintenance', 'retired'))");
            DB::statement("ALTER TABLE vehicles ADD CONSTRAINT chk_vehicles_transmission CHECK (transmission IN ('automatic', 'manual'))");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};

