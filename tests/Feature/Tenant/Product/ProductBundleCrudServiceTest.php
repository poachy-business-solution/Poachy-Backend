<?php

namespace Tests\Feature\Tenant\Product;

use App\Models\Tenant\Product;
use App\Models\Tenant\ProductBundle;
use App\Models\Tenant\ProductBundleItem;
use App\Models\Tenant\ProductUom;
use App\Services\Tenant\Inventory\InventoryService;
use App\Services\Tenant\Product\ProductBundleService;
use App\Services\Tenant\Product\SkuGeneratorService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Stancl\Tenancy\Contracts\Tenant as TenantContract;
use Tests\TestCase;

class ProductBundleCrudServiceTest extends TestCase
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

        Storage::fake('public');
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

    private function createProduct(array $overrides = []): Product
    {
        $product = Product::create(array_merge([
            'name' => 'Component '.uniqid(),
            'slug' => 'component-'.uniqid(),
            'sku' => 'SKU-'.uniqid(),
            'base_uom_id' => 1,
            'base_selling_price' => 50,
        ], $overrides));

        ProductUom::create([
            'product_id' => $product->id,
            'uom_id' => 1,
            'is_base_uom' => true,
            'conversion_to_base' => 1,
        ]);

        return $product;
    }

    private function createBundle(array $overrides = []): ProductBundle
    {
        return ProductBundle::create(array_merge([
            'bundle_name' => 'Test Bundle '.uniqid(),
            'bundle_sku' => 'BUNDLE-'.uniqid(),
            'base_uom_id' => 1,
            'bundle_price' => 100.00,
        ], $overrides));
    }

    // =========================================================================
    // list() / getById()
    // =========================================================================

    public function test_list_filters_by_search_term(): void
    {
        $this->createBundle(['bundle_name' => 'Breakfast Combo']);
        $this->createBundle(['bundle_name' => 'Lunch Combo']);

        $result = $this->makeService()->list(['search' => 'Breakfast']);

        $this->assertSame(1, $result->total());
    }

    public function test_list_filters_by_active_and_online_status(): void
    {
        $this->createBundle(['is_active' => true, 'is_available_online' => true]);
        $this->createBundle(['is_active' => false, 'is_available_online' => false]);

        $result = $this->makeService()->list(['is_active' => true, 'is_online' => true]);

        $this->assertSame(1, $result->total());
    }

    public function test_get_by_id_returns_bundle_with_relations(): void
    {
        $bundle = $this->createBundle();

        $found = $this->makeService()->getById($bundle->id);

        $this->assertSame($bundle->id, $found->id);
    }

    public function test_get_by_id_throws_for_unknown_id(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->makeService()->getById(999999);
    }

    // =========================================================================
    // create()
    // =========================================================================

    public function test_create_generates_bundle_sku_when_not_provided(): void
    {
        $bundle = $this->makeService()->create([
            'bundle_name' => 'Generated SKU Bundle',
            'base_uom_id' => 1,
            'bundle_price' => 100,
        ]);

        $this->assertStringStartsWith('BNDL-', $bundle->bundle_sku);
    }

    public function test_create_throws_when_bundle_sku_already_exists(): void
    {
        $this->createBundle(['bundle_sku' => 'DUP-BUNDLE']);

        $this->expectException(\InvalidArgumentException::class);

        $this->makeService()->create([
            'bundle_name' => 'Another Bundle',
            'bundle_sku' => 'DUP-BUNDLE',
            'base_uom_id' => 1,
            'bundle_price' => 100,
        ]);
    }

    public function test_create_with_items_recalculates_pricing(): void
    {
        $productA = $this->createProduct(['base_selling_price' => 50]);
        $productB = $this->createProduct(['base_selling_price' => 30]);

        $bundle = $this->makeService()->create([
            'bundle_name' => 'Combo Bundle',
            'base_uom_id' => 1,
            'bundle_price' => 60,
            'items' => [
                ['product_id' => $productA->id, 'uom_id' => 1, 'quantity' => 1],
                ['product_id' => $productB->id, 'uom_id' => 1, 'quantity' => 1],
            ],
        ]);

        $this->assertCount(2, $bundle->items);
        $this->assertEquals(80, $bundle->calculated_individual_price);
        $this->assertEquals(20, $bundle->discount_amount);
    }

    public function test_create_without_items_leaves_pricing_at_defaults(): void
    {
        $bundle = $this->makeService()->create([
            'bundle_name' => 'Empty Bundle',
            'base_uom_id' => 1,
            'bundle_price' => 100,
        ]);

        $this->assertCount(0, $bundle->items);
        $this->assertNull($bundle->calculated_individual_price);
    }

    // =========================================================================
    // update() / delete()
    // =========================================================================

    public function test_update_throws_when_new_sku_collides(): void
    {
        $this->createBundle(['bundle_sku' => 'TAKEN-BUNDLE']);
        $bundle = $this->createBundle(['bundle_sku' => 'MY-BUNDLE']);

        $this->expectException(\InvalidArgumentException::class);

        $this->makeService()->update($bundle, ['bundle_sku' => 'TAKEN-BUNDLE']);
    }

    public function test_update_allows_keeping_own_sku_unchanged(): void
    {
        $bundle = $this->createBundle(['bundle_sku' => 'MY-BUNDLE']);

        $updated = $this->makeService()->update($bundle, ['bundle_sku' => 'MY-BUNDLE', 'bundle_name' => 'Renamed']);

        $this->assertSame('Renamed', $updated->bundle_name);
    }

    public function test_delete_soft_deletes_bundle(): void
    {
        $bundle = $this->createBundle();

        $result = $this->makeService()->delete($bundle);

        $this->assertTrue($result);
        $this->assertSoftDeleted('product_bundles', ['id' => $bundle->id], connection: 'tenant');
    }

    // =========================================================================
    // addItem() / updateItem() / removeItem()
    // =========================================================================

    public function test_add_item_recalculates_bundle_pricing(): void
    {
        $bundle = $this->createBundle(['bundle_price' => 40]);
        $product = $this->createProduct(['base_selling_price' => 50]);

        $item = $this->makeService()->addItem($bundle, ['product_id' => $product->id, 'uom_id' => 1, 'quantity' => 1]);

        $this->assertEquals(1, $item->quantity_in_base_uom);
        $this->assertEquals(50, $bundle->fresh()->calculated_individual_price);
        $this->assertEquals(10, $bundle->fresh()->discount_amount);
    }

    public function test_add_item_throws_when_uom_not_configured_for_product(): void
    {
        $bundle = $this->createBundle();
        $product = $this->createProduct();

        $this->expectException(\InvalidArgumentException::class);

        $this->makeService()->addItem($bundle, ['product_id' => $product->id, 'uom_id' => 999, 'quantity' => 1]);
    }

    public function test_update_item_recalculates_base_uom_quantity_and_bundle_pricing(): void
    {
        $bundle = $this->createBundle(['bundle_price' => 40]);
        $product = $this->createProduct(['base_selling_price' => 50]);
        $item = $this->makeService()->addItem($bundle, ['product_id' => $product->id, 'uom_id' => 1, 'quantity' => 1]);

        $updated = $this->makeService()->updateItem($item, ['quantity' => 3]);

        $this->assertEquals(3, $updated->quantity_in_base_uom);
        $this->assertEquals(150, $bundle->fresh()->calculated_individual_price);
    }

    public function test_remove_item_recalculates_bundle_pricing(): void
    {
        $bundle = $this->createBundle(['bundle_price' => 40]);
        $product = $this->createProduct(['base_selling_price' => 50]);
        $item = $this->makeService()->addItem($bundle, ['product_id' => $product->id, 'uom_id' => 1, 'quantity' => 1]);

        $result = $this->makeService()->removeItem($item);

        $this->assertTrue($result);
        $this->assertSame(0, $bundle->fresh()->items()->count());
        $this->assertEquals(0, $bundle->fresh()->calculated_individual_price);
    }

    // =========================================================================
    // toggleActive() / toggleOnline() / updatePricing()
    // =========================================================================

    public function test_toggle_active_flips_both_directions(): void
    {
        $bundle = $this->createBundle(['is_active' => true]);
        $service = $this->makeService();

        $off = $service->toggleActive($bundle);
        $this->assertFalse($off->is_active);

        $on = $service->toggleActive($off);
        $this->assertTrue($on->is_active);
    }

    public function test_toggle_online_flips_both_directions(): void
    {
        $bundle = $this->createBundle(['is_available_online' => false]);
        $service = $this->makeService();

        $on = $service->toggleOnline($bundle);
        $this->assertTrue($on->is_available_online);

        $off = $service->toggleOnline($on);
        $this->assertFalse($off->is_available_online);
    }

    public function test_update_pricing_recalculates_discount(): void
    {
        $bundle = $this->createBundle(['bundle_price' => 100]);
        $product = $this->createProduct(['base_selling_price' => 80]);
        $this->makeService()->addItem($bundle, ['product_id' => $product->id, 'uom_id' => 1, 'quantity' => 1]);

        $updated = $this->makeService()->updatePricing($bundle, ['bundle_price' => 60]);

        $this->assertEquals(60, $updated->bundle_price);
        $this->assertEquals(20, $updated->discount_amount);
    }

    // =========================================================================
    // addImages() / removeImage()
    // =========================================================================

    public function test_add_images_merges_and_caps_at_ten(): void
    {
        $bundle = $this->createBundle(['images' => array_fill(0, 9, 'bundles/images/existing.jpg')]);

        $updated = $this->makeService()->addImages($bundle, [
            UploadedFile::fake()->image('a.jpg'),
            UploadedFile::fake()->image('b.jpg'),
        ]);

        $this->assertCount(10, $updated->images);
    }

    public function test_remove_image_deletes_from_storage_and_array(): void
    {
        Storage::disk('public')->put('bundles/images/remove.jpg', 'x');
        $bundle = $this->createBundle(['images' => ['bundles/images/remove.jpg', 'bundles/images/keep.jpg']]);

        $updated = $this->makeService()->removeImage($bundle, 'bundles/images/remove.jpg');

        $this->assertSame(['bundles/images/keep.jpg'], $updated->images);
        Storage::disk('public')->assertMissing('bundles/images/remove.jpg');
    }

    public function test_remove_image_throws_when_image_not_in_bundle(): void
    {
        $bundle = $this->createBundle(['images' => ['bundles/images/keep.jpg']]);

        $this->expectException(\InvalidArgumentException::class);

        $this->makeService()->removeImage($bundle, 'bundles/images/not-there.jpg');
    }

    // =========================================================================
    // getBreakdown()
    // =========================================================================

    public function test_get_breakdown_computes_savings(): void
    {
        $bundle = $this->createBundle(['bundle_price' => 70]);
        $productA = $this->createProduct(['base_selling_price' => 50]);
        $productB = $this->createProduct(['base_selling_price' => 30]);
        $this->makeService()->addItem($bundle, ['product_id' => $productA->id, 'uom_id' => 1, 'quantity' => 1]);
        $this->makeService()->addItem($bundle, ['product_id' => $productB->id, 'uom_id' => 1, 'quantity' => 1]);

        $breakdown = $this->makeService()->getBreakdown($bundle->fresh());

        $this->assertEquals(80, $breakdown['individual_total']);
        $this->assertEquals(10, $breakdown['savings']);
        $this->assertCount(2, $breakdown['items']);
    }

    // =========================================================================
    // Schema helpers
    // =========================================================================

    private function dropTestTables(): void
    {
        foreach ([
            'product_bundle_items',
            'product_bundles',
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
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->string('type')->default('count');
            $table->boolean('is_base_unit')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        DB::connection($conn)->table('units_of_measure')->insert([
            'id' => 1, 'code' => 'pcs', 'name' => 'Piece', 'is_base_unit' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        Schema::connection($conn)->create('products', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->nullable();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('sku')->unique();
            $table->string('product_type')->default('simple');
            $table->string('stock_status')->default('in_stock');
            $table->boolean('is_weighed')->default(false);
            $table->boolean('requires_batch_tracking')->default(false);
            $table->boolean('requires_serial_tracking')->default(false);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_available_online')->default(false);
            $table->decimal('base_selling_price', 15, 2)->default(0);
            $table->unsignedBigInteger('base_uom_id')->nullable();
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

        Schema::connection($conn)->create('product_bundles', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->nullable();
            $table->string('bundle_name');
            $table->string('bundle_sku')->unique();
            $table->text('description')->nullable();
            $table->json('images')->nullable();
            $table->unsignedBigInteger('base_uom_id')->default(1);
            $table->decimal('bundle_price', 15, 2)->default(0);
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
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('product_variant_id')->nullable();
            $table->unsignedBigInteger('uom_id')->default(1);
            $table->decimal('quantity', 15, 4);
            $table->decimal('quantity_in_base_uom', 15, 4);
            $table->timestamps();
        });
    }
}
