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
        Schema::create('incidents', function (Blueprint $table) {
            $table->id();
            $table->string('incident_number', 64)->unique(); // INC-20260501-XXXX
            $table->foreignId('booking_id')->nullable()->constrained('bookings')->nullOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained('vehicles')->nullOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained('drivers')->nullOnDelete();
            $table->string('type', 32); // accident, breakdown, damage, theft, traffic_violation, customer_dispute, service_delay
            $table->string('severity', 32); // minor, moderate, severe, critical
            $table->string('status', 32)->default('reported'); // reported, investigating, action_required, resolved, closed
            $table->string('location_text', 255)->nullable();
            $table->dateTime('occurred_at');
            $table->dateTime('reported_at');
            $table->string('reported_by_name', 128)->nullable();
            $table->string('reported_by_role', 64)->nullable(); // customer, driver, staff, third_party
            $table->text('description');
            $table->boolean('police_report_filed')->default(false);
            $table->string('police_station', 128)->nullable();
            $table->string('police_report_number', 128)->nullable();
            $table->boolean('insurance_claim_filed')->default(false);
            $table->string('insurance_claim_reference', 128)->nullable();

            $table->integer('total_financial_impact_minor_units')->default(0);
            $table->string('currency', 3)->default('USD');

            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('resolution_summary')->nullable();
            $table->dateTime('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['booking_id', 'status'], 'idx_incidents_booking_status');
            $table->index(['vehicle_id', 'occurred_at'], 'idx_incidents_vehicle_date');
            $table->index(['status', 'severity'], 'idx_incidents_status_severity');
        });

        Schema::create('incident_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incident_id')->constrained('incidents')->cascadeOnDelete();
            $table->string('action_type', 64); // status_change, note_added, communication, financial_charge, police_followup, resolved
            $table->text('description');
            $table->foreignId('performed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('performed_at');
            $table->timestamps();

            $table->index('incident_id', 'idx_incident_actions_incident');
        });

        Schema::create('incident_financial_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incident_id')->constrained('incidents')->cascadeOnDelete();
            $table->foreignId('payment_transaction_id')->nullable()->constrained('payment_transactions')->nullOnDelete();
            $table->string('entry_type', 32); // customer_deduction, customer_charge, third_party_recovery, insurance_payout, repair_cost
            $table->string('description', 255);
            $table->integer('amount_minor_units');
            $table->string('currency', 3)->default('USD');
            $table->string('original_currency', 3)->nullable();
            $table->integer('original_amount_minor_units')->nullable();
            $table->decimal('exchange_rate', 12, 6)->nullable();
            $table->string('status', 32)->default('pending'); // pending, recorded, settled, waived
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('incident_id', 'idx_inc_fin_incident');
            $table->index('payment_transaction_id', 'idx_inc_fin_payment');
        });

        Schema::create('incident_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incident_id')->constrained('incidents')->cascadeOnDelete();
            $table->string('category', 64); // vehicle_damage, scene_photo, third_party_vehicle, repair_estimate, police_report_pdf, insurance_claim_pdf
            $table->string('file_uuid', 64)->unique();
            $table->string('file_path', 255); // relative path on local disk e.g. incident_attachments/{uuid}.[jpg|pdf]
            $table->string('original_filename', 255);
            $table->string('mime_type', 64);
            $table->unsignedInteger('file_size_bytes');
            $table->string('caption', 128)->nullable();
            $table->string('processing_status', 32)->default('clean'); // quarantined, clean, failed, purged_retention
            $table->timestamps();

            $table->index('incident_id', 'idx_incident_attach_incident');
            $table->index('file_uuid', 'idx_incident_attach_uuid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('incident_attachments');
        Schema::dropIfExists('incident_financial_entries');
        Schema::dropIfExists('incident_actions');
        Schema::dropIfExists('incidents');
    }
};
