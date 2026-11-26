<?php

declare(strict_types=1);

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
        Schema::create('proposals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->string('reference', 32);
            $table->string('status', 32)->default('draft');
            $table->foreignId('vehicle_id')->constrained('vehicles');
            $table->string('service_type', 32);
            $table->foreignId('schedule_block_id')->nullable()->constrained('vehicle_schedule_blocks')->nullOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('currency', 3)->default('EUR');
            $table->unsignedInteger('total_amount_minor_units');
            $table->unsignedBigInteger('approximate_lkr_amount_minor_units');
            $table->boolean('deposit_required')->default(false);
            $table->unsignedInteger('deposit_amount_minor_units')->default(0);
            $table->string('payment_choice', 32);
            $table->string('selected_payment_choice', 32)->nullable();
            $table->dateTime('expires_at');
            $table->text('staff_notes')->nullable();
            $table->text('internal_notes')->nullable();
            $table->foreignId('sent_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('sent_at')->nullable();
            $table->dateTime('responded_at')->nullable();
            $table->string('response_action', 32)->nullable();
            $table->text('response_notes')->nullable();
            $table->json('snapshot_data');
            $table->timestamps();

            $table->unique(['booking_id', 'version']);
            $table->index('reference');
            $table->index(['status', 'expires_at']);
        });

        Schema::create('proposal_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proposal_id')->constrained('proposals')->cascadeOnDelete();
            $table->string('component_type', 32);
            $table->string('description', 255);
            $table->decimal('quantity', 10, 2)->default(1.00);
            $table->string('unit', 32);
            $table->unsignedInteger('unit_price_minor_units');
            $table->unsignedInteger('total_amount_minor_units');
            $table->boolean('is_deposit')->default(false);
            $table->boolean('is_tax')->default(false);
            $table->boolean('is_discount')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['proposal_id', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('proposal_items');
        Schema::dropIfExists('proposals');
    }
};
