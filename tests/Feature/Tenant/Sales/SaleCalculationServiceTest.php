<?php

namespace Tests\Feature\Tenant\Sales;

use App\Models\Tenant\Product;
use App\Models\Tenant\ProductBatch;
use App\Models\Tenant\ProductSerial;
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
    // Helpers
    // =========================================================================

    private function getProductCost(Product $product, int $storeId, ?int $variantId): float
    {
        $method = new ReflectionMethod(SaleCalculationService::class, 'getProductCost');
        $method->setAccessible(true);

        return $method->invoke(app(SaleCalculationService::class), $product, $storeId, $variantId);
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
            'products',
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
