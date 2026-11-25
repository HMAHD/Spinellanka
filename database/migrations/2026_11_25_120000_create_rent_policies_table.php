<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rent_policies', function (Blueprint $table): void {
            $table->id();
            $table->unsignedSmallInteger('min_driver_age')->default(23);
            $table->unsignedSmallInteger('max_driver_age')->default(70);
            $table->unsignedSmallInteger('min_licence_years')->default(2);
            $table->boolean('idp_required')->default(true);
            $table->unsignedSmallInteger('max_additional_drivers')->default(1);
            $table->unsignedInteger('security_deposit_minor_units')->default(50000);
            $table->unsignedInteger('insurance_excess_minor_units')->default(50000);
            $table->unsignedInteger('daily_mileage_km')->default(100);
            $table->unsignedInteger('excess_mileage_rate_minor_units')->default(30);
            $table->unsignedSmallInteger('grace_period_minutes')->default(59);
            $table->unsignedSmallInteger('extra_hours_threshold')->default(5);
            $table->unsignedSmallInteger('full_day_threshold_hours')->default(6);
            $table->boolean('office_handover_available')->default(true);
            $table->boolean('airport_handover_available')->default(true);
            $table->boolean('hotel_delivery_available')->default(true);
            $table->unsignedInteger('hotel_delivery_fee_minor_units')->default(2000);
            $table->text('prohibited_roads_text')->nullable();
            $table->unsignedInteger('smoking_penalty_minor_units')->default(15000);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rent_policies');
    }
};
