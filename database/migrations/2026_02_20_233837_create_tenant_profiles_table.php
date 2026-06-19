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
        Schema::connection('central')->create('tenant_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->unique();

            // Rating Metrics - Individual averages for each rating type
            $table->decimal('average_overall_rating', 3, 2)->nullable();
            $table->decimal('average_product_quality_rating', 3, 2)->nullable();
            $table->decimal('average_delivery_rating', 3, 2)->nullable();
            $table->decimal('average_service_rating', 3, 2)->nullable();

            // Review Counts
            $table->unsignedInteger('total_reviews')->default(0);
            $table->unsignedInteger('approved_reviews')->default(0);
            $table->unsignedInteger('pending_reviews')->default(0);

            // Order Metrics
            $table->unsignedInteger('total_orders')->default(0);
            $table->unsignedInteger('completed_orders')->default(0);
            $table->decimal('total_revenue', 15, 2)->default(0);

            // Product Metrics
            $table->unsignedInteger('total_marketplace_products')->default(0);
            $table->unsignedInteger('active_marketplace_products')->default(0);

            // Tracking Timestamps
            $table->timestamp('ratings_last_calculated_at')->nullable();
            $table->timestamp('orders_last_calculated_at')->nullable();
            $table->timestamp('products_last_calculated_at')->nullable();

            $table->timestamps();

            // Foreign key
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');

            // Indexes
            $table->index(['average_overall_rating', 'approved_reviews']);
            $table->index('total_orders');
            $table->index('active_marketplace_products');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('central')->dropIfExists('tenant_profiles');
    }
};
