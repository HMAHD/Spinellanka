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
        Schema::create('legal_holds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->restrictOnDelete();
            $table->string('reason', 255);
            $table->text('details')->nullable();
            $table->foreignId('applied_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('applied_at');
            $table->boolean('is_active')->default(true);
            $table->foreignId('released_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('released_at')->nullable();
            $table->text('release_reason')->nullable();
            $table->timestamps();

            $table->index(['booking_id', 'is_active'], 'idx_legal_holds_booking_active');
        });

        Schema::create('data_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_reference', 64)->unique(); // REQ-20260501-XXXX
            $table->string('request_type', 32); // access_export, correction, erasure_deletion, restriction
            $table->string('subject_email', 128)->index();
            $table->string('subject_phone', 32)->nullable();
            $table->string('subject_name', 128)->nullable();
            $table->string('verification_token_hash', 64)->nullable();
            $table->dateTime('verification_expires_at')->nullable();
            $table->string('status', 32)->default('pending_verification'); // pending_verification, verified, in_progress, completed, rejected
            $table->dateTime('verified_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('processed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'created_at'], 'idx_data_requests_status');
        });

        Schema::create('data_exports', function (Blueprint $table) {
            $table->id();
            $table->string('export_uuid', 64)->unique();
            $table->foreignId('requested_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('purpose', 255);
            $table->string('scope', 64); // customer_booking_history, financial_records, operations_audit
            $table->string('file_path', 255); // relative path on local disk e.g. exports/{uuid}.csv
            $table->unsignedInteger('file_size_bytes');
            $table->dateTime('expires_at');
            $table->dateTime('downloaded_at')->nullable();
            $table->unsignedInteger('download_count')->default(0);
            $table->timestamps();

            $table->index(['requested_by_user_id', 'expires_at'], 'idx_exports_user_expires');
            $table->index('export_uuid', 'idx_exports_uuid');
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->dateTime('anonymized_at')->nullable()->after('completed_at')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex(['anonymized_at']);
            $table->dropColumn('anonymized_at');
        });

        Schema::dropIfExists('data_exports');
        Schema::dropIfExists('data_requests');
        Schema::dropIfExists('legal_holds');
    }
};
