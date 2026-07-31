<?php

namespace Tests\Feature\Tenant\Product;

use App\Models\Tenant\Product;
use App\Models\Tenant\ProductUom;
use App\Services\Tenant\Product\ProductUomService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Stancl\Tenancy\Contracts\Tenant as TenantContract;
use Tests\TestCase;

class ProductUomServiceTest extends TestCase
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

    private function makeService(): ProductUomService
    {
        return new ProductUomService();
    }

    private function createProduct(array $overrides = []): Product
    {
        return Product::create(array_merge([
            'name' => 'Test Product',
            'slug' => 'test-product-'.uniqid(),
            'sku' => 'SKU-'.uniqid(),
            'base_uom_id' => 1,
        ], $overrides));
    }

    // =========================================================================
    // create()
    // =========================================================================

    public function test_create_adds_a_non_base_uom(): void
    {
        $product = $this->createProduct();

        $productUom = $this->makeService()->create($product, [
            'uom_id' => 2,
            'is_base_uom' => false,
            'conversion_to_base' => 12,
        ]);

        $this->assertSame(2, $productUom->uom_id);
        $this->assertFalse($productUom->is_base_uom);
        $this->assertEquals(12, $productUom->conversion_to_base);
    }

    public function test_create_throws_when_uom_already_assigned_to_product(): void
    {
        $product = $this->createProduct();
        $this->makeService()->create($product, ['uom_id' => 2, 'conversion_to_base' => 12]);

        $this->expectException(\InvalidArgumentException::class);

        $this->makeService()->create($product, ['uom_id' => 2, 'conversion_to_base' => 6]);
    }

    public function test_create_throws_for_inactive_uom(): void
    {
        $product = $this->createProduct();
        DB::connection('tenant')->table('units_of_measure')->where('id', 2)->update(['is_active' => false]);

        $this->expectException(ModelNotFoundException::class);

        $this->makeService()->create($product, ['uom_id' => 2, 'conversion_to_base' => 12]);
    }

    public function test_create_throws_for_non_positive_conversion_factor(): void
    {
        $product = $this->createProduct();

        $this->expectException(\InvalidArgumentException::class);

        $this->makeService()->create($product, ['uom_id' => 2, 'conversion_to_base' => 0]);
    }

    public function test_create_as_base_uom_forces_conversion_to_one_and_updates_product(): void
    {
        $product = $this->createProduct(['base_uom_id' => 1]);

        $productUom = $this->makeService()->create($product, [
            'uom_id' => 2,
            'is_base_uom' => true,
            'conversion_to_base' => 5,
        ]);

        $this->assertEquals(1, $productUom->conversion_to_base);
        $this->assertSame(2, $product->fresh()->base_uom_id);
    }

    public function test_create_as_base_uom_unsets_previous_base_uom(): void
    {
        $product = $this->createProduct();
        $original = $this->makeService()->create($product, [
            'uom_id' => 1,
            'is_base_uom' => true,
            'conversion_to_base' => 1,
        ]);

        $this->makeService()->create($product, [
            'uom_id' => 2,
            'is_base_uom' => true,
            'conversion_to_base' => 1,
        ]);

        $this->assertFalse($original->fresh()->is_base_uom);
    }

    // =========================================================================
    // update()
    // =========================================================================

    public function test_update_changes_configuration_flags(): void
    {
        $product = $this->createProduct();
        $productUom = $this->makeService()->create($product, ['uom_id' => 2, 'conversion_to_base' => 12]);

        $updated = $this->makeService()->update($productUom, ['is_purchase_uom' => false]);

        $this->assertFalse($updated->is_purchase_uom);
    }

    public function test_update_ignores_attempts_to_change_uom_id_or_product_id(): void
    {
        $product = $this->createProduct();
        $productUom = $this->makeService()->create($product, ['uom_id' => 2, 'conversion_to_base' => 12]);

        $updated = $this->makeService()->update($productUom, ['uom_id' => 1, 'product_id' => 999]);

        $this->assertSame(2, $updated->uom_id);
        $this->assertSame($product->id, $updated->product_id);
    }

    public function test_update_promoting_to_base_forces_conversion_to_one_and_updates_product(): void
    {
        $product = $this->createProduct(['base_uom_id' => 1]);
        $productUom = $this->makeService()->create($product, ['uom_id' => 2, 'conversion_to_base' => 12]);

        $updated = $this->makeService()->update($productUom, ['is_base_uom' => true]);

        $this->assertEquals(1, $updated->conversion_to_base);
        $this->assertSame(2, $product->fresh()->base_uom_id);
    }

    public function test_update_throws_when_removing_base_flag_from_only_uom(): void
    {
        $product = $this->createProduct();
        $productUom = $this->makeService()->create($product, [
            'uom_id' => 1,
            'is_base_uom' => true,
            'conversion_to_base' => 1,
        ]);

        $this->expectException(\InvalidArgumentException::class);

        $this->makeService()->update($productUom, ['is_base_uom' => false]);
    }

    public function test_update_throws_when_setting_non_positive_conversion_factor(): void
    {
        $product = $this->createProduct();
        $productUom = $this->makeService()->create($product, ['uom_id' => 2, 'conversion_to_base' => 12]);

        $this->expectException(\InvalidArgumentException::class);

        $this->makeService()->update($productUom, ['conversion_to_base' => -1]);
    }

    public function test_update_throws_when_changing_base_uom_conversion_away_from_one(): void
    {
        $product = $this->createProduct();
        $productUom = $this->makeService()->create($product, [
            'uom_id' => 2,
            'is_base_uom' => true,
            'conversion_to_base' => 1,
        ]);

        $this->expectException(\InvalidArgumentException::class);

        $this->makeService()->update($productUom, ['conversion_to_base' => 3]);
    }

    // =========================================================================
    // getBaseUom() / getPurchaseUoms() / getSalesUoms()
    // =========================================================================

    public function test_get_base_uom_returns_only_the_flagged_uom(): void
    {
        $product = $this->createProduct();
        $this->makeService()->create($product, ['uom_id' => 1, 'is_base_uom' => true, 'conversion_to_base' => 1]);
        $this->makeService()->create($product, ['uom_id' => 2, 'conversion_to_base' => 12]);

        $base = $this->makeService()->getBaseUom($product);

        $this->assertSame(1, $base->uom_id);
    }

    public function test_get_base_uom_returns_null_when_none_flagged(): void
    {
        $product = $this->createProduct();
        $this->makeService()->create($product, ['uom_id' => 2, 'conversion_to_base' => 12]);

        $this->assertNull($this->makeService()->getBaseUom($product));
    }

    public function test_get_purchase_uoms_filters_correctly(): void
    {
        $product = $this->createProduct();
        $this->makeService()->create($product, ['uom_id' => 1, 'is_purchase_uom' => true, 'conversion_to_base' => 1]);
        $this->makeService()->create($product, ['uom_id' => 2, 'is_purchase_uom' => false, 'conversion_to_base' => 12]);

        $result = $this->makeService()->getPurchaseUoms($product);

        $this->assertCount(1, $result);
        $this->assertSame(1, $result->first()->uom_id);
    }

    public function test_get_sales_uoms_filters_correctly(): void
    {
        $product = $this->createProduct();
        $this->makeService()->create($product, ['uom_id' => 1, 'is_sales_uom' => false, 'conversion_to_base' => 1]);
        $this->makeService()->create($product, ['uom_id' => 2, 'is_sales_uom' => true, 'conversion_to_base' => 12]);

        $result = $this->makeService()->getSalesUoms($product);

        $this->assertCount(1, $result);
        $this->assertSame(2, $result->first()->uom_id);
    }

    // =========================================================================
    // convertQuantity()
    // =========================================================================

    public function test_convert_quantity_between_two_uoms(): void
    {
        $product = $this->createProduct();
        $this->makeService()->create($product, ['uom_id' => 1, 'is_base_uom' => true, 'conversion_to_base' => 1]);
        $this->makeService()->create($product, ['uom_id' => 2, 'conversion_to_base' => 12]);

        // 1 box (uom 2) -> 12 pieces (uom 1)
        $result = $this->makeService()->convertQuantity($product, 1, 2, 1);

        $this->assertEquals(12, $result);
    }

    public function test_convert_quantity_returns_same_value_for_identical_uom(): void
    {
        $product = $this->createProduct();

        $result = $this->makeService()->convertQuantity($product, 5, 1, 1);

        $this->assertEquals(5, $result);
    }

    public function test_convert_quantity_throws_when_uom_not_assigned_to_product(): void
    {
        $product = $this->createProduct();
        $this->makeService()->create($product, ['uom_id' => 1, 'is_base_uom' => true, 'conversion_to_base' => 1]);

        $this->expectException(ModelNotFoundException::class);

        $this->makeService()->convertQuantity($product, 1, 1, 2);
    }

    // =========================================================================
    // Schema helpers
    // =========================================================================

    private function dropTestTables(): void
    {
        foreach (['product_uoms', 'products', 'units_of_measure'] as $table) {
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
            $table->unsignedBigInteger('base_uom_id')->nullable();
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
