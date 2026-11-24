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
        Schema::create('estimate_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('estimate_id')->constrained('estimates')->cascadeOnDelete();
            $table->foreignId('rule_component_id')->nullable()->constrained('pricing_rule_components')->nullOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('component_type', 30);
            $table->string('description', 255);
            $table->string('unit', 30);
            $table->decimal('quantity', 10, 2)->default(1.00);
            $table->bigInteger('unit_price_minor_units');
            $table->bigInteger('total_amount_minor_units');
            $table->string('currency', 3)->default('USD');
            $table->boolean('is_deposit')->default(false);
            $table->boolean('is_tax')->default(false);
            $table->boolean('is_discount')->default(false);
            $table->timestamps();

            $table->index(['estimate_id', 'sort_order'], 'idx_estimate_items_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('estimate_items');
    }
};
