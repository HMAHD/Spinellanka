<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->restrictOnDelete();
            $table->foreignId('driver_id')->constrained('drivers')->restrictOnDelete();
            $table->foreignId('vehicle_id')->constrained('vehicles')->restrictOnDelete();
            $table->boolean('is_replacement')->default(false);
            $table->text('replacement_reason')->nullable();
            $table->foreignId('replacement_authorized_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 32)->default('assigned');
            $table->text('issue_reported_notes')->nullable();
            $table->dateTime('issue_reported_at')->nullable();
            $table->dateTime('assigned_at');
            $table->dateTime('acknowledged_at')->nullable();
            $table->dateTime('en_route_at')->nullable();
            $table->dateTime('arrived_at')->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();

            $table->index('booking_id', 'idx_driver_assign_booking');
            $table->index(['driver_id', 'status'], 'idx_driver_assign_driver');
        });

        Schema::create('driver_access_tokens', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->foreignId('driver_assignment_id')->constrained('driver_assignments')->cascadeOnDelete();
            $table->foreignId('driver_id')->constrained('drivers')->restrictOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->dateTime('expires_at');
            $table->dateTime('revoked_at')->nullable();
            $table->string('revocation_reason', 128)->nullable();
            $table->timestamps();

            $table->index(['booking_id', 'revoked_at'], 'idx_driver_tokens_booking_active');
            $table->index(['driver_assignment_id', 'revoked_at'], 'idx_driver_tokens_assignment_active');
            $table->index('expires_at', 'idx_driver_tokens_expires');
        });

        Schema::create('driver_trip_updates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('driver_assignment_id')->constrained('driver_assignments')->cascadeOnDelete();
            $table->string('status', 32);
            $table->text('notes')->nullable();
            $table->string('client_device_timestamp', 64)->nullable();
            $table->dateTime('recorded_at');
            $table->timestamps();

            $table->index(['driver_assignment_id', 'status'], 'idx_driver_updates_assign');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_trip_updates');
        Schema::dropIfExists('driver_access_tokens');
        Schema::dropIfExists('driver_assignments');
    }
};
