<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('airport_pickup_details', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('booking_id')->unique()->constrained('bookings')->cascadeOnDelete();
            $table->string('airport', 16)->default('CMB');
            $table->string('flight_number', 32);
            $table->dateTime('arrival_time');
            $table->string('destination_text', 500);
            $table->string('destination_place_id', 255)->nullable();
            $table->decimal('destination_lat', 10, 7)->nullable();
            $table->decimal('destination_lng', 10, 7)->nullable();
            $table->text('destination_notes')->nullable();
            $table->unsignedSmallInteger('passenger_count');
            $table->unsignedSmallInteger('luggage_count');
            $table->boolean('meet_and_greet')->default(true);
            $table->string('name_board_text', 255)->nullable();
            $table->text('waiting_instructions')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('airport_pickup_details');
    }
};
