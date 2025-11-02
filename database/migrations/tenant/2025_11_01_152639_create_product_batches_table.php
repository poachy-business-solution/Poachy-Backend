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
        Schema::create('product_batches', function (Blueprint $table) {
            $table->id();

            $table->foreignId('store_id')->constrained('stores')->onDelete('restrict');
            $table->foreignId('product_id')->constrained('products')->onDelete('restrict');
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->onDelete('restrict');

            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->onDelete('restrict'); // Link to PO
            $table->string('batch_number')->unique(); // e.g., "BATCH-2025-001"
            
            // Purchase Information
            $table->foreignId('purchase_uom_id')->constrained('units_of_measure')->onDelete('restrict'); // How it was purchased
            $table->decimal('quantity_received_in_purchase_uom', 15, 4); // e.g., 10 bags
            $table->decimal('quantity_received_in_base_uom', 15, 4); // e.g., 500 kg
            $table->decimal('quantity_remaining_in_base_uom', 15, 4); // Tracked in base UOM
            
            // Costing
            $table->decimal('cost_per_purchase_uom', 15, 2); // e.g., 4000 per bag
            $table->decimal('cost_per_base_uom', 15, 2); // e.g., 80 per kg
            $table->decimal('total_cost', 15, 2); // Total batch cost
            
            // Dates (for perishable goods)
            $table->date('manufacture_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->boolean('is_expired')->default(false);
            
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->onDelete('set null');
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['store_id', 'product_id', 'expiry_date']);
            $table->index(['purchase_order_id']);
            $table->index(['expiry_date', 'is_expired']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_batches');
    }
};
