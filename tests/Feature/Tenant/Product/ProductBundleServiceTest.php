<?php

namespace Tests\Feature\Tenant\Product;

use App\Models\Tenant\Inventory;
use App\Models\Tenant\Product;
use App\Models\Tenant\ProductBundle;
use App\Models\Tenant\ProductBundleItem;
use App\Services\Tenant\Inventory\InventoryService;
use App\Services\Tenant\Product\ProductBundleService;
use App\Services\Tenant\Product\SkuGeneratorService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Stancl\Tenancy\Contracts\Tenant as TenantContract;
use Tests\TestCase;

class ProductBundleServiceTest extends TestCase
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

    private function makeService(): ProductBundleService
    {
        return new ProductBundleService(new SkuGeneratorService, new InventoryService);
    }

    private function createProduct(int $baseUomId = 1): Product
    {
        return Product::create([
            'name' => 'Component '.uniqid(),
            'slug' => 'component-'.uniqid(),
            'sku' => 'SKU-'.uniqid(),
            'base_uom_id' => $baseUomId,
        ]);
    }

    private function createBundle(): ProductBundle
    {
        return ProductBundle::create([
            'uuid' => (string) Str::uuid(),
            'bundle_name' => 'Test Bundle',
            'bundle_sku' => 'BUNDLE-'.uniqid(),
            'base_uom_id' => 1,
            'bundle_price' => 100.00,
        ]);
    }

    public function test_returns_available_when_all_components_have_enough_stock(): void
    {
        $product = $this->createProduct();
        $bundle = $this->createBundle();

        ProductBundleItem::create([
            'bundle_id' => $bundle->id,
            'product_id' => $product->id,
            'uom_id' => $product->base_uom_id,
            'quantity' => 2,
            'quantity_in_base_uom' => 2,
        ]);

        Inventory::create([
            'store_id' => 1,
            'product_id' => $product->id,
            'quantity_on_hand' => 10,
            'quantity_reserved' => 0,
            'quantity_available' => 10,
        ]);

        $result = $this->makeService()->checkStockAvailability($bundle->fresh(['items']), storeId: 1, requestedQuantity: 3);

        $this->assertTrue($result['available']);
        $this->assertTrue($result['items'][0]['available']);
        $this->assertSame(6.0, (float) $result['items'][0]['required_quantity']);
    }

    public function test_returns_unavailable_when_a_component_has_insufficient_stock(): void
    {
        $product = $this->createProduct();
        $bundle = $this->createBundle();

        ProductBundleItem::create([
            'bundle_id' => $bundle->id,
            'product_id' => $product->id,
            'uom_id' => $product->base_uom_id,
            'quantity' => 2,
            'quantity_in_base_uom' => 2,
        ]);

        Inventory::create([
            'store_id' => 1,
            'product_id' => $product->id,
            'quantity_on_hand' => 1,
            'quantity_reserved' => 0,
            'quantity_available' => 1,
        ]);

        $result = $this->makeService()->checkStockAvailability($bundle->fresh(['items']), storeId: 1, requestedQuantity: 3);

        $this->assertFalse($result['available']);
        $this->assertFalse($result['items'][0]['available']);
    }

    public function test_availability_is_scoped_per_store(): void
    {
        $product = $this->createProduct();
        $bundle = $this->createBundle();

        ProductBundleItem::create([
            'bundle_id' => $bundle->id,
            'product_id' => $product->id,
            'uom_id' => $product->base_uom_id,
            'quantity' => 1,
            'quantity_in_base_uom' => 1,
        ]);

        // Stock exists at store 1, not store 2.
        Inventory::create([
            'store_id' => 1,
            'product_id' => $product->id,
            'quantity_on_hand' => 10,
            'quantity_reserved' => 0,
            'quantity_available' => 10,
        ]);

        $service = $this->makeService();
        $bundle->load('items');

        $this->assertTrue($service->checkStockAvailability($bundle, storeId: 1)['available']);
        $this->assertFalse($service->checkStockAvailability($bundle, storeId: 2)['available']);
    }

    private function dropTestTables(): void
    {
        foreach (['inventory', 'product_bundle_items', 'product_bundles', 'products', 'units_of_measure'] as $table) {
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
            'id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
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

        Schema::connection($conn)->create('product_bundles', function (Blueprint $table) {
            $table->id();
            $table->string('uuid');
            $table->string('bundle_name');
            $table->string('bundle_sku')->unique();
            $table->unsignedBigInteger('base_uom_id')->nullable();
            $table->decimal('bundle_price', 15, 2)->default(0);
            $table->decimal('calculated_individual_price', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_available_online')->default(false);
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

        Schema::connection($conn)->create('inventory', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('product_variant_id')->nullable();
            $table->decimal('quantity_on_hand', 15, 4)->default(0);
            $table->decimal('quantity_reserved', 15, 4)->default(0);
            $table->decimal('quantity_available', 15, 4)->default(0);
            $table->decimal('quantity_damaged', 15, 4)->default(0);
            $table->date('last_restock_date')->nullable();
            $table->date('last_stock_take_date')->nullable();
            $table->unsignedBigInteger('last_restocked_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }
}
