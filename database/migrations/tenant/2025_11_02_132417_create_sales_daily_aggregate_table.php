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
        Schema::create('sales_daily_aggregates', function (Blueprint $table) {
            $table->id();
            
            // Dimensions
            $table->date('aggregate_date')->index();
            $table->foreignId('store_id')->constrained('stores')->onDelete('cascade');
            
            $table->string('sellable_type', 50); // 'Product', 'ProductVariant', 'ProductBundle'
            
            // For reporting convenience (denormalized)
            $table->foreignId('product_id')->nullable()->constrained('products')->onDelete('cascade');
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->onDelete('cascade');
            $table->foreignId('bundle_id')->nullable()->constrained('product_bundles')->onDelete('cascade');
            $table->foreignId('category_id')->nullable()->constrained('product_categories')->onDelete('set null');
            
            // Metrics
            $table->decimal('total_quantity_sold', 15, 4)->default(0);
            $table->decimal('total_revenue', 15, 2)->default(0); // Subtotal (before tax/discount)
            $table->decimal('total_cost', 15, 2)->default(0); // COGS
            $table->decimal('total_profit', 15, 2)->default(0); // Revenue - Cost
            $table->decimal('total_tax', 15, 2)->default(0);
            $table->decimal('total_discount', 15, 2)->default(0);
            $table->integer('transaction_count')->default(0); // Number of sales
            $table->integer('unique_customers')->default(0); // Distinct customer count
            
            $table->timestamps();
            $table->softDeletes();
            
            // Unique constraint: one record per day per store per item
            $table->unique(
                ['aggregate_date', 'store_id', 'sellable_type'],
                'unique_daily_aggregate'
            );
            
            // Indexes for reporting
            $table->index(['store_id', 'aggregate_date']);
            $table->index(['product_id', 'aggregate_date']);
            $table->index(['category_id', 'aggregate_date']);
            $table->index(['sellable_type', 'aggregate_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_daily_aggregate');
    }
};
