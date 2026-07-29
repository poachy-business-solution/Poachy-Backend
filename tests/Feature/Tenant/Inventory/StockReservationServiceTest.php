<?php

namespace Tests\Feature\Tenant\Inventory;

use App\Models\Tenant\Inventory;
use App\Models\Tenant\InventoryMovement;
use App\Models\Tenant\InventoryReservation;
use App\Services\Tenant\Inventory\InventoryMovementService;
use App\Services\Tenant\Inventory\InventoryService;
use App\Services\Tenant\Inventory\StockReservationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Stancl\Tenancy\Contracts\Tenant as TenantContract;
use Tests\TestCase;

class StockReservationServiceTest extends TestCase
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
    }

    protected function tearDown(): void
    {
        $this->dropTestTables();
        DB::connection('tenant')->statement('SET foreign_key_checks = 1');
        parent::tearDown();
    }

    // =========================================================================
    // reserveStock() / reserveSingleItem()
    // =========================================================================

    public function test_reserve_stock_happy_path(): void
    {
        $this->seedInventory(qtyOnHand: 10, qtyReserved: 0);

        $reservations = $this->service()->reserveStock('MarketplaceOrder', 1, [
            ['product_id' => 1, 'quantity' => 4, 'uom_id' => 1, 'store_id' => 1],
        ]);

        $this->assertCount(1, $reservations);
        $reservation = $reservations->first();
        $this->assertSame('active', $reservation->status->value);
        $this->assertEquals(4.0, (float) $reservation->quantity_reserved);
        $this->assertTrue($reservation->reserved_until->between(now()->addMinutes(29), now()->addMinutes(31)));

        $inventory = $this->freshInventory();
        $this->assertEquals(4.0, (float) $inventory->quantity_reserved);
        $this->assertEquals(6.0, (float) $inventory->quantity_available);
    }

    public function test_reserve_stock_throws_when_no_inventory_row(): void
    {
        $this->expectException(\RuntimeException::class);

        $this->service()->reserveStock('MarketplaceOrder', 1, [
            ['product_id' => 1, 'quantity' => 1, 'uom_id' => 1, 'store_id' => 1],
        ]);
    }

    public function test_reserve_stock_throws_when_insufficient_available(): void
    {
        $this->seedInventory(qtyOnHand: 3, qtyReserved: 0);

        $this->expectException(\RuntimeException::class);

        $this->service()->reserveStock('MarketplaceOrder', 1, [
            ['product_id' => 1, 'quantity' => 5, 'uom_id' => 1, 'store_id' => 1],
        ]);
    }

    public function test_reserve_stock_respects_custom_expiry(): void
    {
        $this->seedInventory(qtyOnHand: 10, qtyReserved: 0);

        $reservation = $this->service()->reserveStock('MarketplaceOrder', 1, [
            ['product_id' => 1, 'quantity' => 1, 'uom_id' => 1, 'store_id' => 1],
        ], expiresInMinutes: 120)->first();

        $this->assertTrue($reservation->reserved_until->between(now()->addMinutes(119), now()->addMinutes(121)));
    }

    public function test_reserve_stock_rolls_back_entire_batch_on_later_item_failure(): void
    {
        $this->seedInventory(qtyOnHand: 10, qtyReserved: 0);

        try {
            $this->service()->reserveStock('MarketplaceOrder', 1, [
                ['product_id' => 1, 'quantity' => 6, 'uom_id' => 1, 'store_id' => 1],
                ['product_id' => 1, 'quantity' => 5, 'uom_id' => 1, 'store_id' => 1], // only 4 left
            ]);
            $this->fail('Expected exception was not thrown.');
        } catch (\RuntimeException) {
            // expected
        }

        $this->assertSame(0, InventoryReservation::count());
        $inventory = $this->freshInventory();
        $this->assertEquals(0.0, (float) $inventory->quantity_reserved);
        $this->assertEquals(10.0, (float) $inventory->quantity_available);
    }

    public function test_reserve_stock_converts_non_base_uom_before_reserving(): void
    {
        $this->seedInventory(qtyOnHand: 100, qtyReserved: 0);
        DB::connection('tenant')->table('product_uoms')->insert([
            'product_id' => 1, 'uom_id' => 2, 'conversion_to_base' => 12.0,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $reservation = $this->service()->reserveStock('MarketplaceOrder', 1, [
            ['product_id' => 1, 'quantity' => 2, 'uom_id' => 2, 'store_id' => 1], // 2 boxes = 24 base units
        ])->first();

        $this->assertEquals(24.0, (float) $reservation->quantity_reserved);
        $this->assertEquals(24.0, (float) $this->freshInventory()->quantity_reserved);
    }

    // =========================================================================
    // releaseReservation()
    // =========================================================================

    public function test_release_reservation_cancels_and_frees_stock(): void
    {
        $this->seedInventory(qtyOnHand: 10, qtyReserved: 0);
        $reservation = $this->reserve(quantity: 4);

        $result = $this->service()->releaseReservation($reservation->id, 'customer cancelled', cancelledBy: 1);

        $this->assertTrue($result);
        $fresh = $reservation->fresh();
        $this->assertSame('cancelled', $fresh->status->value);
        $this->assertSame('customer cancelled', $fresh->cancellation_reason);
        $this->assertSame(1, $fresh->cancelled_by);

        $inventory = $this->freshInventory();
        $this->assertEquals(0.0, (float) $inventory->quantity_reserved);
        $this->assertEquals(10.0, (float) $inventory->quantity_available);
    }

    public function test_release_reservation_is_a_noop_when_already_closed(): void
    {
        $this->seedInventory(qtyOnHand: 10, qtyReserved: 0);
        $reservation = $this->reserve(quantity: 4);
        $this->service()->releaseReservation($reservation->id, 'first release');

        $result = $this->service()->releaseReservation($reservation->id, 'second release attempt');

        $this->assertFalse($result);
    }

    // =========================================================================
    // confirmReservation()
    // =========================================================================

    public function test_confirm_reservation_records_movement_and_uses_post_movement_balance(): void
    {
        // on_hand=100, already 20 reserved (this reservation), available=80.
        $this->seedInventory(qtyOnHand: 100, qtyReserved: 20);
        $reservation = $this->reserveRaw(quantity: 20);

        $movement = $this->service()->confirmReservation($reservation->id);

        $this->assertInstanceOf(InventoryMovement::class, $movement);
        $this->assertSame('sale', $movement->movement_type->value);
        $this->assertEquals(-20.0, (float) $movement->quantity);

        $this->assertSame('fulfilled', $reservation->fresh()->status->value);

        $inventory = $this->freshInventory();
        $this->assertEquals(80.0, (float) $inventory->quantity_on_hand);
        $this->assertEquals(0.0, (float) $inventory->quantity_reserved);
        // Would be 100 (stale on_hand) if confirmReservation() didn't refresh after recordMovement().
        $this->assertEquals(80.0, (float) $inventory->quantity_available);
    }

    public function test_confirm_reservation_throws_if_not_active(): void
    {
        $this->seedInventory(qtyOnHand: 10, qtyReserved: 0);
        $reservation = $this->reserve(quantity: 2);
        $this->service()->releaseReservation($reservation->id, 'cancelled first');

        $this->expectException(\RuntimeException::class);

        $this->service()->confirmReservation($reservation->id);
    }

    // =========================================================================
    // expireStaleReservations()
    // =========================================================================

    public function test_expire_stale_reservations_releases_only_lapsed_ones(): void
    {
        $this->seedInventory(qtyOnHand: 10, qtyReserved: 8);
        $stale = $this->reserveRaw(quantity: 5, reservedUntil: now()->subMinute());
        $valid = $this->reserveRaw(quantity: 3, reservedUntil: now()->addMinutes(30));

        $count = $this->service()->expireStaleReservations();

        $this->assertSame(1, $count);
        $this->assertSame('expired', $stale->fresh()->status->value);
        $this->assertSame('active', $valid->fresh()->status->value);

        $inventory = $this->freshInventory();
        $this->assertEquals(3.0, (float) $inventory->quantity_reserved);
    }

    // =========================================================================
    // Read helpers
    // =========================================================================

    public function test_get_active_reservations_filters_by_store_and_product(): void
    {
        $this->seedInventory(qtyOnHand: 10, qtyReserved: 0, storeId: 1, productId: 1);
        $this->seedInventory(qtyOnHand: 10, qtyReserved: 0, storeId: 1, productId: 2);
        $this->seedInventory(qtyOnHand: 10, qtyReserved: 0, storeId: 2, productId: 1);

        $this->reserve(quantity: 1, storeId: 1, productId: 1);
        $this->reserve(quantity: 1, storeId: 1, productId: 2);
        $this->reserve(quantity: 1, storeId: 2, productId: 1);

        $storeOneAll = $this->service()->getActiveReservations(storeId: 1);
        $storeOneProductOne = $this->service()->getActiveReservations(storeId: 1, productId: 1);

        $this->assertCount(2, $storeOneAll);
        $this->assertCount(1, $storeOneProductOne);
    }

    public function test_get_reservations_by_reference_returns_regardless_of_status(): void
    {
        $this->seedInventory(qtyOnHand: 10, qtyReserved: 0);
        $active = $this->reserve(quantity: 2, referenceId: 99);
        $this->service()->releaseReservation($active->id, 'cancelled');

        $results = $this->service()->getReservationsByReference('MarketplaceOrder', 99);

        $this->assertCount(1, $results);
        $this->assertSame('cancelled', $results->first()->status->value);
    }

    // =========================================================================
    // Bulk-by-reference helpers
    // =========================================================================

    public function test_release_all_reservations_for_reference_only_releases_active(): void
    {
        $this->seedInventory(qtyOnHand: 20, qtyReserved: 0);
        $first = $this->reserve(quantity: 3, referenceId: 5);
        $second = $this->reserve(quantity: 2, referenceId: 5);
        $this->service()->releaseReservation($second->id, 'already cancelled earlier');

        $count = $this->service()->releaseAllReservationsForReference('MarketplaceOrder', 5, 'order cancelled');

        $this->assertSame(1, $count);
        $this->assertSame('cancelled', $first->fresh()->status->value);
    }

    public function test_confirm_all_reservations_for_reference_only_confirms_active(): void
    {
        $this->seedInventory(qtyOnHand: 20, qtyReserved: 0);
        $first = $this->reserve(quantity: 3, referenceId: 7);
        $second = $this->reserve(quantity: 2, referenceId: 7);
        $this->service()->releaseReservation($second->id, 'cancelled before payment');

        $count = $this->service()->confirmAllReservationsForReference('MarketplaceOrder', 7);

        $this->assertSame(1, $count);
        $this->assertSame('fulfilled', $first->fresh()->status->value);
        $this->assertSame('cancelled', $second->fresh()->status->value);
    }

    // =========================================================================
    // extendReservation()
    // =========================================================================

    public function test_extend_reservation_pushes_out_expiry(): void
    {
        $this->seedInventory(qtyOnHand: 10, qtyReserved: 0);
        $reservation = $this->reserve(quantity: 1);
        $originalExpiry = $reservation->reserved_until;

        $result = $this->service()->extendReservation($reservation->id, 15);

        $this->assertTrue($result);
        $this->assertTrue($reservation->fresh()->reserved_until->equalTo($originalExpiry->copy()->addMinutes(15)));
    }

    public function test_extend_reservation_throws_when_not_active(): void
    {
        $this->seedInventory(qtyOnHand: 10, qtyReserved: 0);
        $reservation = $this->reserve(quantity: 1);
        $this->service()->releaseReservation($reservation->id, 'cancelled');

        $this->expectException(\RuntimeException::class);

        $this->service()->extendReservation($reservation->id, 15);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function service(): StockReservationService
    {
        return new StockReservationService(new InventoryMovementService(new InventoryService));
    }

    private function reserve(float $quantity, int $storeId = 1, int $productId = 1, int $referenceId = 1): InventoryReservation
    {
        return $this->service()->reserveStock('MarketplaceOrder', $referenceId, [
            ['product_id' => $productId, 'quantity' => $quantity, 'uom_id' => 1, 'store_id' => $storeId],
        ])->first();
    }

    /** Creates a reservation directly (bypassing reserveStock's own inventory checks) so tests can set up pre-existing reserved state precisely. */
    private function reserveRaw(float $quantity, ?Carbon $reservedUntil = null): InventoryReservation
    {
        $inventory = $this->freshInventory();

        return InventoryReservation::create([
            'inventory_id' => $inventory->id,
            'reference_type' => 'MarketplaceOrder',
            'reference_id' => 1,
            'quantity_reserved' => $quantity,
            'reserved_until' => $reservedUntil ?? now()->addMinutes(30),
            'status' => 'active',
        ]);
    }

    private function seedInventory(float $qtyOnHand, float $qtyReserved, int $storeId = 1, int $productId = 1): void
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

    private function freshInventory(int $storeId = 1, int $productId = 1): Inventory
    {
        return Inventory::where('store_id', $storeId)->where('product_id', $productId)->firstOrFail();
    }

    private function seedBaseData(): void
    {
        $conn = 'tenant';

        DB::connection($conn)->table('stores')->insert([
            ['id' => 1, 'name' => 'Store One', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'Store Two', 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::connection($conn)->table('units_of_measure')->insert([
            ['id' => 1, 'code' => 'pcs', 'name' => 'Piece', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'code' => 'box', 'name' => 'Box', 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::connection($conn)->table('products')->insert([
            ['id' => 1, 'name' => 'Product One', 'slug' => 'product-one', 'sku' => 'SKU-001', 'base_uom_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'Product Two', 'slug' => 'product-two', 'sku' => 'SKU-002', 'base_uom_id' => 1, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    private function dropTestTables(): void
    {
        foreach ([
            'inventory_reservations',
            'inventory_movements',
            'inventory',
            'product_uoms',
            'product_variants',
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

        Schema::connection($conn)->create('inventory_reservations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('inventory_id');
            $table->string('reference_type', 50);
            $table->unsignedBigInteger('reference_id');
            $table->decimal('quantity_reserved', 15, 4)->default(0);
            $table->timestamp('reserved_until')->nullable();
            $table->string('status')->default('active');
            $table->text('cancellation_reason')->nullable();
            $table->unsignedBigInteger('cancelled_by')->nullable();
            $table->timestamps();
        });
    }
}
