<?php

namespace Tests\Feature\Tenant\Sales;

use App\Models\Tenant\Product;
use App\Models\Tenant\ProductBatch;
use App\Models\Tenant\ProductBundle;
use App\Models\Tenant\ProductSerial;
use App\Models\Tenant\ProductVariant;
use App\Services\Tenant\Sales\SaleCalculationService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Stancl\Tenancy\Contracts\Tenant as TenantContract;
use Tests\TestCase;

class SaleCalculationServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'tenant');
        Config::set('database.connections.tenant.database', 'poachy_test');
        DB::purge('tenant');
        DB::connection('tenant')->statement('SET foreign_key_checks = 0');

        $this->dropTestTables();
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

    // =========================================================================
    // getProductCost() — the method fixed to source serial cost correctly
    // =========================================================================

    public function test_serial_tracked_product_cost_averages_available_serial_costs(): void
    {
        $product = $this->createProduct(['requires_serial_tracking' => true]);
        $this->createSerial($product->id, 'IMEI-001', cost: 40.0);
        $this->createSerial($product->id, 'IMEI-002', cost: 60.0);

        $cost = $this->getProductCost($product, storeId: 1, variantId: null);

        $this->assertEquals(50.0, $cost); // (40 + 60) / 2
    }

    public function test_serial_tracked_product_cost_excludes_sold_serials(): void
    {
        $product = $this->createProduct(['requires_serial_tracking' => true]);
        $this->createSerial($product->id, 'IMEI-001', cost: 40.0);
        $this->createSerial($product->id, 'IMEI-002', cost: 100.0, status: 'sold');

        $cost = $this->getProductCost($product, storeId: 1, variantId: null);

        $this->assertEquals(40.0, $cost); // only the available one counts
    }

    public function test_serial_tracked_product_cost_is_zero_when_no_serials_exist(): void
    {
        $product = $this->createProduct(['requires_serial_tracking' => true]);

        $cost = $this->getProductCost($product, storeId: 1, variantId: null);

        $this->assertEquals(0.0, $cost);
    }

    public function test_serial_tracked_product_never_reads_batch_cost(): void
    {
        // Same product id happens to also have a stray batch row (shouldn't happen in
        // practice given the mutual-exclusivity guard, but confirms the branch is
        // driven by requires_serial_tracking, not by which rows happen to exist).
        $product = $this->createProduct(['requires_serial_tracking' => true]);
        $this->createSerial($product->id, 'IMEI-001', cost: 40.0);
        $this->createBatch($product->id, costPerBaseUom: 999.0);

        $cost = $this->getProductCost($product, storeId: 1, variantId: null);

        $this->assertEquals(40.0, $cost);
    }

    public function test_batch_tracked_product_cost_unaffected_by_serial_fix(): void
    {
        $product = $this->createProduct(['requires_batch_tracking' => true]);
        $this->createBatch($product->id, costPerBaseUom: 10.0);
        $this->createBatch($product->id, costPerBaseUom: 20.0);

        $cost = $this->getProductCost($product, storeId: 1, variantId: null);

        $this->assertEquals(15.0, $cost); // unchanged FIFO-average behavior
    }

    public function test_untracked_product_cost_is_zero_with_no_batches(): void
    {
        $product = $this->createProduct();

        $cost = $this->getProductCost($product, storeId: 1, variantId: null);

        $this->assertEquals(0.0, $cost);
    }

    // =========================================================================
    // Resolver tax defaults — products created before tax configuration exists
    // =========================================================================

    public function test_resolve_product_item_treats_missing_tax_rate_as_zero_percent(): void
    {
        $product = $this->createProduct([
            'base_selling_price' => 125.0,
            'tax_rate_id' => null,
        ]);

        $lineItem = $this->resolveProductItem(storeId: 1, productId: $product->id, quantity: 2.0);

        $this->assertNull($lineItem['tax_rate_id']);
        $this->assertSame(0, $lineItem['tax_rate_percentage']);
        $this->assertEquals(250.0, $lineItem['line_total_after_discount']);
    }

    public function test_resolve_variant_item_treats_missing_product_tax_rate_as_zero_percent(): void
    {
        $product = $this->createProduct([
            'base_selling_price' => 100.0,
            'tax_rate_id' => null,
        ]);
        $variant = $this->createVariant($product->id, ['variant_price' => 80.0]);

        $lineItem = $this->resolveVariantItem(
            storeId: 1,
            productId: $product->id,
            variantId: $variant->id,
            quantity: 3.0
        );

        $this->assertNull($lineItem['tax_rate_id']);
        $this->assertSame(0, $lineItem['tax_rate_percentage']);
        $this->assertEquals(240.0, $lineItem['line_total_after_discount']);
    }

    public function test_resolve_bundle_item_treats_missing_tax_rate_as_zero_percent(): void
    {
        $component = $this->createProduct(['base_selling_price' => 50.0]);
        $bundle = $this->createBundle(['tax_rate_id' => null, 'bundle_price' => 175.0]);
        $this->createBundleItem($bundle->id, $component->id);

        $lineItem = $this->resolveBundleItem(storeId: 1, bundleId: $bundle->id, quantity: 2.0);

        $this->assertNull($lineItem['tax_rate_id']);
        $this->assertSame(0, $lineItem['tax_rate_percentage']);
        $this->assertEquals(350.0, $lineItem['line_total_after_discount']);
    }

    public function test_resolve_product_item_with_sales_uom_converts_quantity_and_price(): void
    {
        $product = $this->createProduct(['base_selling_price' => 25.0]);
        DB::connection('tenant')->table('product_uoms')->insert([
            'product_id' => $product->id,
            'uom_id' => 2,
            'is_base_uom' => false,
            'is_sales_uom' => true,
            'conversion_to_base' => 12,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $lineItem = $this->resolveProductItem(
            storeId: 1,
            productId: $product->id,
            quantity: 2.0,
            requestedUomId: 2
        );

        $this->assertSame(2, $lineItem['uom_id']);
        $this->assertSame('ctn', $lineItem['uom_code']);
        $this->assertEquals(2.0, $lineItem['quantity']);
        $this->assertEquals(24.0, $lineItem['quantity_in_base_uom']);
        $this->assertEquals(300.0, $lineItem['unit_price']);
        $this->assertEquals(600.0, $lineItem['line_total_after_discount']);
    }

    public function test_resolve_product_item_rejects_uom_not_configured_for_product_sales(): void
    {
        $product = $this->createProduct(['base_selling_price' => 25.0]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Selected UOM is not configured as a sales UOM for this product');

        $this->resolveProductItem(
            storeId: 1,
            productId: $product->id,
            quantity: 1.0,
            requestedUomId: 2
        );
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function getProductCost(Product $product, int $storeId, ?int $variantId): float
    {
        $method = new ReflectionMethod(SaleCalculationService::class, 'getProductCost');
        $method->setAccessible(true);

        return $method->invoke(app(SaleCalculationService::class), $product, $storeId, $variantId);
    }

    private function resolveProductItem(int $storeId, int $productId, float $quantity, ?int $requestedUomId = null): array
    {
        $method = new ReflectionMethod(SaleCalculationService::class, 'resolveProductItem');
        $method->setAccessible(true);

        return $method->invoke(app(SaleCalculationService::class), $storeId, $productId, $quantity, [], $requestedUomId);
    }

    private function resolveVariantItem(int $storeId, int $productId, int $variantId, float $quantity): array
    {
        $method = new ReflectionMethod(SaleCalculationService::class, 'resolveVariantItem');
        $method->setAccessible(true);

        return $method->invoke(app(SaleCalculationService::class), $storeId, $productId, $variantId, $quantity);
    }

    private function resolveBundleItem(int $storeId, int $bundleId, float $quantity): array
    {
        $method = new ReflectionMethod(SaleCalculationService::class, 'resolveBundleItem');
        $method->setAccessible(true);

        return $method->invoke(app(SaleCalculationService::class), $storeId, $bundleId, $quantity);
    }

    private function createProduct(array $overrides = []): Product
    {
        return Model::withoutEvents(fn () => Product::create(array_merge([
            'name' => 'Test Product',
            'slug' => 'test-product-'.uniqid(),
            'sku' => 'SKU-'.uniqid(),
            'requires_batch_tracking' => false,
            'requires_serial_tracking' => false,
            'base_uom_id' => 1,
        ], $overrides)));
    }

    private function createVariant(int $productId, array $overrides = []): ProductVariant
    {
        return Model::withoutEvents(fn () => ProductVariant::create(array_merge([
            'product_id' => $productId,
            'variant_name' => 'Small',
            'sku' => 'VAR-'.uniqid(),
            'uom_id' => 1,
            'uom_quantity' => 1.0,
            'quantity_in_base_uom' => 1.0,
            'base_selling_price_adjustment' => 0.0,
            'variant_price' => 80.0,
            'stock_status' => 'in_stock',
            'is_active' => true,
        ], $overrides)));
    }

    private function createBundle(array $overrides = []): ProductBundle
    {
        return Model::withoutEvents(fn () => ProductBundle::create(array_merge([
            'bundle_name' => 'Test Bundle',
            'bundle_sku' => 'BUNDLE-'.uniqid(),
            'base_uom_id' => 1,
            'bundle_price' => 175.0,
            'tax_rate_id' => null,
            'is_active' => true,
        ], $overrides)));
    }

    private function createBundleItem(int $bundleId, int $productId): void
    {
        DB::connection('tenant')->table('product_bundle_items')->insert([
            'bundle_id' => $bundleId,
            'product_id' => $productId,
            'product_variant_id' => null,
            'uom_id' => 1,
            'quantity' => 1.0,
            'quantity_in_base_uom' => 1.0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createSerial(int $productId, string $serialNumber, float $cost, string $status = 'available'): ProductSerial
    {
        return Model::withoutEvents(fn () => ProductSerial::create([
            'store_id' => 1,
            'product_id' => $productId,
            'purchase_order_id' => 1,
            'serial_number' => $serialNumber,
            'status' => $status,
            'cost' => $cost,
        ]));
    }

    private function createBatch(int $productId, float $costPerBaseUom): ProductBatch
    {
        return Model::withoutEvents(fn () => ProductBatch::create([
            'store_id' => 1,
            'product_id' => $productId,
            'purchase_order_id' => 1,
            'batch_number' => 'BATCH-'.uniqid(),
            'purchase_uom_id' => 1,
            'quantity_received_in_purchase_uom' => 10.0,
            'quantity_received_in_base_uom' => 10.0,
            'quantity_remaining_in_base_uom' => 10.0,
            'cost_per_purchase_uom' => $costPerBaseUom,
            'cost_per_base_uom' => $costPerBaseUom,
            'total_cost' => $costPerBaseUom * 10,
            'is_expired' => false,
        ]));
    }

    private function dropTestTables(): void
    {
        foreach ([
            'product_serials',
            'product_batches',
            'store_products',
            'product_bundle_items',
            'product_bundles',
            'product_uoms',
            'product_variants',
            'products',
            'units_of_measure',
            'stores',
        ] as $table) {
            Schema::connection('tenant')->dropIfExists($table);
        }
    }

    private function createMinimalSchema(): void
    {
        $conn = 'tenant';

        Schema::connection($conn)->create('stores', function (Blueprint $table) {
            $table->id();
            $table->string('name')->default('Test Store');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::connection($conn)->create('units_of_measure', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->string('type');
            $table->boolean('is_base_unit')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        DB::connection($conn)->table('units_of_measure')->insert([
            [
                'id' => 1,
                'code' => 'pcs',
                'name' => 'Piece',
                'type' => 'count',
                'is_base_unit' => true,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'code' => 'ctn',
                'name' => 'Carton',
                'type' => 'count',
                'is_base_unit' => false,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        Schema::connection($conn)->create('products', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->nullable();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('sku')->unique();
            $table->string('product_type')->default('simple');
            $table->string('stock_status')->default('in_stock');
            $table->boolean('requires_batch_tracking')->default(false);
            $table->boolean('requires_serial_tracking')->default(false);
            $table->boolean('is_weighed')->default(false);
            $table->unsignedBigInteger('base_uom_id')->default(1);
            $table->unsignedBigInteger('category_id')->nullable();
            $table->unsignedBigInteger('brand_id')->nullable();
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->unsignedBigInteger('tax_rate_id')->nullable();
            $table->decimal('base_selling_price', 10, 2)->default(0);
            $table->decimal('online_price', 10, 2)->nullable();
            $table->decimal('reorder_level', 12, 4)->default(0);
            $table->integer('shelf_life_days')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_available_online')->default(false);
            $table->string('primary_image')->nullable();
            $table->json('secondary_images')->nullable();
            $table->text('notes')->nullable();
            $table->text('online_description')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::connection($conn)->create('product_variants', function (Blueprint $table) {
            $table->id();
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

        Schema::connection($conn)->create('product_uoms', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('uom_id');
            $table->boolean('is_base_uom')->default(false);
            $table->boolean('is_sales_uom')->default(true);
            $table->decimal('conversion_to_base', 12, 6)->default(1);
            $table->timestamps();
        });

        Schema::connection($conn)->create('product_bundles', function (Blueprint $table) {
            $table->id();
            $table->string('bundle_name');
            $table->string('bundle_sku')->unique();
            $table->text('description')->nullable();
            $table->json('images')->nullable();
            $table->unsignedBigInteger('base_uom_id');
            $table->decimal('bundle_price', 15, 2);
            $table->decimal('calculated_individual_price', 15, 2)->nullable();
            $table->decimal('discount_amount', 15, 2)->nullable();
            $table->unsignedBigInteger('tax_rate_id')->nullable();
            $table->boolean('is_available_online')->default(false);
            $table->boolean('is_active')->default(true);
            $table->decimal('online_price', 15, 2)->nullable();
            $table->text('online_description')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::connection($conn)->create('product_bundle_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bundle_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('product_variant_id')->nullable();
            $table->unsignedBigInteger('uom_id');
            $table->decimal('quantity', 15, 4);
            $table->decimal('quantity_in_base_uom', 15, 4);
            $table->timestamps();
        });

        Schema::connection($conn)->create('store_products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('product_variant_id')->nullable();
            $table->decimal('store_selling_price', 15, 2)->nullable();
            $table->boolean('is_available')->default(true);
            $table->integer('min_stock_level')->default(0);
            $table->timestamps();
        });

        Schema::connection($conn)->create('product_batches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('product_variant_id')->nullable();
            $table->unsignedBigInteger('purchase_order_id');
            $table->string('batch_number')->unique();
            $table->unsignedBigInteger('purchase_uom_id');
            $table->decimal('quantity_received_in_purchase_uom', 15, 4);
            $table->decimal('quantity_received_in_base_uom', 15, 4);
            $table->decimal('quantity_remaining_in_base_uom', 15, 4);
            $table->decimal('cost_per_purchase_uom', 15, 2);
            $table->decimal('cost_per_base_uom', 15, 2);
            $table->decimal('total_cost', 15, 2);
            $table->date('manufacture_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->boolean('is_expired')->default(false);
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::connection($conn)->create('product_serials', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('product_variant_id')->nullable();
            $table->unsignedBigInteger('purchase_order_id');
            $table->string('serial_number')->unique();
            $table->string('status')->default('available');
            $table->decimal('cost', 15, 2)->default(0);
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->unsignedBigInteger('sale_item_id')->nullable();
            $table->unsignedBigInteger('marketplace_sale_item_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }
}
