<?php

namespace Tests\Feature\Tenant\Product;

use App\Models\Tenant\Product;
use App\Models\Tenant\ProductBrand;
use App\Repositories\Tenant\ProductBrandRepository;
use App\Services\Tenant\Product\ProductBrandService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Stancl\Tenancy\Contracts\Tenant as TenantContract;
use Tests\TestCase;

class ProductBrandServiceTest extends TestCase
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

        Cache::tags(['tenant', 'test-tenant', 'product_brands'])->flush();
        Storage::fake('public');
    }

    protected function tearDown(): void
    {
        $this->dropTestTables();
        DB::connection('tenant')->statement('SET foreign_key_checks = 1');
        parent::tearDown();
    }

    private function makeService(): ProductBrandService
    {
        return new ProductBrandService(new ProductBrandRepository);
    }

    private function createBrand(array $overrides = []): ProductBrand
    {
        return ProductBrand::create(array_merge([
            'name' => 'Test Brand '.uniqid(),
        ], $overrides));
    }

    // =========================================================================
    // getAllBrands()
    // =========================================================================

    public function test_get_all_brands_filters_by_active_status(): void
    {
        $this->createBrand(['is_active' => true]);
        $this->createBrand(['is_active' => false]);

        $result = $this->makeService()->getAllBrands(['is_active' => true]);

        $this->assertCount(1, $result);
    }

    public function test_get_all_brands_filters_by_featured_status(): void
    {
        $this->createBrand(['is_featured' => true]);
        $this->createBrand(['is_featured' => false]);

        $result = $this->makeService()->getAllBrands(['is_featured' => true]);

        $this->assertCount(1, $result);
    }

    public function test_get_all_brands_filters_by_search(): void
    {
        $this->createBrand(['name' => 'Samsung']);
        $this->createBrand(['name' => 'Apple']);

        $result = $this->makeService()->getAllBrands(['search' => 'Sam']);

        $this->assertCount(1, $result);
        $this->assertSame('Samsung', $result->first()->name);
    }

    public function test_get_all_brands_paginates_when_requested(): void
    {
        $this->createBrand();
        $this->createBrand();

        $result = $this->makeService()->getAllBrands([], paginate: true, perPage: 1);

        $this->assertSame(2, $result->total());
        $this->assertCount(1, $result->items());
    }

    // =========================================================================
    // getBrandById()
    // =========================================================================

    public function test_get_brand_by_id_returns_null_for_unknown_id(): void
    {
        $this->assertNull($this->makeService()->getBrandById(999999));
    }

    public function test_get_brand_by_id_with_products_loads_relation(): void
    {
        $brand = $this->createBrand();
        Product::create([
            'name' => 'Branded Product', 'slug' => 'branded-product-'.uniqid(), 'sku' => 'SKU-'.uniqid(),
            'brand_id' => $brand->id, 'base_uom_id' => 1, 'is_active' => true,
        ]);

        $found = $this->makeService()->getBrandById($brand->id, withProducts: true);

        $this->assertTrue($found->relationLoaded('products'));
        $this->assertCount(1, $found->products);
    }

    // =========================================================================
    // createBrand()
    // =========================================================================

    public function test_create_brand_generates_slug_when_not_provided(): void
    {
        $brand = $this->makeService()->createBrand(['name' => 'My Great Brand']);

        $this->assertSame('my-great-brand', $brand->slug);
    }

    public function test_create_brand_throws_when_explicit_slug_taken(): void
    {
        $this->createBrand(['slug' => 'taken-slug']);

        $this->expectException(\InvalidArgumentException::class);

        $this->makeService()->createBrand(['name' => 'Another', 'slug' => 'taken-slug']);
    }

    public function test_create_brand_uploads_logo(): void
    {
        $brand = $this->makeService()->createBrand([
            'name' => 'Logo Brand',
            'logo' => UploadedFile::fake()->image('logo.png'),
        ]);

        $this->assertNotEmpty($brand->logo_url);
        Storage::disk('public')->assertExists($brand->logo_url);
    }

    // =========================================================================
    // activateBrand() / deactivateBrand() / featureBrand() / unfeatureBrand()
    // =========================================================================

    public function test_activate_and_deactivate_brand(): void
    {
        $brand = $this->createBrand(['is_active' => false]);
        $service = $this->makeService();

        $service->activateBrand($brand->id);
        $this->assertTrue($brand->fresh()->is_active);

        $service->deactivateBrand($brand->id);
        $this->assertFalse($brand->fresh()->is_active);
    }

    public function test_activate_brand_throws_for_unknown_id(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->makeService()->activateBrand(999999);
    }

    public function test_feature_and_unfeature_brand(): void
    {
        $brand = $this->createBrand(['is_featured' => false]);
        $service = $this->makeService();

        $service->featureBrand($brand->id);
        $this->assertTrue($brand->fresh()->is_featured);

        $service->unfeatureBrand($brand->id);
        $this->assertFalse($brand->fresh()->is_featured);
    }

    // =========================================================================
    // updateBrandLogo()
    // =========================================================================

    public function test_update_brand_logo_deletes_old_and_uploads_new(): void
    {
        Storage::disk('public')->put('products/brands/logos/old.png', 'x');
        $brand = $this->createBrand(['logo_url' => 'products/brands/logos/old.png']);

        $this->makeService()->updateBrandLogo($brand->id, UploadedFile::fake()->image('new.png'));

        Storage::disk('public')->assertMissing('products/brands/logos/old.png');
        Storage::disk('public')->assertExists($brand->fresh()->logo_url);
    }

    public function test_update_brand_logo_throws_for_unknown_id(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->makeService()->updateBrandLogo(999999, UploadedFile::fake()->image('new.png'));
    }

    // =========================================================================
    // deleteBrand()
    // =========================================================================

    public function test_delete_brand_throws_when_it_has_products(): void
    {
        $brand = $this->createBrand();
        Product::create([
            'name' => 'Branded', 'slug' => 'branded-'.uniqid(), 'sku' => 'SKU-'.uniqid(),
            'brand_id' => $brand->id, 'base_uom_id' => 1,
        ]);

        $this->expectException(\InvalidArgumentException::class);

        $this->makeService()->deleteBrand($brand->id);
    }

    public function test_delete_brand_removes_logo_and_record(): void
    {
        Storage::disk('public')->put('products/brands/logos/logo.png', 'x');
        $brand = $this->createBrand(['logo_url' => 'products/brands/logos/logo.png']);

        $result = $this->makeService()->deleteBrand($brand->id);

        $this->assertTrue($result);
        Storage::disk('public')->assertMissing('products/brands/logos/logo.png');
        $this->assertSoftDeleted('product_brands', ['id' => $brand->id], connection: 'tenant');
    }

    public function test_delete_brand_throws_for_unknown_id(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->makeService()->deleteBrand(999999);
    }

    // =========================================================================
    // Schema helpers
    // =========================================================================

    private function dropTestTables(): void
    {
        foreach (['products', 'product_brands', 'units_of_measure'] as $table) {
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
            $table->timestamps();
        });

        DB::connection($conn)->table('units_of_measure')->insert([
            'id' => 1, 'code' => 'pcs', 'name' => 'Piece', 'created_at' => now(), 'updated_at' => now(),
        ]);

        Schema::connection($conn)->create('product_brands', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('logo_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->integer('display_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::connection($conn)->create('products', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->nullable();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('sku')->unique();
            $table->string('product_type')->default('simple');
            $table->string('description')->nullable();
            $table->string('stock_status')->default('in_stock');
            $table->boolean('is_weighed')->default(false);
            $table->boolean('requires_batch_tracking')->default(false);
            $table->boolean('requires_serial_tracking')->default(false);
            $table->decimal('base_selling_price', 15, 2)->default(0);
            $table->string('primary_image')->nullable();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->unsignedBigInteger('brand_id')->nullable();
            $table->unsignedBigInteger('base_uom_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_available_online')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });
    }
}
