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
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // e.g., "Black Friday Sale", "Buy 2 Get 1 Free"
            $table->string('code')->unique(); // Internal reference code
            $table->text('description')->nullable();
            
            // Promotion Type
            $table->string('promotion_type'); // percentage_discount(20% off), fixed_discount(kes 500 off), buy_x_get_y(buy 2 get 1 free), bundle_discount(buy combo get discount), free_shipping(online only), loyalty_bonus(loyalty points)
            
            // Discount Value
            $table->decimal('discount_value', 15, 2)->nullable(); // For percentage or fixed
            $table->integer('buy_quantity')->nullable(); // For Buy X Get Y
            $table->integer('get_quantity')->nullable(); // For Buy X Get Y
            $table->boolean('get_items_free')->default(true); // TRUE = free, FALSE = discounted
            $table->decimal('get_items_discount_percentage', 5, 2)->nullable(); // If not free
            
            // Conditions
            $table->decimal('min_purchase_amount', 15, 2)->nullable();
            $table->decimal('max_discount_amount', 15, 2)->nullable(); // Cap for percentage discounts
            $table->integer('max_uses_per_customer')->nullable();
            $table->integer('total_usage_limit')->nullable();
            $table->integer('total_usage_count')->default(0);
            
            // Validity
            $table->timestamp('start_date');
            $table->timestamp('end_date');
            $table->json('active_days')->nullable(); // ["monday", "friday"] - specific days only
            $table->time('active_time_start')->nullable(); // e.g., 18:00 (happy hour)
            $table->time('active_time_end')->nullable(); // e.g., 20:00
            
            // Applicability
            $table->json('applicable_store_ids')->nullable(); // [1, 3, 5] or NULL = all stores
            $table->json('applicable_customer_group_ids')->nullable(); // [2, 4] or NULL = all customers
            $table->string('applicable_to'); // all_products, specific_categories, specific_products, specific_brands
            
            // Display
            $table->boolean('show_on_website')->default(true);
            $table->boolean('show_in_pos')->default(true);
            $table->string('banner_image_url')->nullable();
            $table->integer('display_priority')->default(0); // Higher = shown first
            
            // Status
            $table->boolean('is_active')->default(true);
            $table->boolean('auto_apply')->default(true); // Automatically apply at checkout
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['start_date', 'end_date', 'is_active']);
            $table->index('code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('promotions');
    }
};
