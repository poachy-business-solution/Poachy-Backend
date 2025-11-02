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
        Schema::create('product_bundle_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bundle_id')->constrained('product_bundles')->onDelete('cascade');
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->onDelete('cascade');
            // If NULL: Use base product
            // If SET: Use specific variant (e.g., 500ml Coke, not just "Coke")
            
            $table->foreignId('uom_id')->constrained('units_of_measure')->onDelete('restrict'); 
            $table->decimal('quantity', 15, 4); // How many of this product/variant in the bundle
            $table->decimal('quantity_in_base_uom', 15, 4); //  For inventory deduction
            
            $table->timestamps();
            
            $table->index(['bundle_id', 'product_id']);
            // Allow same product multiple times if different variants
            // e.g., Bundle: 1× 300ml Coke + 1× 500ml Coke
        });
        
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_bundle_items');
    }
};
