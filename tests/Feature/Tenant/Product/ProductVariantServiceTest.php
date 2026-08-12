<?php

namespace Tests\Feature\Tenant\Product;

use App\Models\Tenant\Product;
use App\Models\Tenant\ProductUom;
use App\Services\Tenant\Product\ProductVariantService;
use App\Services\Tenant\Product\SkuGeneratorService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Stancl\Tenancy\Contracts\Tenant as TenantContract;
use Tests\TestCase;

class ProductVariantServiceTest extends TestCase
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

    private function makeService(): ProductVariantService
    {
        return new ProductVariantService(new SkuGeneratorService);
    }

    private function createVariableProduct(array $overrides = []): Product
    {
        $product = Product::create(array_merge([
            'name' => 'Variable Product',
            'slug' => 'variable-product-'.uniqid(),
            'sku' => 'SKU-'.uniqid(),
            'product_type' => 'variable',
            'base_uom_id' => 1,
            'base_selling_price' => 100,
        ], $overrides));

        ProductUom::create([
            'product_id' => $product->id,
            'uom_id' => 1,
            'is_base_uom' => true,
            'conversion_to_base' => 1,
        ]);
        ProductUom::create([
            'product_id' => $product->id,
            'uom_id' => 2,
            'is_base_uom' => false,
            'conversion_to_base' => 12,
        ]);

        return $product;
    }

    private function createSimpleProduct(array $overrides = []): Product
    {
        return Product::create(array_merge([
            'name' => 'Simple Product',
            'slug' => 'simple-product-'.uniqid(),
            'sku' => 'SKU-'.uniqid(),
            'product_type' => 'simple',
            'base_uom_id' => 1,
        ], $overrides));
    }

    // =========================================================================
    // create()
    // =========================================================================

    public function test_create_throws_for_simple_product(): void
    {
        $product = $this->createSimpleProduct();

        $this->expectException(\InvalidArgumentException::class);

        $this->makeService()->create($product, ['uom_id' => 1, 'uom_quantity' => 1]);
    }

    public function test_create_generates_sku_when_not_provided(): void
    {
        $product = $this->createVariableProduct();

        $variant = $this->makeService()->create($product, ['uom_id' => 1, 'uom_quantity' => 1]);

        $this->assertStringStartsWith($product->sku.'-', $variant->sku);
    }

    public function test_create_throws_when_sku_already_exists(): void
    {
        $product = $this->createVariableProduct();
        $this->makeService()->create($product, ['sku' => 'DUP-SKU', 'uom_id' => 1, 'uom_quantity' => 1]);

        $this->expectException(\InvalidArgumentException::class);

        $this->makeService()->create($product, ['sku' => 'DUP-SKU', 'uom_id' => 2, 'uom_quantity' => 1]);
    }

    public function test_create_calculates_quantity_in_base_uom(): void
    {
        $product = $this->createVariableProduct();

        $variant = $this->makeService()->create($product, [
            'sku' => 'VAR-1',
            'uom_id' => 2,
            'uom_quantity' => 1,
        ]);

        $this->assertEquals(12, $variant->quantity_in_base_uom);
    }

    public function test_create_calculates_variant_price_from_adjustment(): void
    {
        $product = $this->createVariableProduct(['base_selling_price' => 100]);

        $variant = $this->makeService()->create($product, [
            'sku' => 'VAR-2',
            'uom_id' => 1,
            'uom_quantity' => 1,
            'base_selling_price_adjustment' => 25,
        ]);

        $this->assertEquals(125, $variant->variant_price);
    }

    public function test_create_calculates_online_price_from_product_online_price_and_adjustment(): void
    {
        $product = $this->createVariableProduct(['online_price' => 200]);

        $variant = $this->makeService()->create($product, [
            'sku' => 'VAR-3',
            'uom_id' => 1,
            'uom_quantity' => 1,
            'base_selling_price_adjustment' => 10,
        ]);

        $this->assertEquals(210, $variant->online_price);
    }

    public function test_create_throws_when_uom_not_configured_for_product(): void
    {
        $product = $this->createVariableProduct();

        $this->expectException(\InvalidArgumentException::class);

        $this->makeService()->create($product, ['sku' => 'VAR-4', 'uom_id' => 999, 'uom_quantity' => 1]);
    }

    // =========================================================================
    // getById() / update()
    // =========================================================================

    public function test_get_by_id_returns_variant_with_relations(): void
    {
        $product = $this->createVariableProduct();
        $variant = $this->makeService()->create($product, ['sku' => 'VAR-5', 'uom_id' => 1, 'uom_quantity' => 1]);

        $found = $this->makeService()->getById($variant->id);

        $this->assertSame($variant->id, $found->id);
    }

    public function test_get_by_id_throws_for_unknown_id(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->makeService()->getById(999999);
    }

    public function test_update_throws_when_new_sku_collides(): void
    {
        $product = $this->createVariableProduct();
        $this->makeService()->create($product, ['sku' => 'TAKEN', 'uom_id' => 1, 'uom_quantity' => 1]);
        $variant = $this->makeService()->create($product, ['sku' => 'MINE', 'uom_id' => 2, 'uom_quantity' => 1]);

        $this->expectException(\InvalidArgumentException::class);

        $this->makeService()->update($variant, ['sku' => 'TAKEN']);
    }

    public function test_update_recalculates_quantity_in_base_uom_when_uom_quantity_changes(): void
    {
        $product = $this->createVariableProduct();
        $variant = $this->makeService()->create($product, ['sku' => 'VAR-6', 'uom_id' => 2, 'uom_quantity' => 1]);

        $updated = $this->makeService()->update($variant, ['uom_quantity' => 3]);

        $this->assertEquals(36, $updated->quantity_in_base_uom);
    }

    public function test_update_recalculates_variant_price_from_new_adjustment(): void
    {
        $product = $this->createVariableProduct(['base_selling_price' => 100]);
        $variant = $this->makeService()->create($product, [
            'sku' => 'VAR-7', 'uom_id' => 1, 'uom_quantity' => 1, 'base_selling_price_adjustment' => 0,
        ]);

        $updated = $this->makeService()->update($variant, ['base_selling_price_adjustment' => 50]);

        $this->assertEquals(150, $updated->variant_price);
    }

    public function test_update_ignores_attempt_to_change_product_id(): void
    {
        $product = $this->createVariableProduct();
        $otherProduct = $this->createVariableProduct();
        $variant = $this->makeService()->create($product, ['sku' => 'VAR-8', 'uom_id' => 1, 'uom_quantity' => 1]);

        $updated = $this->makeService()->update($variant, ['product_id' => $otherProduct->id]);

        $this->assertSame($product->id, $updated->product_id);
    }

    // =========================================================================
    // toggleActive() / updateInventoryDetails()
    // =========================================================================

    public function test_toggle_active_flips_both_directions(): void
    {
        $product = $this->createVariableProduct();
        $variant = $this->makeService()->create($product, ['sku' => 'VAR-9', 'uom_id' => 1, 'uom_quantity' => 1, 'is_active' => true]);
        $service = $this->makeService();

        $off = $service->toggleActive($variant);
        $this->assertFalse($off->is_active);

        $on = $service->toggleActive($off);
        $this->assertTrue($on->is_active);
    }

    public function test_update_inventory_details_updates_fields(): void
    {
        $product = $this->createVariableProduct();
        $variant = $this->makeService()->create($product, ['sku' => 'VAR-10', 'uom_id' => 1, 'uom_quantity' => 1]);

        $updated = $this->makeService()->updateInventoryDetails($variant, ['reorder_level' => 15, 'shelf_life_days' => 30]);

        $this->assertEquals(15, $updated->reorder_level);
        $this->assertSame(30, $updated->shelf_life_days);
    }

    // =========================================================================
    // listForProduct() / list()
    // =========================================================================

    public function test_list_for_product_filters_by_active_status(): void
    {
        $product = $this->createVariableProduct();
        $this->makeService()->create($product, ['sku' => 'VAR-11', 'uom_id' => 1, 'uom_quantity' => 1, 'is_active' => true]);
        $this->makeService()->create($product, ['sku' => 'VAR-12', 'uom_id' => 2, 'uom_quantity' => 1, 'is_active' => false]);

        $result = $this->makeService()->listForProduct($product, ['is_active' => true]);

        $this->assertCount(1, $result);
        $this->assertSame('VAR-11', $result->first()->sku);
    }

    public function test_list_for_product_filters_by_available_online(): void
    {
        $product = $this->createVariableProduct();
        $this->makeService()->create($product, ['sku' => 'VAR-13', 'uom_id' => 1, 'uom_quantity' => 1, 'online_price' => 50]);
        $this->makeService()->create($product, ['sku' => 'VAR-14', 'uom_id' => 2, 'uom_quantity' => 1, 'online_price' => null]);

        $result = $this->makeService()->listForProduct($product, ['available_online' => true]);

        $this->assertCount(1, $result);
        $this->assertSame('VAR-13', $result->first()->sku);
    }

    public function test_list_across_products_filters_by_product_id(): void
    {
        $productA = $this->createVariableProduct();
        $productB = $this->createVariableProduct();
        $this->makeService()->create($productA, ['sku' => 'VAR-15', 'uom_id' => 1, 'uom_quantity' => 1]);
        $this->makeService()->create($productB, ['sku' => 'VAR-16', 'uom_id' => 1, 'uom_quantity' => 1]);

        $result = $this->makeService()->list(['product_id' => $productA->id]);

        $this->assertSame(1, $result->total());
    }

    // =========================================================================
    // Schema helpers
    // =========================================================================

    private function dropTestTables(): void
    {
        foreach (['product_price_history', 'product_variants', 'product_uoms', 'products', 'units_of_measure'] as $table) {
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
            ['id' => 1, 'code' => 'pcs', 'name' => 'Piece', 'is_base_unit' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'code' => 'box', 'name' => 'Box', 'is_base_unit' => false, 'created_at' => now(), 'updated_at' => now()],
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
            $table->decimal('base_selling_price', 15, 2)->default(0);
            $table->unsignedBigInteger('base_uom_id')->nullable();
            $table->decimal('online_price', 12, 2)->nullable();
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

        Schema::connection($conn)->create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->nullable();
            $table->unsignedBigInteger('product_id');
            $table->string('variant_name')->nullable();
            $table->string('sku')->unique();
            $table->json('attributes')->nullable();
            $table->unsignedBigInteger('uom_id');
            $table->decimal('uom_quantity', 15, 4);
            $table->decimal('quantity_in_base_uom', 15, 4);
            $table->decimal('base_selling_price_adjustment', 15, 2)->default(0);
            $table->decimal('variant_price', 15, 2)->nullable();
            $table->decimal('online_price', 12, 2)->nullable();
            $table->string('stock_status')->default('in_stock');
            $table->decimal('reorder_level', 15, 4)->default(0);
            $table->integer('shelf_life_days')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
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
