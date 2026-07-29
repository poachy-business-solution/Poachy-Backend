<?php

namespace Tests\Feature\Tenant\Inventory;

use App\Events\Tenant\StockTransferApproved;
use App\Events\Tenant\StockTransferCancelled;
use App\Events\Tenant\StockTransferCompleted;
use App\Events\Tenant\StockTransferCreatedPendingApproval;
use App\Events\Tenant\StockTransferInTransit;
use App\Models\Tenant\Inventory;
use App\Models\Tenant\StockTransfer;
use App\Models\Tenant\StockTransferItem;
use App\Services\Tenant\Inventory\InventoryMovementService;
use App\Services\Tenant\Inventory\InventoryService;
use App\Services\Tenant\Inventory\ProductBatchService;
use App\Services\Tenant\Inventory\StockTransferService;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Stancl\Tenancy\Contracts\Tenant as TenantContract;
use Tests\TestCase;

class StockTransferServiceTest extends TestCase
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

    // =========================================================================
    // createTransfer()
    // =========================================================================

    public function test_create_transfer_happy_path(): void
    {
        $this->seedInventory(storeId: 1, productId: 1, qtyOnHand: 10, qtyReserved: 0);

        $transfer = Model::withoutEvents(fn () => $this->service()->createTransfer($this->transferData()));

        $this->assertSame('pending', $transfer->status);
        $this->assertSame(1, $transfer->requested_by);

        $item = StockTransferItem::first();
        $this->assertEquals(5.0, (float) $item->quantity_requested);
        $this->assertEquals(5.0, (float) $item->quantity_requested_in_base_uom);
    }

    public function test_create_transfer_throws_when_no_inventory_row_at_source(): void
    {
        $this->expectException(\RuntimeException::class);

        Model::withoutEvents(fn () => $this->service()->createTransfer($this->transferData()));
    }

    public function test_create_transfer_throws_when_insufficient_stock(): void
    {
        $this->seedInventory(storeId: 1, productId: 1, qtyOnHand: 2, qtyReserved: 0);

        $this->expectException(\RuntimeException::class);

        Model::withoutEvents(fn () => $this->service()->createTransfer($this->transferData(quantity: 5.0)));
    }

    // =========================================================================
    // approveTransfer()
    // =========================================================================

    public function test_approve_transfer_moves_pending_to_approved(): void
    {
        $transfer = $this->createDraftTransfer();

        $approved = Model::withoutEvents(fn () => $this->service()->approveTransfer($transfer->id));

        $this->assertSame('approved', $approved->status);
        $this->assertSame(1, $approved->approved_by);
    }

    public function test_approve_transfer_throws_if_stock_consumed_since_creation(): void
    {
        $transfer = $this->createDraftTransfer(qtyOnHand: 5, quantity: 5.0);

        DB::connection('tenant')->table('inventory')
            ->where('store_id', 1)->where('product_id', 1)
            ->update(['quantity_available' => 1]);

        $this->expectException(\RuntimeException::class);

        Model::withoutEvents(fn () => $this->service()->approveTransfer($transfer->id));
    }

    public function test_approve_transfer_throws_if_not_pending(): void
    {
        $transfer = $this->createDraftTransfer();
        Model::withoutEvents(fn () => $this->service()->approveTransfer($transfer->id));

        $this->expectException(\RuntimeException::class);

        Model::withoutEvents(fn () => $this->service()->approveTransfer($transfer->id));
    }

    // =========================================================================
    // sendTransfer()
    // =========================================================================

    public function test_send_transfer_moves_approved_to_in_transit_and_deducts_source(): void
    {
        $transfer = $this->createApprovedTransfer(qtyOnHand: 10, quantity: 5.0);

        $sent = Model::withoutEvents(fn () => $this->service()->sendTransfer($transfer->id));

        $this->assertSame('in_transit', $sent->status);
        $this->assertSame(1, $sent->sent_by);

        $sourceInventory = Inventory::where('store_id', 1)->where('product_id', 1)->first();
        $this->assertEquals(5.0, (float) $sourceInventory->quantity_on_hand);

        $item = StockTransferItem::first();
        $this->assertEquals(5.0, (float) $item->quantity_sent);
    }

    public function test_send_transfer_throws_if_not_approved(): void
    {
        $transfer = $this->createDraftTransfer();

        $this->expectException(\RuntimeException::class);

        Model::withoutEvents(fn () => $this->service()->sendTransfer($transfer->id));
    }

    // =========================================================================
    // receiveTransfer()
    // =========================================================================

    public function test_receive_transfer_moves_in_transit_to_completed_and_credits_destination(): void
    {
        $transfer = $this->createInTransitTransfer(qtyOnHand: 10, quantity: 5.0);
        $item = StockTransferItem::first();

        $completed = Model::withoutEvents(fn () => $this->service()->receiveTransfer($transfer->id, [
            $item->id => 5.0,
        ]));

        $this->assertSame('completed', $completed->status);
        $this->assertSame(1, $completed->received_by);

        $destinationInventory = Inventory::where('store_id', 2)->where('product_id', 1)->first();
        $this->assertEquals(5.0, (float) $destinationInventory->quantity_on_hand);

        $this->assertEquals(5.0, (float) $item->fresh()->quantity_received);
    }

    public function test_receive_transfer_throws_if_not_in_transit(): void
    {
        $transfer = $this->createApprovedTransfer(qtyOnHand: 10, quantity: 5.0);
        $item = StockTransferItem::first();

        $this->expectException(\RuntimeException::class);

        Model::withoutEvents(fn () => $this->service()->receiveTransfer($transfer->id, [$item->id => 5.0]));
    }

    public function test_receive_transfer_throws_if_received_exceeds_sent(): void
    {
        $transfer = $this->createInTransitTransfer(qtyOnHand: 10, quantity: 5.0);
        $item = StockTransferItem::first();

        $this->expectException(\RuntimeException::class);

        Model::withoutEvents(fn () => $this->service()->receiveTransfer($transfer->id, [$item->id => 6.0]));
    }

    public function test_partial_receipt_still_completes_transfer_with_discrepancy_note(): void
    {
        $transfer = $this->createInTransitTransfer(qtyOnHand: 10, quantity: 5.0);
        $item = StockTransferItem::first();

        $completed = Model::withoutEvents(fn () => $this->service()->receiveTransfer($transfer->id, [
            $item->id => 3.0,
        ]));

        $this->assertSame('completed', $completed->status);
        $this->assertStringContainsString('Discrepancy', $item->fresh()->notes ?? '');
    }

    // =========================================================================
    // cancelTransfer()
    // =========================================================================

    public function test_cancel_from_pending_succeeds(): void
    {
        $transfer = $this->createDraftTransfer();

        $cancelled = Model::withoutEvents(fn () => $this->service()->cancelTransfer($transfer->id, 'no longer needed'));

        $this->assertSame('cancelled', $cancelled->status);
        $this->assertSame('no longer needed', $cancelled->rejection_reason);
    }

    public function test_cancel_from_in_transit_throws(): void
    {
        $transfer = $this->createInTransitTransfer(qtyOnHand: 10, quantity: 5.0);

        $this->expectException(\RuntimeException::class);

        Model::withoutEvents(fn () => $this->service()->cancelTransfer($transfer->id, 'too late'));
    }

    // =========================================================================
    // getStoreTransfers() / getPendingApprovals()
    // =========================================================================

    public function test_get_store_transfers_filters_by_direction_and_status(): void
    {
        $this->seedInventory(storeId: 1, productId: 1, qtyOnHand: 20, qtyReserved: 0);
        $this->seedInventory(storeId: 3, productId: 1, qtyOnHand: 20, qtyReserved: 0);

        $outbound = Model::withoutEvents(fn () => $this->service()->createTransfer($this->transferData(from: 1, to: 2, quantity: 3.0)));
        Model::withoutEvents(fn () => $this->service()->createTransfer($this->transferData(from: 3, to: 1, quantity: 1.0)));

        $storeOneAll = $this->service()->getStoreTransfers(storeId: 1, direction: 'all');
        $storeOneOutbound = $this->service()->getStoreTransfers(storeId: 1, direction: 'outbound');
        $storeOneInbound = $this->service()->getStoreTransfers(storeId: 1, direction: 'inbound');

        $this->assertCount(2, $storeOneAll);
        $this->assertCount(1, $storeOneOutbound);
        $this->assertSame($outbound->id, $storeOneOutbound->first()->id);
        $this->assertCount(1, $storeOneInbound);
    }

    public function test_get_pending_approvals_filters_by_store(): void
    {
        $this->seedInventory(storeId: 1, productId: 1, qtyOnHand: 20, qtyReserved: 0);
        $transfer = Model::withoutEvents(fn () => $this->service()->createTransfer($this->transferData(from: 1, to: 2, quantity: 3.0)));

        $storeOnePending = $this->service()->getPendingApprovals(storeId: 1);
        $storeThreePending = $this->service()->getPendingApprovals(storeId: 3);

        $this->assertCount(1, $storeOnePending);
        $this->assertSame($transfer->id, $storeOnePending->first()->id);
        $this->assertCount(0, $storeThreePending);
    }

    // =========================================================================
    // Transfer numbering
    // =========================================================================

    public function test_transfer_numbers_are_sequential(): void
    {
        $this->seedInventory(storeId: 1, productId: 1, qtyOnHand: 20, qtyReserved: 0);

        $first = Model::withoutEvents(fn () => $this->service()->createTransfer($this->transferData(quantity: 1.0)));
        $second = Model::withoutEvents(fn () => $this->service()->createTransfer($this->transferData(quantity: 1.0)));

        $firstSeq = (int) substr($first->transfer_number, -4);
        $secondSeq = (int) substr($second->transfer_number, -4);

        $this->assertSame($firstSeq + 1, $secondSeq);
    }

    // =========================================================================
    // Event integration (not suppressed — proves the observer wiring end-to-end)
    // =========================================================================

    public function test_status_transitions_dispatch_corresponding_events(): void
    {
        Event::fake([
            StockTransferCreatedPendingApproval::class,
            StockTransferApproved::class,
            StockTransferInTransit::class,
            StockTransferCompleted::class,
            StockTransferCancelled::class,
        ]);

        $this->seedInventory(storeId: 1, productId: 1, qtyOnHand: 10, qtyReserved: 0);

        $transfer = $this->service()->createTransfer($this->transferData(quantity: 5.0));
        Event::assertDispatched(StockTransferCreatedPendingApproval::class, fn ($e) => $e->transfer->id === $transfer->id);

        $this->service()->approveTransfer($transfer->id);
        Event::assertDispatched(StockTransferApproved::class, fn ($e) => $e->transfer->id === $transfer->id);

        $this->service()->sendTransfer($transfer->id);
        Event::assertDispatched(StockTransferInTransit::class, fn ($e) => $e->transfer->id === $transfer->id);

        $item = StockTransferItem::first();
        $this->service()->receiveTransfer($transfer->id, [$item->id => 5.0]);
        Event::assertDispatched(StockTransferCompleted::class, fn ($e) => $e->transfer->id === $transfer->id);
    }

    public function test_cancel_dispatches_cancelled_event(): void
    {
        Event::fake([StockTransferCreatedPendingApproval::class, StockTransferCancelled::class]);

        $transfer = $this->createDraftTransfer();

        $this->service()->cancelTransfer($transfer->id, 'changed plans');

        Event::assertDispatched(StockTransferCancelled::class, fn ($e) => $e->transfer->id === $transfer->id);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function service(): StockTransferService
    {
        return new StockTransferService(new InventoryMovementService(new InventoryService), new ProductBatchService);
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

    private function createDraftTransfer(float $qtyOnHand = 10, float $quantity = 5.0): StockTransfer
    {
        $this->seedInventory(storeId: 1, productId: 1, qtyOnHand: $qtyOnHand, qtyReserved: 0);

        return Model::withoutEvents(fn () => $this->service()->createTransfer($this->transferData(quantity: $quantity)));
    }

    private function createApprovedTransfer(float $qtyOnHand = 10, float $quantity = 5.0): StockTransfer
    {
        $transfer = $this->createDraftTransfer($qtyOnHand, $quantity);

        return Model::withoutEvents(fn () => $this->service()->approveTransfer($transfer->id));
    }

    private function createInTransitTransfer(float $qtyOnHand = 10, float $quantity = 5.0): StockTransfer
    {
        $transfer = $this->createApprovedTransfer($qtyOnHand, $quantity);

        return Model::withoutEvents(fn () => $this->service()->sendTransfer($transfer->id));
    }

    private function seedInventory(int $storeId, int $productId, float $qtyOnHand, float $qtyReserved = 0): void
    {
        DB::connection('tenant')->table('inventory')->insert([
            'store_id' => $storeId,
            'product_id' => $productId,
            'product_variant_id' => null,
            'quantity_on_hand' => $qtyOnHand,
            'quantity_reserved' => $qtyReserved,
            'quantity_available' => max(0, $qtyOnHand - $qtyReserved),
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
            ['id' => 3, 'name' => 'Store Three', 'created_at' => now(), 'updated_at' => now()],
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
            'name' => 'Test Product',
            'slug' => 'test-product',
            'sku' => 'SKU-TEST-001',
            'requires_batch_tracking' => false,
            'base_uom_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function dropTestTables(): void
    {
        foreach ([
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
    }
}
