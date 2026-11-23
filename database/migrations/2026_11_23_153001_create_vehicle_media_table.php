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
        Schema::create('vehicle_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained('vehicles')->cascadeOnDelete();
            $table->string('file_path');
            $table->string('disk', 50)->default('public');
            $table->string('media_type', 50);
            $table->string('alt_text');
            $table->string('photographer')->nullable();
            $table->string('usage_rights')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_approved')->default(false);
            $table->timestamps();

            // Indexes
            $table->index(['vehicle_id', 'is_approved', 'sort_order'], 'idx_vehicle_media_sort');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicle_media');
    }
};

