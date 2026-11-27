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
        Schema::table('vehicles', function (Blueprint $table) {
            $table->unsignedSmallInteger('fuel_tank_capacity_litres')->default(50)->after('turnaround_buffer_minutes');
        });

        Schema::create('handovers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->restrictOnDelete();
            $table->foreignId('rent_detail_id')->constrained('rent_details')->restrictOnDelete();
            $table->foreignId('vehicle_id')->constrained('vehicles')->restrictOnDelete();
            $table->string('type', 32); // collection, return
            $table->string('status', 32)->default('scheduled'); // scheduled, documents_verified, condition_captured, signatures_captured, released, exception_approved, completed, cancelled
            $table->dateTime('scheduled_at');
            $table->dateTime('completed_at')->nullable();
            $table->foreignId('staff_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('location_type', 32); // office, airport, hotel, custom
            $table->string('location_text', 255);

            // Physical original-document inspection result (STRICTLY NO UPLOADS)
            $table->boolean('documents_inspected')->default(false);
            $table->string('inspected_doc_types', 128)->nullable(); // e.g. "passport,national_licence,idp"
            $table->string('driver_licence_country', 64)->nullable();
            $table->dateTime('driver_licence_expiry')->nullable();
            $table->boolean('idp_or_aac_verified')->default(false);
            $table->text('document_inspection_notes')->nullable();

            // Signatures (Stored as clean PNG files in private storage, never raw Base64 data in SQL)
            $table->string('customer_signature_path', 255)->nullable();
            $table->string('customer_signer_name', 128)->nullable();
            $table->dateTime('customer_signed_at')->nullable();
            $table->string('staff_signature_path', 255)->nullable();
            $table->dateTime('staff_signed_at')->nullable();

            // Release Gate & Exception
            $table->boolean('is_released')->default(false);
            $table->boolean('has_exception')->default(false);
            $table->text('exception_reason')->nullable();
            $table->foreignId('exception_authorized_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('exception_authorized_at')->nullable();

            $table->text('operational_notes')->nullable();
            $table->timestamps();

            $table->index(['booking_id', 'type'], 'idx_handovers_booking_type');
            $table->index(['vehicle_id', 'scheduled_at'], 'idx_handovers_vehicle_date');
            $table->index(['status', 'scheduled_at'], 'idx_handovers_status_scheduled');
        });

        Schema::create('vehicle_condition_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('handover_id')->constrained('handovers')->cascadeOnDelete();
            $table->string('record_type', 32); // collection, return
            $table->integer('odometer_km');
            $table->string('fuel_level', 32); // empty, quarter, half, three_quarters, full
            $table->json('accessories_checklist')->nullable(); // spare_tire, jack, tool_kit, first_aid, dash_cam
            $table->boolean('is_clean')->default(true);
            $table->boolean('smoking_detected')->default(false);
            $table->boolean('is_zero_km_verified')->default(false);
            $table->text('zero_km_reason')->nullable();
            $table->text('exterior_condition_notes')->nullable();
            $table->text('interior_condition_notes')->nullable();
            $table->dateTime('recorded_at');
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('handover_id', 'idx_cond_handover');
        });

        Schema::create('condition_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_condition_record_id')->constrained('vehicle_condition_records')->cascadeOnDelete();
            $table->string('category', 64); // odometer, fuel_gauge, exterior_scratch, exterior_dent, interior_stain, windscreen, spare_wheel, tools, other_condition
            $table->string('file_uuid', 64)->unique();
            $table->string('file_path', 255); // relative path on local disk e.g. condition_attachments/{uuid}.jpg
            $table->string('original_filename', 255);
            $table->string('mime_type', 64);
            $table->unsignedInteger('file_size_bytes');
            $table->string('caption', 128)->nullable();
            $table->string('processing_status', 32)->default('clean'); // quarantined, clean, failed, purged_retention
            $table->dateTime('captured_at');
            $table->timestamps();

            $table->index('vehicle_condition_record_id', 'idx_cond_attach_record');
            $table->index('file_uuid', 'idx_cond_attach_uuid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('condition_attachments');
        Schema::dropIfExists('vehicle_condition_records');
        Schema::dropIfExists('handovers');

        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn('fuel_tank_capacity_litres');
        });
    }
};
