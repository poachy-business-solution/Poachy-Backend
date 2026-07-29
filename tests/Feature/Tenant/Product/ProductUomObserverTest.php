<?php

namespace Tests\Feature\Tenant\Product;

use App\Models\Tenant\Product;
use App\Models\Tenant\ProductUom;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Stancl\Tenancy\Contracts\Tenant as TenantContract;
use Tests\TestCase;

class ProductUomObserverTest extends TestCase
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

    public function test_promoting_a_product_uom_to_base_updates_the_parent_products_base_uom_id(): void
    {
        $product = Product::create([
            'name' => 'Test Product',
            'slug' => 'test-product-'.uniqid(),
            'sku' => 'SKU-'.uniqid(),
            'base_uom_id' => 1,
            'is_available_online' => false,
        ]);

        $productUom = ProductUom::create([
            'product_id' => $product->id,
            'uom_id' => 2,
            'is_base_uom' => false,
            'conversion_to_base' => 1,
        ]);

        $productUom->update(['is_base_uom' => true]);

        $product->refresh();
        $this->assertSame(2, $product->base_uom_id);
    }

    public function test_changing_conversion_to_base_alone_does_not_touch_product_base_uom_id(): void
    {
        $product = Product::create([
            'name' => 'Test Product',
            'slug' => 'test-product-'.uniqid(),
            'sku' => 'SKU-'.uniqid(),
            'base_uom_id' => 1,
            'is_available_online' => false,
        ]);

        $productUom = ProductUom::create([
            'product_id' => $product->id,
            'uom_id' => 2,
            'is_base_uom' => false,
            'conversion_to_base' => 1,
        ]);

        $productUom->update(['conversion_to_base' => 12]);

        $product->refresh();
        $this->assertSame(1, $product->base_uom_id);
    }

    private function dropTestTables(): void
    {
        foreach (['product_uoms', 'products'] as $table) {
            Schema::connection('tenant')->dropIfExists($table);
        }
    }

    private function createMinimalSchema(): void
    {
        $conn = 'tenant';

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
            $table->unsignedBigInteger('category_id')->nullable();
            $table->unsignedBigInteger('brand_id')->nullable();
            $table->unsignedBigInteger('tax_rate_id')->nullable();
            $table->decimal('online_price', 15, 2)->nullable();
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
    }
}
