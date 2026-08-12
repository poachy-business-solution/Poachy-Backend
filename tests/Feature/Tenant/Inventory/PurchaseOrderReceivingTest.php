<?php

namespace Tests\Feature\Tenant\Inventory;

use App\Enums\Tenant\PurchaseOrderItemStatus;
use App\Enums\Tenant\PurchaseOrderStatus;
use App\Models\Tenant\Product;
use App\Models\Tenant\ProductBatch;
use App\Models\Tenant\ProductSerial;
use App\Models\Tenant\PurchaseOrder;
use App\Models\Tenant\PurchaseOrderItem;
use App\Models\Tenant\StoreProduct;
use App\Models\Tenant\Supplier;
use App\Services\Tenant\Inventory\InventoryMovementService;
use App\Services\Tenant\Inventory\ProductBatchService;
use App\Services\Tenant\Inventory\PurchaseOrderService;
use App\Services\Tenant\Product\ProductStockReceivingService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class PurchaseOrderReceivingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'tenant');
        Config::set('database.connections.tenant.database', 'poachy_test');
        DB::purge('tenant');
        DB::connection('tenant')->statement('SET foreign_key_checks = 0');

        $this->dropTestTables();
        $this->createMinimalSchema();
        $this->seedBaseData();
    }

    protected function tearDown(): void
    {
        $this->dropTestTables();
        DB::connection('tenant')->statement('SET foreign_key_checks = 1');
        Mockery::close();
        parent::tearDown();
    }

    public function test_over_receiving_throws_including_on_a_second_partial_call(): void
    {
        $this->bindMovementMock();

        $po = $this->createPo();
        $item = $this->createPoItem($po->id, quantityOrdered: 10.0);

        Model::withoutEvents(function () use ($po, $item) {
            (new ProductBatchService)->receiveGoodsFromPurchaseOrder($po->id, [
                $item->id => ['quantity' => 6.0],
            ]);
        });

        $this->assertEquals(6.0, (float) $item->fresh()->quantity_received);

        $this->expectException(\RuntimeException::class);

        Model::withoutEvents(function () use ($po, $item) {
            (new ProductBatchService)->receiveGoodsFromPurchaseOrder($po->id, [
                $item->id => ['quantity' => 5.0], // only 4 pending
            ]);
        });
    }

    public function test_receiving_against_invalid_status_throws(): void
    {
        $this->bindMovementMock();

        foreach ([PurchaseOrderStatus::DRAFT, PurchaseOrderStatus::RECEIVED, PurchaseOrderStatus::CANCELLED] as $status) {
            $po = $this->createPo(status: $status);
            $item = $this->createPoItem($po->id, quantityOrdered: 10.0);

            try {
                Model::withoutEvents(function () use ($po, $item) {
                    (new ProductBatchService)->receiveGoodsFromPurchaseOrder($po->id, [
                        $item->id => ['quantity' => 1.0],
                    ]);
                });
                $this->fail("Expected RuntimeException for status {$status->value}");
            } catch (\RuntimeException) {
                // expected
            }

            $this->assertEquals(0.0, (float) $item->fresh()->quantity_received, "Item should be untouched for status {$status->value}");
        }
    }

    public function test_partial_receiving_across_multiple_calls_reaches_received(): void
    {
        $this->bindMovementMock();

        $po = $this->createPo();
        $item = $this->createPoItem($po->id, quantityOrdered: 10.0);

        Model::withoutEvents(function () use ($po, $item) {
            (new ProductBatchService)->receiveGoodsFromPurchaseOrder($po->id, [
                $item->id => ['quantity' => 4.0],
            ]);
        });

        $this->assertSame(PurchaseOrderItemStatus::PARTIALLY_RECEIVED, $item->fresh()->status);
        $this->assertSame(PurchaseOrderStatus::PARTIALLY_RECEIVED, $po->fresh()->status);

        Model::withoutEvents(function () use ($po, $item) {
            (new ProductBatchService)->receiveGoodsFromPurchaseOrder($po->id, [
                $item->id => ['quantity' => 6.0],
            ]);
        });

        $this->assertSame(PurchaseOrderItemStatus::RECEIVED, $item->fresh()->status);
        $this->assertSame(PurchaseOrderStatus::RECEIVED, $po->fresh()->status);
        $this->assertSame(2, ProductBatch::where('purchase_order_id', $po->id)->count());
    }

    public function test_supplier_total_orders_increments_once_on_first_full_receipt_only(): void
    {
        $this->bindMovementMock();

        $po = $this->createPo();
        $item = $this->createPoItem($po->id, quantityOrdered: 10.0);

        $this->assertSame(0, Supplier::find(1)->total_orders);

        // Partial receipt: not fully received yet.
        Model::withoutEvents(function () use ($po, $item) {
            (new ProductBatchService)->receiveGoodsFromPurchaseOrder($po->id, [
                $item->id => ['quantity' => 4.0],
            ]);
        });
        $this->assertSame(0, Supplier::find(1)->total_orders);

        // Completes receipt: now fully received for the first time, even though it
        // took two partial calls to get here (the bug this regression test guards against).
        Model::withoutEvents(function () use ($po, $item) {
            (new ProductBatchService)->receiveGoodsFromPurchaseOrder($po->id, [
                $item->id => ['quantity' => 6.0],
            ]);
        });
        $this->assertSame(1, Supplier::find(1)->total_orders);

        // The PO is now fully received, so the service itself refuses any further
        // receiving call against it — confirms it can't be incremented a second time.
        $this->expectException(\RuntimeException::class);

        Model::withoutEvents(function () use ($po) {
            (new ProductBatchService)->receiveGoodsFromPurchaseOrder($po->id, []);
        });
    }

    public function test_mixed_item_po_status_is_partially_received_not_received(): void
    {
        $this->bindMovementMock();

        $po = $this->createPo();
        $itemOne = $this->createPoItem($po->id, quantityOrdered: 10.0);
        $itemTwo = $this->createPoItem($po->id, quantityOrdered: 10.0);

        Model::withoutEvents(function () use ($po, $itemOne) {
            (new ProductBatchService)->receiveGoodsFromPurchaseOrder($po->id, [
                $itemOne->id => ['quantity' => 10.0],
            ]);
        });

        $this->assertSame(PurchaseOrderItemStatus::RECEIVED, $itemOne->fresh()->status);
        $this->assertSame(PurchaseOrderItemStatus::PENDING, $itemTwo->fresh()->status);
        $this->assertSame(PurchaseOrderStatus::PARTIALLY_RECEIVED, $po->fresh()->status);
    }

    // =========================================================================
    // Serial tracking
    // =========================================================================

    public function test_receiving_serial_tracked_product_creates_one_serial_per_unit(): void
    {
        $this->bindMovementMock();

        $po = $this->createPo();
        $item = $this->createPoItem($po->id, quantityOrdered: 3.0, productId: 2);

        Model::withoutEvents(function () use ($po, $item) {
            (new ProductBatchService)->receiveGoodsFromPurchaseOrder($po->id, [
                $item->id => [
                    'quantity' => 3.0,
                    'serial_numbers' => ['IMEI-001', 'IMEI-002', 'IMEI-003'],
                ],
            ]);
        });

        $this->assertEquals(3.0, (float) $item->fresh()->quantity_received);
        $this->assertSame(3, ProductSerial::where('purchase_order_id', $po->id)->count());
        $this->assertSame(
            ['IMEI-001', 'IMEI-002', 'IMEI-003'],
            ProductSerial::where('purchase_order_id', $po->id)->orderBy('id')->pluck('serial_number')->toArray()
        );
        $this->assertTrue(ProductSerial::where('serial_number', 'IMEI-001')->first()->is_available);
        $this->assertSame(0, ProductBatch::where('purchase_order_id', $po->id)->count());
    }

    public function test_receiving_serial_tracked_product_throws_when_serial_count_mismatches_quantity(): void
    {
        $this->bindMovementMock();

        $po = $this->createPo();
        $item = $this->createPoItem($po->id, quantityOrdered: 3.0, productId: 2);

        $this->expectException(\RuntimeException::class);

        Model::withoutEvents(function () use ($po, $item) {
            (new ProductBatchService)->receiveGoodsFromPurchaseOrder($po->id, [
                $item->id => [
                    'quantity' => 3.0,
                    'serial_numbers' => ['IMEI-001', 'IMEI-002'], // only 2 for quantity 3
                ],
            ]);
        });
    }

    public function test_receiving_serial_tracked_product_throws_on_duplicate_serial_within_request(): void
    {
        $this->bindMovementMock();

        $po = $this->createPo();
        $item = $this->createPoItem($po->id, quantityOrdered: 2.0, productId: 2);

        $this->expectException(\RuntimeException::class);

        Model::withoutEvents(function () use ($po, $item) {
            (new ProductBatchService)->receiveGoodsFromPurchaseOrder($po->id, [
                $item->id => [
                    'quantity' => 2.0,
                    'serial_numbers' => ['IMEI-001', 'IMEI-001'],
                ],
            ]);
        });
    }

    public function test_receiving_serial_tracked_product_throws_on_serial_already_in_use(): void
    {
        $this->bindMovementMock();

        $po = $this->createPo();
        $itemOne = $this->createPoItem($po->id, quantityOrdered: 1.0, productId: 2);

        Model::withoutEvents(function () use ($po, $itemOne) {
            (new ProductBatchService)->receiveGoodsFromPurchaseOrder($po->id, [
                $itemOne->id => ['quantity' => 1.0, 'serial_numbers' => ['IMEI-001']],
            ]);
        });

        $itemTwo = $this->createPoItem($po->id, quantityOrdered: 1.0, productId: 2);

        $this->expectException(\RuntimeException::class);

        Model::withoutEvents(function () use ($po, $itemTwo) {
            (new ProductBatchService)->receiveGoodsFromPurchaseOrder($po->id, [
                $itemTwo->id => ['quantity' => 1.0, 'serial_numbers' => ['IMEI-001']],
            ]);
        });
    }

    // =========================================================================
    // One-shot mobile stock receiving
    // =========================================================================

    public function test_one_shot_receive_stock_allocates_product_and_receives_purchase_order(): void
    {
        $this->bindMovementMock();
        Auth::shouldReceive('id')->andReturn(1);

        $product = Product::findOrFail(1);

        $result = Model::withoutEvents(fn () => $this->makeOneShotService()->receive($product, [
            'store_id' => 1,
            'quantity' => 5.0,
            'unit_cost' => 12.50,
            'supplier_id' => 1,
            'expiry_date' => now()->addYear()->toDateString(),
        ]));

        $this->assertTrue($result['store_product_created']);
        $this->assertSame(1, StoreProduct::where('store_id', 1)->where('product_id', $product->id)->count());
        $this->assertSame(1, PurchaseOrder::count());
        $this->assertSame(PurchaseOrderStatus::RECEIVED, PurchaseOrder::first()->status);
        $this->assertSame(PurchaseOrderItemStatus::RECEIVED, PurchaseOrderItem::first()->status);
        $this->assertEquals(5.0, (float) PurchaseOrderItem::first()->quantity_received);
        $this->assertSame(1, ProductBatch::where('purchase_order_id', PurchaseOrder::first()->id)->count());
        $this->assertSame(1, Supplier::find(1)->total_orders);
    }

    public function test_one_shot_receive_stock_reuses_existing_store_assignment(): void
    {
        $this->bindMovementMock();
        Auth::shouldReceive('id')->andReturn(1);

        $product = Product::findOrFail(1);

        Model::withoutEvents(fn () => StoreProduct::create([
            'store_id' => 1,
            'product_id' => $product->id,
            'is_available' => true,
            'min_stock_level' => 0,
        ]));

        $result = Model::withoutEvents(fn () => $this->makeOneShotService()->receive($product, [
            'store_id' => 1,
            'quantity' => 2.0,
            'unit_cost' => 10.00,
            'supplier_id' => 1,
        ]));

        $this->assertFalse($result['store_product_created']);
        $this->assertSame(1, StoreProduct::where('store_id', 1)->where('product_id', $product->id)->count());
    }

    public function test_receiving_serial_tracked_product_partial_receipt_across_multiple_calls(): void
    {
        $this->bindMovementMock();

        $po = $this->createPo();
        $item = $this->createPoItem($po->id, quantityOrdered: 4.0, productId: 2);

        Model::withoutEvents(function () use ($po, $item) {
            (new ProductBatchService)->receiveGoodsFromPurchaseOrder($po->id, [
                $item->id => ['quantity' => 2.0, 'serial_numbers' => ['IMEI-001', 'IMEI-002']],
            ]);
        });

        $this->assertSame(PurchaseOrderItemStatus::PARTIALLY_RECEIVED, $item->fresh()->status);

        Model::withoutEvents(function () use ($po, $item) {
            (new ProductBatchService)->receiveGoodsFromPurchaseOrder($po->id, [
                $item->id => ['quantity' => 2.0, 'serial_numbers' => ['IMEI-003', 'IMEI-004']],
            ]);
        });

        $this->assertSame(PurchaseOrderItemStatus::RECEIVED, $item->fresh()->status);
        $this->assertSame(4, ProductSerial::where('purchase_order_id', $po->id)->count());
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function bindMovementMock(): void
    {
        $movementMock = Mockery::mock(InventoryMovementService::class);
        $movementMock->shouldReceive('recordMovement');
        app()->bind(InventoryMovementService::class, fn () => $movementMock);
    }

    private function makeOneShotService(): ProductStockReceivingService
    {
        return new ProductStockReceivingService(
            new PurchaseOrderService,
            new ProductBatchService
        );
    }

    private function createPo(PurchaseOrderStatus $status = PurchaseOrderStatus::SENT): PurchaseOrder
    {
        return Model::withoutEvents(fn () => PurchaseOrder::create([
            'po_number' => 'PO-TEST-'.uniqid(),
            'supplier_id' => 1,
            'store_id' => 1,
            'order_date' => now()->toDateString(),
            'status' => $status,
            'created_by' => 1,
        ]));
    }

    private function createPoItem(int $poId, float $quantityOrdered, int $productId = 1): PurchaseOrderItem
    {
        return Model::withoutEvents(fn () => PurchaseOrderItem::create([
            'purchase_order_id' => $poId,
            'product_id' => $productId,
            'product_variant_id' => null,
            'uom_id' => 1,
            'quantity_ordered' => $quantityOrdered,
            'quantity_ordered_in_base_uom' => $quantityOrdered,
            'quantity_received' => 0,
            'quantity_received_in_base_uom' => 0,
            'unit_cost' => 5.00,
            'unit_cost_in_base_uom' => 5.00,
            'status' => PurchaseOrderItemStatus::PENDING,
        ]));
    }

    private function seedBaseData(): void
    {
        $conn = 'tenant';

        DB::connection($conn)->table('suppliers')->insert([
            'id' => 1,
            'name' => 'Test Supplier',
            'total_orders' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::connection($conn)->table('stores')->insert([
            'id' => 1,
            'name' => 'Main Store',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::connection($conn)->table('units_of_measure')->insert([
            'id' => 1,
            'code' => 'pcs',
            'name' => 'Piece',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Model::withoutEvents(fn () => Product::create([
            'name' => 'Batch Tracked Product',
            'slug' => 'batch-tracked-product',
            'sku' => 'SKU-BATCH-001',
            'product_type' => 'simple',
            'stock_status' => 'in_stock',
            'requires_batch_tracking' => true,
            'base_uom_id' => 1,
            'is_available_online' => false,
        ]));

        Model::withoutEvents(fn () => Product::create([
            'name' => 'Serial Tracked Product',
            'slug' => 'serial-tracked-product',
            'sku' => 'SKU-SERIAL-001',
            'product_type' => 'simple',
            'stock_status' => 'in_stock',
            'requires_serial_tracking' => true,
            'base_uom_id' => 1,
            'is_available_online' => false,
        ]));
    }

    private function dropTestTables(): void
    {
        foreach ([
            'product_batches',
            'product_serials',
            'sale_items',
            'marketplace_sale_items',
            'purchase_order_items',
            'purchase_orders',
            'product_variants',
            'products',
            'product_uoms',
            'units_of_measure',
            'stores',
            'suppliers',
            'store_products',
        ] as $table) {
            Schema::connection('tenant')->dropIfExists($table);
        }
    }

    private function createMinimalSchema(): void
    {
        $conn = 'tenant';

        Schema::connection($conn)->create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->default('Test Supplier');
            $table->unsignedInteger('total_orders')->default(0);
            $table->decimal('outstanding_balance', 15, 2)->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::connection($conn)->create('stores', function (Blueprint $table) {
            $table->id();
            $table->string('name')->default('Test Store');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::connection($conn)->create('units_of_measure', function (Blueprint $table) {
            $table->id();
            $table->string('code');
            $table->string('name');
            $table->timestamps();
        });

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

        Schema::connection($conn)->create('product_uoms', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('uom_id');
            $table->decimal('conversion_to_base', 12, 4)->default(1.0);
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

        Schema::connection($conn)->create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('po_number')->unique();
            $table->unsignedBigInteger('supplier_id');
            $table->unsignedBigInteger('store_id');
            $table->date('order_date');
            $table->date('expected_delivery_date')->nullable();
            $table->string('status')->default('sent');
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('shipping_cost', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->string('payment_status')->default('unpaid');
            $table->decimal('amount_paid', 15, 2)->default(0);
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
            $table->timestamps();
        });

        Schema::connection($conn)->create('marketplace_sale_items', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
        });

        Schema::connection($conn)->create('product_serials', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('product_variant_id')->nullable();
            $table->unsignedBigInteger('purchase_order_id');
            $table->string('serial_number')->unique();
            $table->string('status')->default('available');
            $table->decimal('cost', 15, 2);
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->unsignedBigInteger('sale_item_id')->nullable();
            $table->unsignedBigInteger('marketplace_sale_item_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }
}
