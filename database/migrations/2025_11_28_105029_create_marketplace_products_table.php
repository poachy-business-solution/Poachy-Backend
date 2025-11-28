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
        Schema::connection('central')->create('marketplace_products', function (Blueprint $table) {
            $table->id();

            // Source Information
            $table->string('tenant_id');
            $table->unsignedBigInteger('tenant_product_id');
            $table->string('tenant_product_type')->default('product'); // product, variant, bundle
            $table->unsignedBigInteger('tenant_variant_id')->nullable();
            $table->unsignedBigInteger('tenant_bundle_id')->nullable();

            // Product Details (Denormalized for fast queries)
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->text('online_description')->nullable();
            $table->string('sku');

            // Categorization - BOTH tenant and marketplace
            // Tenant's original categorization (for display)
            $table->unsignedBigInteger('tenant_category_id')->nullable();
            $table->string('tenant_category_name')->nullable();
            $table->unsignedBigInteger('tenant_brand_id')->nullable();
            $table->string('tenant_brand_name')->nullable();

            // Mapped marketplace categorization (for browsing/filtering)
            $table->foreignId('marketplace_category_id')->nullable()->constrained('marketplace_categories')->onDelete('set null');
            $table->foreignId('marketplace_brand_id')->nullable()->constrained('marketplace_brands')->onDelete('set null');

            // Pricing & UOM
            $table->decimal('online_price', 15, 2);
            $table->string('base_uom_code'); // 'kg', 'pcs', 'l'
            $table->string('base_uom_name'); // 'Kilogram', 'Piece'
            $table->decimal('tax_rate', 5, 2)->default(0);

            // Inventory (Cached from tenant)
            $table->decimal('available_quantity', 15, 4)->default(0);
            $table->string('stock_status')->default('in_stock'); // in_stock, low_stock, out_of_stock

            // Media
            $table->string('primary_image')->nullable();
            $table->json('secondary_images')->nullable();

            // Performance Metrics
            $table->integer('view_count')->default(0);
            $table->integer('order_count')->default(0);
            $table->decimal('average_rating', 3, 2)->default(0);
            $table->integer('rating_count')->default(0);

            // Visibility & Status
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->integer('display_priority')->default(0);

            // Sync Tracking
            $table->timestamp('last_synced_at')->nullable();
            $table->string('sync_status')->default('synced'); // synced, pending, failed

            $table->timestamps();
            $table->softDeletes();

            // Foreign Keys
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');

            // Unique: One entry per tenant product/variant/bundle
            $table->unique(['tenant_id', 'tenant_product_type', 'tenant_product_id', 'tenant_variant_id', 'tenant_bundle_id'], 'unique_tenant_product');

            // Indexes for marketplace browsing
            $table->index(['tenant_id', 'is_active']);
            $table->index(['marketplace_category_id', 'is_active']);
            $table->index(['marketplace_brand_id', 'is_active']);
            $table->index(['is_featured', 'display_priority']);
            $table->index('stock_status');
            $table->fullText(['name', 'description'], 'ft_marketplace_product_search');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('central')->dropIfExists('marketplace_products');
    }
};
