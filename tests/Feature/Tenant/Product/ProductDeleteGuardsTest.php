<?php

namespace Tests\Feature\Tenant\Product;

use App\Models\Tenant\Product;
use App\Models\Tenant\ProductUom;
use App\Models\Tenant\ProductVariant;
use App\Models\Tenant\SaleItem;
use App\Services\Tenant\Product\ProductUomService;
use App\Services\Tenant\Product\ProductVariantService;
use App\Services\Tenant\Product\SkuGeneratorService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Stancl\Tenancy\Contracts\Tenant as TenantContract;
use Tests\TestCase;

class ProductDeleteGuardsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'tenant');
        Config::set('database.connections.tenant.database', 'poachy_test');
        DB::purge('tenant');
        DB::connection('tenant')->statement('SET foreign_key_checks = 0');

        $this->createMinimalSchema();

        $fakeTenant = new \stdClass;
        $fakeTenant->id = 'test-tenant';
        app()->instance(TenantContract::class, $fakeTenant);
    }

    protected function tearDown(): void
    {
        $this->dropTestTables();
        DB::connection('tenant')->statement('SET foreign_key_checks = 1');
        parent::tearDown();
    }

    private function createProduct(): Product
    {
        return Product::create([
            'name' => 'Test Product',
            'slug' => 'test-product-'.uniqid(),
            'sku' => 'SKU-'.uniqid(),
            'base_uom_id' => 1,
        ]);
    }

    // =========================================================================
    // ProductUomService::delete()
    // =========================================================================

    public function test_uom_delete_throws_when_referenced_in_a_sale_item(): void
    {
        $product = $this->createProduct();

        $productUom = ProductUom::create([
            'product_id' => $product->id,
            'uom_id' => 2,
            'is_base_uom' => false,
            'conversion_to_base' => 1,
        ]);

        // A second UOM row so the "only UOM" guard doesn't fire first.
        ProductUom::create([
            'product_id' => $product->id,
            'uom_id' => 1,
            'is_base_uom' => true,
            'conversion_to_base' => 1,
        ]);

        SaleItem::create([
            'product_id' => $product->id,
            'uom_id' => 2,
            'quantity' => 1,
            'quantity_in_base_uom' => 1,
            'unit_price' => 10,
            'subtotal' => 10,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('referenced in existing transactions');

        (new ProductUomService)->delete($productUom);
    }

    public function test_uom_delete_succeeds_when_not_referenced(): void
    {
        $product = $this->createProduct();

        $productUom = ProductUom::create([
            'product_id' => $product->id,
            'uom_id' => 2,
            'is_base_uom' => false,
            'conversion_to_base' => 1,
        ]);

        ProductUom::create([
            'product_id' => $product->id,
            'uom_id' => 1,
            'is_base_uom' => true,
            'conversion_to_base' => 1,
        ]);

        $result = (new ProductUomService)->delete($productUom);

        $this->assertTrue($result);
        $this->assertNull(ProductUom::find($productUom->id));
    }

    // =========================================================================
    // ProductVariantService::delete()
    // =========================================================================

    public function test_variant_delete_throws_when_referenced_in_a_sale_item(): void
    {
        $product = $this->createProduct();

        $variant = ProductVariant::create([
            'uuid' => (string) Str::uuid(),
            'product_id' => $product->id,
            'variant_name' => 'Large',
            'sku' => 'VAR-'.uniqid(),
            'uom_id' => 1,
            'uom_quantity' => 1,
            'quantity_in_base_uom' => 1,
        ]);

        SaleItem::create([
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'uom_id' => 1,
            'quantity' => 1,
            'quantity_in_base_uom' => 1,
            'unit_price' => 10,
            'subtotal' => 10,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('referenced in existing transactions');

        (new ProductVariantService(new SkuGeneratorService))->delete($variant);
    }

    public function test_variant_delete_succeeds_when_not_referenced(): void
    {
        $product = $this->createProduct();

        $variant = ProductVariant::create([
            'uuid' => (string) Str::uuid(),
            'product_id' => $product->id,
            'variant_name' => 'Large',
            'sku' => 'VAR-'.uniqid(),
            'uom_id' => 1,
            'uom_quantity' => 1,
            'quantity_in_base_uom' => 1,
        ]);

        $result = (new ProductVariantService(new SkuGeneratorService))->delete($variant);

        $this->assertTrue($result);
        $this->assertNull(ProductVariant::find($variant->id));
        $this->assertNotNull(
            DB::connection('tenant')->table('product_variants')->where('id', $variant->id)->value('deleted_at')
        );
    }

    private function dropTestTables(): void
    {
        foreach ([
            'sale_items',
            'purchase_order_items',
            'inventory_movements',
            'stock_transfer_items',
            'product_batches',
            'marketplace_sale_items',
            'inventory',
            'product_variants',
            'product_uoms',
            'products',
            'units_of_measure',
        ] as $table) {
            Schema::connection('tenant')->dropIfExists($table);
        }
    }

    private function createMinimalSchema(): void
    {
        $conn = 'tenant';

        Schema::connection($conn)->create('units_of_measure', function (Blueprint $table) {
            $table->id();
            $table->string('name')->default('Pieces');
            $table->string('code')->default('PCS');
            $table->timestamps();
        });

        DB::connection($conn)->table('units_of_measure')->insert([
            ['id' => 1, 'code' => 'PCS', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'code' => 'BOX', 'created_at' => now(), 'updated_at' => now()],
        ]);

        Schema::connection($conn)->create('products', function (Blueprint $table) {
            $table->id();
            $table->string('uuid');
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('sku')->unique();
            $table->string('product_type')->default('simple');
            $table->string('stock_status')->default('in_stock');
            $table->boolean('is_weighed')->default(false);
            $table->boolean('requires_batch_tracking')->default(false);
            $table->boolean('requires_serial_tracking')->default(false);
            $table->unsignedBigInteger('base_uom_id')->nullable();
            $table->boolean('is_available_online')->default(false);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::connection($conn)->create('product_uoms', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('uom_id');
            $table->boolean('is_base_uom')->default(false);
            $table->boolean('is_purchase_uom')->default(true);
            $table->boolean('is_sales_uom')->default(true);
            $table->boolean('is_inventory_uom')->default(true);
            $table->decimal('conversion_to_base', 15, 6)->default(1);
            $table->timestamps();
        });

        Schema::connection($conn)->create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->string('uuid');
            $table->unsignedBigInteger('product_id');
            $table->string('variant_name');
            $table->string('sku')->unique();
            $table->json('attributes')->nullable();
            $table->unsignedBigInteger('uom_id');
            $table->decimal('uom_quantity', 15, 4);
            $table->decimal('quantity_in_base_uom', 15, 4);
            $table->decimal('base_selling_price_adjustment', 15, 2)->default(0);
            $table->decimal('variant_price', 15, 2)->nullable();
            $table->string('stock_status')->default('in_stock');
            $table->decimal('reorder_level', 15, 4)->default(0);
            $table->integer('shelf_life_days')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::connection($conn)->create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sale_id')->nullable();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('product_variant_id')->nullable();
            $table->unsignedBigInteger('bundle_id')->nullable();
            $table->unsignedBigInteger('uom_id')->nullable();
            $table->decimal('quantity', 15, 4);
            $table->decimal('quantity_in_base_uom', 15, 4);
            $table->decimal('unit_price', 15, 2);
            $table->decimal('unit_cost', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('subtotal', 15, 2);
            $table->timestamps();
            $table->softDeletes();
        });

        // The remaining tables the delete guards check are never populated in these tests —
        // they just need to exist so the guard's exists() OR-chain can evaluate to false
        // for each once sale_items has already returned false.
        Schema::connection($conn)->create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('product_variant_id')->nullable();
            $table->unsignedBigInteger('uom_id')->nullable();
            $table->softDeletes();
        });

        Schema::connection($conn)->create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('uom_id')->nullable();
            $table->softDeletes();
        });

        Schema::connection($conn)->create('stock_transfer_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('product_variant_id')->nullable();
            $table->unsignedBigInteger('uom_id')->nullable();
            $table->softDeletes();
        });

        Schema::connection($conn)->create('product_batches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('product_variant_id')->nullable();
            $table->unsignedBigInteger('purchase_uom_id')->nullable();
            $table->softDeletes();
        });

        Schema::connection($conn)->create('marketplace_sale_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('product_variant_id')->nullable();
            $table->unsignedBigInteger('uom_id')->nullable();
            $table->softDeletes();
        });

        Schema::connection($conn)->create('inventory', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_variant_id')->nullable();
            $table->softDeletes();
        });
    }
}
