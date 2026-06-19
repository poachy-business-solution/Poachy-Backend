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
        Schema::connection('central')->create('shopping_cart_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('cart_id')->constrained('shopping_carts')->onDelete('cascade');
            $table->foreignId('marketplace_product_id')->constrained('marketplace_products')->onDelete('cascade');

            // Item Details
            $table->string('product_name'); // Snapshot
            $table->string('product_sku');
            $table->unsignedBigInteger('tenant_product_id');
            $table->unsignedBigInteger('tenant_variant_id')->nullable();

            // Quantity & Pricing
            $table->decimal('quantity', 15, 4);
            $table->string('uom_code');
            $table->decimal('unit_price', 15, 2); // Price when added
            $table->decimal('current_price', 15, 2)->nullable(); // Current price (for comparison)

            // Timing
            $table->timestamp('added_at')->useCurrent();
            $table->timestamp('updated_at');

            $table->index(['cart_id']);
            $table->index(['marketplace_product_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('central')->dropIfExists('shopping_cart_items');
    }
};
