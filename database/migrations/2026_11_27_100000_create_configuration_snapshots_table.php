<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configuration_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('version_number')->unique();
            $table->string('schema_version', 16);
            $table->string('package_classification', 32);
            $table->string('source_revision', 64);
            $table->string('checksum_sha256', 64);
            $table->json('record_counts');
            $table->longText('payload_json');
            $table->boolean('is_active')->default(false);
            $table->foreignId('applied_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('applied_at')->nullable();
            $table->foreignId('rolled_back_from_id')->nullable()->constrained('configuration_snapshots')->nullOnDelete();
            $table->string('rollback_reason', 255)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'version_number'], 'idx_config_snapshots_active_version');
            $table->index('package_classification', 'idx_config_snapshots_classification');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('configuration_snapshots');
    }
};
