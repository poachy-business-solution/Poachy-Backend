<?php

namespace Tests\Feature\Tenant\Product;

use App\Models\Tenant\Product;
use App\Services\Tenant\Product\ProductService;
use App\Services\Tenant\Product\SkuGeneratorService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Stancl\Tenancy\Contracts\Tenant as TenantContract;
use Tests\TestCase;

class ProductServiceTest extends TestCase
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

        Cache::tags(['tenant', 'test-tenant', 'products'])->flush();
        Storage::fake('public');
    }

    protected function tearDown(): void
    {
        $this->dropTestTables();
        DB::connection('tenant')->statement('SET foreign_key_checks = 1');
        parent::tearDown();
    }

    private function makeService(): ProductService
    {
        return new ProductService(new SkuGeneratorService);
    }

    private function baseData(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Test Product',
            'category_id' => 1,
            'base_uom_id' => 1,
        ], $overrides);
    }

    private function createProduct(array $overrides = []): Product
    {
        return Product::create(array_merge([
            'name' => 'Existing Product',
            'slug' => 'existing-product-'.uniqid(),
            'sku' => 'SKU-'.uniqid(),
            'category_id' => 1,
            'base_uom_id' => 1,
        ], $overrides));
    }

    // =========================================================================
    // create()
    // =========================================================================

    public function test_create_generates_uuid_slug_and_sku(): void
    {
        $product = $this->makeService()->create($this->baseData());

        $this->assertNotEmpty($product->uuid);
        $this->assertSame('test-product', $product->slug);
        $this->assertNotEmpty($product->sku);
    }

    public function test_create_uses_provided_sku_when_given(): void
    {
        $product = $this->makeService()->create($this->baseData(['sku' => 'CUSTOM-SKU-1']));

        $this->assertSame('CUSTOM-SKU-1', $product->sku);
    }

    public function test_create_throws_when_provided_sku_already_exists(): void
    {
        $this->createProduct(['sku' => 'DUPLICATE-SKU']);

        $this->expectException(\InvalidArgumentException::class);

        $this->makeService()->create($this->baseData(['sku' => 'DUPLICATE-SKU']));
    }

    public function test_create_generates_unique_slug_when_name_collides(): void
    {
        $this->makeService()->create($this->baseData(['name' => 'Same Name']));
        $second = $this->makeService()->create($this->baseData(['name' => 'Same Name']));

        $this->assertSame('same-name-1', $second->slug);
    }

    public function test_create_uploads_primary_image(): void
    {
        $file = UploadedFile::fake()->image('primary.jpg');

        $product = $this->makeService()->create($this->baseData(['primary_image' => $file]));

        $this->assertNotEmpty($product->primary_image);
        Storage::disk('public')->assertExists($product->primary_image);
    }

    public function test_create_uploads_secondary_images(): void
    {
        $files = [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg')];

        $product = $this->makeService()->create($this->baseData(['secondary_images' => $files]));

        $this->assertCount(2, $product->secondary_images);
        foreach ($product->secondary_images as $path) {
            Storage::disk('public')->assertExists($path);
        }
    }

    public function test_create_defaults_secondary_images_to_empty_array(): void
    {
        $product = $this->makeService()->create($this->baseData());

        $this->assertSame([], $product->secondary_images);
    }

    // =========================================================================
    // update()
    // =========================================================================

    public function test_update_regenerates_slug_when_name_changes(): void
    {
        $product = $this->createProduct(['slug' => 'old-slug']);

        $updated = $this->makeService()->update($product, ['name' => 'Brand New Name']);

        $this->assertSame('brand-new-name', $updated->slug);
    }

    public function test_update_keeps_slug_when_name_unchanged(): void
    {
        $product = $this->createProduct(['slug' => 'stable-slug', 'name' => 'Stable Name']);

        $updated = $this->makeService()->update($product, ['name' => 'Stable Name', 'notes' => 'updated notes']);

        $this->assertSame('stable-slug', $updated->slug);
    }

    public function test_update_respects_explicit_slug_override(): void
    {
        $product = $this->createProduct(['slug' => 'old-slug']);

        $updated = $this->makeService()->update($product, ['name' => 'New Name', 'slug' => 'manual-slug']);

        $this->assertSame('manual-slug', $updated->slug);
    }

    public function test_update_throws_when_new_sku_collides_with_another_product(): void
    {
        $this->createProduct(['sku' => 'TAKEN-SKU']);
        $product = $this->createProduct(['sku' => 'MY-SKU']);

        $this->expectException(\InvalidArgumentException::class);

        $this->makeService()->update($product, ['sku' => 'TAKEN-SKU']);
    }

    public function test_update_allows_keeping_own_sku_unchanged(): void
    {
        $product = $this->createProduct(['sku' => 'MY-SKU']);

        $updated = $this->makeService()->update($product, ['sku' => 'MY-SKU', 'notes' => 'note']);

        $this->assertSame('MY-SKU', $updated->sku);
        $this->assertSame('note', $updated->notes);
    }

    public function test_update_records_price_history_when_base_selling_price_changes(): void
    {
        $product = $this->createProduct(['base_selling_price' => 100]);

        $this->makeService()->update($product, ['base_selling_price' => 150]);

        $this->assertSame(1, DB::connection('tenant')->table('product_price_history')
            ->where('product_id', $product->id)->count());
    }

    // =========================================================================
    // updateInventoryConfig() / updateOnlineConfig()
    // =========================================================================

    public function test_update_inventory_config_updates_fields(): void
    {
        $product = $this->createProduct(['requires_batch_tracking' => false, 'reorder_level' => 0]);

        $updated = $this->makeService()->updateInventoryConfig($product, [
            'requires_batch_tracking' => true,
            'reorder_level' => 20,
        ]);

        $this->assertTrue($updated->requires_batch_tracking);
        $this->assertEquals(20, $updated->reorder_level);
    }

    public function test_update_online_config_updates_availability_and_price(): void
    {
        $product = $this->createProduct(['is_available_online' => false]);

        $updated = $this->makeService()->updateOnlineConfig($product, [
            'is_available_online' => true,
            'online_price' => 999,
        ]);

        $this->assertTrue($updated->is_available_online);
        $this->assertEquals(999, $updated->online_price);
    }

    // =========================================================================
    // toggleActive() / toggleFeatured()
    // =========================================================================

    public function test_toggle_active_flips_both_directions(): void
    {
        $product = $this->createProduct(['is_active' => true]);
        $service = $this->makeService();

        $off = $service->toggleActive($product);
        $this->assertFalse($off->is_active);

        $on = $service->toggleActive($off);
        $this->assertTrue($on->is_active);
    }

    public function test_toggle_featured_flips_both_directions(): void
    {
        $product = $this->createProduct(['is_featured' => false]);
        $service = $this->makeService();

        $on = $service->toggleFeatured($product);
        $this->assertTrue($on->is_featured);

        $off = $service->toggleFeatured($on);
        $this->assertFalse($off->is_featured);
    }

    // =========================================================================
    // addImages() / deleteImages() / replacePrimaryImage()
    // =========================================================================

    public function test_add_images_merges_with_existing_secondary_images(): void
    {
        $product = $this->createProduct(['secondary_images' => ['products/images/existing.jpg']]);

        $updated = $this->makeService()->addImages($product, [UploadedFile::fake()->image('new.jpg')]);

        $this->assertCount(2, $updated->secondary_images);
        $this->assertContains('products/images/existing.jpg', $updated->secondary_images);
    }

    public function test_delete_images_removes_matching_images_from_storage_and_db(): void
    {
        Storage::disk('public')->put('products/images/keep.jpg', 'x');
        Storage::disk('public')->put('products/images/remove.jpg', 'x');
        $product = $this->createProduct(['secondary_images' => [
            'products/images/keep.jpg',
            'products/images/remove.jpg',
        ]]);

        $result = $this->makeService()->deleteImages($product, ['products/images/remove.jpg']);

        $this->assertSame(1, $result['deleted_count']);
        $this->assertSame(1, $result['remaining_images']);
        Storage::disk('public')->assertMissing('products/images/remove.jpg');
        $this->assertSame(['products/images/keep.jpg'], array_values($product->fresh()->secondary_images));
    }

    public function test_delete_images_reports_zero_when_no_matching_images(): void
    {
        $product = $this->createProduct(['secondary_images' => ['products/images/keep.jpg']]);

        $result = $this->makeService()->deleteImages($product, ['products/images/not-in-list.jpg']);

        $this->assertSame(0, $result['deleted_count']);
        $this->assertSame(1, $result['failed_count']);
        $this->assertSame(1, $result['remaining_images']);
    }

    public function test_replace_primary_image_deletes_old_and_uploads_new(): void
    {
        Storage::disk('public')->put('products/images/old.jpg', 'x');
        $product = $this->createProduct(['primary_image' => 'products/images/old.jpg']);

        $newPath = $this->makeService()->replacePrimaryImage($product, UploadedFile::fake()->image('new.jpg'));

        Storage::disk('public')->assertMissing('products/images/old.jpg');
        Storage::disk('public')->assertExists($newPath);
    }

    // =========================================================================
    // list()
    // =========================================================================

    public function test_list_filters_by_search_term(): void
    {
        $this->createProduct(['name' => 'Blue Widget', 'sku' => 'BW-001']);
        $this->createProduct(['name' => 'Red Gadget', 'sku' => 'RG-001']);

        $result = $this->makeService()->list(['search' => 'Widget']);

        $this->assertSame(1, $result->total());
        $this->assertSame('Blue Widget', $result->first()->name);
    }

    public function test_list_filters_by_category_and_active_and_featured(): void
    {
        $this->createProduct(['category_id' => 1, 'is_active' => true, 'is_featured' => true]);
        $this->createProduct(['category_id' => 2, 'is_active' => true, 'is_featured' => false]);

        $result = $this->makeService()->list(['category_id' => 1, 'is_featured' => true]);

        $this->assertSame(1, $result->total());
    }

    public function test_list_filters_by_stock_status_and_online_availability(): void
    {
        $this->createProduct(['stock_status' => 'out_of_stock', 'is_available_online' => true]);
        $this->createProduct(['stock_status' => 'in_stock', 'is_available_online' => false]);

        $result = $this->makeService()->list(['status' => 'out_of_stock', 'is_available_online' => true]);

        $this->assertSame(1, $result->total());
    }

    // =========================================================================
    // getByUuid()
    // =========================================================================

    public function test_get_by_uuid_returns_product_with_relations(): void
    {
        $product = $this->createProduct();

        $found = $this->makeService()->getByUuid($product->uuid);

        $this->assertSame($product->id, $found->id);
    }

    public function test_get_by_uuid_caches_result(): void
    {
        $product = $this->createProduct();
        $service = $this->makeService();

        $service->getByUuid($product->uuid);
        DB::connection('tenant')->table('products')->where('id', $product->id)->update(['name' => 'Changed In DB']);
        $second = $service->getByUuid($product->uuid);

        $this->assertSame('Existing Product', $second->name);
    }

    public function test_get_by_uuid_throws_for_unknown_uuid(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->makeService()->getByUuid((string) \Illuminate\Support\Str::uuid());
    }

    // =========================================================================
    // clearProductCache()
    // =========================================================================

    public function test_clear_product_cache_forces_fresh_fetch_for_given_uuid(): void
    {
        $product = $this->createProduct();
        $service = $this->makeService();
        $service->getByUuid($product->uuid);
        DB::connection('tenant')->table('products')->where('id', $product->id)->update(['name' => 'Changed In DB']);

        $service->clearProductCache($product->uuid);

        $this->assertSame('Changed In DB', $service->getByUuid($product->uuid)->name);
    }

    // =========================================================================
    // Schema helpers
    // =========================================================================

    private function dropTestTables(): void
    {
        foreach ([
            'product_price_history',
            'products',
            'product_categories',
            'product_brands',
            'suppliers',
            'tax_rates',
            'units_of_measure',
        ] as $table) {
            Schema::connection('tenant')->dropIfExists($table);
        }
    }

    private function createMinimalSchema(): void
    {
        $conn = 'tenant';

        Schema::connection($conn)->create('product_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->nullable();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::connection($conn)->create('product_brands', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->timestamps();
        });

        Schema::connection($conn)->create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::connection($conn)->create('tax_rates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('rate', 5, 2)->default(0);
            $table->timestamps();
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

        DB::connection($conn)->table('product_categories')->insert([
            'id' => 1, 'name' => 'Electronics', 'slug' => 'electronics', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::connection($conn)->table('units_of_measure')->insert([
            'id' => 1, 'code' => 'pcs', 'name' => 'Piece', 'type' => 'count', 'is_base_unit' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        Schema::connection($conn)->create('products', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->nullable();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('sku')->unique();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->unsignedBigInteger('brand_id')->nullable();
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->string('product_type')->default('simple');
            $table->string('stock_status')->default('in_stock');
            $table->boolean('is_weighed')->default(false);
            $table->boolean('requires_batch_tracking')->default(false);
            $table->boolean('requires_serial_tracking')->default(false);
            $table->decimal('base_selling_price', 15, 2)->default(0);
            $table->unsignedBigInteger('tax_rate_id')->nullable();
            $table->unsignedBigInteger('base_uom_id')->nullable();
            $table->decimal('reorder_level', 15, 4)->default(0);
            $table->integer('shelf_life_days')->nullable();
            $table->string('primary_image')->nullable();
            $table->json('secondary_images')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_available_online')->default(false);
            $table->decimal('online_price', 12, 2)->nullable();
            $table->text('online_description')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::connection($conn)->create('product_price_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('product_variant_id')->nullable();
            $table->unsignedBigInteger('base_uom_id')->nullable();
            $table->decimal('old_selling_price', 15, 2)->nullable();
            $table->decimal('new_selling_price', 15, 2);
            $table->string('change_reason', 255)->default('manual');
            $table->unsignedBigInteger('changed_by')->nullable();
            $table->timestamp('effective_from')->useCurrent();
            $table->timestamp('created_at')->useCurrent();
        });
    }
}
