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
        Schema::create('reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('booking_id')->unique()->constrained('bookings')->cascadeOnDelete();
            $table->string('traveler_name');
            $table->string('traveler_country', 2)->nullable();
            $table->string('service_type', 32);
            $table->unsignedTinyInteger('rating')->nullable(); // 1 to 5
            $table->string('title')->nullable();
            $table->text('review_text')->nullable();
            $table->string('source', 32)->default('direct'); // direct, trustpilot, google
            $table->string('external_url', 500)->nullable();
            $table->string('status', 32)->default('pending_moderation'); // pending_moderation, published, rejected
            $table->boolean('is_featured')->default(false);
            $table->boolean('verified_trip')->default(true);
            $table->string('invitation_token', 64)->nullable()->unique();
            $table->timestamp('invited_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('moderated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('moderated_at')->nullable();
            $table->text('moderation_notes')->nullable();
            $table->timestamps();

            $table->index(['status', 'is_featured']);
            $table->index('service_type');
            $table->index('rating');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
