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
        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->onDelete('restrict');
            $table->foreignId('product_id')->constrained('products')->onDelete('restrict');
            
            // Purchase UOM
            $table->foreignId('uom_id')->constrained('units_of_measure')->onDelete('restrict'); // UOM being purchased
            $table->decimal('quantity_ordered', 15, 4)->comment('In purchase UOM'); // In purchase UOM
            $table->decimal('quantity_received', 15, 4)->default(0)->comment('In purchase UOM'); // In purchase UOM
            $table->decimal('quantity_ordered_in_base_uom', 15, 4); // For inventory planning
            $table->decimal('quantity_received_in_base_uom', 15, 4)->default(0); // For inventory
            
            // Costing
            $table->decimal('unit_cost', 15, 2); // Cost per purchase UOM
            $table->decimal('unit_cost_in_base_uom', 15, 2); // Cost per base UOM
            $table->foreignId('tax_rate_id')->constrained('tax_rates')->onDelete('restrict');
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('subtotal', 15, 2); // quantity_ordered × unit_cost
            
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->index(['purchase_order_id']);
            $table->index(['product_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_order_items');
    }
};
