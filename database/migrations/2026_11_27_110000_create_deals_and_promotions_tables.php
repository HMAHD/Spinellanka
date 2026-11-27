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
        Schema::create('promotions', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('code')->nullable()->unique();
            $table->text('explanation');
            $table->string('scope')->default('global'); // global, service, deal
            $table->string('service_type')->nullable(); // airport_pickup, airport_transfer, ride, rent
            $table->unsignedBigInteger('deal_id')->nullable();
            $table->string('discount_type'); // fixed_amount, percentage_basis_points
            $table->unsignedInteger('discount_value'); // minor units for fixed, basis points (1000 = 10%) for percentage
            $table->string('currency', 3)->nullable()->default('USD');
            $table->string('status')->default('draft'); // draft, active, archived
            $table->integer('priority')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['scope', 'service_type', 'deal_id']);
            $table->index(['status', 'starts_at', 'ends_at']);
            $table->index('priority');
        });

        Schema::create('deals', function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();
            $table->string('service_type'); // airport_pickup, airport_transfer, ride, rent
            $table->string('title');
            $table->text('summary');
            $table->longText('description_markdown');
            $table->json('preset_payload'); // Service-specific booking criteria defaults
            $table->string('image_path')->nullable();
            $table->string('image_alt')->nullable();
            $table->string('image_source')->nullable();
            $table->string('image_approver')->nullable();
            $table->timestamp('image_approved_at')->nullable();
            $table->string('status')->default('draft'); // draft, published, archived
            $table->boolean('is_featured')->default(false);
            $table->integer('priority')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->unsignedBigInteger('promotion_id')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'published_at', 'expires_at']);
            $table->index('service_type');
            $table->index(['is_featured', 'priority']);
        });

        Schema::table('promotions', function (Blueprint $table): void {
            $table->foreign('deal_id')->references('id')->on('deals')->cascadeOnDelete();
        });

        Schema::table('deals', function (Blueprint $table): void {
            $table->foreign('promotion_id')->references('id')->on('promotions')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('deals', function (Blueprint $table): void {
            $table->dropForeign(['promotion_id']);
        });

        Schema::table('promotions', function (Blueprint $table): void {
            $table->dropForeign(['deal_id']);
        });

        Schema::dropIfExists('deals');
        Schema::dropIfExists('promotions');
    }
};
