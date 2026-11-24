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
        Schema::create('booking_holds', function (Blueprint $table) {
            $table->id();
            $table->string('hold_token_hash', 64)->unique();
            $table->foreignId('vehicle_id')->constrained('vehicles')->cascadeOnDelete();
            $table->foreignId('schedule_block_id')->constrained('vehicle_schedule_blocks')->cascadeOnDelete();
            $table->string('group_id', 36)->nullable();
            $table->string('session_id', 255)->nullable();
            $table->string('service_type', 32);
            $table->unsignedSmallInteger('hold_duration_minutes')->default(15);
            $table->string('status', 24)->default('active');
            $table->dateTime('expires_at');
            $table->dateTime('released_at')->nullable();
            $table->string('release_reason', 255)->nullable();
            $table->timestamps();

            $table->index(['status', 'expires_at'], 'idx_holds_status_expiry');
            $table->index(['vehicle_id', 'status', 'expires_at'], 'idx_holds_vehicle_status_expiry');
            $table->index(['group_id'], 'idx_holds_group');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booking_holds');
    }
};
