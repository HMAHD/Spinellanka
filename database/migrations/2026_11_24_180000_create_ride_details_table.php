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
        Schema::create('ride_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->unique()->constrained('bookings')->cascadeOnDelete();
            $table->dateTime('pickup_time');
            $table->string('origin_text', 500);
            $table->decimal('origin_latitude', 10, 7)->nullable();
            $table->decimal('origin_longitude', 10, 7)->nullable();
            $table->text('origin_notes')->nullable();
            $table->string('destination_text', 500);
            $table->decimal('destination_latitude', 10, 7)->nullable();
            $table->decimal('destination_longitude', 10, 7)->nullable();
            $table->text('destination_notes')->nullable();
            $table->boolean('has_return')->default(false);
            $table->dateTime('return_pickup_time')->nullable();
            $table->string('return_origin_text', 500)->nullable();
            $table->string('return_destination_text', 500)->nullable();
            $table->text('return_notes')->nullable();
            $table->unsignedSmallInteger('passenger_count');
            $table->unsignedSmallInteger('luggage_count');
            $table->unsignedSmallInteger('total_waiting_minutes')->default(0);
            $table->decimal('estimated_outbound_distance_km', 8, 2)->nullable();
            $table->unsignedSmallInteger('estimated_outbound_duration_minutes')->nullable();
            $table->decimal('estimated_return_distance_km', 8, 2)->nullable();
            $table->unsignedSmallInteger('estimated_return_duration_minutes')->nullable();
            $table->json('route_map_data')->nullable();
            $table->timestamps();

            $table->index(['pickup_time'], 'idx_ride_details_pickup_time');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ride_details');
    }
};
