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
        Schema::connection('central')->create('merchant_reviews', function (Blueprint $table) {
            $table->id();

            $table->string('tenant_id');
            $table->foreignId('customer_id')->constrained('marketplace_customers')->onDelete('cascade');
            $table->foreignId('order_id')->constrained('marketplace_orders')->onDelete('cascade');

            // Rating Breakdown
            $table->decimal('overall_rating', 2, 1); // 1-5
            $table->decimal('product_quality_rating', 2, 1)->nullable();
            $table->decimal('delivery_rating', 2, 1)->nullable();
            $table->decimal('service_rating', 2, 1)->nullable();

            // Review
            $table->text('review_text')->nullable();

            // Moderation
            $table->string('status')->default('pending');
            $table->timestamp('moderated_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');

            $table->unique(['order_id']);

            $table->index(['tenant_id', 'status']);
            $table->index(['customer_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('central')->dropIfExists('merchant_reviews');
    }
};
