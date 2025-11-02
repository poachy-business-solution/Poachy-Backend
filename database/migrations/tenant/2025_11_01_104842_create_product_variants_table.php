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
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->onDelete('restrict');

            // Variant info
            $table->string('variant_name'); // e.g., "500ml",
            $table->string('sku')->unique(); // Must be unique across all variants            
            $table->json('attributes')->nullable(); // e.g., {"color": "Red", "size": "L"}

            // Variant UOM (can be different from product base UOM)
            $table->foreignId('uom_id')->constrained('units_of_measure');
            $table->decimal('uom_quantity', 15, 4); // How many of this UOM (e.g., 500g)
            $table->decimal('quantity_in_base_uom', 15, 4); // Equivalent in product's base UOM (0.5 kg)

            // Adjustments relative to base product
            $table->decimal('base_selling_price_adjustment', 15, 2)->default(0);    // e.g., -10.00 or +20.00
            $table->decimal('variant_price', 15, 2)->nullable();

            // Inventory & logistics
            $table->string('stock_status')->default('in_stock'); // in_stock, out_of_stock, discontinued
            $table->decimal('reorder_level', 15, 4)->default(0)->comment('In base UOM');
            $table->integer('shelf_life_days')->nullable(); // e.g., 365

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Indexes
            $table->index('product_id');
            $table->index('sku');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
