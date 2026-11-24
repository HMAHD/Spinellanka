<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('airport_transfer_details', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('booking_id')->unique()->constrained('bookings')->cascadeOnDelete();
            $table->string('airport', 16)->default('CMB');
            $table->string('flight_number', 32);
            $table->dateTime('departure_time');
            $table->string('origin_text', 500);
            $table->string('origin_place_id', 255)->nullable();
            $table->decimal('origin_lat', 10, 7)->nullable();
            $table->decimal('origin_lng', 10, 7)->nullable();
            $table->text('origin_notes')->nullable();
            $table->dateTime('recommended_pickup_time');
            $table->dateTime('chosen_pickup_time');
            $table->boolean('is_unsafe_time')->default(false);
            $table->boolean('warning_accepted')->default(false);
            $table->unsignedSmallInteger('passenger_count');
            $table->unsignedSmallInteger('luggage_count');
            $table->text('special_instructions')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('airport_transfer_details');
    }
};
