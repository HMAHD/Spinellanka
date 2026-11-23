<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('extra_compatibility', function (Blueprint $table) {
            $table->id();
            $table->foreignId('extra_id')->constrained('extras')->cascadeOnDelete();
            $table->string('service_type', 50)->nullable();
            $table->string('vehicle_type', 50)->nullable();
            $table->foreignId('vehicle_id')->nullable()->constrained('vehicles')->cascadeOnDelete();
            $table->boolean('is_compatible')->default(true);
            $table->timestamps();

            $table->string('scope_key', 120)->virtualAs(
                "CONCAT(extra_id, ':', COALESCE(service_type, '*'), ':', COALESCE(vehicle_type, '*'), ':', COALESCE(vehicle_id, 0))"
            );
            $table->unique('scope_key', 'uq_extra_compat_scope');
            $table->index(['extra_id', 'service_type', 'vehicle_type'], 'idx_extra_compat_lookup');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('extra_compatibility');
    }
};
