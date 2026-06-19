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
        Schema::create('store_products', function (Blueprint $table) {
            $table->id();

            $table->foreignId('store_id')->constrained('stores')->onDelete('cascade');
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');

            // Pricing (if NULL, uses product's base price)
            $table->decimal('store_selling_price', 15, 2)->nullable(); // Per specified UOM
            $table->boolean('is_available')->default(true);
            $table->unsignedInteger('min_stock_level')->default(0);

            $table->timestamps();

            // UNIQUE: One config per store + product
            $table->unique(['store_id', 'product_id']);

            // Indexes for performance
            $table->index('store_id');
            $table->index('product_id');
            $table->index('is_available');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('store_products');
    }
};
