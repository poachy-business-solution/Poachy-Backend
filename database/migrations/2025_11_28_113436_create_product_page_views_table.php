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
        // Specialized table for product-specific analytics. Helps identify popular products, engagement patterns, and conversion triggers.
        Schema::connection('central')->create('product_page_views', function (Blueprint $table) {
            $table->id();

            $table->foreignId('marketplace_product_id')->constrained('marketplace_products')->onDelete('cascade');
            $table->foreignId('customer_id')->nullable()->constrained('marketplace_customers')->onDelete('set null');
            $table->string('session_id');

            // Context
            $table->string('referrer_source')->nullable(); // search, category, home, external
            $table->string('referrer_url')->nullable();
            $table->string('search_query')->nullable(); // If came from search

            // Engagement
            $table->unsignedInteger('time_spent_seconds')->default(0);
            $table->boolean('scrolled_to_description')->default(false);
            $table->boolean('scrolled_to_reviews')->default(false);
            $table->boolean('clicked_images')->default(false);
            $table->boolean('added_to_cart')->default(false);
            $table->boolean('added_to_wishlist')->default(false);

            // Device
            $table->string('device_type')->nullable();
            $table->string('browser')->nullable();

            $table->timestamp('viewed_at')->useCurrent();

            // Indexes
            $table->index(['marketplace_product_id', 'viewed_at']);
            $table->index(['customer_id', 'viewed_at']);
            $table->index('session_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('central')->dropIfExists('product_page_views');
    }
};
