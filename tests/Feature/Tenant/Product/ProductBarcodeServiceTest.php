<?php

namespace Tests\Feature\Tenant\Product;

use App\Exceptions\Tenant\ProductBarcodeLookupConflictException;
use App\Models\Tenant\Product;
use App\Models\Tenant\ProductBarcode;
use App\Models\Tenant\ProductBarcodeSuggestion;
use App\Models\Tenant\ProductScaleBarcodeFormat;
use App\Models\Tenant\ProductUom;
use App\Models\Tenant\ProductVariant;
use App\Models\Tenant\StoreProduct;
use App\Services\Tenant\Product\ProductBarcodeService;
use App\Services\Tenant\Product\ProductBarcodeSuggestionService;
use App\Services\Tenant\Product\ProductScaleBarcodeFormatService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class ProductBarcodeServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'tenant');
        Config::set('database.connections.tenant.database', 'poachy_test');
        DB::purge('tenant');
        DB::connection('tenant')->statement('SET foreign_key_checks = 0');

        $this->dropTables();
        $this->createSchema();
    }

    protected function tearDown(): void
    {
        $this->dropTables();
        DB::connection('tenant')->statement('SET foreign_key_checks = 1');

        parent::tearDown();
    }

    public function test_lookup_resolves_active_product_barcode(): void
    {
        $product = $this->product();
        $barcode = $this->barcodeFor($product, ['barcode' => '6161234567890']);

        $resolved = $this->service()->lookup('6161234567890');

        $this->assertTrue($resolved->is($barcode));
        $this->assertTrue($resolved->barcodeable->is($product));
    }

    public function test_store_specific_barcode_takes_precedence_when_store_context_is_supplied(): void
    {
        $globalProduct = $this->product(['name' => 'Global Product', 'sku' => 'SKU-GLOBAL']);
        $storeProduct = $this->product(['name' => 'Store Product', 'sku' => 'SKU-STORE']);

        $this->barcodeFor($globalProduct, ['barcode' => '6161234567890']);
        $storeBarcode = $this->barcodeFor($storeProduct, ['barcode' => '6161234567890', 'store_id' => 2]);

        $resolved = $this->service()->lookup('6161234567890', 2);

        $this->assertTrue($resolved->is($storeBarcode));
        $this->assertTrue($resolved->barcodeable->is($storeProduct));
    }

    public function test_lookup_returns_conflict_for_ambiguous_global_matches(): void
    {
        $this->barcodeFor($this->product(['sku' => 'SKU-ONE']), [
            'barcode' => '6161234567890',
            'supplier_id' => 1,
        ]);
        $this->barcodeFor($this->product(['sku' => 'SKU-TWO']), [
            'barcode' => '6161234567890',
            'supplier_id' => 2,
        ]);

        $this->expectException(ProductBarcodeLookupConflictException::class);

        $this->service()->lookup('6161234567890');
    }

    public function test_lookup_ignores_inactive_products(): void
    {
        $product = $this->product(['is_active' => false]);
        $this->barcodeFor($product, ['barcode' => '6161234567890']);

        $this->expectException(NotFoundHttpException::class);

        $this->service()->lookup('6161234567890');
    }

    public function test_store_context_includes_price_and_availability(): void
    {
        $product = $this->product();
        $barcode = $this->barcodeFor($product, ['barcode' => '6161234567890']);

        Model::withoutEvents(fn () => StoreProduct::create([
            'store_id' => 3,
            'product_id' => $product->id,
            'store_selling_price' => 95,
            'is_available' => true,
            'min_stock_level' => 4,
        ]));

        $context = $this->service()->storeContext($barcode->load('barcodeable'), 3);

        $this->assertSame(3, $context['store_id']);
        $this->assertTrue($context['is_available']);
        $this->assertSame('95.00', (string) $context['store_selling_price']);
        $this->assertSame(4, $context['min_stock_level']);
    }

    public function test_create_marks_existing_primary_barcode_as_non_primary(): void
    {
        $product = $this->product();

        $first = $this->service()->createFor($product, [
            'barcode' => '6161234567890',
            'barcode_type' => 'EAN-13',
            'is_primary' => true,
        ]);
        $second = $this->service()->createFor($product, [
            'barcode' => '6161234567891',
            'barcode_type' => 'EAN-13',
            'is_primary' => true,
        ]);

        $this->assertFalse($first->refresh()->is_primary);
        $this->assertTrue($second->refresh()->is_primary);
    }

    public function test_lookup_resolves_variant_barcode(): void
    {
        $product = $this->product(['product_type' => 'variable']);
        $variant = Model::withoutEvents(fn () => ProductVariant::create([
            'product_id' => $product->id,
            'variant_name' => 'Small',
            'sku' => 'SKU-001-S',
            'uom_id' => 1,
            'uom_quantity' => 1,
            'quantity_in_base_uom' => 1,
        ]));
        $this->barcodeFor($variant, ['barcode' => '6161234567890']);

        $resolved = $this->service()->lookup('6161234567890');

        $this->assertTrue($resolved->barcodeable->is($variant));
        $this->assertTrue($resolved->barcodeable->relationLoaded('product'));
    }

    public function test_manual_workflow_records_manual_source_and_metadata(): void
    {
        $product = $this->product();

        $barcode = $this->service()->createManual([
            'target_type' => 'product',
            'product_uuid' => $product->uuid,
            'barcode' => '6161234567890',
            'barcode_type' => 'EAN-13',
        ]);

        $this->assertSame('manual', $barcode->source);
        $this->assertSame('manual_entry', $barcode->metadata['workflow']);
    }

    public function test_scanned_workflow_records_scanned_attachment_metadata(): void
    {
        $product = $this->product();

        $barcode = $this->service()->createScanned([
            'target_type' => 'product',
            'product_uuid' => $product->uuid,
            'barcode' => '6161234567890',
            'barcode_type' => 'EAN-13',
            'source' => 'manufacturer',
        ]);

        $this->assertSame('manufacturer', $barcode->source);
        $this->assertSame('scanned_attachment', $barcode->metadata['workflow']);
    }

    public function test_generated_workflow_creates_unique_internal_barcode(): void
    {
        $product = $this->product();

        $barcode = $this->service()->generate([
            'target_type' => 'product',
            'product_uuid' => $product->uuid,
            'is_primary' => true,
        ]);

        $this->assertStringStartsWith('PCH-', $barcode->barcode);
        $this->assertSame('INTERNAL', $barcode->barcode_type);
        $this->assertSame('generated', $barcode->source);
        $this->assertSame('backend_generation', $barcode->metadata['workflow']);
        $this->assertTrue($barcode->is_primary);
    }

    public function test_supplier_workflow_requires_supplier_id(): void
    {
        $product = $this->product();

        $this->expectException(HttpException::class);

        $this->service()->createSupplier([
            'target_type' => 'product',
            'product_uuid' => $product->uuid,
            'barcode' => '6161234567890',
        ]);
    }

    public function test_supplier_workflow_records_supplier_source(): void
    {
        $product = $this->product();

        $barcode = $this->service()->createSupplier([
            'target_type' => 'product',
            'product_uuid' => $product->uuid,
            'barcode' => 'SUP-001',
            'supplier_id' => 7,
        ]);

        $this->assertSame('supplier', $barcode->source);
        $this->assertSame(7, $barcode->supplier_id);
        $this->assertSame('supplier_catalog', $barcode->metadata['workflow']);
    }

    public function test_scale_workflow_registers_scale_barcode_without_parsing(): void
    {
        $product = $this->product(['is_weighed' => true]);

        $barcode = $this->service()->createScale([
            'target_type' => 'product',
            'product_uuid' => $product->uuid,
            'barcode' => '2100012345678',
            'scale_format' => 'prefix-plu-weight-checkdigit',
        ]);

        $this->assertSame('SCALE', $barcode->barcode_type);
        $this->assertSame('scale', $barcode->source);
        $this->assertSame('scale_registration', $barcode->metadata['workflow']);
        $this->assertSame('prefix-plu-weight-checkdigit', $barcode->metadata['scale_format']);
    }

    public function test_lookup_parses_scale_weight_barcode_after_static_miss(): void
    {
        $product = $this->product(['is_weighed' => true]);
        $pluBarcode = $this->barcodeFor($product, [
            'barcode' => '01234',
            'barcode_type' => 'SCALE',
            'source' => 'scale',
        ]);
        ProductScaleBarcodeFormat::create([
            'name' => 'EAN13 weight scale',
            'prefix' => '21',
            'length' => 13,
            'product_code_start' => 2,
            'product_code_length' => 5,
            'value_start' => 7,
            'value_length' => 5,
            'value_type' => 'weight',
            'decimal_places' => 3,
            'checksum' => 'ean13',
        ]);

        $result = $this->service()->lookupResult('2101234007501');

        $this->assertTrue($result['barcode']->is($pluBarcode));
        $this->assertSame('01234', $result['scale_barcode']['plu']);
        $this->assertSame('00750', $result['scale_barcode']['raw_value']);
        $this->assertSame(0.75, $result['scale_barcode']['value']);

        $saleLine = $this->service()->saleLine($result['barcode'], $result['scale_barcode']);

        $this->assertSame(0.75, $saleLine['quantity']);
        $this->assertSame(0.75, $saleLine['quantity_in_base_uom']);
        $this->assertSame(0.75, $saleLine['sale_item_payload']['quantity']);
    }

    public function test_static_barcode_exact_match_takes_precedence_over_scale_parser(): void
    {
        $exactProduct = $this->product(['sku' => 'SKU-EXACT']);
        $scaleProduct = $this->product(['sku' => 'SKU-SCALE', 'is_weighed' => true]);
        $exactBarcode = $this->barcodeFor($exactProduct, ['barcode' => '2101234007501']);
        $this->barcodeFor($scaleProduct, [
            'barcode' => '01234',
            'barcode_type' => 'SCALE',
            'source' => 'scale',
        ]);
        ProductScaleBarcodeFormat::create([
            'name' => 'EAN13 weight scale',
            'prefix' => '21',
            'length' => 13,
            'product_code_start' => 2,
            'product_code_length' => 5,
            'value_start' => 7,
            'value_length' => 5,
            'value_type' => 'weight',
            'decimal_places' => 3,
            'checksum' => 'ean13',
        ]);

        $result = $this->service()->lookupResult('2101234007501');

        $this->assertTrue($result['barcode']->is($exactBarcode));
        $this->assertNull($result['scale_barcode']);
    }

    public function test_scale_barcode_format_service_created_format_is_used_by_parser(): void
    {
        $product = $this->product(['is_weighed' => true]);
        $pluBarcode = $this->barcodeFor($product, [
            'barcode' => '54321',
            'barcode_type' => 'SCALE',
            'source' => 'scale',
        ]);

        $this->scaleFormatService()->create([
            'name' => 'EAN13 quantity scale',
            'prefix' => '22',
            'length' => 13,
            'product_code_start' => 2,
            'product_code_length' => 5,
            'value_start' => 7,
            'value_length' => 5,
            'value_type' => 'quantity',
            'decimal_places' => 2,
            'checksum' => 'ean13',
        ]);

        $result = $this->service()->lookupResult('2254321002504');

        $this->assertTrue($result['barcode']->is($pluBarcode));
        $this->assertSame('quantity', $result['scale_barcode']['value_type']);
        $this->assertSame(2.5, $result['scale_barcode']['value']);
        $this->assertSame(2.5, $this->service()->saleLine($result['barcode'], $result['scale_barcode'])['quantity']);
    }

    public function test_scale_barcode_format_delete_deactivates_without_removing_row(): void
    {
        $format = $this->scaleFormatService()->create([
            'name' => 'EAN13 weight scale',
            'prefix' => '21',
            'length' => 13,
            'product_code_start' => 2,
            'product_code_length' => 5,
            'value_start' => 7,
            'value_length' => 5,
            'value_type' => 'weight',
            'decimal_places' => 3,
            'checksum' => 'ean13',
        ]);

        $deactivated = $this->scaleFormatService()->deactivate($format);

        $this->assertFalse($deactivated->is_active);
        $this->assertDatabaseHas('product_scale_barcode_formats', [
            'id' => $format->id,
            'is_active' => false,
        ], 'tenant');
    }

    public function test_import_workflow_returns_created_rows_and_row_errors(): void
    {
        $product = $this->product();

        $result = $this->service()->importBatch([
            [
                'target_type' => 'product',
                'product_uuid' => $product->uuid,
                'barcode' => '6161234567890',
                'barcode_type' => 'EAN-13',
            ],
            [
                'target_type' => 'product',
                'product_uuid' => $product->uuid,
                'barcode' => '6161234567890',
                'barcode_type' => 'EAN-13',
            ],
        ]);

        $this->assertCount(1, $result['created']);
        $this->assertCount(1, $result['errors']);
        $this->assertSame('imported', $result['created']->first()->source);
        $this->assertSame('structured_import', $result['created']->first()->metadata['workflow']);
        $this->assertSame(1, $result['errors'][0]['row']);
    }

    public function test_sale_line_payload_for_product_uom_scan_is_pos_ready(): void
    {
        $product = $this->product();
        $productUomId = DB::connection('tenant')->table('product_uoms')->insertGetId([
            'product_id' => $product->id,
            'uom_id' => 2,
            'is_base_uom' => false,
            'is_sales_uom' => true,
            'conversion_to_base' => 12,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $productUom = ProductUom::findOrFail($productUomId);
        $barcode = $this->barcodeFor($productUom, ['barcode' => 'CTN-001']);

        $saleLine = $this->service()->saleLine($barcode->load('barcodeable'));

        $this->assertSame('product_uom', $saleLine['target_type']);
        $this->assertSame($product->id, $saleLine['product_id']);
        $this->assertSame($productUomId, $saleLine['product_uom_id']);
        $this->assertSame(2, $saleLine['uom_id']);
        $this->assertSame('ctn', $saleLine['uom_code']);
        $this->assertSame(12.0, $saleLine['quantity_in_base_uom']);
        $this->assertSame([
            'product_id' => $product->id,
            'variant_id' => null,
            'bundle_id' => null,
            'uom_id' => 2,
            'quantity' => 1.0,
        ], $saleLine['sale_item_payload']);
    }

    public function test_unknown_barcode_suggestion_stays_pending_until_approved(): void
    {
        $product = $this->product();

        $suggestion = $this->suggestionService()->suggest([
            'target_type' => 'product',
            'product_uuid' => $product->uuid,
            'barcode' => 'UNKNOWN-001',
            'barcode_type' => 'CODE-128',
            'notes' => 'Cashier matched item at checkout',
        ], 5);

        $this->assertSame(ProductBarcodeSuggestion::STATUS_PENDING, $suggestion->status);

        $this->expectException(NotFoundHttpException::class);
        $this->service()->lookup('UNKNOWN-001');
    }

    public function test_approving_unknown_barcode_suggestion_creates_active_barcode(): void
    {
        $product = $this->product();
        $suggestion = $this->suggestionService()->suggest([
            'target_type' => 'product',
            'product_uuid' => $product->uuid,
            'barcode' => 'UNKNOWN-002',
            'barcode_type' => 'CODE-128',
        ], 5);

        $result = $this->suggestionService()->approve($suggestion, ['is_primary' => true], 9);
        $approvedSuggestion = $result['suggestion'];
        $barcode = $result['barcode'];

        $this->assertSame(ProductBarcodeSuggestion::STATUS_APPROVED, $approvedSuggestion->status);
        $this->assertSame($barcode->id, $approvedSuggestion->approved_barcode_id);
        $this->assertSame('approved_unknown_barcode_suggestion', $barcode->metadata['workflow']);
        $this->assertSame($suggestion->id, $barcode->metadata['suggestion_id']);
        $this->assertTrue($barcode->is_primary);
        $this->assertTrue($this->service()->lookup('UNKNOWN-002')->barcodeable->is($product));
    }

    public function test_rejecting_unknown_barcode_suggestion_does_not_create_barcode(): void
    {
        $product = $this->product();
        $suggestion = $this->suggestionService()->suggest([
            'target_type' => 'product',
            'product_uuid' => $product->uuid,
            'barcode' => 'UNKNOWN-003',
        ], 5);

        $rejected = $this->suggestionService()->reject($suggestion, [
            'rejection_reason' => 'Barcode belongs to another product',
        ], 9);

        $this->assertSame(ProductBarcodeSuggestion::STATUS_REJECTED, $rejected->status);
        $this->assertSame('Barcode belongs to another product', $rejected->rejection_reason);
        $this->assertFalse(ProductBarcode::where('barcode', 'UNKNOWN-003')->exists());
    }

    public function test_suggestion_conflicts_with_existing_active_barcode(): void
    {
        $product = $this->product();
        $this->barcodeFor($product, ['barcode' => 'KNOWN-001']);

        $this->expectException(HttpException::class);

        $this->suggestionService()->suggest([
            'target_type' => 'product',
            'product_uuid' => $product->uuid,
            'barcode' => 'KNOWN-001',
        ], 5);
    }

    private function service(): ProductBarcodeService
    {
        return new ProductBarcodeService;
    }

    private function suggestionService(): ProductBarcodeSuggestionService
    {
        return new ProductBarcodeSuggestionService($this->service());
    }

    private function scaleFormatService(): ProductScaleBarcodeFormatService
    {
        return new ProductScaleBarcodeFormatService;
    }

    private function product(array $overrides = []): Product
    {
        return Model::withoutEvents(fn () => Product::create(array_merge([
            'uuid' => (string) Str::uuid(),
            'name' => 'Product One',
            'slug' => 'product-one-'.uniqid(),
            'sku' => 'SKU-001-'.uniqid(),
            'base_uom_id' => 1,
            'base_selling_price' => 100,
            'is_active' => true,
        ], $overrides)));
    }

    private function barcodeFor(Model $entity, array $overrides = []): ProductBarcode
    {
        return ProductBarcode::create(array_merge([
            'barcodeable_type' => $entity->getMorphClass(),
            'barcodeable_id' => $entity->getKey(),
            'barcode' => '6161234567890',
            'barcode_type' => 'EAN-13',
            'is_active' => true,
            'is_primary' => true,
            'source' => 'manufacturer',
        ], $overrides));
    }

    private function dropTables(): void
    {
        foreach ([
            'store_products',
            'product_barcode_suggestions',
            'product_barcodes',
            'product_scale_barcode_formats',
            'product_variants',
            'product_uoms',
            'products',
            'stores',
            'suppliers',
            'units_of_measure',
            'users',
        ] as $table) {
            Schema::connection('tenant')->dropIfExists($table);
        }
    }

    private function createSchema(): void
    {
        $conn = 'tenant';

        Schema::connection($conn)->create('units_of_measure', function (Blueprint $table) {
            $table->id();
            $table->string('code');
            $table->string('name');
            $table->string('type')->default('count');
            $table->string('source_type')->default('system');
            $table->boolean('is_base_unit')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        DB::connection($conn)->table('units_of_measure')->insert([
            [
                'id' => 1,
                'code' => 'pcs',
                'name' => 'Piece',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'code' => 'ctn',
                'name' => 'Carton',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        Schema::connection($conn)->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('password')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::connection($conn)->create('stores', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::connection($conn)->create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('supplier_type')->default('supplier');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::connection($conn)->create('products', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->nullable();
            $table->string('name');
            $table->string('slug');
            $table->string('sku')->unique();
            $table->unsignedBigInteger('base_uom_id')->nullable();
            $table->string('product_type')->default('simple');
            $table->string('stock_status')->default('in_stock');
            $table->decimal('base_selling_price', 10, 2)->default(0);
            $table->decimal('online_price', 10, 2)->nullable();
            $table->boolean('is_weighed')->default(false);
            $table->boolean('requires_batch_tracking')->default(false);
            $table->boolean('requires_serial_tracking')->default(false);
            $table->decimal('reorder_level', 12, 4)->default(0);
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
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::connection($conn)->create('product_uoms', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('uom_id');
            $table->boolean('is_base_uom')->default(false);
            $table->boolean('is_sales_uom')->default(true);
            $table->decimal('conversion_to_base', 12, 6)->default(1);
            $table->timestamps();
        });

        Schema::connection($conn)->create('product_barcodes', function (Blueprint $table) {
            $table->id();
            $table->string('barcodeable_type');
            $table->unsignedBigInteger('barcodeable_id');
            $table->string('barcode', 50);
            $table->string('barcode_type')->default('INTERNAL');
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->string('region', 10)->nullable();
            $table->unsignedBigInteger('store_id')->nullable();
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->string('source')->default('manual');
            $table->json('metadata')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::connection($conn)->create('product_scale_barcode_formats', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('prefix', 20);
            $table->unsignedSmallInteger('length')->default(13);
            $table->unsignedSmallInteger('product_code_start');
            $table->unsignedSmallInteger('product_code_length');
            $table->unsignedSmallInteger('value_start');
            $table->unsignedSmallInteger('value_length');
            $table->string('value_type')->default('weight');
            $table->unsignedSmallInteger('decimal_places')->default(3);
            $table->string('checksum')->nullable();
            $table->unsignedBigInteger('store_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('priority')->default(0);
            $table->json('metadata')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::connection($conn)->create('product_barcode_suggestions', function (Blueprint $table) {
            $table->id();
            $table->string('suggested_barcodeable_type');
            $table->unsignedBigInteger('suggested_barcodeable_id');
            $table->string('barcode', 50);
            $table->string('barcode_type')->default('INTERNAL');
            $table->string('status')->default('pending');
            $table->boolean('is_primary')->default(false);
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->string('region', 10)->nullable();
            $table->unsignedBigInteger('store_id')->nullable();
            $table->json('metadata')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('submitted_by')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->unsignedBigInteger('approved_barcode_id')->nullable();
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
    }
}
