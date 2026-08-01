<?php

namespace Tests\Feature\Tenant\Sync;

use App\Enums\Tenant\PaymentStatus;
use App\Enums\Tenant\ReservationStatus;
use App\Jobs\Tenant\ProcessInboundCancellationSync;
use App\Models\Tenant\Inventory;
use App\Models\Tenant\InventoryReservation;
use App\Models\Tenant\MarketplaceSale;
use App\Models\Tenant\MarketplaceSaleItem;
use App\Models\Tenant\MarketplaceSaleItemBatchDepletion;
use App\Models\Tenant\Product;
use App\Models\Tenant\ProductBatch;
use App\Models\Tenant\ProductSerial;
use App\Services\Tenant\Inventory\InventoryMovementService;
use App\Services\Tenant\Inventory\ProductBatchService;
use App\Services\Tenant\Inventory\ProductSerialService;
use App\Services\Tenant\Inventory\StockReservationService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Stancl\Tenancy\Contracts\Tenant;
use Tests\TestCase;

class ProcessInboundCancellationSyncTest extends TestCase
{
    private const TEST_DB = 'poachy_test';

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'tenant');
        Config::set('database.connections.tenant.database', self::TEST_DB);
        DB::purge('tenant');

        DB::connection('tenant')->statement('SET foreign_key_checks = 0');

        $this->dropTestTables();
        $this->createMinimalSchema();

        $fakeTenant = new \stdClass;
        $fakeTenant->id = 'test-tenant';
        app()->instance(Tenant::class, $fakeTenant);

        Http::fake(); // respondToCentral()'s outbound ACK call
    }

    protected function tearDown(): void
    {
        $this->dropTestTables();
        DB::connection('tenant')->statement('SET foreign_key_checks = 1');
        parent::tearDown();
    }

    // =========================================================================
    // No MarketplaceSale yet — payment never synced, reservation is still ACTIVE
    // =========================================================================

    public function test_releases_active_reservation_when_no_sale_exists_yet(): void
    {
        $product = $this->createProduct(requiresBatchTracking: false);
        $inventory = $this->createInventory($product->id, onHand: 10, reserved: 3);

        $reservation = Model::withoutEvents(fn () => InventoryReservation::create([
            'inventory_id' => $inventory->id,
            'reference_type' => 'MarketplaceOrder',
            'reference_id' => 555,
            'quantity_reserved' => 3,
            'status' => ReservationStatus::ACTIVE,
        ]));

        $job = new ProcessInboundCancellationSync(['order_id' => 555]);

        Model::withoutEvents(fn () => $job->handle(
            app(StockReservationService::class),
            app(InventoryMovementService::class),
            app(ProductBatchService::class),
            app(ProductSerialService::class),
        ));

        $reservation->refresh();
        $this->assertSame(ReservationStatus::CANCELLED, $reservation->status);

        $inventory->refresh();
        $this->assertEquals(0, (float) $inventory->quantity_reserved);

        $this->assertSame(0, MarketplaceSale::count());
    }

    // =========================================================================
    // MarketplaceSale already exists — payment synced, real deductions happened
    // =========================================================================

    public function test_reverses_sale_restores_inventory_and_batch_and_flags_refunded(): void
    {
        $product = $this->createProduct(requiresBatchTracking: true);
        $inventory = $this->createInventory($product->id, onHand: 5, reserved: 0);
        $batch = $this->createBatch($product->id, quantityRemaining: 0.0, quantityReceived: 5.0);

        $sale = Model::withoutEvents(fn () => MarketplaceSale::create([
            'central_order_id' => 777,
            'sale_number' => 'MKT-SALE-TEST',
            'store_id' => 1,
            'sale_date' => now(),
            'subtotal' => 500.0,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total_amount' => 500.0,
            'payment_status' => PaymentStatus::PAID,
            'amount_paid' => 500.0,
            'amount_due' => 0,
        ]));

        $saleItem = Model::withoutEvents(fn () => MarketplaceSaleItem::create([
            'marketplace_sale_id' => $sale->id,
            'product_id' => $product->id,
            'uom_id' => 1,
            'quantity' => 5,
            'quantity_in_base_uom' => 5,
            'unit_price' => 100.0,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'subtotal' => 500.0,
        ]));

        MarketplaceSaleItemBatchDepletion::create([
            'marketplace_sale_item_id' => $saleItem->id,
            'product_id' => $product->id,
            'batch_id' => $batch->id,
            'quantity_in_base_uom' => 5.0,
        ]);

        $job = new ProcessInboundCancellationSync(['order_id' => 777]);

        Model::withoutEvents(fn () => $job->handle(
            app(StockReservationService::class),
            app(InventoryMovementService::class),
            app(ProductBatchService::class),
            app(ProductSerialService::class),
        ));

        $inventory->refresh();
        $this->assertEquals(10.0, (float) $inventory->quantity_on_hand);

        $this->assertEquals(5.0, (float) $batch->fresh()->quantity_remaining_in_base_uom);

        $sale->refresh();
        $this->assertSame(PaymentStatus::REFUNDED, $sale->payment_status);
    }

    public function test_reverses_sale_restores_serials_to_available_and_clears_link(): void
    {
        $product = $this->createProduct(requiresBatchTracking: false, requiresSerialTracking: true);
        $inventory = $this->createInventory($product->id, onHand: 0, reserved: 0);

        $sale = Model::withoutEvents(fn () => MarketplaceSale::create([
            'central_order_id' => 888,
            'sale_number' => 'MKT-SALE-SERIAL-TEST',
            'store_id' => 1,
            'sale_date' => now(),
            'subtotal' => 500.0,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total_amount' => 500.0,
            'payment_status' => PaymentStatus::PAID,
            'amount_paid' => 500.0,
            'amount_due' => 0,
        ]));

        $saleItem = Model::withoutEvents(fn () => MarketplaceSaleItem::create([
            'marketplace_sale_id' => $sale->id,
            'product_id' => $product->id,
            'uom_id' => 1,
            'quantity' => 1,
            'quantity_in_base_uom' => 1,
            'unit_price' => 500.0,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'subtotal' => 500.0,
        ]));

        $serial = $this->createSerial($product->id, 'IMEI-001', $saleItem->id);

        $job = new ProcessInboundCancellationSync(['order_id' => 888]);

        Model::withoutEvents(fn () => $job->handle(
            app(StockReservationService::class),
            app(InventoryMovementService::class),
            app(ProductBatchService::class),
            app(ProductSerialService::class),
        ));

        $serial->refresh();
        $this->assertSame('available', $serial->status->value);
        $this->assertNull($serial->marketplace_sale_item_id);

        $sale->refresh();
        $this->assertSame(PaymentStatus::REFUNDED, $sale->payment_status);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function createProduct(bool $requiresBatchTracking, bool $requiresSerialTracking = false): Product
    {
        return Product::withoutEvents(fn () => Product::create([
            'name' => 'Test Product',
            'slug' => 'test-product-'.uniqid(),
            'sku' => 'SKU-'.uniqid(),
            'requires_batch_tracking' => $requiresBatchTracking,
            'requires_serial_tracking' => $requiresSerialTracking,
            'base_uom_id' => 1,
        ]));
    }

    private function createSerial(int $productId, string $serialNumber, int $marketplaceSaleItemId): ProductSerial
    {
        return Model::withoutEvents(fn () => ProductSerial::create([
            'store_id' => 1,
            'product_id' => $productId,
            'purchase_order_id' => 1,
            'serial_number' => $serialNumber,
            'status' => 'sold',
            'cost' => 50.00,
            'marketplace_sale_item_id' => $marketplaceSaleItemId,
        ]));
    }

    private function createInventory(int $productId, float $onHand, float $reserved): Inventory
    {
        return Model::withoutEvents(fn () => Inventory::create([
            'store_id' => 1,
            'product_id' => $productId,
            'quantity_on_hand' => $onHand,
            'quantity_reserved' => $reserved,
            'quantity_available' => $onHand - $reserved,
        ]));
    }

    private function createBatch(int $productId, float $quantityRemaining, float $quantityReceived): ProductBatch
    {
        return Model::withoutEvents(fn () => ProductBatch::create([
            'store_id' => 1,
            'product_id' => $productId,
            'purchase_order_id' => 1,
            'batch_number' => 'BATCH-TEST-'.uniqid(),
            'purchase_uom_id' => 1,
            'quantity_received_in_purchase_uom' => $quantityReceived,
            'quantity_received_in_base_uom' => $quantityReceived,
            'quantity_remaining_in_base_uom' => $quantityRemaining,
            'cost_per_purchase_uom' => 5.00,
            'cost_per_base_uom' => 5.00,
            'total_cost' => $quantityReceived * 5.00,
        ]));
    }

    private function dropTestTables(): void
    {
        $conn = 'tenant';

        foreach ([
            'marketplace_sale_item_batch_depletions',
            'marketplace_sale_items',
            'marketplace_sales',
            'inventory_reservations',
            'product_batches',
            'product_serials',
            'inventory_movements',
            'inventory',
            'product_variants',
            'product_uoms',
            'products',
            'units_of_measure',
            'users',
            'stores',
        ] as $table) {
            Schema::connection($conn)->dropIfExists($table);
        }
    }

    private function createMinimalSchema(): void
    {
        $conn = 'tenant';

        Schema::connection($conn)->create('stores', function (Blueprint $table) {
            $table->id();
            $table->string('name')->default('Test Store');
            $table->timestamps();
            $table->softDeletes();
        });

        DB::connection($conn)->table('stores')->insert(['id' => 1, 'name' => 'Main Store', 'created_at' => now(), 'updated_at' => now()]);

        Schema::connection($conn)->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name')->default('Test User');
            $table->timestamps();
        });

        Schema::connection($conn)->create('units_of_measure', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->timestamps();
        });

        Schema::connection($conn)->create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug');
            $table->string('sku')->unique();
            $table->string('product_type')->default('simple');
            $table->string('stock_status')->default('in_stock');
            $table->boolean('requires_batch_tracking')->default(false);
            $table->boolean('requires_serial_tracking')->default(false);
            $table->boolean('is_weighed')->default(false);
            $table->unsignedBigInteger('base_uom_id')->default(1);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_available_online')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::connection($conn)->create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->string('sku')->nullable();
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

        Schema::connection($conn)->create('inventory', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('product_variant_id')->nullable();
            $table->decimal('quantity_on_hand', 15, 4)->default(0);
            $table->decimal('quantity_reserved', 15, 4)->default(0);
            $table->decimal('quantity_available', 15, 4)->default(0);
            $table->decimal('quantity_damaged', 15, 4)->default(0);
            $table->date('last_restock_date')->nullable();
            $table->date('last_stock_take_date')->nullable();
            $table->unsignedBigInteger('last_restocked_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::connection($conn)->create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('product_variant_id')->nullable();
            $table->string('movement_type');
            $table->unsignedBigInteger('uom_id');
            $table->decimal('quantity', 15, 4);
            $table->decimal('quantity_in_base_uom', 15, 4);
            $table->decimal('unit_cost', 15, 2)->nullable();
            $table->decimal('unit_cost_in_base_uom', 15, 2)->nullable();
            $table->decimal('total_cost', 15, 2)->nullable();
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->decimal('balance_after', 15, 4);
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by_user')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::connection($conn)->create('inventory_reservations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('inventory_id');
            $table->string('reference_type');
            $table->unsignedBigInteger('reference_id');
            $table->decimal('quantity_reserved', 15, 4);
            $table->timestamp('reserved_until')->nullable();
            $table->string('status')->default('active');
            $table->string('cancellation_reason')->nullable();
            $table->unsignedBigInteger('cancelled_by')->nullable();
            $table->timestamps();
        });

        Schema::connection($conn)->create('product_batches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('product_variant_id')->nullable();
            $table->unsignedBigInteger('purchase_order_id');
            $table->unsignedBigInteger('source_batch_id')->nullable();
            $table->string('batch_number')->unique();
            $table->unsignedBigInteger('purchase_uom_id');
            $table->decimal('quantity_received_in_purchase_uom', 15, 4);
            $table->decimal('quantity_received_in_base_uom', 15, 4);
            $table->decimal('quantity_remaining_in_base_uom', 15, 4);
            $table->decimal('cost_per_purchase_uom', 15, 2);
            $table->decimal('cost_per_base_uom', 15, 2);
            $table->decimal('total_cost', 15, 2);
            $table->date('manufacture_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->boolean('is_expired')->default(false);
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::connection($conn)->create('product_serials', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('product_variant_id')->nullable();
            $table->unsignedBigInteger('purchase_order_id');
            $table->string('serial_number')->unique();
            $table->string('status')->default('available');
            $table->decimal('cost', 15, 2)->default(0);
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->unsignedBigInteger('sale_item_id')->nullable();
            $table->unsignedBigInteger('marketplace_sale_item_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::connection($conn)->create('marketplace_sales', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('central_order_id')->unique();
            $table->string('sale_number')->unique();
            $table->unsignedBigInteger('store_id');
            $table->dateTime('sale_date');
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->string('payment_status')->default('paid');
            $table->decimal('amount_paid', 12, 2)->default(0);
            $table->decimal('amount_due', 12, 2)->default(0);
            $table->string('payment_method')->nullable();
            $table->string('payment_reference')->nullable();
            $table->string('fulfillment_type')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::connection($conn)->create('marketplace_sale_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('marketplace_sale_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('product_variant_id')->nullable();
            $table->unsignedBigInteger('bundle_id')->nullable();
            $table->unsignedBigInteger('uom_id')->default(1);
            $table->decimal('quantity', 12, 4)->default(1);
            $table->decimal('quantity_in_base_uom', 12, 4)->default(1);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->timestamps();
        });

        Schema::connection($conn)->create('marketplace_sale_item_batch_depletions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('marketplace_sale_item_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('batch_id');
            $table->decimal('quantity_in_base_uom', 15, 4);
            $table->timestamps();
        });
    }
}
