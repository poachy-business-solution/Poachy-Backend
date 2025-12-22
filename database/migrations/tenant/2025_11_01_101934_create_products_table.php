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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            // Basic info
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('sku')->unique(); // Stock Keeping Unit            

            // Categorization
            $table->foreignId('category_id')->constrained('product_categories')->onDelete('restrict');
            $table->foreignId('brand_id')->nullable()->constrained('product_brands')->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->onDelete('set null');

            // Flags & status
            $table->string('product_type')->default('simple'); // simple or variable
            $table->string('stock_status')->default('in_stock'); // in_stock, out_of_stock, discontinued
            $table->boolean('is_weighed')->default(false);
            $table->boolean('requires_batch_tracking')->default(false);
            $table->boolean('requires_serial_tracking')->default(false);

            // Pricing & Cost
            $table->decimal('base_selling_price', 15, 2)->default(0)->comment('Price per base UOM');
            $table->foreignId('tax_rate_id')->nullable()->constrained('tax_rates')->onDelete('restrict');

            // Inventory & Logistics
            $table->foreignId('base_uom_id')->constrained('units_of_measure')->onDelete('restrict');
            $table->decimal('reorder_level', 15, 4)->default(0)->comment('In base UOM');
            $table->integer('shelf_life_days')->nullable(); // e.g., 365

            // Media & Visibility
            $table->string('primary_image')->nullable();
            $table->json('secondary_images')->nullable(); // Multiple images
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);

            // Online marketplace
            $table->boolean('is_available_online')->default(false);
            $table->decimal('online_price', 12, 2)->nullable()->comment('Price per base UOM');
            $table->text('online_description')->nullable();

            // Additional meta data
            $table->text('notes')->nullable();

            $table->timestamps();

            // Indexes for performance
            $table->index('sku');
            $table->index('category_id');
            $table->index('supplier_id');
            $table->index('brand_id');
            $table->index('is_active');
            $table->index('is_available_online');
            $table->index('reorder_level');

            // FULLTEXT search index
            $table->fullText(['name', 'sku', 'description'], 'ft_product_search');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
