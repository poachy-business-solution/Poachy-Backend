<?php

namespace Tests\Feature\Tenant\Offers;

use App\Events\Tenant\PromotionActivated;
use App\Events\Tenant\PromotionCreated;
use App\Events\Tenant\PromotionDeactivated;
use App\Events\Tenant\PromotionUpdated;
use App\Models\Tenant\Product;
use App\Models\Tenant\ProductBrand;
use App\Models\Tenant\ProductCategory;
use App\Models\Tenant\Promotion;
use App\Repositories\Tenant\PromotionRepository;
use App\Services\Tenant\Offers\PromotionService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Stancl\Tenancy\Contracts\Tenant as TenantContract;
use Tests\TestCase;

class PromotionServiceTest extends TestCase
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

        Cache::tags(['tenant', 'test-tenant', 'promotions'])->flush();
        Event::fake([PromotionCreated::class, PromotionUpdated::class, PromotionActivated::class, PromotionDeactivated::class]);
        Storage::fake('public');
    }

    protected function tearDown(): void
    {
        $this->dropTestTables();
        DB::connection('tenant')->statement('SET foreign_key_checks = 1');
        parent::tearDown();
    }

    private function makeService(): PromotionService
    {
        return new PromotionService(new PromotionRepository);
    }

    private function baseData(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Test Promotion',
            'code' => 'PROMO'.uniqid(),
            'promotion_type' => 'percentage_discount',
            'discount_value' => 15,
            'start_date' => now()->subDay(),
            'end_date' => now()->addMonth(),
            'applicable_to' => 'all_products',
        ], $overrides);
    }

    private function createPromotion(array $overrides = []): Promotion
    {
        return Promotion::create($this->baseData($overrides));
    }

    // =========================================================================
    // createPromotion()
    // =========================================================================

    public function test_create_promotion_succeeds_and_fires_event(): void
    {
        $promotion = $this->makeService()->createPromotion($this->baseData());

        $this->assertNotNull($promotion->id);
        Event::assertDispatched(PromotionCreated::class);
    }

    public function test_create_promotion_throws_when_end_before_start(): void
    {
        $this->expectException(ValidationException::class);

        $this->makeService()->createPromotion($this->baseData([
            'start_date' => now(), 'end_date' => now()->subDay(),
        ]));
    }

    public function test_create_promotion_throws_when_percentage_exceeds_100(): void
    {
        $this->expectException(ValidationException::class);

        $this->makeService()->createPromotion($this->baseData([
            'promotion_type' => 'percentage_discount', 'discount_value' => 150,
        ]));
    }

    public function test_create_promotion_throws_when_discount_value_non_positive(): void
    {
        $this->expectException(ValidationException::class);

        $this->makeService()->createPromotion($this->baseData(['discount_value' => 0]));
    }

    public function test_create_promotion_throws_when_active_time_window_invalid(): void
    {
        $this->expectException(ValidationException::class);

        $this->makeService()->createPromotion($this->baseData([
            'active_time_start' => '18:00', 'active_time_end' => '17:00',
        ]));
    }

    public function test_create_promotion_syncs_specific_products_applicability(): void
    {
        $product = Product::create(['name' => 'P1', 'slug' => 'p1-'.uniqid(), 'sku' => 'SKU-'.uniqid(), 'base_uom_id' => 1]);

        $promotion = $this->makeService()->createPromotion($this->baseData([
            'applicable_to' => 'specific_products',
            'applicability' => ['products' => [['product_id' => $product->id]]],
        ]));

        $this->assertCount(1, $promotion->products);
    }

    // =========================================================================
    // updatePromotion()
    // =========================================================================

    public function test_update_promotion_throws_when_restricted_field_changed_after_use(): void
    {
        $promotion = $this->createPromotion(['total_usage_count' => 1]);

        $this->expectException(ValidationException::class);

        $this->makeService()->updatePromotion($promotion, ['discount_value' => 25]);
    }

    public function test_update_promotion_throws_when_changing_applicability_type_with_existing_relations(): void
    {
        $product = Product::create(['name' => 'P1', 'slug' => 'p1-'.uniqid(), 'sku' => 'SKU-'.uniqid(), 'base_uom_id' => 1]);
        $promotion = $this->createPromotion(['applicable_to' => 'specific_products']);
        $promotion->products()->attach($product->id, ['product_variant_id' => null]);

        $this->expectException(ValidationException::class);

        $this->makeService()->updatePromotion($promotion, ['applicable_to' => 'specific_categories']);
    }

    public function test_update_promotion_succeeds_and_fires_event(): void
    {
        $promotion = $this->createPromotion(['name' => 'Old']);

        $updated = $this->makeService()->updatePromotion($promotion, ['name' => 'New']);

        $this->assertSame('New', $updated->name);
        Event::assertDispatched(PromotionUpdated::class);
    }

    // =========================================================================
    // deletePromotion()
    // =========================================================================

    public function test_delete_promotion_soft_deletes(): void
    {
        $promotion = $this->createPromotion();

        $result = $this->makeService()->deletePromotion($promotion);

        $this->assertTrue($result);
        $this->assertSoftDeleted('promotions', ['id' => $promotion->id], connection: 'tenant');
    }

    // =========================================================================
    // activatePromotion() / deactivatePromotion()
    // =========================================================================

    public function test_activate_promotion_throws_when_expired(): void
    {
        $promotion = $this->createPromotion(['is_active' => false, 'end_date' => now()->subDay()]);

        $this->expectException(ValidationException::class);

        $this->makeService()->activatePromotion($promotion);
    }

    public function test_activate_promotion_throws_when_already_active(): void
    {
        $promotion = $this->createPromotion(['is_active' => true]);

        $this->expectException(ValidationException::class);

        $this->makeService()->activatePromotion($promotion);
    }

    public function test_activate_promotion_succeeds_and_fires_event(): void
    {
        $promotion = $this->createPromotion(['is_active' => false]);

        $activated = $this->makeService()->activatePromotion($promotion);

        $this->assertTrue($activated->is_active);
        Event::assertDispatched(PromotionActivated::class);
    }

    public function test_deactivate_promotion_throws_when_already_inactive(): void
    {
        $promotion = $this->createPromotion(['is_active' => false]);

        $this->expectException(ValidationException::class);

        $this->makeService()->deactivatePromotion($promotion);
    }

    public function test_deactivate_promotion_succeeds_and_fires_event(): void
    {
        $promotion = $this->createPromotion(['is_active' => true]);

        $deactivated = $this->makeService()->deactivatePromotion($promotion);

        $this->assertFalse($deactivated->is_active);
        Event::assertDispatched(PromotionDeactivated::class);
    }

    // =========================================================================
    // attach/detach products, categories, brands
    // =========================================================================

    public function test_attach_products_throws_for_wrong_applicability_type(): void
    {
        $product = Product::create(['name' => 'P1', 'slug' => 'p1-'.uniqid(), 'sku' => 'SKU-'.uniqid(), 'base_uom_id' => 1]);
        $promotion = $this->createPromotion(['applicable_to' => 'specific_categories']);

        $this->expectException(ValidationException::class);

        $this->makeService()->attachProducts($promotion, [['product_id' => $product->id]]);
    }

    public function test_attach_and_detach_products(): void
    {
        $product = Product::create(['name' => 'P1', 'slug' => 'p1-'.uniqid(), 'sku' => 'SKU-'.uniqid(), 'base_uom_id' => 1]);
        $promotion = $this->createPromotion(['applicable_to' => 'specific_products']);

        $withProduct = $this->makeService()->attachProducts($promotion, [['product_id' => $product->id]]);
        $this->assertCount(1, $withProduct->products);

        $withoutProduct = $this->makeService()->detachProduct($promotion, $product->id);
        $this->assertCount(0, $withoutProduct->products);
    }

    public function test_attach_categories_allows_all_products_type(): void
    {
        // Unlike CouponService (which only allows specific_categories), PromotionService
        // also allows attaching categories to an all_products promotion, as a refinement.
        $category = ProductCategory::create(['name' => 'Cat', 'slug' => 'cat-'.uniqid()]);
        $promotion = $this->createPromotion(['applicable_to' => 'all_products']);

        $result = $this->makeService()->attachCategories($promotion, [$category->id]);

        $this->assertCount(1, $result->categories);
    }

    public function test_attach_categories_throws_for_wrong_applicability_type(): void
    {
        $category = ProductCategory::create(['name' => 'Cat', 'slug' => 'cat-'.uniqid()]);
        $promotion = $this->createPromotion(['applicable_to' => 'specific_brands']);

        $this->expectException(ValidationException::class);

        $this->makeService()->attachCategories($promotion, [$category->id]);
    }

    public function test_attach_and_detach_brands(): void
    {
        $brand = ProductBrand::create(['name' => 'Brand', 'slug' => 'brand-'.uniqid()]);
        $promotion = $this->createPromotion(['applicable_to' => 'specific_brands']);

        $withBrand = $this->makeService()->attachBrands($promotion, [$brand->id]);
        $this->assertCount(1, $withBrand->brands);

        $withoutBrand = $this->makeService()->detachBrand($promotion, $brand->id);
        $this->assertCount(0, $withoutBrand->brands);
    }

    public function test_bulk_attach_and_detach_products(): void
    {
        $productA = Product::create(['name' => 'PA', 'slug' => 'pa-'.uniqid(), 'sku' => 'SKU-'.uniqid(), 'base_uom_id' => 1]);
        $productB = Product::create(['name' => 'PB', 'slug' => 'pb-'.uniqid(), 'sku' => 'SKU-'.uniqid(), 'base_uom_id' => 1]);
        $promotion = $this->createPromotion(['applicable_to' => 'specific_products']);

        $withProducts = $this->makeService()->bulkAttachProducts($promotion, [
            ['product_id' => $productA->id], ['product_id' => $productB->id],
        ]);
        $this->assertCount(2, $withProducts->products);

        $withoutProducts = $this->makeService()->bulkDetachProducts($promotion, [$productA->id, $productB->id]);
        $this->assertCount(0, $withoutProducts->products);
    }

    // =========================================================================
    // updateApplicableStores() / updateApplicableCustomerGroups()
    // =========================================================================

    public function test_update_applicable_stores_persists_store_ids(): void
    {
        $promotion = $this->createPromotion();

        $updated = $this->makeService()->updateApplicableStores($promotion, [1, 2]);

        $this->assertSame([1, 2], $updated->applicable_store_ids);
    }

    public function test_update_applicable_customer_groups_persists_group_ids(): void
    {
        $promotion = $this->createPromotion();

        $updated = $this->makeService()->updateApplicableCustomerGroups($promotion, [3, 4]);

        $this->assertSame([3, 4], $updated->applicable_customer_group_ids);
    }

    // =========================================================================
    // updateBanner() / removeBanner()
    // =========================================================================

    public function test_update_banner_stores_new_image_and_fires_event(): void
    {
        $promotion = $this->createPromotion();

        $updated = $this->makeService()->updateBanner($promotion, UploadedFile::fake()->image('banner.jpg'));

        $this->assertNotEmpty($updated->banner_image_url);
        Event::assertDispatched(PromotionUpdated::class);
    }

    public function test_remove_banner_clears_image(): void
    {
        $promotion = $this->createPromotion();
        $this->makeService()->updateBanner($promotion, UploadedFile::fake()->image('banner.jpg'));

        $updated = $this->makeService()->removeBanner($promotion->fresh());

        $this->assertNull($updated->banner_image_url);
    }

    // =========================================================================
    // getCurrentlyRunning() / getFeaturedPromotions()
    // =========================================================================

    public function test_get_currently_running_excludes_future_and_past_promotions(): void
    {
        $this->createPromotion(['start_date' => now()->subDay(), 'end_date' => now()->addDay()]);
        $this->createPromotion(['start_date' => now()->addDays(5), 'end_date' => now()->addDays(10)]);

        $result = $this->makeService()->getCurrentlyRunning();

        $this->assertCount(1, $result);
    }

    // =========================================================================
    // Schema helpers
    // =========================================================================

    private function dropTestTables(): void
    {
        foreach ([
            'promotion_products', 'promotion_categories', 'promotion_brands', 'promotions',
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

        Schema::connection($conn)->create('promotions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->text('description')->nullable();
            $table->string('promotion_type');
            $table->decimal('discount_value', 15, 2)->nullable();
            $table->integer('buy_quantity')->nullable();
            $table->integer('get_quantity')->nullable();
            $table->boolean('get_items_free')->default(true);
            $table->decimal('get_items_discount_percentage', 5, 2)->nullable();
            $table->decimal('min_purchase_amount', 15, 2)->nullable();
            $table->decimal('max_discount_amount', 15, 2)->nullable();
            $table->integer('max_uses_per_customer')->nullable();
            $table->integer('total_usage_limit')->nullable();
            $table->integer('total_usage_count')->default(0);
            $table->timestamp('start_date');
            $table->timestamp('end_date');
            $table->json('active_days')->nullable();
            $table->time('active_time_start')->nullable();
            $table->time('active_time_end')->nullable();
            $table->json('applicable_store_ids')->nullable();
            $table->json('applicable_customer_group_ids')->nullable();
            $table->string('applicable_to');
            $table->boolean('show_on_website')->default(true);
            $table->boolean('show_in_pos')->default(true);
            $table->string('banner_image_url')->nullable();
            $table->integer('display_priority')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('auto_apply')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::connection($conn)->create('promotion_products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('promotion_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('product_variant_id')->nullable();
            $table->timestamps();
        });

        Schema::connection($conn)->create('promotion_categories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('promotion_id');
            $table->unsignedBigInteger('category_id');
            $table->timestamps();
        });

        Schema::connection($conn)->create('promotion_brands', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('promotion_id');
            $table->unsignedBigInteger('brand_id');
            $table->timestamps();
        });
    }
}
