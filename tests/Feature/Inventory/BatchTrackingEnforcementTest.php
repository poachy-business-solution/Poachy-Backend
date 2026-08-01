<?php

namespace Tests\Feature\Inventory;

use App\Enums\Tenant\PurchaseOrderStatus;
use App\Models\Tenant\Product;
use App\Models\Tenant\ProductBatch;
use App\Models\Tenant\PurchaseOrder;
use App\Models\Tenant\PurchaseOrderItem;
use App\Models\Tenant\Sale;
use App\Models\Tenant\SaleItem;
use App\Models\Tenant\SaleItemBatchDepletion;
use App\Services\Tenant\Customer\CustomerService;
use App\Services\Tenant\Inventory\InventoryMovementService;
use App\Services\Tenant\Inventory\InventoryService;
use App\Services\Tenant\Inventory\ProductBatchService;
use App\Services\Tenant\Sales\CreditService;
use App\Services\Tenant\Sales\LoyaltyService;
use App\Services\Tenant\Sales\SaleCalculationService;
use App\Services\Tenant\Sales\SaleService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

/**
 * Test-specific subclass that exposes the protected deductInventory() method.
 */
class ExposedSaleService extends SaleService
{
    public function testDeductInventory(Sale $sale, array $lineItems): void
    {
        $this->deductInventory($sale, $lineItems);
    }
}

class BatchTrackingEnforcementTest extends TestCase
{
    private const TEST_DB = 'poachy_test';

    protected function setUp(): void
    {
        parent::setUp();

        // Point the tenant connection at our isolated test database and make it the default
        // so Eloquent models (which don't declare $connection) use MySQL instead of sqlite.
        Config::set('database.default', 'tenant');
        Config::set('database.connections.tenant.database', self::TEST_DB);
        DB::purge('tenant');

        // Disable FK enforcement so tables can be created without all dependencies
        DB::connection('tenant')->statement('SET foreign_key_checks = 0');

        $this->dropTestTables();
        $this->createMinimalSchema();
    }

    protected function tearDown(): void
    {
        $this->dropTestTables();
        DB::connection('tenant')->statement('SET foreign_key_checks = 1');
        parent::tearDown();
    }

    // =========================================================================
    // SaleService — FIFO depletion gate
    // =========================================================================

    public function test_non_batch_product_skips_fifo_in_pos_sale(): void
    {
        $product = $this->createProduct(requiresBatchTracking: false);

        $movementMock = Mockery::mock(InventoryMovementService::class);
        $movementMock->shouldReceive('recordSale')->once()->andReturn(collect());

        $batchSpy = Mockery::spy(ProductBatchService::class);

        $saleService = $this->makeSaleService($movementMock, $batchSpy);
        $sale = $this->makeSale(storeId: 1);
        $this->createSaleItem($sale->id, $product->id, quantity: 5.0);

        $saleService->testDeductInventory($sale, [
            [
                'bundle_id' => null,
                'product_id' => $product->id,
                'variant_id' => null,
                'quantity' => 5.0,
                'uom_id' => 1,
                'unit_cost' => 10.0,
            ],
        ]);

        $batchSpy->shouldNotHaveReceived('depleteBatchesFIFO');
        $this->assertSame(0, SaleItemBatchDepletion::count());
    }

    public function test_batch_tracked_product_runs_fifo_in_pos_sale(): void
    {
        $product = $this->createProduct(requiresBatchTracking: true);

        $movementMock = Mockery::mock(InventoryMovementService::class);
        $movementMock->shouldReceive('recordSale')->once()->andReturn(collect());

        $batchMock = Mockery::mock(ProductBatchService::class);
        $batchMock->shouldReceive('depleteBatchesFIFO')
            ->once()
            ->with(1, $product->id, null, 5.0)
            ->andReturn(['depletions' => [], 'total_cost' => 0, 'average_cost' => 0]);

        $saleService = $this->makeSaleService($movementMock, $batchMock);
        $sale = $this->makeSale(storeId: 1);
        $this->createSaleItem($sale->id, $product->id, quantity: 5.0);

        $saleService->testDeductInventory($sale, [
            [
                'bundle_id' => null,
                'product_id' => $product->id,
                'variant_id' => null,
                'quantity' => 5.0,
                'uom_id' => 1,
                'unit_cost' => 10.0,
            ],
        ]);
    }

    public function test_batch_tracked_sale_records_depletion_for_refund_reversal(): void
    {
        $product = $this->createProduct(requiresBatchTracking: true);
        $po = $this->createPurchaseOrder();
        $poItem = $this->createPurchaseOrderItem($po->id, $product->id, uomId: 1);

        // Receive a single batch with 5 units (partial receipt against the
        // 10-unit poItem — keeps the PO status short of "received", same as
        // the other PO-receiving tests above, so no supplier lookup fires),
        // then sell all 5 — mirrors "one batch, fully depleted by one sale".
        $movementMock = Mockery::mock(InventoryMovementService::class);
        $movementMock->shouldReceive('recordMovement')->once();

        $batch = null;

        Model::withoutEvents(function () use (&$batch, $po, $poItem, $movementMock): void {
            app()->bind(InventoryMovementService::class, fn () => $movementMock);

            $result = (new ProductBatchService)->receiveGoodsFromPurchaseOrder($po->id, [
                $poItem->id => [
                    'quantity' => 5.0,
                    'manufacture_date' => null,
                    'expiry_date' => null,
                    'notes' => null,
                ],
            ]);

            $batch = $result['batches']->first();
        });

        $saleMovementMock = Mockery::mock(InventoryMovementService::class);
        $saleMovementMock->shouldReceive('recordSale')->once()->andReturn(collect());

        $saleService = $this->makeSaleService($saleMovementMock, new ProductBatchService);
        $sale = $this->makeSale(storeId: 1);
        $saleItem = $this->createSaleItem($sale->id, $product->id, quantity: 5.0);

        Model::withoutEvents(function () use ($saleService, $sale, $product): void {
            $saleService->testDeductInventory($sale, [
                [
                    'bundle_id' => null,
                    'product_id' => $product->id,
                    'variant_id' => null,
                    'quantity' => 5.0,
                    'uom_id' => 1,
                    'unit_cost' => 10.0,
                ],
            ]);
        });

        $this->assertSame(1, SaleItemBatchDepletion::count());
        $depletion = SaleItemBatchDepletion::first();
        $this->assertSame($saleItem->id, $depletion->sale_item_id);
        $this->assertSame($batch->id, $depletion->batch_id);
        $this->assertEquals(5.0, (float) $depletion->quantity_in_base_uom);

        $this->assertEquals(0.0, (float) $batch->fresh()->quantity_remaining_in_base_uom);
    }

    // =========================================================================
    // ProductBatchService — PO receipt routing
    // =========================================================================

    public function test_po_receipt_non_batch_product_does_not_create_batch(): void
    {
        $product = $this->createProduct(requiresBatchTracking: false);
        $po = $this->createPurchaseOrder();
        $poItem = $this->createPurchaseOrderItem($po->id, $product->id, uomId: 1);

        // InventoryMovementService::recordMovement() should be called once (direct inventory path)
        $movementMock = Mockery::mock(InventoryMovementService::class);
        $movementMock->shouldReceive('recordMovement')->once();

        $result = null;

        // withoutEvents() suppresses all Eloquent model observers (clearCache, AuditService, etc.)
        // so the test is not blocked by Cache::tags() or missing audit tables.
        Model::withoutEvents(function () use (&$result, $po, $poItem, $movementMock): void {
            app()->bind(InventoryMovementService::class, fn () => $movementMock);

            $result = (new ProductBatchService)->receiveGoodsFromPurchaseOrder($po->id, [
                $poItem->id => [
                    'quantity' => 5.0,
                    'manufacture_date' => null,
                    'expiry_date' => null,
                    'notes' => null,
                ],
            ]);
        });

        $this->assertSame(0, ProductBatch::count(), 'No ProductBatch should be created for a non-batch-tracked product.');
        $this->assertTrue($result['batches']->isEmpty(), 'Returned batches collection should be empty.');
    }

    public function test_po_receipt_batch_tracked_product_creates_batch(): void
    {
        $product = $this->createProduct(requiresBatchTracking: true);
        $po = $this->createPurchaseOrder();
        $poItem = $this->createPurchaseOrderItem($po->id, $product->id, uomId: 1);

        // InventoryMovementService::recordMovement() should be called once (via updateInventoryFromBatch)
        $movementMock = Mockery::mock(InventoryMovementService::class);
        $movementMock->shouldReceive('recordMovement')->once();

        $result = null;

        Model::withoutEvents(function () use (&$result, $po, $poItem, $movementMock): void {
            app()->bind(InventoryMovementService::class, fn () => $movementMock);

            $result = (new ProductBatchService)->receiveGoodsFromPurchaseOrder($po->id, [
                $poItem->id => [
                    'quantity' => 5.0,
                    'manufacture_date' => null,
                    'expiry_date' => '2026-12-31',
                    'notes' => null,
                ],
            ]);
        });

        $this->assertSame(1, ProductBatch::count(), 'Exactly one ProductBatch should be created for a batch-tracked product.');
        $this->assertCount(1, $result['batches']);

        $batch = ProductBatch::first();
        $this->assertSame($product->id, $batch->product_id);
        $this->assertEquals(5.0, (float) $batch->quantity_received_in_base_uom);
        $this->assertEquals(5.0, (float) $batch->quantity_remaining_in_base_uom);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function createProduct(bool $requiresBatchTracking, int $baseUomId = 1): Product
    {
        // withoutEvents() skips ProductObserver which calls Cache::tags() (not supported by array driver)
        return Product::withoutEvents(fn () => Product::create([
            'name' => 'Test Product',
            'slug' => 'test-product-'.uniqid(),
            'sku' => 'SKU-'.uniqid(),
            'product_type' => 'simple',
            'stock_status' => 'in_stock',
            'requires_batch_tracking' => $requiresBatchTracking,
            'base_uom_id' => $baseUomId,
            'is_available_online' => false,
        ]));
    }

    private function createPurchaseOrder(): PurchaseOrder
    {
        // withoutEvents() skips PurchaseOrderObserver which calls AuditService and relations
        return PurchaseOrder::withoutEvents(fn () => PurchaseOrder::create([
            'po_number' => 'PO-TEST-'.uniqid(),
            'supplier_id' => 1,
            'store_id' => 1,
            'order_date' => now()->toDateString(),
            'status' => PurchaseOrderStatus::SENT->value,
            'created_by' => 1,
        ]));
    }

    private function createPurchaseOrderItem(int $poId, int $productId, int $uomId): PurchaseOrderItem
    {
        return PurchaseOrderItem::withoutEvents(fn () => PurchaseOrderItem::create([
            'purchase_order_id' => $poId,
            'product_id' => $productId,
            'product_variant_id' => null,
            'uom_id' => $uomId,
            'quantity_ordered' => 10.0,
            'quantity_ordered_in_base_uom' => 10.0,
            'quantity_received' => 0.0,
            'quantity_received_in_base_uom' => 0.0,
            'unit_cost' => 5.00,
            'unit_cost_in_base_uom' => 5.00,
            'status' => 'pending',
        ]));
    }

    private function makeSaleService(
        InventoryMovementService $movementService,
        ProductBatchService $batchService,
    ): ExposedSaleService {
        return new ExposedSaleService(
            Mockery::mock(SaleCalculationService::class),
            Mockery::mock(InventoryService::class),
            $movementService,
            $batchService,
            Mockery::mock(CustomerService::class),
            Mockery::mock(LoyaltyService::class),
            Mockery::mock(CreditService::class),
        );
    }

    private function makeSale(int $storeId): Sale
    {
        $sale = new Sale;
        $sale->forceFill(['id' => 1, 'store_id' => $storeId, 'sale_number' => 'SALE-TEST-'.uniqid()]);

        return $sale;
    }

    /**
     * deductInventory() looks up the sale's persisted SaleItem rows (created
     * moments earlier by createSaleItems() in the real flow) to tag each
     * inventory line for batch-depletion tracking — so tests need a real
     * SaleItem row matching each line item passed to testDeductInventory().
     */
    private function createSaleItem(int $saleId, int $productId, float $quantity): SaleItem
    {
        return SaleItem::withoutEvents(fn () => SaleItem::create([
            'sale_id' => $saleId,
            'product_id' => $productId,
            'uom_id' => 1,
            'quantity' => $quantity,
            'quantity_in_base_uom' => $quantity,
            'unit_price' => 20.0,
            'unit_cost' => 10.0,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'subtotal' => $quantity * 20.0,
        ]));
    }

    private function createMinimalSchema(): void
    {
        $conn = 'tenant';

        // Needed by PurchaseOrder eager-load of 'supplier' and fresh() reload
        Schema::connection($conn)->create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->default('Test Supplier');
            $table->unsignedInteger('total_orders')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        // Needed by PurchaseOrder eager-load of 'store' and fresh() reload
        Schema::connection($conn)->create('stores', function (Blueprint $table) {
            $table->id();
            $table->string('name')->default('Test Store');
            $table->timestamps();
            $table->softDeletes();
        });

        // Needed by PurchaseOrderItem eager-load of 'uom'
        Schema::connection($conn)->create('units_of_measure', function (Blueprint $table) {
            $table->id();
            $table->string('code');
            $table->string('name');
            $table->timestamps();
        });

        // Needed by PurchaseOrderItem eager-load of 'productVariant'
        Schema::connection($conn)->create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->string('sku')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::connection($conn)->create('products', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->nullable();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('sku')->unique();
            $table->string('product_type')->default('simple');
            $table->string('stock_status')->default('in_stock');
            $table->boolean('requires_batch_tracking')->default(false);
            $table->boolean('requires_serial_tracking')->default(false);
            $table->boolean('is_weighed')->default(false);
            $table->unsignedBigInteger('base_uom_id')->default(1);
            $table->unsignedBigInteger('category_id')->nullable();
            $table->unsignedBigInteger('brand_id')->nullable();
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->unsignedBigInteger('tax_rate_id')->nullable();
            $table->decimal('base_selling_price', 10, 2)->default(0);
            $table->decimal('online_price', 10, 2)->nullable();
            $table->decimal('reorder_level', 12, 4)->default(0);
            $table->integer('shelf_life_days')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_available_online')->default(false);
            $table->string('primary_image')->nullable();
            $table->json('secondary_images')->nullable();
            $table->text('notes')->nullable();
            $table->text('online_description')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // Needed by getConversionToBaseUom() when uomId !== base_uom_id
        Schema::connection($conn)->create('product_uoms', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('uom_id');
            $table->decimal('conversion_to_base', 12, 4)->default(1.0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::connection($conn)->create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('po_number')->unique();
            $table->unsignedBigInteger('supplier_id');
            $table->unsignedBigInteger('store_id');
            $table->date('order_date');
            $table->date('expected_delivery_date')->nullable();
            $table->string('status')->default('sent');
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('tax_amount', 10, 2)->default(0);
            $table->decimal('shipping_cost', 10, 2)->default(0);
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->string('payment_status')->default('unpaid');
            $table->decimal('amount_paid', 10, 2)->default(0);
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->default(1);
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::connection($conn)->create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('purchase_order_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('product_variant_id')->nullable();
            $table->unsignedBigInteger('uom_id');
            $table->decimal('quantity_ordered', 12, 4);
            $table->decimal('quantity_received', 12, 4)->default(0);
            $table->decimal('quantity_ordered_in_base_uom', 12, 4);
            $table->decimal('quantity_received_in_base_uom', 12, 4)->default(0);
            $table->decimal('unit_cost', 10, 2);
            $table->decimal('unit_cost_in_base_uom', 10, 2);
            $table->unsignedBigInteger('tax_rate_id')->nullable();
            $table->decimal('tax_amount', 10, 2)->default(0);
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->string('status')->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::connection($conn)->create('product_batches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('product_variant_id')->nullable();
            $table->unsignedBigInteger('purchase_order_id');
            $table->string('batch_number')->unique();
            $table->unsignedBigInteger('purchase_uom_id');
            $table->decimal('quantity_received_in_purchase_uom', 12, 4);
            $table->decimal('quantity_received_in_base_uom', 12, 4);
            $table->decimal('quantity_remaining_in_base_uom', 12, 4);
            $table->decimal('cost_per_purchase_uom', 10, 2);
            $table->decimal('cost_per_base_uom', 10, 2);
            $table->decimal('total_cost', 10, 2);
            $table->date('manufacture_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->boolean('is_expired')->default(false);
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::connection($conn)->create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sale_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('product_variant_id')->nullable();
            $table->unsignedBigInteger('bundle_id')->nullable();
            $table->unsignedBigInteger('uom_id')->default(1);
            $table->decimal('quantity', 12, 4)->default(1);
            $table->decimal('quantity_in_base_uom', 12, 4)->default(1);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('unit_cost', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->unsignedBigInteger('tax_rate_id')->nullable();
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::connection($conn)->create('sale_item_batch_depletions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sale_item_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('batch_id');
            $table->decimal('quantity_in_base_uom', 15, 4);
            $table->timestamps();
        });
    }

    private function dropTestTables(): void
    {
        $conn = 'tenant';

        Schema::connection($conn)->dropIfExists('sale_item_batch_depletions');
        Schema::connection($conn)->dropIfExists('sale_items');
        Schema::connection($conn)->dropIfExists('product_batches');
        Schema::connection($conn)->dropIfExists('purchase_order_items');
        Schema::connection($conn)->dropIfExists('purchase_orders');
        Schema::connection($conn)->dropIfExists('product_uoms');
        Schema::connection($conn)->dropIfExists('products');
        Schema::connection($conn)->dropIfExists('product_variants');
        Schema::connection($conn)->dropIfExists('units_of_measure');
        Schema::connection($conn)->dropIfExists('stores');
        Schema::connection($conn)->dropIfExists('suppliers');
    }
}
