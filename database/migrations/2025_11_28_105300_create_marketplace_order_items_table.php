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
        Schema::connection('central')->create('marketplace_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('marketplace_orders')->onDelete('restrict');

            // Product Reference
            $table->foreignId('marketplace_product_id')->constrained('marketplace_products')->onDelete('restrict');
            $table->unsignedBigInteger('tenant_product_id');
            $table->unsignedBigInteger('tenant_variant_id')->nullable();
            $table->unsignedBigInteger('tenant_bundle_id')->nullable();

            // Item Details (snapshot at order time)
            $table->string('product_name');
            $table->string('product_sku');
            $table->string('variant_name')->nullable();

            // UOM & Quantity
            $table->string('uom_code'); // 'kg', 'pcs'
            $table->string('uom_name');
            $table->decimal('quantity', 15, 4);
            $table->decimal('quantity_in_base_uom', 15, 4); // For tenant inventory deduction

            // Pricing (at time of order)
            $table->decimal('unit_price', 15, 2);
            $table->decimal('tax_rate', 5, 2);
            $table->decimal('tax_amount', 15, 2);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('subtotal', 15, 2);

            // Fulfillment Status (per item)
            $table->string('fulfillment_status')->default('pending');
            // pending, confirmed, preparing, ready, delivered, cancelled

            $table->timestamps();
            $table->softDeletes();

            $table->index('order_id');
            $table->index('marketplace_product_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('central')->dropIfExists('marketplace_order_items');
    }
};
