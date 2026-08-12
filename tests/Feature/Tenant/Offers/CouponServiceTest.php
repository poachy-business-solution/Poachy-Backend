<?php

namespace Tests\Feature\Tenant\Offers;

use App\Events\Tenant\CouponActivated;
use App\Events\Tenant\CouponCreated;
use App\Events\Tenant\CouponDeactivated;
use App\Events\Tenant\CouponUpdated;
use App\Models\Tenant\Coupon;
use App\Models\Tenant\Product;
use App\Models\Tenant\ProductBrand;
use App\Models\Tenant\ProductCategory;
use App\Repositories\Tenant\CouponRepository;
use App\Services\Tenant\Offers\CouponService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Stancl\Tenancy\Contracts\Tenant as TenantContract;
use Tests\TestCase;

class CouponServiceTest extends TestCase
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

        Cache::tags(['tenant', 'test-tenant', 'coupons'])->flush();
        Event::fake([CouponCreated::class, CouponUpdated::class, CouponActivated::class, CouponDeactivated::class]);
    }

    protected function tearDown(): void
    {
        $this->dropTestTables();
        DB::connection('tenant')->statement('SET foreign_key_checks = 1');
        parent::tearDown();
    }

    private function makeService(): CouponService
    {
        return new CouponService(new CouponRepository);
    }

    private function baseCouponData(array $overrides = []): array
    {
        return array_merge([
            'code' => 'SAVE'.uniqid(),
            'description' => 'Test coupon',
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'valid_from' => now()->subDay()->toDateString(),
            'valid_until' => now()->addMonth()->toDateString(),
            'applicable_to' => 'all_products',
        ], $overrides);
    }

    private function createCoupon(array $overrides = []): Coupon
    {
        return Coupon::create($this->baseCouponData($overrides));
    }

    // =========================================================================
    // createCoupon()
    // =========================================================================

    public function test_create_coupon_succeeds_and_fires_event(): void
    {
        $coupon = $this->makeService()->createCoupon($this->baseCouponData());

        $this->assertNotNull($coupon->id);
        Event::assertDispatched(CouponCreated::class);
    }

    public function test_create_coupon_throws_when_valid_until_before_valid_from(): void
    {
        $this->expectException(ValidationException::class);

        $this->makeService()->createCoupon($this->baseCouponData([
            'valid_from' => now()->toDateString(),
            'valid_until' => now()->subDay()->toDateString(),
        ]));
    }

    public function test_create_coupon_throws_when_percentage_exceeds_100(): void
    {
        $this->expectException(ValidationException::class);

        $this->makeService()->createCoupon($this->baseCouponData([
            'discount_type' => 'percentage', 'discount_value' => 150,
        ]));
    }

    public function test_create_coupon_throws_when_discount_value_non_positive(): void
    {
        $this->expectException(ValidationException::class);

        $this->makeService()->createCoupon($this->baseCouponData(['discount_value' => 0]));
    }

    public function test_create_coupon_throws_when_usage_limit_below_one(): void
    {
        $this->expectException(ValidationException::class);

        $this->makeService()->createCoupon($this->baseCouponData(['usage_limit' => 0]));
    }

    public function test_create_coupon_syncs_specific_products_applicability(): void
    {
        $product = Product::create(['name' => 'P1', 'slug' => 'p1-'.uniqid(), 'sku' => 'SKU-'.uniqid(), 'base_uom_id' => 1]);

        $coupon = $this->makeService()->createCoupon($this->baseCouponData([
            'applicable_to' => 'specific_products',
            'applicability' => ['products' => [['product_id' => $product->id]]],
        ]));

        $this->assertCount(1, $coupon->products);
    }

    public function test_create_coupon_syncs_specific_categories_applicability(): void
    {
        $category = ProductCategory::create(['name' => 'Cat', 'slug' => 'cat-'.uniqid()]);

        $coupon = $this->makeService()->createCoupon($this->baseCouponData([
            'applicable_to' => 'specific_categories',
            'applicability' => ['categories' => [$category->id]],
        ]));

        $this->assertCount(1, $coupon->categories);
    }

    // =========================================================================
    // updateCoupon()
    // =========================================================================

    public function test_update_coupon_throws_when_restricted_field_changed_after_use(): void
    {
        $coupon = $this->createCoupon(['usage_count' => 1]);

        $this->expectException(ValidationException::class);

        $this->makeService()->updateCoupon($coupon, ['discount_value' => 20]);
    }

    public function test_update_coupon_allows_non_restricted_field_change_after_use(): void
    {
        $coupon = $this->createCoupon(['usage_count' => 1, 'description' => 'Old']);

        $updated = $this->makeService()->updateCoupon($coupon, ['description' => 'New description']);

        $this->assertSame('New description', $updated->description);
    }

    public function test_update_coupon_throws_when_changing_applicability_type_with_existing_relations(): void
    {
        $product = Product::create(['name' => 'P1', 'slug' => 'p1-'.uniqid(), 'sku' => 'SKU-'.uniqid(), 'base_uom_id' => 1]);
        $coupon = $this->createCoupon(['applicable_to' => 'specific_products']);
        $coupon->products()->attach($product->id, ['product_variant_id' => null]);

        $this->expectException(ValidationException::class);

        $this->makeService()->updateCoupon($coupon, ['applicable_to' => 'specific_categories']);
    }

    public function test_update_coupon_succeeds_and_fires_event(): void
    {
        $coupon = $this->createCoupon(['description' => 'Old']);

        $updated = $this->makeService()->updateCoupon($coupon, ['description' => 'New']);

        $this->assertSame('New', $updated->description);
        Event::assertDispatched(CouponUpdated::class);
    }

    // =========================================================================
    // deleteCoupon()
    // =========================================================================

    public function test_delete_coupon_soft_deletes(): void
    {
        $coupon = $this->createCoupon();

        $result = $this->makeService()->deleteCoupon($coupon);

        $this->assertTrue($result);
        $this->assertSoftDeleted('coupons', ['id' => $coupon->id], connection: 'tenant');
    }

    // =========================================================================
    // activateCoupon() / deactivateCoupon()
    // =========================================================================

    public function test_activate_coupon_throws_when_expired(): void
    {
        $coupon = $this->createCoupon(['is_active' => false, 'valid_until' => now()->subDay()->toDateString()]);

        $this->expectException(ValidationException::class);

        $this->makeService()->activateCoupon($coupon);
    }

    public function test_activate_coupon_throws_when_already_active(): void
    {
        $coupon = $this->createCoupon(['is_active' => true]);

        $this->expectException(ValidationException::class);

        $this->makeService()->activateCoupon($coupon);
    }

    public function test_activate_coupon_succeeds_and_fires_event(): void
    {
        $coupon = $this->createCoupon(['is_active' => false]);

        $activated = $this->makeService()->activateCoupon($coupon);

        $this->assertTrue($activated->is_active);
        Event::assertDispatched(CouponActivated::class);
    }

    public function test_deactivate_coupon_throws_when_already_inactive(): void
    {
        $coupon = $this->createCoupon(['is_active' => false]);

        $this->expectException(ValidationException::class);

        $this->makeService()->deactivateCoupon($coupon);
    }

    public function test_deactivate_coupon_succeeds_and_fires_event(): void
    {
        $coupon = $this->createCoupon(['is_active' => true]);

        $deactivated = $this->makeService()->deactivateCoupon($coupon);

        $this->assertFalse($deactivated->is_active);
        Event::assertDispatched(CouponDeactivated::class);
    }

    // =========================================================================
    // attach/detach products, categories, brands
    // =========================================================================

    public function test_attach_products_throws_for_wrong_applicability_type(): void
    {
        $product = Product::create(['name' => 'P1', 'slug' => 'p1-'.uniqid(), 'sku' => 'SKU-'.uniqid(), 'base_uom_id' => 1]);
        $coupon = $this->createCoupon(['applicable_to' => 'specific_categories']);

        $this->expectException(ValidationException::class);

        $this->makeService()->attachProducts($coupon, [['product_id' => $product->id]]);
    }

    public function test_attach_and_detach_products(): void
    {
        $product = Product::create(['name' => 'P1', 'slug' => 'p1-'.uniqid(), 'sku' => 'SKU-'.uniqid(), 'base_uom_id' => 1]);
        $coupon = $this->createCoupon(['applicable_to' => 'specific_products']);

        $withProduct = $this->makeService()->attachProducts($coupon, [['product_id' => $product->id]]);
        $this->assertCount(1, $withProduct->products);

        $withoutProduct = $this->makeService()->detachProduct($coupon, $product->id);
        $this->assertCount(0, $withoutProduct->products);
    }

    public function test_attach_categories_throws_for_wrong_applicability_type(): void
    {
        $category = ProductCategory::create(['name' => 'Cat', 'slug' => 'cat-'.uniqid()]);
        $coupon = $this->createCoupon(['applicable_to' => 'all_products']);

        $this->expectException(ValidationException::class);

        $this->makeService()->attachCategories($coupon, [$category->id]);
    }

    public function test_attach_and_detach_categories(): void
    {
        $category = ProductCategory::create(['name' => 'Cat', 'slug' => 'cat-'.uniqid()]);
        $coupon = $this->createCoupon(['applicable_to' => 'specific_categories']);

        $withCategory = $this->makeService()->attachCategories($coupon, [$category->id]);
        $this->assertCount(1, $withCategory->categories);

        $withoutCategory = $this->makeService()->detachCategory($coupon, $category->id);
        $this->assertCount(0, $withoutCategory->categories);
    }

    public function test_attach_brands_throws_for_wrong_applicability_type(): void
    {
        $brand = ProductBrand::create(['name' => 'Brand', 'slug' => 'brand-'.uniqid()]);
        $coupon = $this->createCoupon(['applicable_to' => 'all_products']);

        $this->expectException(ValidationException::class);

        $this->makeService()->attachBrands($coupon, [$brand->id]);
    }

    public function test_attach_and_detach_brands(): void
    {
        $brand = ProductBrand::create(['name' => 'Brand', 'slug' => 'brand-'.uniqid()]);
        $coupon = $this->createCoupon(['applicable_to' => 'specific_brands']);

        $withBrand = $this->makeService()->attachBrands($coupon, [$brand->id]);
        $this->assertCount(1, $withBrand->brands);

        $withoutBrand = $this->makeService()->detachBrand($coupon, $brand->id);
        $this->assertCount(0, $withoutBrand->brands);
    }

    public function test_bulk_attach_and_detach_products(): void
    {
        $productA = Product::create(['name' => 'PA', 'slug' => 'pa-'.uniqid(), 'sku' => 'SKU-'.uniqid(), 'base_uom_id' => 1]);
        $productB = Product::create(['name' => 'PB', 'slug' => 'pb-'.uniqid(), 'sku' => 'SKU-'.uniqid(), 'base_uom_id' => 1]);
        $coupon = $this->createCoupon(['applicable_to' => 'specific_products']);

        $withProducts = $this->makeService()->bulkAttachProducts($coupon, [
            ['product_id' => $productA->id], ['product_id' => $productB->id],
        ]);
        $this->assertCount(2, $withProducts->products);

        $withoutProducts = $this->makeService()->bulkDetachProducts($coupon, [$productA->id, $productB->id]);
        $this->assertCount(0, $withoutProducts->products);
    }

    // =========================================================================
    // getAvailableCoupons()
    // =========================================================================

    public function test_get_available_coupons_excludes_inactive_and_expired(): void
    {
        $this->createCoupon(['is_active' => true]);
        $this->createCoupon(['is_active' => false]);
        $this->createCoupon(['is_active' => true, 'valid_until' => now()->subDay()->toDateString()]);

        $result = $this->makeService()->getAvailableCoupons();

        $this->assertCount(1, $result);
    }

    // =========================================================================
    // Schema helpers
    // =========================================================================

    private function dropTestTables(): void
    {
        foreach ([
            'coupon_products', 'coupon_categories', 'coupon_brands', 'coupons',
            'products', 'product_categories', 'product_brands', 'units_of_measure',
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
            $table->timestamps();
        });
        DB::connection($conn)->table('units_of_measure')->insert([
            'id' => 1, 'code' => 'pcs', 'name' => 'Piece', 'created_at' => now(), 'updated_at' => now(),
        ]);

        Schema::connection($conn)->create('product_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->integer('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::connection($conn)->create('product_brands', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->integer('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
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
            $table->string('stock_status')->default('in_stock');
            $table->boolean('is_weighed')->default(false);
            $table->boolean('requires_batch_tracking')->default(false);
            $table->boolean('requires_serial_tracking')->default(false);
            $table->decimal('base_selling_price', 15, 2)->default(0);
            $table->unsignedBigInteger('base_uom_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_available_online')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::connection($conn)->create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('description');
            $table->string('discount_type');
            $table->decimal('discount_value', 15, 2);
            $table->decimal('min_purchase_amount', 15, 2)->nullable();
            $table->decimal('max_discount_amount', 15, 2)->nullable();
            $table->integer('usage_limit')->nullable();
            $table->integer('usage_count')->default(0);
            $table->integer('usage_limit_per_customer')->nullable();
            $table->date('valid_from');
            $table->date('valid_until');
            $table->string('applicable_to');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::connection($conn)->create('coupon_products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('coupon_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('product_variant_id')->nullable();
            $table->timestamps();
        });

        Schema::connection($conn)->create('coupon_categories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('coupon_id');
            $table->unsignedBigInteger('category_id');
            $table->timestamps();
        });

        Schema::connection($conn)->create('coupon_brands', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('coupon_id');
            $table->unsignedBigInteger('brand_id');
            $table->timestamps();
        });
    }
}
