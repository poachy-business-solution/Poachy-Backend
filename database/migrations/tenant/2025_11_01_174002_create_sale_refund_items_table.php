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
        Schema::create('sale_refund_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('refund_id')->constrained('sale_refunds')->onDelete('set null');
            $table->foreignId('sale_item_id')->constrained('sale_items')->onDelete('restrict');
            $table->foreignId('product_id')->constrained('products')->onDelete('restrict');
            $table->decimal('quantity_refunded', 15, 4); // In original sale UOM
            $table->decimal('quantity_refunded_in_base_uom', 15, 4); // For inventory restoration
            $table->decimal('refund_amount', 15, 2);
            $table->timestamps();
            
            $table->index(['refund_id']);
            $table->index(['sale_item_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sale_refund_items');
    }
};
