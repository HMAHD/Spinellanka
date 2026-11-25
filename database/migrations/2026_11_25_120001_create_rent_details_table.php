<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rent_details', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('booking_id')->unique()->constrained('bookings')->cascadeOnDelete();
            $table->dateTime('pickup_datetime');
            $table->dateTime('return_datetime');
            $table->unsignedSmallInteger('duration_days');
            $table->unsignedTinyInteger('extra_hours')->default(0);
            $table->string('pickup_location_type', 32)->default('office');
            $table->string('pickup_location_text', 500);
            $table->string('pickup_place_id', 255)->nullable();
            $table->decimal('pickup_lat', 10, 7)->nullable();
            $table->decimal('pickup_lng', 10, 7)->nullable();
            $table->string('return_location_type', 32)->default('office');
            $table->string('return_location_text', 500);
            $table->string('return_place_id', 255)->nullable();
            $table->decimal('return_lat', 10, 7)->nullable();
            $table->decimal('return_lng', 10, 7)->nullable();
            $table->unsignedTinyInteger('driver_age');
            $table->string('driver_licence_country', 100);
            $table->unsignedTinyInteger('driver_licence_years');
            $table->boolean('has_idp')->default(true);
            $table->boolean('has_additional_driver')->default(false);
            $table->string('additional_driver_name', 255)->nullable();
            $table->unsignedTinyInteger('additional_driver_age')->nullable();
            $table->string('additional_driver_licence_country', 100)->nullable();
            $table->unsignedTinyInteger('additional_driver_licence_years')->nullable();
            $table->boolean('additional_driver_has_idp')->default(false);
            $table->unsignedInteger('expected_distance_km')->nullable();
            $table->unsignedInteger('included_distance_km');
            $table->unsignedInteger('security_deposit_minor_units');
            $table->unsignedInteger('insurance_excess_minor_units');
            $table->boolean('original_documents_acknowledged')->default(true);
            $table->text('special_instructions')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rent_details');
    }
};
