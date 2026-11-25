<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outbox_events', function (Blueprint $table): void {
            $table->id();
            $table->string('event_type', 64);
            $table->string('aggregate_type', 64);
            $table->unsignedBigInteger('aggregate_id');
            $table->string('idempotency_key', 128)->unique();
            $table->unsignedInteger('payload_version')->default(1);
            $table->json('payload');
            $table->string('status', 32)->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at'], 'idx_outbox_events_status_created');
            $table->index(['aggregate_type', 'aggregate_id'], 'idx_outbox_events_aggregate');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbox_events');
    }
};

