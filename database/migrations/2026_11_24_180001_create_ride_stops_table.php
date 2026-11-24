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
        Schema::create('ride_stops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ride_detail_id')->constrained('ride_details')->cascadeOnDelete();
            $table->unsignedSmallInteger('stop_order');
            $table->string('location_text', 255);
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->unsignedSmallInteger('planned_waiting_minutes')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['ride_detail_id', 'stop_order'], 'idx_ride_stops_detail_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ride_stops');
    }
};
