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
        Schema::create('vehicle_driver_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained('vehicles')->restrictOnDelete();
            $table->foreignId('driver_id')->constrained('drivers')->restrictOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at')->nullable();
            $table->boolean('is_permanent')->default(false);
            $table->string('reason')->nullable();
            $table->foreignId('assigned_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Indexes
            $table->index(['vehicle_id', 'starts_at', 'ends_at'], 'idx_vda_vehicle_window');
            $table->index(['driver_id', 'starts_at', 'ends_at'], 'idx_vda_driver_window');
            $table->index(['vehicle_id', 'is_permanent', 'ends_at'], 'idx_vda_vehicle_perm');
            $table->index(['driver_id', 'is_permanent', 'ends_at'], 'idx_vda_driver_perm');
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'])) {
            DB::statement('ALTER TABLE vehicle_driver_assignments ADD CONSTRAINT chk_vda_valid_interval CHECK (ends_at IS NULL OR ends_at > starts_at)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicle_driver_assignments');
    }
};
