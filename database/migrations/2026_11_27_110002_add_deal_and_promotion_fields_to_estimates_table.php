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
        Schema::table('estimates', function (Blueprint $table): void {
            $table->foreignId('applied_deal_id')->nullable()->after('assumptions')->constrained('deals')->nullOnDelete();
            $table->foreignId('applied_promotion_id')->nullable()->after('applied_deal_id')->constrained('promotions')->nullOnDelete();
            $table->json('promotion_snapshot')->nullable()->after('applied_promotion_id');
        });

        Schema::table('proposals', function (Blueprint $table): void {
            $table->foreignId('applied_deal_id')->nullable()->after('snapshot_data')->constrained('deals')->nullOnDelete();
            $table->foreignId('applied_promotion_id')->nullable()->after('applied_deal_id')->constrained('promotions')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('proposals', function (Blueprint $table): void {
            $table->dropForeign(['applied_promotion_id']);
            $table->dropForeign(['applied_deal_id']);
            $table->dropColumn(['applied_deal_id', 'applied_promotion_id']);
        });

        Schema::table('estimates', function (Blueprint $table): void {
            $table->dropForeign(['applied_promotion_id']);
            $table->dropForeign(['applied_deal_id']);
            $table->dropColumn(['applied_deal_id', 'applied_promotion_id', 'promotion_snapshot']);
        });
    }
};
