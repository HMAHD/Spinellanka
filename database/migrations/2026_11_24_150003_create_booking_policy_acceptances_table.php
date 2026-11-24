<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_policy_acceptances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->string('policy_type', 64);
            $table->string('version', 32);
            $table->string('text_hash', 64);
            $table->timestamp('accepted_at');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->unique(['booking_id', 'policy_type', 'version'], 'booking_policy_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_policy_acceptances');
    }
};

