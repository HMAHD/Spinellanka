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
        Schema::create('exchange_rate_bases', function (Blueprint $table): void {
            $table->id();
            $table->decimal('rate', 10, 4);
            $table->string('source_note', 255);
            $table->dateTime('effective_at');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('effective_at', 'idx_erb_effective_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exchange_rate_bases');
    }
};
