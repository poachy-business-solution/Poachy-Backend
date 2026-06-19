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
        Schema::create('stock_transfer_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transfer_id')->constrained('stock_transfers')->onDelete('restrict');
            $table->foreignId('product_id')->constrained('products')->onDelete('restrict');
            
            // Transfer UOM
            $table->foreignId('uom_id')->constrained('units_of_measure')->onDelete('restrict');
            $table->decimal('quantity_requested', 15, 4)->comment('In transfer UOM'); // In transfer UOM
            $table->decimal('quantity_sent', 15, 4)->default(0)->comment('In transfer UOM'); // In transfer UOM
            $table->decimal('quantity_received', 15, 4)->default(0)->comment('In transfer UOM'); // In transfer UOM
            
            // Base UOM equivalents (for inventory updates)
            $table->decimal('quantity_requested_in_base_uom', 15, 4);
            $table->decimal('quantity_sent_in_base_uom', 15, 4)->default(0);
            $table->decimal('quantity_received_in_base_uom', 15, 4)->default(0);
            
            $table->text('notes')->nullable(); // e.g., "5 units damaged in transit"
            $table->timestamps();
            
            $table->index(['transfer_id']);
            $table->index(['product_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_transfer_items');
    }
};
