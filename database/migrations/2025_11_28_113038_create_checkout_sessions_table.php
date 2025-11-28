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
        //  Detailed checkout funnel analysis. Identifies where customers drop off (shipping vs payment vs review).
        Schema::connection('central')->create('checkout_sessions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('cart_id')->constrained('shopping_carts')->onDelete('cascade');
            $table->foreignId('customer_id')->nullable()->constrained('marketplace_customers')->onDelete('cascade');

            // Checkout Steps (track progress)
            $table->string('current_step')->default('cart');
            // cart, shipping, payment, review, completed, abandoned

            $table->boolean('step_cart_viewed')->default(false);
            $table->timestamp('step_cart_viewed_at')->nullable();

            $table->boolean('step_shipping_viewed')->default(false);
            $table->timestamp('step_shipping_viewed_at')->nullable();
            $table->boolean('step_shipping_completed')->default(false);
            $table->timestamp('step_shipping_completed_at')->nullable();

            $table->boolean('step_payment_viewed')->default(false);
            $table->timestamp('step_payment_viewed_at')->nullable();
            $table->boolean('step_payment_attempted')->default(false);
            $table->timestamp('step_payment_attempted_at')->nullable();

            $table->boolean('step_review_viewed')->default(false);
            $table->timestamp('step_review_viewed_at')->nullable();

            // Completion
            $table->boolean('is_completed')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_order_id')->nullable()->constrained('marketplace_orders')->onDelete('set null');

            // Abandonment
            $table->boolean('is_abandoned')->default(false);
            $table->timestamp('abandoned_at')->nullable();
            $table->string('abandoned_at_step')->nullable(); // Where they left

            // Metadata
            $table->string('device_type')->nullable();
            $table->string('browser')->nullable();
            $table->json('abandonment_reasons')->nullable(); // Exit surveys, heuristics

            $table->timestamps();

            $table->index(['customer_id', 'is_completed']);
            $table->index(['is_abandoned', 'abandoned_at_step']);
            $table->index(['current_step', 'updated_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('central')->dropIfExists('checkout_sessions');
    }
};
