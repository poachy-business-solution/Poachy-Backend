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
        Schema::create('product_price_history', function (Blueprint $table) {
            $table->id();

            // Support both product and variant
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->onDelete('cascade');

            // Optional: Track UOM if price is UOM-specific
            $table->foreignId('base_uom_id')->constrained('units_of_measure')->onDelete('restrict');

            // Price changes
            $table->decimal('old_selling_price', 15, 2)->nullable();
            $table->decimal('new_selling_price', 15, 2);
            $table->decimal('old_cost', 15, 2)->nullable();
            $table->decimal('new_cost', 15, 2);

            // Audit
            $table->string('change_reason', 255); // e.g., "Supplier increase", "Promotion", "Correction"
            $table->foreignId('changed_by')
                  ->constrained('users')
                  ->onDelete('restrict');

            $table->timestamp('effective_from')->useCurrent(); // When new price takes effect
            $table->timestamp('created_at')->useCurrent();

            // Indexes
            $table->index(['product_id', 'effective_from']);
            $table->index(['product_variant_id', 'effective_from']);
            $table->index('effective_from');
            $table->index('changed_by');
            $table->index('change_reason');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_price_history');
    }
};
