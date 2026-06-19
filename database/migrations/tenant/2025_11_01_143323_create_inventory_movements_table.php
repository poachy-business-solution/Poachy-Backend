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
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->onDelete('restrict');
            $table->foreignId('product_id')->constrained('products')->onDelete('restrict');
            
            // Movement Type
            $table->string('movement_type'); // purchase, sale, adjustment, transfer_in, transfer_out, return, damage, expiry, theft, stock_take
            
            // UOM Information
            $table->foreignId('uom_id')->constrained('units_of_measure'); // UOM used in transaction
            $table->decimal('quantity', 15, 4); // Quantity in the UOM used (can be +/-)
            $table->decimal('quantity_in_base_uom', 15, 4); // Equivalent in product's base UOM
            
            // Costing
            $table->decimal('unit_cost', 15, 2)->nullable(); // Cost per UOM used
            $table->decimal('unit_cost_in_base_uom', 15, 2)->nullable(); // Cost per base UOM
            $table->decimal('total_cost', 15, 2)->nullable(); // Total value of movement
            
            // Reference to source transaction
            $table->string('reference_type')->nullable(); // Sale, PurchaseOrder, etc.
            $table->unsignedBigInteger('reference_id')->nullable();
            
            // Balances (in base UOM)
            $table->decimal('balance_after', 15, 4); // Stock level after this movement
            
            $table->text('notes')->nullable();
            $table->foreignId('created_by_user')->constrained('users')->onDelete('restrict');
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['store_id', 'product_id', 'created_at']);
            $table->index(['reference_type', 'reference_id']);
        });
        
        // Example: Sold 500g of rice
        // uom_id: g
        // quantity: -500 (negative for sale)
        // quantity_in_base_uom: -0.5 (converted to kg)
        // unit_cost_in_base_uom: 80 (cost per kg)
        // balance_after: 499.5 (remaining kg in inventory)
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
    }
};
