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
        Schema::create('payment_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->restrictOnDelete();
            $table->foreignId('proposal_id')->nullable()->constrained('proposals')->nullOnDelete();
            $table->string('reference', 64)->unique();
            $table->string('gateway_provider', 32);
            $table->string('gateway_transaction_id', 128)->nullable()->index();
            $table->string('payment_method', 32);
            $table->string('payment_type', 32);
            $table->string('status', 32)->index();
            $table->bigInteger('amount_minor_units'); // Signed to support compensating entries
            $table->string('currency', 3);
            $table->string('idempotency_key', 128)->unique();
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->dateTime('settled_at')->nullable();
            $table->timestamps();

            $table->index(['booking_id', 'status']);
        });

        Schema::create('payment_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_transaction_id')->nullable()->constrained('payment_transactions')->nullOnDelete();
            $table->string('provider', 32);
            $table->string('provider_event_id', 128)->unique();
            $table->string('event_type', 64);
            $table->json('payload');
            $table->string('status', 32)->index();
            $table->text('error_message')->nullable();
            $table->dateTime('received_at');
            $table->dateTime('processed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('webhook_receipts', function (Blueprint $table): void {
            $table->id();
            $table->string('provider', 32)->index();
            $table->string('event_id', 128)->index(); // Indexed, not unique to permit gateway retries
            $table->boolean('signature_valid')->default(false);
            $table->string('source_ip', 45)->nullable();
            $table->json('payload_summary')->nullable();
            $table->dateTime('received_at');
            $table->timestamps();
        });

        Schema::create('refunds', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_transaction_id')->constrained('payment_transactions')->restrictOnDelete();
            $table->foreignId('booking_id')->constrained('bookings')->restrictOnDelete();
            $table->string('reference', 64)->unique();
            $table->bigInteger('amount_minor_units');
            $table->string('currency', 3);
            $table->text('reason');
            $table->string('status', 32)->index();
            $table->string('gateway_refund_id', 128)->nullable()->index();
            $table->foreignId('approved_by_user_id')->constrained('users')->restrictOnDelete();
            $table->dateTime('processed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('financial_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->restrictOnDelete();
            $table->foreignId('proposal_id')->nullable()->constrained('proposals')->nullOnDelete();
            $table->foreignId('payment_transaction_id')->nullable()->constrained('payment_transactions')->nullOnDelete();
            $table->string('document_type', 32)->index();
            $table->string('document_number', 64)->unique();
            $table->unsignedInteger('version')->default(1);
            $table->bigInteger('amount_minor_units');
            $table->string('currency', 3);
            $table->string('file_path')->nullable();
            $table->string('checksum', 64)->nullable();
            $table->json('metadata')->nullable();
            $table->dateTime('issued_at');
            $table->timestamps();

            $table->index(['booking_id', 'document_type']);
        });

        Schema::create('document_sequences', function (Blueprint $table): void {
            $table->id();
            $table->string('document_type', 32);
            $table->unsignedSmallInteger('year');
            $table->unsignedBigInteger('last_number')->default(0);
            $table->timestamps();

            $table->unique(['document_type', 'year']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_sequences');
        Schema::dropIfExists('financial_documents');
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('webhook_receipts');
        Schema::dropIfExists('payment_events');
        Schema::dropIfExists('payment_transactions');
    }
};
