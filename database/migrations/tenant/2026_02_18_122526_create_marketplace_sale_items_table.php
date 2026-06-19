<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_sale_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('marketplace_sale_id')
                ->constrained('marketplace_sales')
                ->onDelete('cascade');

            // Product (one of product, variant, or bundle will be set)
            $table->foreignId('product_id')->constrained('products')->onDelete('restrict');
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->onDelete('restrict');
            $table->foreignId('bundle_id')->nullable()->constrained('product_bundles')->onDelete('restrict');

            $table->foreignId('uom_id')->constrained('units_of_measure')->onDelete('restrict');

            // Quantities
            $table->decimal('quantity', 15, 4);
            $table->decimal('quantity_in_base_uom', 15, 4);

            // Pricing
            $table->decimal('unit_price', 15, 2);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('subtotal', 15, 2);

            $table->timestamps();

            $table->index('marketplace_sale_id');
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_sale_items');
    }
};
