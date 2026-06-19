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
        Schema::create('inventory', function (Blueprint $table) {
            $table->id();

            $table->foreignId('store_id')->constrained('stores')->onDelete('restrict');
            $table->foreignId('product_id')->constrained('products')->onDelete('restrict');
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->onDelete('restrict');

            // All quantities in product's BASE UOM for consistency
            $table->decimal('quantity_on_hand', 15, 4)->default(0)->comment('In base UOM'); // Always in base UOM
            $table->decimal('quantity_reserved', 15, 4)->default(0)->comment('In base UOM'); // Always in base UOM
            $table->decimal('quantity_available', 15, 4)->default(0)->comment('In base UOM'); // Computed: on_hand - reserved
            $table->decimal('quantity_damaged', 15,4)->default(0)->comment('Defective/unusable stock');

            // Audit & tracking
            $table->date('last_restock_date')->nullable();
            $table->date('last_stock_take_date')->nullable();
            $table->foreignId('last_restocked_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            // Constraints
            $table->unique(['store_id', 'product_id', 'product_variant_id']);
            $table->index(['product_id', 'quantity_available']);

            // Indexes
            $table->index('quantity_available');
            $table->index('quantity_on_hand');
            $table->index('quantity_reserved');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory');
    }
};
