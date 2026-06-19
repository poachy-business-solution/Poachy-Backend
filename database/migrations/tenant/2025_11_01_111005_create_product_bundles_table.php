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
        Schema::create('product_bundles', function (Blueprint $table) {
            $table->id();
            
            // Bundle details
            $table->string('bundle_name'); // e.g., "Breakfast Combo"
            $table->string('bundle_sku')->unique(); // e.g., "BUNDLE-BREAKFAST-001"
            $table->text('description')->nullable();
            $table->json('images')->nullable();
            
            // Pricing & UOM
            $table->foreignId('base_uom_id')->constrained('units_of_measure')->onDelete('restrict'); // Usually 'pcs' (piece)
            $table->decimal('bundle_price', 15, 2); // Total bundle price
            $table->decimal('calculated_individual_price', 15, 2)->nullable(); // Sum of individual item prices (for discount calculation)
            $table->decimal('discount_amount', 15, 2)->nullable(); // How much customer saves
            $table->foreignId('tax_rate_id')->constrained('tax_rates')->onDelete('restrict');
            
            // Availability
            $table->boolean('is_available_online')->default(false);
            $table->boolean('is_active')->default(true);
            
            // Online specific
            $table->decimal('online_price', 15, 2)->nullable(); // Different price online
            $table->text('online_description')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['bundle_sku', 'is_active']);
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_bundles');
    }
};
