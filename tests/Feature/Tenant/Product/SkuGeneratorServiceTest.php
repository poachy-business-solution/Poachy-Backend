<?php

namespace Tests\Feature\Tenant\Product;

use App\Models\Tenant\Product;
use App\Models\Tenant\ProductBrand;
use App\Models\Tenant\ProductCategory;
use App\Models\Tenant\ProductVariant;
use App\Services\Tenant\Product\SkuGeneratorService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Stancl\Tenancy\Contracts\Tenant as TenantContract;
use Tests\TestCase;

class SkuGeneratorServiceTest extends TestCase
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

    private function makeService(): SkuGeneratorService
    {
        return new SkuGeneratorService;
    }

    // =========================================================================
    // generate()
    // =========================================================================

    public function test_generate_uses_category_and_brand_codes(): void
    {
        $category = ProductCategory::create(['name' => 'Electronics']);
        $brand = ProductBrand::create(['name' => 'Samsung']);

        $sku = $this->makeService()->generate(['category_id' => $category->id, 'brand_id' => $brand->id]);

        $this->assertStringStartsWith('ELEC-SAMS-', $sku);
        $this->assertMatchesRegularExpression('/^ELEC-SAMS-[A-Z0-9]{4}$/', $sku);
    }

    public function test_generate_falls_back_to_generic_codes_when_category_and_brand_missing(): void
    {
        $sku = $this->makeService()->generate(['category_id' => 999999]);

        $this->assertStringStartsWith('GENR-NOBR-', $sku);
    }

    public function test_generate_pads_short_category_names(): void
    {
        $category = ProductCategory::create(['name' => 'TV']);

        $sku = $this->makeService()->generate(['category_id' => $category->id]);

        $this->assertStringStartsWith('TVXX-', $sku);
    }

    public function test_generate_returns_unique_sku_each_time(): void
    {
        $category = ProductCategory::create(['name' => 'Electronics']);
        $service = $this->makeService();

        $first = $service->generate(['category_id' => $category->id]);
        $second = $service->generate(['category_id' => $category->id]);

        $this->assertNotSame($first, $second);
    }

    // =========================================================================
    // generateVariantSku()
    // =========================================================================

    public function test_generate_variant_sku_uses_sequential_numbering_by_default(): void
    {
        $product = Product::create([
            'name' => 'Shirt', 'slug' => 'shirt', 'sku' => 'CLOT-NOBR-ABCD', 'base_uom_id' => 1,
        ]);

        $sku = $this->makeService()->generateVariantSku($product, []);

        $this->assertSame('CLOT-NOBR-ABCD-V01', $sku);
    }

    public function test_generate_variant_sku_increments_sequential_number(): void
    {
        $product = Product::create([
            'name' => 'Shirt', 'slug' => 'shirt', 'sku' => 'CLOT-NOBR-ABCD', 'base_uom_id' => 1,
        ]);
        ProductVariant::create([
            'product_id' => $product->id, 'sku' => 'CLOT-NOBR-ABCD-V01',
            'uom_id' => 1, 'uom_quantity' => 1, 'quantity_in_base_uom' => 1,
        ]);

        $sku = $this->makeService()->generateVariantSku($product, []);

        $this->assertSame('CLOT-NOBR-ABCD-V02', $sku);
    }

    public function test_generate_variant_sku_uses_attributes_when_provided(): void
    {
        $product = Product::create([
            'name' => 'Shirt', 'slug' => 'shirt', 'sku' => 'CLOT-NOBR-ABCD', 'base_uom_id' => 1,
        ]);

        $sku = $this->makeService()->generateVariantSku($product, [
            'attributes' => ['color' => 'Red', 'size' => 'Large'],
        ]);

        $this->assertStringStartsWith('CLOT-NOBR-ABCD-V', $sku);
        $this->assertNotSame('CLOT-NOBR-ABCD-V01', $sku);
    }

    // =========================================================================
    // generateBundleSku()
    // =========================================================================

    public function test_generate_bundle_sku_uses_category_code(): void
    {
        $category = ProductCategory::create(['name' => 'Food']);

        $sku = $this->makeService()->generateBundleSku($category->id);

        $this->assertStringStartsWith('BNDL-FOOD-B', $sku);
    }

    public function test_generate_bundle_sku_uses_generic_code_without_category(): void
    {
        $sku = $this->makeService()->generateBundleSku();

        $this->assertStringStartsWith('BNDL-GENR-B', $sku);
    }

    // =========================================================================
    // isValidFormat() / isValidVariantFormat() / isValidBundleFormat()
    // =========================================================================

    public function test_is_valid_format_accepts_correct_pattern(): void
    {
        $this->assertTrue($this->makeService()->isValidFormat('ELEC-SAMS-8A4D'));
    }

    public function test_is_valid_format_rejects_incorrect_pattern(): void
    {
        $this->assertFalse($this->makeService()->isValidFormat('not-a-valid-sku'));
        $this->assertFalse($this->makeService()->isValidFormat('ELEC-SAMS'));
    }

    public function test_is_valid_variant_format_accepts_correct_pattern(): void
    {
        $this->assertTrue($this->makeService()->isValidVariantFormat('ELEC-SAMS-8A4D-V01'));
    }

    public function test_is_valid_bundle_format_accepts_correct_pattern(): void
    {
        $this->assertTrue($this->makeService()->isValidBundleFormat('BNDL-FOOD-B7F3'));
    }

    public function test_is_valid_bundle_format_rejects_product_sku(): void
    {
        $this->assertFalse($this->makeService()->isValidBundleFormat('ELEC-SAMS-8A4D'));
    }

    // =========================================================================
    // parse() / parseVariantSku() / parseBundleSku()
    // =========================================================================

    public function test_parse_decomposes_product_sku(): void
    {
        $result = $this->makeService()->parse('ELEC-SAMS-8A4D');

        $this->assertSame(['category_code' => 'ELEC', 'brand_code' => 'SAMS', 'unique_code' => '8A4D'], $result);
    }

    public function test_parse_throws_for_invalid_format(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->makeService()->parse('not-valid');
    }

    public function test_parse_variant_sku_decomposes_including_product_sku(): void
    {
        $result = $this->makeService()->parseVariantSku('ELEC-SAMS-8A4D-V01');

        $this->assertSame('ELEC-SAMS-8A4D', $result['product_sku']);
        $this->assertSame('V01', $result['variant_code']);
    }

    public function test_parse_variant_sku_throws_for_invalid_format(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->makeService()->parseVariantSku('ELEC-SAMS-8A4D');
    }

    public function test_parse_bundle_sku_decomposes_correctly(): void
    {
        $result = $this->makeService()->parseBundleSku('BNDL-FOOD-B7F3');

        $this->assertSame('BNDL', $result['prefix']);
        $this->assertSame('FOOD', $result['category_code']);
        $this->assertSame('B7F3', $result['unique_code']);
    }

    public function test_parse_bundle_sku_throws_for_invalid_format(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->makeService()->parseBundleSku('ELEC-SAMS-8A4D');
    }

    // =========================================================================
    // generateVariantCodeFromUom()
    // =========================================================================

    public function test_generate_variant_code_from_uom_formats_quantity_and_unit(): void
    {
        $code = $this->makeService()->generateVariantCodeFromUom('Kilogram', 5);

        $this->assertSame('5KI', $code);
    }

    // =========================================================================
    // Schema helpers
    // =========================================================================

    private function dropTestTables(): void
    {
        foreach (['product_variants', 'product_bundles', 'products', 'product_categories', 'product_brands'] as $table) {
            Schema::connection('tenant')->dropIfExists($table);
        }
    }

    private function createMinimalSchema(): void
    {
        $conn = 'tenant';

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
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_available_online')->default(false);
            $table->unsignedBigInteger('base_uom_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::connection($conn)->create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->nullable();
            $table->unsignedBigInteger('product_id');
            $table->string('sku')->unique();
            $table->unsignedBigInteger('uom_id');
            $table->decimal('uom_quantity', 15, 4);
            $table->decimal('quantity_in_base_uom', 15, 4);
            $table->decimal('base_selling_price_adjustment', 15, 2)->default(0);
            $table->string('stock_status')->default('in_stock');
            $table->decimal('reorder_level', 15, 4)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::connection($conn)->create('product_bundles', function (Blueprint $table) {
            $table->id();
            $table->string('bundle_name');
            $table->string('bundle_sku')->unique();
            $table->timestamps();
            $table->softDeletes();
        });
    }
}
