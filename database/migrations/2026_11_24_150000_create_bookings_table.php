<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table): void {
            $table->id();
            $table->string('reference', 16)->unique();
            $table->string('service_type', 32);
            $table->string('status', 32)->index();
            $table->foreignId('vehicle_id')->constrained('vehicles')->restrictOnDelete();
            $table->foreignId('estimate_id')->nullable()->constrained('estimates')->nullOnDelete();
            $table->foreignId('schedule_block_id')->nullable()->constrained('vehicle_schedule_blocks')->nullOnDelete();
            $table->string('source', 32)->default('web');
            $table->foreignId('assigned_staff_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('currency', 3)->default('USD');
            $table->bigInteger('total_amount_minor_units')->nullable();
            $table->bigInteger('approximate_lkr_amount_minor_units')->nullable();
            $table->string('request_hash', 64);
            $table->string('confirmation_channel', 32)->nullable();
            $table->text('confirmation_notes')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->text('decline_reason')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('declined_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();

            $table->index(['service_type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
