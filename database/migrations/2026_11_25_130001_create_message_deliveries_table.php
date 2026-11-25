<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('outbox_event_id')->constrained('outbox_events')->cascadeOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained('bookings')->nullOnDelete();
            $table->string('channel', 32);
            $table->string('recipient_type', 32);
            $table->string('recipient_identifier', 191);
            $table->string('template_name', 64);
            $table->string('template_version', 32)->default('v1');
            $table->json('template_params');
            $table->string('idempotency_key', 128)->unique();
            $table->string('provider', 64);
            $table->string('provider_message_id', 191)->nullable();
            $table->string('status', 32)->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->unsignedInteger('max_attempts')->default(5);
            $table->timestamp('last_attempted_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->string('last_error_code', 64)->nullable();
            $table->text('last_error_message')->nullable();
            $table->timestamps();

            $table->index(['status', 'attempts'], 'idx_deliveries_status_attempts');
            $table->index(['status', 'created_at'], 'idx_deliveries_status_created');
            $table->index(['status', 'last_attempted_at'], 'idx_deliveries_status_attempted');
            $table->index(['booking_id', 'channel'], 'idx_deliveries_booking_channel');
            $table->index(['provider', 'provider_message_id'], 'idx_deliveries_provider_message');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_deliveries');
    }
};
