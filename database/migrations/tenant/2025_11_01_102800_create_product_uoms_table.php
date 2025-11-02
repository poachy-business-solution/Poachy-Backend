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
        Schema::create('product_uoms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->onDelete('restrict');
            $table->foreignId('uom_id')->constrained('units_of_measure')->onDelete('cascade');
            $table->boolean('is_base_uom')->default(false)->comment('Primary UOM');
            $table->boolean('is_purchase_uom')->default(true)->comment('Can buy in this UOM');
            $table->boolean('is_sales_uom')->default(true)->comment('Can sell in this UOM');
            $table->boolean('is_inventory_uom')->default(true)->comment('Can track stock in this UOM');
            $table->decimal('conversion_to_base', 15, 6)->default(1)->comment('Pricing adjustment'); // How many base units in 1 of this UOM           
            $table->timestamps();
            
            $table->unique(['product_id', 'uom_id']);
            $table->index(['product_id', 'is_base_uom']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_uoms');
    }
};
