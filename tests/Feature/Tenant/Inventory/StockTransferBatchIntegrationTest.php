<?php

namespace Tests\Feature\Tenant\Inventory;

use App\Models\Tenant\Inventory;
use App\Models\Tenant\ProductBatch;
use App\Models\Tenant\ProductSerial;
use App\Models\Tenant\StockTransfer;
use App\Services\Tenant\Inventory\InventoryMovementService;
use App\Services\Tenant\Inventory\InventoryService;
use App\Services\Tenant\Inventory\ProductBatchService;
use App\Services\Tenant\Inventory\ProductSerialService;
use App\Services\Tenant\Inventory\StockTransferService;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Stancl\Tenancy\Contracts\Tenant as TenantContract;
use Tests\TestCase;

class StockTransferBatchIntegrationTest extends TestCase
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

        $fakeTenant = new \stdClass;
        $fakeTenant->id = 'test-tenant';
        app()->instance(TenantContract::class, $fakeTenant);

        Auth::setUser(new class implements Authenticatable
        {
            public function getAuthIdentifierName()
            {
                return 'id';
            }

            public function getAuthIdentifier()
            {
                return 1;
            }

            public function getAuthPasswordName()
            {
                return 'password';
            }

            public function getAuthPassword()
            {
                return '';
            }

            public function getRememberToken()
            {
                return null;
            }

            public function setRememberToken($value) {}

            public function getRememberTokenName()
            {
                return 'remember_token';
            }
        });
    }

    protected function tearDown(): void
    {
        $this->dropTestTables();
        DB::connection('tenant')->statement('SET foreign_key_checks = 1');
        parent::tearDown();
    }

    public function test_batch_tracked_transfer_with_single_sufficient_source_batch(): void
    {
        $this->seedInventory(storeId: 1, productId: 1, qtyOnHand: 10);
        $sourceBatch = $this->createBatch([
            'store_id' => 1,
            'purchase_order_id' => 1,
            'quantity_remaining_in_base_uom' => 10.0,
            'cost_per_base_uom' => 5.0,
            'expiry_date' => now()->addMonths(6)->toDateString(),
        ]);

        $transfer = $this->createInTransitTransfer(quantity: 6.0);

        $this->assertEquals(4.0, (float) $sourceBatch->fresh()->quantity_remaining_in_base_uom);

        $destinationBatches = ProductBatch::where('store_id', 2)->get();
        $this->assertCount(1, $destinationBatches);

        $destinationBatch = $destinationBatches->first();
        $this->assertSame($sourceBatch->id, $destinationBatch->source_batch_id);
        $this->assertEquals(6.0, (float) $destinationBatch->quantity_remaining_in_base_uom);
        $this->assertEquals(5.0, (float) $destinationBatch->cost_per_base_uom);
        $this->assertSame($sourceBatch->expiry_date->toDateString(), $destinationBatch->expiry_date->toDateString());
        $this->assertStringContainsString($transfer->transfer_number, $destinationBatch->notes);
    }

    public function test_batch_tracked_transfer_spanning_two_source_batches(): void
    {
        $this->seedInventory(storeId: 1, productId: 1, qtyOnHand: 14);
        $batchA = $this->createBatch(['store_id' => 1, 'purchase_order_id' => 1, 'quantity_remaining_in_base_uom' => 4.0, 'cost_per_base_uom' => 5.0]);
        $batchB = $this->createBatch(['store_id' => 1, 'purchase_order_id' => 2, 'quantity_remaining_in_base_uom' => 10.0, 'cost_per_base_uom' => 8.0]);

        $this->createInTransitTransfer(quantity: 7.0);

        $this->assertEquals(0.0, (float) $batchA->fresh()->quantity_remaining_in_base_uom);
        $this->assertEquals(7.0, (float) $batchB->fresh()->quantity_remaining_in_base_uom);

        $destinationBatches = ProductBatch::where('store_id', 2)->orderBy('source_batch_id')->get();
        $this->assertCount(2, $destinationBatches);

        $fromA = $destinationBatches->firstWhere('source_batch_id', $batchA->id);
        $fromB = $destinationBatches->firstWhere('source_batch_id', $batchB->id);

        $this->assertEquals(4.0, (float) $fromA->quantity_remaining_in_base_uom);
        $this->assertEquals(5.0, (float) $fromA->cost_per_base_uom);
        $this->assertEquals(3.0, (float) $fromB->quantity_remaining_in_base_uom);
        $this->assertEquals(8.0, (float) $fromB->cost_per_base_uom);
    }

    public function test_non_batch_tracked_transfer_does_not_touch_product_batches(): void
    {
        DB::connection('tenant')->table('products')->insert([
            'id' => 2,
            'name' => 'Non Batch Product',
            'slug' => 'non-batch-product',
            'sku' => 'SKU-NONBATCH-001',
            'requires_batch_tracking' => false,
            'base_uom_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->seedInventory(storeId: 1, productId: 2, qtyOnHand: 10);

        $this->createInTransitTransfer(productId: 2, quantity: 5.0);

        $this->assertSame(0, ProductBatch::count());
    }

    public function test_insufficient_batch_stock_throws_at_send_despite_sufficient_store_level_inventory(): void
    {
        // Store-level inventory says 10 available (enough to pass create/approve checks)...
        $this->seedInventory(storeId: 1, productId: 1, qtyOnHand: 10);
        // ...but the batch ledger only actually has 3 remaining (simulates drift between the two ledgers).
        $this->createBatch(['store_id' => 1, 'purchase_order_id' => 1, 'quantity_remaining_in_base_uom' => 3.0]);

        $transfer = Model::withoutEvents(function () {
            $t = $this->service()->createTransfer($this->transferData(quantity: 6.0));

            return $this->service()->approveTransfer($t->id);
        });

        try {
            Model::withoutEvents(fn () => $this->service()->sendTransfer($transfer->id));
            $this->fail('Expected RuntimeException was not thrown.');
        } catch (\RuntimeException) {
            // expected
        }

        // The whole sendTransfer() transaction rolled back, including the store-level movement.
        $sourceInventory = Inventory::where('store_id', 1)->where('product_id', 1)->first();
        $this->assertEquals(10.0, (float) $sourceInventory->quantity_on_hand);
        $this->assertSame('approved', $transfer->fresh()->status);
    }

    public function test_serial_tracked_transfer_moves_serial_to_destination_store(): void
    {
        DB::connection('tenant')->table('products')->insert([
            'id' => 3,
            'name' => 'Serial Tracked Product',
            'slug' => 'serial-tracked-product',
            'sku' => 'SKU-SERIAL-001',
            'requires_batch_tracking' => false,
            'requires_serial_tracking' => true,
            'base_uom_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->seedInventory(storeId: 1, productId: 3, qtyOnHand: 1);
        $serial = $this->createSerial(['store_id' => 1, 'product_id' => 3, 'serial_number' => 'IMEI-001']);

        $transfer = Model::withoutEvents(function () {
            $t = $this->service()->createTransfer($this->transferData(quantity: 1.0, productId: 3));

            return $this->service()->approveTransfer($t->id);
        });

        $item = $transfer->items->first();

        Model::withoutEvents(fn () => $this->service()->sendTransfer($transfer->id, [
            $item->id => ['IMEI-001'],
        ]));

        $serial->refresh();
        $this->assertSame(2, $serial->store_id);
        $this->assertSame('available', $serial->status->value);
    }

    public function test_serial_tracked_transfer_throws_when_serial_count_mismatches_quantity(): void
    {
        DB::connection('tenant')->table('products')->insert([
            'id' => 3,
            'name' => 'Serial Tracked Product',
            'slug' => 'serial-tracked-product',
            'sku' => 'SKU-SERIAL-001',
            'requires_batch_tracking' => false,
            'requires_serial_tracking' => true,
            'base_uom_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->seedInventory(storeId: 1, productId: 3, qtyOnHand: 2);
        $this->createSerial(['store_id' => 1, 'product_id' => 3, 'serial_number' => 'IMEI-001']);
        $this->createSerial(['store_id' => 1, 'product_id' => 3, 'serial_number' => 'IMEI-002']);

        $transfer = Model::withoutEvents(function () {
            $t = $this->service()->createTransfer($this->transferData(quantity: 2.0, productId: 3));

            return $this->service()->approveTransfer($t->id);
        });

        $item = $transfer->items->first();

        $this->expectException(\RuntimeException::class);

        Model::withoutEvents(fn () => $this->service()->sendTransfer($transfer->id, [
            $item->id => ['IMEI-001'], // only 1 for quantity 2
        ]));
    }

    public function test_serial_tracked_transfer_throws_when_serial_not_at_source_store(): void
    {
        DB::connection('tenant')->table('products')->insert([
            'id' => 3,
            'name' => 'Serial Tracked Product',
            'slug' => 'serial-tracked-product',
            'sku' => 'SKU-SERIAL-001',
            'requires_batch_tracking' => false,
            'requires_serial_tracking' => true,
            'base_uom_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->seedInventory(storeId: 1, productId: 3, qtyOnHand: 1);
        // Serial exists but belongs to store 2, not the transfer's source store (1).
        $this->createSerial(['store_id' => 2, 'product_id' => 3, 'serial_number' => 'IMEI-001']);

        $transfer = Model::withoutEvents(function () {
            $t = $this->service()->createTransfer($this->transferData(quantity: 1.0, productId: 3));

            return $this->service()->approveTransfer($t->id);
        });

        $item = $transfer->items->first();

        $this->expectException(\RuntimeException::class);

        Model::withoutEvents(fn () => $this->service()->sendTransfer($transfer->id, [
            $item->id => ['IMEI-001'],
        ]));
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function service(): StockTransferService
    {
        return new StockTransferService(new InventoryMovementService(new InventoryService), new ProductBatchService, new ProductSerialService);
    }

    private function transferData(int $from = 1, int $to = 2, float $quantity = 5.0, int $productId = 1): array
    {
        return [
            'from_store_id' => $from,
            'to_store_id' => $to,
            'items' => [
                ['product_id' => $productId, 'quantity' => $quantity, 'uom_id' => 1],
            ],
        ];
    }

    private function createInTransitTransfer(int $productId = 1, float $quantity = 5.0): StockTransfer
    {
        return Model::withoutEvents(function () use ($productId, $quantity) {
            $transfer = $this->service()->createTransfer($this->transferData(productId: $productId, quantity: $quantity));
            $this->service()->approveTransfer($transfer->id);

            return $this->service()->sendTransfer($transfer->id);
        });
    }

    private function createSerial(array $overrides = []): ProductSerial
    {
        return Model::withoutEvents(fn () => ProductSerial::create(array_merge([
            'store_id' => 1,
            'product_id' => 1,
            'purchase_order_id' => 1,
            'serial_number' => 'IMEI-'.uniqid(),
            'status' => 'available',
            'cost' => 40.0,
        ], $overrides)));
    }

    private function createBatch(array $overrides = []): ProductBatch
    {
        return Model::withoutEvents(fn () => ProductBatch::create(array_merge([
            'store_id' => 1,
            'product_id' => 1,
            'product_variant_id' => null,
            'purchase_order_id' => 1,
            'batch_number' => 'BATCH-'.uniqid(),
            'purchase_uom_id' => 1,
            'quantity_received_in_purchase_uom' => 10.0,
            'quantity_received_in_base_uom' => 10.0,
            'quantity_remaining_in_base_uom' => 10.0,
            'cost_per_purchase_uom' => 5.0,
            'cost_per_base_uom' => 5.0,
            'total_cost' => 50.0,
            'is_expired' => false,
        ], $overrides)));
    }

    private function seedInventory(int $storeId, int $productId, float $qtyOnHand): void
    {
        DB::connection('tenant')->table('inventory')->insert([
            'store_id' => $storeId,
            'product_id' => $productId,
            'product_variant_id' => null,
            'quantity_on_hand' => $qtyOnHand,
            'quantity_reserved' => 0,
            'quantity_available' => $qtyOnHand,
            'quantity_damaged' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedBaseData(): void
    {
        $conn = 'tenant';

        DB::connection($conn)->table('stores')->insert([
            ['id' => 1, 'name' => 'Store One', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'Store Two', 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::connection($conn)->table('users')->insert([
            'id' => 1,
            'name' => 'Test User',
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

        DB::connection($conn)->table('products')->insert([
            'id' => 1,
            'name' => 'Batch Tracked Product',
            'slug' => 'batch-tracked-product',
            'sku' => 'SKU-BATCH-001',
            'requires_batch_tracking' => true,
            'base_uom_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function dropTestTables(): void
    {
        foreach ([
            'product_batches',
            'product_serials',
            'stock_transfer_items',
            'stock_transfers',
            'inventory_movements',
            'inventory',
            'product_variants',
            'product_uoms',
            'products',
            'units_of_measure',
            'users',
            'stores',
        ] as $table) {
            Schema::connection('tenant')->dropIfExists($table);
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
            $table->boolean('requires_batch_tracking')->default(false);
            $table->boolean('requires_serial_tracking')->default(false);
            $table->unsignedBigInteger('base_uom_id')->default(1);
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

        Schema::connection($conn)->create('stock_transfers', function (Blueprint $table) {
            $table->id();
            $table->string('transfer_number')->unique();
            $table->unsignedBigInteger('from_store_id');
            $table->unsignedBigInteger('to_store_id');
            $table->string('status')->default('pending');
            $table->date('transfer_date');
            $table->date('expected_arrival_date')->nullable();
            $table->date('actual_arrival_date')->nullable();
            $table->unsignedBigInteger('requested_by')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('sent_by')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->unsignedBigInteger('received_by')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->text('notes')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
        });

        Schema::connection($conn)->create('stock_transfer_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('transfer_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('product_variant_id')->nullable();
            $table->unsignedBigInteger('uom_id');
            $table->decimal('quantity_requested', 15, 4);
            $table->decimal('quantity_sent', 15, 4)->default(0);
            $table->decimal('quantity_received', 15, 4)->default(0);
            $table->decimal('quantity_requested_in_base_uom', 15, 4);
            $table->decimal('quantity_sent_in_base_uom', 15, 4)->default(0);
            $table->decimal('quantity_received_in_base_uom', 15, 4)->default(0);
            $table->text('notes')->nullable();
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
    }
}
