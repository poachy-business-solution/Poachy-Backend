<?php

namespace Tests\Feature\Tenant\Catalog;

use App\Models\Tenant\Customer;
use App\Models\Tenant\Product;
use App\Models\Tenant\ProductBrand;
use App\Models\Tenant\ProductCategory;
use App\Models\Tenant\ProductUom;
use App\Models\Tenant\ProductVariant;
use App\Models\Tenant\StoreProduct;
use App\Models\Tenant\TaxRate;
use App\Models\Tenant\UnitOfMeasure;
use App\Services\Tenant\Catalog\CatalogDeltaSyncService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CatalogDeltaSyncServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'tenant');
        Config::set('database.connections.tenant.database', 'poachy_test');
        DB::purge('tenant');
        DB::connection('tenant')->statement('SET foreign_key_checks = 0');

        $this->dropCatalogTables();
        $this->createCatalogSchema();
    }

    protected function tearDown(): void
    {
        $this->dropCatalogTables();
        DB::connection('tenant')->statement('SET foreign_key_checks = 1');

        parent::tearDown();
    }

    public function test_snapshot_returns_grouped_catalog_entities_with_cursor_metadata(): void
    {
        $this->seedBaseCatalog();

        $result = (new CatalogDeltaSyncService)->sync();

        $this->assertArrayHasKey('next_cursor', $result);
        $this->assertCount(1, $result['entities']['products']);
        $this->assertCount(1, $result['entities']['variants']);
        $this->assertCount(1, $result['entities']['prices']);
        $this->assertCount(1, $result['entities']['product_uoms']);
        $this->assertCount(1, $result['entities']['uoms']);
        $this->assertCount(1, $result['entities']['tax_rates']);
        $this->assertCount(1, $result['entities']['customers']);

        $this->assertSame('SKU-001', $result['entities']['products'][0]['sku']);
        $this->assertSame(1, $result['entities']['prices'][0]['store_id']);
        $this->assertSame('regular', $result['entities']['customers'][0]['customer_type']);
    }

    public function test_updated_since_returns_only_changed_rows(): void
    {
        $old = now()->subDays(2);
        $since = now()->subDay();
        $new = now()->subHour();

        Model::withoutEvents(function () use ($old, $new) {
            Product::unguarded(fn () => Product::create([
                'name' => 'Old Product',
                'slug' => 'old-product',
                'sku' => 'OLD-001',
                'base_uom_id' => 1,
                'created_at' => $old,
                'updated_at' => $old,
            ]));

            Product::unguarded(fn () => Product::create([
                'name' => 'New Product',
                'slug' => 'new-product',
                'sku' => 'NEW-001',
                'base_uom_id' => 1,
                'created_at' => $new,
                'updated_at' => $new,
            ]));
        });

        $result = (new CatalogDeltaSyncService)->sync(updatedSince: $since->toISOString());

        $this->assertSame(['NEW-001'], collect($result['entities']['products'])->pluck('sku')->all());
    }

    public function test_include_deleted_returns_soft_deleted_tombstones(): void
    {
        $since = now()->subHour();

        $brand = Model::withoutEvents(fn () => ProductBrand::create([
            'name' => 'Deleted Brand',
            'slug' => 'deleted-brand',
            'created_at' => now()->subMinutes(30),
            'updated_at' => now()->subMinutes(30),
        ]));

        Model::withoutEvents(fn () => $brand->delete());

        $withoutDeleted = (new CatalogDeltaSyncService)->sync(
            updatedSince: $since->toISOString(),
            includeDeleted: false
        );
        $withDeleted = (new CatalogDeltaSyncService)->sync(
            updatedSince: $since->toISOString(),
            includeDeleted: true
        );

        $this->assertSame([], $withoutDeleted['entities']['brands']);
        $this->assertCount(1, $withDeleted['entities']['brands']);
        $this->assertTrue($withDeleted['entities']['brands'][0]['deleted']);
        $this->assertNotNull($withDeleted['entities']['brands'][0]['deleted_at']);
    }

    private function seedBaseCatalog(): void
    {
        Model::withoutEvents(function () {
            ProductCategory::create(['name' => 'General', 'slug' => 'general']);
            ProductBrand::create(['name' => 'Acme', 'slug' => 'acme']);
            UnitOfMeasure::create(['code' => 'pcs', 'name' => 'Piece', 'type' => 'count', 'source_type' => 'system', 'is_base_unit' => true]);
            TaxRate::create(['tax_name' => 'VAT', 'rate' => 16, 'effective_from' => now()->toDateString(), 'is_default' => true]);
            $product = Product::create([
                'name' => 'Product One',
                'slug' => 'product-one',
                'sku' => 'SKU-001',
                'category_id' => 1,
                'brand_id' => 1,
                'tax_rate_id' => 1,
                'base_uom_id' => 1,
                'base_selling_price' => 100,
            ]);
            ProductVariant::create([
                'product_id' => $product->id,
                'variant_name' => 'Small',
                'sku' => 'SKU-001-S',
                'uom_id' => 1,
                'uom_quantity' => 1,
                'quantity_in_base_uom' => 1,
            ]);
            ProductUom::create(['product_id' => $product->id, 'uom_id' => 1, 'is_base_uom' => true]);
            StoreProduct::create(['store_id' => 1, 'product_id' => $product->id, 'is_available' => true, 'store_selling_price' => 95]);
            Customer::create(['customer_number' => 'CUS-001', 'name' => 'Jane Buyer', 'customer_type' => 'regular']);
        });
    }

    private function dropCatalogTables(): void
    {
        foreach ([
            'coupons',
            'promotions',
            'customers',
            'store_products',
            'product_uoms',
            'product_variants',
            'products',
            'tax_rates',
            'units_of_measure',
            'product_brands',
            'product_categories',
        ] as $table) {
            Schema::connection('tenant')->dropIfExists($table);
        }
    }

    private function createCatalogSchema(): void
    {
        $conn = 'tenant';

        Schema::connection($conn)->create('product_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->unsignedInteger('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::connection($conn)->create('product_brands', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('logo_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::connection($conn)->create('units_of_measure', function (Blueprint $table) {
            $table->id();
            $table->string('code');
            $table->string('name');
            $table->string('type')->default('count');
            $table->string('source_type')->default('system');
            $table->boolean('is_base_unit')->default(false);
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::connection($conn)->create('tax_rates', function (Blueprint $table) {
            $table->id();
            $table->string('tax_name');
            $table->decimal('rate', 5, 2);
            $table->date('effective_from');
            $table->date('effective_until')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::connection($conn)->create('products', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->nullable();
            $table->string('name');
            $table->string('slug');
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
            $table->decimal('base_selling_price', 10, 2)->default(0);
            $table->decimal('online_price', 10, 2)->nullable();
            $table->unsignedBigInteger('tax_rate_id')->nullable();
            $table->unsignedBigInteger('base_uom_id')->nullable();
            $table->decimal('reorder_level', 12, 4)->default(0);
            $table->integer('shelf_life_days')->nullable();
            $table->string('primary_image')->nullable();
            $table->json('secondary_images')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_available_online')->default(false);
            $table->timestamps();
        });

        Schema::connection($conn)->create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->nullable();
            $table->unsignedBigInteger('product_id');
            $table->string('variant_name');
            $table->string('sku')->nullable();
            $table->json('attributes')->nullable();
            $table->unsignedBigInteger('uom_id')->nullable();
            $table->decimal('uom_quantity', 12, 4)->default(1);
            $table->decimal('quantity_in_base_uom', 12, 4)->default(1);
            $table->decimal('base_selling_price_adjustment', 10, 2)->default(0);
            $table->decimal('variant_price', 10, 2)->nullable();
            $table->decimal('online_price', 10, 2)->nullable();
            $table->string('stock_status')->default('in_stock');
            $table->decimal('reorder_level', 12, 4)->default(0);
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
            $table->boolean('is_purchase_uom')->default(true);
            $table->boolean('is_sales_uom')->default(true);
            $table->boolean('is_inventory_uom')->default(true);
            $table->decimal('conversion_to_base', 12, 6)->default(1);
            $table->timestamps();
        });

        Schema::connection($conn)->create('store_products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('product_variant_id')->nullable();
            $table->decimal('store_selling_price', 10, 2)->nullable();
            $table->boolean('is_available')->default(true);
            $table->unsignedInteger('min_stock_level')->default(0);
            $table->timestamps();
        });

        Schema::connection($conn)->create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('customer_number');
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->text('address')->nullable();
            $table->string('customer_type')->default('walk_in');
            $table->decimal('loyalty_points', 12, 2)->default(0);
            $table->decimal('total_lifetime_purchases', 12, 2)->default(0);
            $table->unsignedInteger('total_visits')->default(0);
            $table->unsignedBigInteger('preferred_store_id')->nullable();
            $table->decimal('credit_limit', 12, 2)->default(0);
            $table->decimal('current_debt', 12, 2)->default(0);
            $table->decimal('store_credit_balance', 12, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('accepts_marketing')->default(true);
            $table->timestamp('registered_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::connection($conn)->create('promotions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->nullable();
            $table->text('description')->nullable();
            $table->string('promotion_type')->default('percentage_discount');
            $table->decimal('discount_value', 10, 2)->nullable();
            $table->unsignedInteger('buy_quantity')->nullable();
            $table->unsignedInteger('get_quantity')->nullable();
            $table->boolean('get_items_free')->default(false);
            $table->decimal('get_items_discount_percentage', 10, 2)->nullable();
            $table->decimal('min_purchase_amount', 10, 2)->nullable();
            $table->decimal('max_discount_amount', 10, 2)->nullable();
            $table->unsignedInteger('max_uses_per_customer')->nullable();
            $table->unsignedInteger('total_usage_limit')->nullable();
            $table->unsignedInteger('total_usage_count')->default(0);
            $table->timestamp('start_date')->nullable();
            $table->timestamp('end_date')->nullable();
            $table->json('active_days')->nullable();
            $table->time('active_time_start')->nullable();
            $table->time('active_time_end')->nullable();
            $table->json('applicable_store_ids')->nullable();
            $table->json('applicable_customer_group_ids')->nullable();
            $table->string('applicable_to')->default('all_products');
            $table->boolean('show_on_website')->default(false);
            $table->boolean('show_in_pos')->default(true);
            $table->string('banner_image_url')->nullable();
            $table->integer('display_priority')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('auto_apply')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::connection($conn)->create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code');
            $table->text('description')->nullable();
            $table->string('discount_type')->default('percentage');
            $table->decimal('discount_value', 10, 2)->default(0);
            $table->decimal('min_purchase_amount', 10, 2)->nullable();
            $table->decimal('max_discount_amount', 10, 2)->nullable();
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('usage_count')->default(0);
            $table->unsignedInteger('usage_limit_per_customer')->nullable();
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->string('applicable_to')->default('all_products');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }
}
