<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_contacts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->string('type', 32);
            $table->string('first_name', 128);
            $table->string('last_name', 128);
            $table->string('phone', 32);
            $table->boolean('is_whatsapp')->default(true);
            $table->string('email', 255)->nullable();
            $table->boolean('marketing_consent')->default(false);
            $table->timestamps();

            $table->unique(['booking_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_contacts');
    }
};
