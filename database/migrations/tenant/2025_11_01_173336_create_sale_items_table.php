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
        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained('sales')->onDelete('restrict');
            $table->foreignId('product_id')->constrained('products')->onDelete('restrict');
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->onDelete('restrict');
            $table->foreignId('bundle_id')->nullable()->constrained('product_bundles')->onDelete('restrict');
            
            // UOM Information
            $table->foreignId('uom_id')->constrained('units_of_measure')->onDelete('restrict'); // UOM sold in
            $table->decimal('quantity', 15, 4); // Quantity in the UOM
            $table->decimal('quantity_in_base_uom', 15, 4); // For inventory deduction
            
            // Pricing (in the UOM sold)
            $table->decimal('unit_price', 15, 2); // Price per UOM at time of sale
            $table->decimal('unit_cost', 15, 2); // Cost per UOM (for profit calc)
            
            // Line totals
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->foreignId('tax_rate_id')->constrained('tax_rates')->onDelete('restrict');
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('subtotal', 15, 2); // (quantity × unit_price) - discount + tax
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['sale_id']);
            $table->index(['product_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sale_items');
    }
};
