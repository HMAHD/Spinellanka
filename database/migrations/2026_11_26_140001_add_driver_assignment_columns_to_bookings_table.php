<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->foreignId('assigned_driver_id')->nullable()->after('vehicle_id')->constrained('drivers')->nullOnDelete();
            $table->foreignId('current_driver_assignment_id')->nullable()->after('assigned_driver_id')->constrained('driver_assignments')->nullOnDelete();
            $table->dateTime('completed_at')->nullable()->after('declined_at');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropForeign(['current_driver_assignment_id']);
            $table->dropForeign(['assigned_driver_id']);
            $table->dropColumn(['current_driver_assignment_id', 'assigned_driver_id', 'completed_at']);
        });
    }
};
