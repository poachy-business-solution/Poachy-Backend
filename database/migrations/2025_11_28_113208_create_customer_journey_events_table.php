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
        //  Comprehensive event tracking for customer journey analysis, funnel optimization, and personalization.
        Schema::connection('central')->create('customer_journey_events', function (Blueprint $table) {
            $table->id();

            // Who
            $table->foreignId('customer_id')->nullable()->constrained('marketplace_customers')->onDelete('cascade');
            $table->string('session_id'); // For guest tracking

            // What
            $table->string('event_type');
            // page_view, product_view, product_list_view, search, filter_used, 
            // add_to_cart, remove_from_cart, add_to_wishlist, 
            // checkout_started, checkout_step_completed, purchase, 
            // review_written, merchant_followed

            $table->string('event_category')->nullable(); // browsing, search, cart, checkout, post_purchase

            // Where (Context)
            $table->string('page_url')->nullable();
            $table->string('page_title')->nullable();
            $table->string('referrer_url')->nullable();

            // Related Entities
            $table->string('related_entity_type')->nullable(); // MarketplaceProduct, MarketplaceCategory, Tenant
            $table->unsignedBigInteger('related_entity_id')->nullable();
            $table->foreignId('marketplace_product_id')->nullable()->constrained('marketplace_products')->onDelete('set null');
            $table->foreignId('marketplace_category_id')->nullable()->constrained('marketplace_categories')->onDelete('set null');
            $table->string('tenant_id')->nullable();

            // Event Data
            $table->json('event_properties')->nullable();
            // For product_view: {price, category, brand}
            // For search: {query, results_count, filters}
            // For add_to_cart: {product_id, quantity, price}

            // Device & Location
            $table->string('device_type')->nullable();
            $table->string('browser')->nullable();
            $table->string('platform')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('city')->nullable();
            $table->string('county')->nullable();

            // Timing
            $table->timestamp('event_timestamp')->useCurrent();
            $table->unsignedInteger('time_on_page_seconds')->nullable(); // How long before next event

            // Session Context
            $table->uuid('session_uuid')->nullable(); // Group events into sessions
            $table->unsignedInteger('sequence_in_session')->nullable(); // Event order

            // Foreign Keys
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('set null');

            // Indexes for analytics
            $table->index(['customer_id', 'event_timestamp']);
            $table->index(['session_id', 'sequence_in_session']);
            $table->index(['event_type', 'event_timestamp']);
            $table->index(['marketplace_product_id', 'event_type']);
            $table->index(['tenant_id', 'event_timestamp']);
            $table->index('session_uuid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('central')->dropIfExists('customer_journey_events');
    }
};
