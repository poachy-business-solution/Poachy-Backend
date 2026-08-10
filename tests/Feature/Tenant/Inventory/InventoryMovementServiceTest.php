<?php

namespace Tests\Feature\Tenant\Inventory;

use App\Enums\Tenant\InventoryMovementType;
use App\Events\Tenant\InventoryBalanceUpdated;
use App\Events\Tenant\InventoryMovementRecorded;
use App\Http\Controllers\Api\Tenant\Inventory\InventoryMovementController;
use App\Http\Requests\Tenant\Inventory\CreateAdjustmentRequest;
use App\Models\Tenant\Inventory;
use App\Models\Tenant\InventoryMovement;
use App\Services\Tenant\Inventory\InventoryMovementService;
use App\Services\Tenant\Inventory\InventoryService;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Stancl\Tenancy\Contracts\Tenant as TenantContract;
use Tests\TestCase;

class InventoryMovementServiceTest extends TestCase
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
    // recordMovement() — core behaviour
    // =========================================================================

    public function test_creates_inventory_row_when_none_exists_and_records_purchase_movement(): void
    {
        $this->assertSame(0, Inventory::count());

        $movement = Model::withoutEvents(fn () => $this->service()->recordMovement([
            'store_id' => 1,
            'product_id' => 1,
            'movement_type' => InventoryMovementType::PURCHASE,
            'uom_id' => 1,
            'quantity' => 10.0,
            'notes' => 'initial stock',
        ]));

        $inventory = Inventory::where('store_id', 1)->where('product_id', 1)->first();

        $this->assertNotNull($inventory);
        $this->assertEquals(10.0, (float) $inventory->quantity_on_hand);
        $this->assertEquals(10.0, (float) $inventory->quantity_available);
        $this->assertNotNull($inventory->last_restock_date);
        $this->assertSame(1, $inventory->last_restocked_by);
        $this->assertEquals(10.0, (float) $movement->balance_after);
    }

    public function test_positive_movement_increases_balance_and_recomputes_available(): void
    {
        $this->seedInventory(onHand: 50, reserved: 5);

        Model::withoutEvents(fn () => $this->service()->recordMovement([
            'store_id' => 1,
            'product_id' => 1,
            'movement_type' => InventoryMovementType::PURCHASE,
            'uom_id' => 1,
            'quantity' => 20.0,
        ]));

        $inventory = $this->freshInventory();
        $this->assertEquals(70.0, (float) $inventory->quantity_on_hand);
        $this->assertEquals(65.0, (float) $inventory->quantity_available);
    }

    public function test_negative_movement_decreases_balance(): void
    {
        $this->seedInventory(onHand: 50, reserved: 0);

        Model::withoutEvents(fn () => $this->service()->recordMovement([
            'store_id' => 1,
            'product_id' => 1,
            'movement_type' => InventoryMovementType::SALE,
            'uom_id' => 1,
            'quantity' => -20.0,
        ]));

        $inventory = $this->freshInventory();
        $this->assertEquals(30.0, (float) $inventory->quantity_on_hand);
        $this->assertEquals(30.0, (float) $inventory->quantity_available);
    }

    public function test_negative_movement_exceeding_stock_throws_and_does_not_persist(): void
    {
        $this->seedInventory(onHand: 10, reserved: 0);

        try {
            Model::withoutEvents(fn () => $this->service()->recordMovement([
                'store_id' => 1,
                'product_id' => 1,
                'movement_type' => InventoryMovementType::SALE,
                'uom_id' => 1,
                'quantity' => -20.0,
            ]));
            $this->fail('Expected RuntimeException was not thrown.');
        } catch (\RuntimeException) {
            // expected
        }

        $inventory = $this->freshInventory();
        $this->assertEquals(10.0, (float) $inventory->quantity_on_hand);
        $this->assertSame(0, InventoryMovement::count());
    }

    public function test_damage_movement_is_exempt_from_negative_balance_guard(): void
    {
        $this->seedInventory(onHand: 10, reserved: 0);

        Model::withoutEvents(fn () => $this->service()->recordDamage([
            'store_id' => 1,
            'product_id' => 1,
            'uom_id' => 1,
            'quantity' => 20.0,
        ]));

        $inventory = $this->freshInventory();
        $this->assertEquals(0.0, (float) $inventory->quantity_on_hand);
        $this->assertEquals(20.0, (float) $inventory->quantity_damaged);
        $this->assertEquals(0.0, (float) $inventory->quantity_available);
    }

    public function test_last_restock_fields_only_update_on_positive_movement(): void
    {
        $this->seedInventory(onHand: 50, reserved: 0);

        Model::withoutEvents(fn () => $this->service()->recordMovement([
            'store_id' => 1,
            'product_id' => 1,
            'movement_type' => InventoryMovementType::SALE,
            'uom_id' => 1,
            'quantity' => -10.0,
        ]));

        $inventory = $this->freshInventory();
        $this->assertNull($inventory->last_restock_date);
        $this->assertNull($inventory->last_restocked_by);

        Model::withoutEvents(fn () => $this->service()->recordMovement([
            'store_id' => 1,
            'product_id' => 1,
            'movement_type' => InventoryMovementType::PURCHASE,
            'uom_id' => 1,
            'quantity' => 10.0,
        ]));

        $inventory = $this->freshInventory();
        $this->assertNotNull($inventory->last_restock_date);
        $this->assertSame(1, $inventory->last_restocked_by);
    }

    public function test_cost_conversion_computes_unit_cost_and_total_cost_in_base_uom(): void
    {
        // uom_id 2 = "box", 1 box = 12 base units (seeded in product_uoms)
        $movement = Model::withoutEvents(fn () => $this->service()->recordMovement([
            'store_id' => 1,
            'product_id' => 1,
            'movement_type' => InventoryMovementType::PURCHASE,
            'uom_id' => 2,
            'quantity' => 2.0,
            'unit_cost' => 120.0,
        ]));

        $this->assertEquals(2.0, (float) $movement->quantity);
        $this->assertEquals(24.0, (float) $movement->quantity_in_base_uom);
        $this->assertEquals(120.0, (float) $movement->unit_cost);
        $this->assertEquals(10.0, (float) $movement->unit_cost_in_base_uom);
        $this->assertEquals(240.0, (float) $movement->total_cost);

        $inventory = $this->freshInventory();
        $this->assertEquals(24.0, (float) $inventory->quantity_on_hand);
    }

    public function test_uom_equal_to_base_uom_skips_product_uom_lookup(): void
    {
        // No product_uoms row exists for (product 1, uom 1) — if the short-circuit
        // in convertToBaseUom() broke, this would throw ModelNotFoundException.
        $movement = Model::withoutEvents(fn () => $this->service()->recordMovement([
            'store_id' => 1,
            'product_id' => 1,
            'movement_type' => InventoryMovementType::PURCHASE,
            'uom_id' => 1,
            'quantity' => 15.0,
        ]));

        $this->assertEquals(15.0, (float) $movement->quantity_in_base_uom);
    }

    // =========================================================================
    // recordPurchase() / recordSale() — batch wrappers
    // =========================================================================

    public function test_record_purchase_has_no_batch_transaction_so_earlier_items_persist_on_later_failure(): void
    {
        $this->seedInventory(onHand: 0, reserved: 0);

        try {
            Model::withoutEvents(fn () => $this->service()->recordPurchase(1, [
                [
                    'store_id' => 1,
                    'product_id' => 1,
                    'uom_id' => 1,
                    'quantity' => 10.0,
                    'unit_cost' => 5.0,
                ],
                [
                    'store_id' => 1,
                    'product_id' => 1,
                    'uom_id' => 999, // no product_uoms row for this uom -> validation error
                    'quantity' => 5.0,
                    'unit_cost' => 5.0,
                ],
            ]));
            $this->fail('Expected an exception from the second item.');
        } catch (ValidationException) {
            // expected
        }

        // First item's movement/inventory change was NOT rolled back, since
        // recordPurchase() has no outer transaction of its own.
        $inventory = $this->freshInventory();
        $this->assertEquals(10.0, (float) $inventory->quantity_on_hand);
        $this->assertSame(1, InventoryMovement::count());
    }

    public function test_record_sale_forces_negative_quantity_and_reference_type(): void
    {
        $this->seedInventory(onHand: 50, reserved: 0);

        $movements = Model::withoutEvents(fn () => $this->service()->recordSale(42, [
            ['store_id' => 1, 'product_id' => 1, 'uom_id' => 1, 'quantity' => 15.0],
        ]));

        $movement = $movements->first();
        $this->assertEquals(-15.0, (float) $movement->quantity);
        $this->assertSame(InventoryMovementType::SALE, $movement->movement_type);
        $this->assertSame('Sale', $movement->reference_type);
        $this->assertSame(42, $movement->reference_id);
    }

    // =========================================================================
    // recordAdjustment() / recordDamage() / recordReturn()
    // =========================================================================

    public function test_record_adjustment_decrease_type_produces_negative_quantity(): void
    {
        $this->seedInventory(onHand: 50, reserved: 0);

        $movement = Model::withoutEvents(fn () => $this->service()->recordAdjustment([
            'store_id' => 1,
            'product_id' => 1,
            'uom_id' => 1,
            'quantity' => 10.0,
            'adjustment_type' => 'decrease',
        ]));

        $this->assertEquals(-10.0, (float) $movement->quantity);
        $this->assertSame(InventoryMovementType::ADJUSTMENT, $movement->movement_type);
    }

    public function test_record_adjustment_without_decrease_type_defaults_to_positive(): void
    {
        $movement = Model::withoutEvents(fn () => $this->service()->recordAdjustment([
            'store_id' => 1,
            'product_id' => 1,
            'uom_id' => 1,
            'quantity' => 10.0,
        ]));

        $this->assertEquals(10.0, (float) $movement->quantity);
    }

    public function test_record_adjustment_with_uom_not_configured_for_product_throws_validation_exception(): void
    {
        DB::connection('tenant')->table('units_of_measure')->insert([
            'id' => 3,
            'code' => 'case',
            'name' => 'Case',
            'type' => 'count',
            'is_base_unit' => false,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            Model::withoutEvents(fn () => $this->service()->recordAdjustment([
                'store_id' => 1,
                'product_id' => 1,
                'uom_id' => 3,
                'quantity' => 10.0,
            ]));
            $this->fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('uom_id', $e->errors());
        }

        $this->assertSame(0, InventoryMovement::count());
    }

    public function test_adjustment_controller_maps_product_uom_validation_failure_to_422(): void
    {
        DB::connection('tenant')->table('units_of_measure')->insert([
            'id' => 3,
            'code' => 'case',
            'name' => 'Case',
            'type' => 'count',
            'is_base_unit' => false,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $controller = new InventoryMovementController($this->service(), new InventoryService);
        $request = CreateAdjustmentRequest::create('/', 'POST', [
            'store_id' => 1,
            'product_id' => 1,
            'adjustment_type' => 'increase',
            'quantity' => 10.0,
            'uom_id' => 3,
            'reason' => 'Opening stock',
        ]);
        $request->setContainer($this->app);
        $request->setUserResolver(fn () => new class
        {
            public function can($ability): bool
            {
                return true;
            }
        });
        $request->validateResolved();

        $response = $controller->createAdjustment($request);

        $this->assertSame(422, $response->getStatusCode());
    }

    public function test_record_damage_forces_negative_with_default_notes(): void
    {
        $this->seedInventory(onHand: 50, reserved: 0);

        $movement = Model::withoutEvents(fn () => $this->service()->recordDamage([
            'store_id' => 1,
            'product_id' => 1,
            'uom_id' => 1,
            'quantity' => 5.0,
        ]));

        $this->assertEquals(-5.0, (float) $movement->quantity);
        $this->assertSame('Damaged goods recorded', $movement->notes);
    }

    public function test_record_return_forces_positive_and_defaults_reference_type(): void
    {
        $movement = Model::withoutEvents(fn () => $this->service()->recordReturn([
            'store_id' => 1,
            'product_id' => 1,
            'uom_id' => 1,
            'quantity' => -5.0,
        ]));

        $this->assertEquals(5.0, (float) $movement->quantity);
        $this->assertSame('SaleRefund', $movement->reference_type);
    }

    // =========================================================================
    // recordTransferOut() / recordTransferIn()
    // =========================================================================

    public function test_record_transfer_out_and_in_use_correct_store_and_reference_type(): void
    {
        DB::connection('tenant')->table('stores')->insert([
            'id' => 2,
            'name' => 'Second Store',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->seedInventory(onHand: 50, reserved: 0, storeId: 1);

        $outMovements = Model::withoutEvents(fn () => $this->service()->recordTransferOut(7, [
            ['from_store_id' => 1, 'to_store_id' => 2, 'product_id' => 1, 'uom_id' => 1, 'quantity' => 20.0],
        ]));
        $inMovements = Model::withoutEvents(fn () => $this->service()->recordTransferIn(7, [
            ['from_store_id' => 1, 'to_store_id' => 2, 'product_id' => 1, 'uom_id' => 1, 'quantity' => 20.0],
        ]));

        $outMovement = $outMovements->first();
        $this->assertSame(1, $outMovement->store_id);
        $this->assertEquals(-20.0, (float) $outMovement->quantity);
        $this->assertSame(InventoryMovementType::TRANSFER_OUT, $outMovement->movement_type);
        $this->assertSame('StockTransfer', $outMovement->reference_type);
        $this->assertSame(7, $outMovement->reference_id);

        $inMovement = $inMovements->first();
        $this->assertSame(2, $inMovement->store_id);
        $this->assertEquals(20.0, (float) $inMovement->quantity);
        $this->assertSame(InventoryMovementType::TRANSFER_IN, $inMovement->movement_type);

        $sourceInventory = Inventory::where('store_id', 1)->where('product_id', 1)->first();
        $destInventory = Inventory::where('store_id', 2)->where('product_id', 1)->first();
        $this->assertEquals(30.0, (float) $sourceInventory->quantity_on_hand);
        $this->assertEquals(20.0, (float) $destInventory->quantity_on_hand);
    }

    // =========================================================================
    // Events
    // =========================================================================

    public function test_record_movement_dispatches_inventory_events(): void
    {
        Event::fake([InventoryMovementRecorded::class, InventoryBalanceUpdated::class]);

        Model::withoutEvents(fn () => $this->service()->recordMovement([
            'store_id' => 1,
            'product_id' => 1,
            'movement_type' => InventoryMovementType::PURCHASE,
            'uom_id' => 1,
            'quantity' => 10.0,
        ]));

        Event::assertDispatched(InventoryMovementRecorded::class, function ($event) {
            return $event->movement->product_id === 1;
        });
        Event::assertDispatched(InventoryBalanceUpdated::class, function ($event) {
            return $event->inventory->store_id === 1;
        });
    }

    // =========================================================================
    // Variant scoping
    // =========================================================================

    public function test_movement_for_a_variant_uses_a_separate_inventory_row_from_the_base_product(): void
    {
        DB::connection('tenant')->table('product_variants')->insert([
            'id' => 1,
            'product_id' => 1,
            'variant_name' => 'Large',
            'sku' => 'SKU-VARIANT-1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Model::withoutEvents(fn () => $this->service()->recordMovement([
            'store_id' => 1,
            'product_id' => 1,
            'movement_type' => InventoryMovementType::PURCHASE,
            'uom_id' => 1,
            'quantity' => 10.0,
        ]));

        Model::withoutEvents(fn () => $this->service()->recordMovement([
            'store_id' => 1,
            'product_id' => 1,
            'variant_id' => 1,
            'movement_type' => InventoryMovementType::PURCHASE,
            'uom_id' => 1,
            'quantity' => 30.0,
        ]));

        $this->assertSame(2, Inventory::where('store_id', 1)->where('product_id', 1)->count());

        $baseInventory = Inventory::where('store_id', 1)->where('product_id', 1)->whereNull('product_variant_id')->first();
        $variantInventory = Inventory::where('store_id', 1)->where('product_id', 1)->where('product_variant_id', 1)->first();

        $this->assertEquals(10.0, (float) $baseInventory->quantity_on_hand);
        $this->assertEquals(30.0, (float) $variantInventory->quantity_on_hand);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function service(): InventoryMovementService
    {
        return new InventoryMovementService(new InventoryService);
    }

    private function seedInventory(float $onHand, float $reserved, int $storeId = 1, int $productId = 1): void
    {
        DB::connection('tenant')->table('inventory')->insert([
            'store_id' => $storeId,
            'product_id' => $productId,
            'product_variant_id' => null,
            'quantity_on_hand' => $onHand,
            'quantity_reserved' => $reserved,
            'quantity_available' => max(0, $onHand - $reserved),
            'quantity_damaged' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function freshInventory(int $storeId = 1, int $productId = 1): Inventory
    {
        return Inventory::where('store_id', $storeId)->where('product_id', $productId)->whereNull('product_variant_id')->firstOrFail();
    }

    private function seedBaseData(): void
    {
        $conn = 'tenant';

        DB::connection($conn)->table('stores')->insert([
            'id' => 1,
            'name' => 'Main Store',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::connection($conn)->table('units_of_measure')->insert([
            [
                'id' => 1,
                'code' => 'pcs',
                'name' => 'Piece',
                'type' => 'count',
                'is_base_unit' => true,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'code' => 'box',
                'name' => 'Box',
                'type' => 'count',
                'is_base_unit' => false,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::connection($conn)->table('products')->insert([
            'id' => 1,
            'name' => 'Test Product',
            'slug' => 'test-product',
            'sku' => 'SKU-TEST-001',
            'base_uom_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::connection($conn)->table('product_uoms')->insert([
            'product_id' => 1,
            'uom_id' => 2,
            'is_base_uom' => false,
            'is_purchase_uom' => true,
            'is_sales_uom' => true,
            'is_inventory_uom' => true,
            'conversion_to_base' => 12.0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function dropTestTables(): void
    {
        foreach ([
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
            $table->string('type');
            $table->boolean('is_base_unit')->default(false);
            $table->boolean('is_active')->default(true);
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
            $table->unsignedBigInteger('product_id');
            $table->string('variant_name');
            $table->string('sku')->unique();
            $table->timestamps();
            $table->softDeletes();
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
    }
}
