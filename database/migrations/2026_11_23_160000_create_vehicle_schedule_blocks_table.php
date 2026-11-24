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
        Schema::create('vehicle_schedule_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained('vehicles')->cascadeOnDelete();
            $table->string('kind', 32);
            $table->nullableMorphs('source');
            $table->string('group_id', 36)->nullable();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->unsignedSmallInteger('buffer_minutes_before')->default(0);
            $table->unsignedSmallInteger('buffer_minutes_after')->default(0);
            $table->dateTime('effective_start');
            $table->dateTime('effective_end');
            $table->boolean('blocks_availability')->default(true);
            $table->string('status', 24)->default('active');
            $table->boolean('is_chauffeured_only')->default(false);
            $table->foreignId('driver_id')->nullable()->constrained('drivers')->nullOnDelete();
            $table->boolean('is_override')->default(false);
            $table->text('override_reason')->nullable();
            $table->foreignId('override_authorized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('expires_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(
                ['vehicle_id', 'status', 'blocks_availability', 'effective_end', 'effective_start'],
                'idx_blocks_overlap'
            );
            $table->index(
                ['driver_id', 'status', 'blocks_availability', 'effective_end', 'effective_start'],
                'idx_blocks_driver_overlap'
            );
            $table->index(['vehicle_id', 'status'], 'idx_blocks_vehicle_status');
            $table->index(['status', 'expires_at'], 'idx_blocks_status_expiry');
            $table->index(['group_id'], 'idx_blocks_group');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicle_schedule_blocks');
    }
};
