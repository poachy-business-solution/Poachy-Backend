<?php

namespace Tests\Feature\Tenant\Inventory;

use App\Enums\Tenant\InventoryMovementType;
use App\Events\Tenant\WasteApprovalRequested;
use App\Events\Tenant\WasteApproved;
use App\Events\Tenant\WasteRejected;
use App\Models\Tenant\ExpiryAlert;
use App\Models\Tenant\Inventory;
use App\Models\Tenant\InventoryMovement;
use App\Models\Tenant\InventoryWaste;
use App\Models\Tenant\ProductBatch;
use App\Services\Tenant\Inventory\ExpiryAlertService;
use App\Services\Tenant\Inventory\InventoryMovementService;
use App\Services\Tenant\Inventory\InventoryService;
use App\Services\Tenant\Inventory\InventoryWasteService;
use App\Services\Tenant\Inventory\ProductBatchService;
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

class InventoryWasteServiceTest extends TestCase
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
    // recordWaste()
    // =========================================================================

    public function test_record_waste_with_explicit_batch_uses_its_cost(): void
    {
        $batch = $this->createBatch(['cost_per_base_uom' => 5.0, 'quantity_remaining_in_base_uom' => 20.0]);

        $waste = Model::withoutEvents(fn () => $this->service()->recordWaste([
            'store_id' => 1,
            'product_id' => 1,
            'batch_id' => $batch->id,
            'waste_type' => 'damaged',
            'quantity_wasted' => 4.0,
            'reason' => 'dropped',
        ]));

        $this->assertEquals(5.0, (float) $waste->cost_per_base_uom);
        $this->assertEquals(20.0, (float) $waste->total_loss);
        $this->assertSame('pending', $waste->approval_status->value);
        $this->assertEquals(20.0, (float) $batch->fresh()->quantity_remaining_in_base_uom);
    }

    public function test_record_waste_without_batch_falls_back_to_latest_batch_cost(): void
    {
        $this->createBatch(['cost_per_base_uom' => 5.0, 'quantity_remaining_in_base_uom' => 10.0, 'created_at' => now()->subDay()]);
        $this->createBatch(['cost_per_base_uom' => 8.0, 'quantity_remaining_in_base_uom' => 10.0, 'created_at' => now()]);

        $waste = Model::withoutEvents(fn () => $this->service()->recordWaste([
            'store_id' => 1,
            'product_id' => 1,
            'waste_type' => 'lost',
            'quantity_wasted' => 2.0,
        ]));

        $this->assertEquals(8.0, (float) $waste->cost_per_base_uom);
    }

    public function test_record_waste_falls_back_to_selling_price_estimate_when_no_batches_exist(): void
    {
        $waste = Model::withoutEvents(fn () => $this->service()->recordWaste([
            'store_id' => 1,
            'product_id' => 1,
            'waste_type' => 'lost',
            'quantity_wasted' => 1.0,
        ]));

        $this->assertEquals(70.0, (float) $waste->cost_per_base_uom);
    }

    public function test_record_waste_dispatches_waste_approval_requested(): void
    {
        Event::fake([WasteApprovalRequested::class]);

        $this->service()->recordWaste([
            'store_id' => 1,
            'product_id' => 1,
            'waste_type' => 'lost',
            'quantity_wasted' => 1.0,
        ]);

        Event::assertDispatched(WasteApprovalRequested::class);
    }

    // =========================================================================
    // approveWaste()
    // =========================================================================

    public function test_approve_waste_with_sufficient_batch_quantity_deducts_batch_and_inventory(): void
    {
        $this->seedInventory(qtyOnHand: 20.0);
        $batch = $this->createBatch(['cost_per_base_uom' => 5.0, 'quantity_remaining_in_base_uom' => 20.0]);
        $waste = $this->recordWaste(batchId: $batch->id, quantity: 4.0);

        $approved = Model::withoutEvents(fn () => $this->service()->approveWaste($waste->id, 1));

        $this->assertSame('approved', $approved->approval_status->value);
        $this->assertSame(1, $approved->approved_by);
        $this->assertEquals(16.0, (float) $batch->fresh()->quantity_remaining_in_base_uom);
        $this->assertEquals(16.0, (float) $this->freshInventory()->quantity_on_hand);
    }

    public function test_approve_waste_throws_when_batch_has_insufficient_quantity(): void
    {
        $this->seedInventory(qtyOnHand: 20.0);
        $batch = $this->createBatch(['quantity_remaining_in_base_uom' => 2.0]);
        $waste = $this->recordWaste(batchId: $batch->id, quantity: 5.0);

        try {
            Model::withoutEvents(fn () => $this->service()->approveWaste($waste->id, 1));
            $this->fail('Expected RuntimeException was not thrown.');
        } catch (\RuntimeException) {
            // expected
        }

        $this->assertSame('pending', $waste->fresh()->approval_status->value);
        $this->assertEquals(2.0, (float) $batch->fresh()->quantity_remaining_in_base_uom);
        $this->assertEquals(20.0, (float) $this->freshInventory()->quantity_on_hand);
    }

    public function test_approve_waste_without_batch_only_deducts_store_inventory(): void
    {
        $this->seedInventory(qtyOnHand: 20.0);
        $waste = $this->recordWaste(quantity: 3.0, wasteType: 'lost');

        Model::withoutEvents(fn () => $this->service()->approveWaste($waste->id, 1));

        $this->assertEquals(17.0, (float) $this->freshInventory()->quantity_on_hand);
        $this->assertSame(0, ProductBatch::count());
    }

    public function test_approve_waste_throws_if_not_pending(): void
    {
        $this->seedInventory(qtyOnHand: 20.0);
        $waste = $this->recordWaste(quantity: 2.0, wasteType: 'lost');
        Model::withoutEvents(fn () => $this->service()->approveWaste($waste->id, 1));

        $this->expectException(\RuntimeException::class);

        Model::withoutEvents(fn () => $this->service()->approveWaste($waste->id, 1));
    }

    public function test_movement_type_follows_waste_type_mapping(): void
    {
        $this->seedInventory(qtyOnHand: 100.0);

        $cases = [
            'expired' => InventoryMovementType::EXPIRY,
            'damaged' => InventoryMovementType::DAMAGE,
            'stolen' => InventoryMovementType::THEFT,
            'lost' => InventoryMovementType::ADJUSTMENT,
        ];

        foreach ($cases as $wasteType => $expectedMovementType) {
            $waste = $this->recordWaste(quantity: 1.0, wasteType: $wasteType);
            Model::withoutEvents(fn () => $this->service()->approveWaste($waste->id, 1));

            $movement = InventoryMovement::where('reference_type', InventoryWaste::class)
                ->where('reference_id', $waste->id)
                ->first();

            $this->assertSame($expectedMovementType, $movement->movement_type, "Failed for waste_type={$wasteType}");
        }
    }

    public function test_fully_depleting_a_batch_auto_resolves_its_expiry_alerts(): void
    {
        $this->seedInventory(qtyOnHand: 10.0);
        $batch = $this->createBatch(['quantity_remaining_in_base_uom' => 4.0]);
        $alert = $this->createExpiryAlert($batch->id);
        $waste = $this->recordWaste(batchId: $batch->id, quantity: 4.0);

        Model::withoutEvents(fn () => $this->service()->approveWaste($waste->id, 1));

        $this->assertEquals(0.0, (float) $batch->fresh()->quantity_remaining_in_base_uom);
        $this->assertTrue($alert->fresh()->is_resolved);
    }

    public function test_approve_waste_dispatches_waste_approved(): void
    {
        Event::fake([WasteApprovalRequested::class, WasteApproved::class]);
        $this->seedInventory(qtyOnHand: 10.0);
        $waste = $this->service()->recordWaste([
            'store_id' => 1, 'product_id' => 1, 'waste_type' => 'lost', 'quantity_wasted' => 1.0,
        ]);

        $this->service()->approveWaste($waste->id, 1);

        Event::assertDispatched(WasteApproved::class, fn ($e) => $e->waste->id === $waste->id);
    }

    // =========================================================================
    // rejectWaste()
    // =========================================================================

    public function test_reject_waste_does_not_touch_inventory_or_batch(): void
    {
        $this->seedInventory(qtyOnHand: 20.0);
        $batch = $this->createBatch(['quantity_remaining_in_base_uom' => 10.0]);
        $waste = $this->recordWaste(batchId: $batch->id, quantity: 4.0);

        $rejected = Model::withoutEvents(fn () => $this->service()->rejectWaste($waste->id, 1, 'not enough evidence'));

        $this->assertSame('rejected', $rejected->approval_status->value);
        $this->assertSame(1, $rejected->approved_by);
        $this->assertSame('not enough evidence', $rejected->reason);
        $this->assertEquals(10.0, (float) $batch->fresh()->quantity_remaining_in_base_uom);
        $this->assertEquals(20.0, (float) $this->freshInventory()->quantity_on_hand);
        $this->assertSame(0, InventoryMovement::count());
    }

    public function test_reject_waste_throws_if_not_pending(): void
    {
        $waste = $this->recordWaste(quantity: 2.0);
        Model::withoutEvents(fn () => $this->service()->rejectWaste($waste->id, 1));

        $this->expectException(\RuntimeException::class);

        Model::withoutEvents(fn () => $this->service()->rejectWaste($waste->id, 1));
    }

    public function test_reject_waste_dispatches_waste_rejected(): void
    {
        Event::fake([WasteApprovalRequested::class, WasteRejected::class]);
        $waste = $this->service()->recordWaste([
            'store_id' => 1, 'product_id' => 1, 'waste_type' => 'lost', 'quantity_wasted' => 1.0,
        ]);

        $this->service()->rejectWaste($waste->id, 1, 'declined');

        Event::assertDispatched(WasteRejected::class, fn ($e) => $e->waste->id === $waste->id);
    }

    // =========================================================================
    // updateWaste()
    // =========================================================================

    public function test_update_waste_recalculates_total_loss_using_original_cost(): void
    {
        $batch = $this->createBatch(['cost_per_base_uom' => 5.0, 'quantity_remaining_in_base_uom' => 20.0]);
        $waste = $this->recordWaste(batchId: $batch->id, quantity: 4.0);
        $this->assertEquals(20.0, (float) $waste->total_loss);

        $updated = Model::withoutEvents(fn () => $this->service()->updateWaste($waste->id, ['quantity_wasted' => 6.0]));

        $this->assertEquals(5.0, (float) $updated->cost_per_base_uom);
        $this->assertEquals(30.0, (float) $updated->total_loss);
    }

    public function test_update_waste_throws_if_not_pending(): void
    {
        $waste = $this->recordWaste(quantity: 2.0);
        Model::withoutEvents(fn () => $this->service()->rejectWaste($waste->id, 1));

        $this->expectException(\RuntimeException::class);

        Model::withoutEvents(fn () => $this->service()->updateWaste($waste->id, ['quantity_wasted' => 3.0]));
    }

    // =========================================================================
    // getStoreSummary()
    // =========================================================================

    public function test_get_store_summary_only_sums_approved_records(): void
    {
        $this->seedInventory(qtyOnHand: 100.0);

        $approvedWaste = $this->recordWaste(quantity: 4.0, wasteType: 'lost'); // cost 70/unit -> loss 280
        Model::withoutEvents(fn () => $this->service()->approveWaste($approvedWaste->id, 1));

        $pendingWaste = $this->recordWaste(quantity: 3.0, wasteType: 'lost');

        $rejectedWaste = $this->recordWaste(quantity: 2.0, wasteType: 'lost');
        Model::withoutEvents(fn () => $this->service()->rejectWaste($rejectedWaste->id, 1));

        $summary = $this->service()->getStoreSummary(storeId: 1);

        $this->assertSame(3, $summary['total_waste_records']);
        $this->assertSame(1, $summary['pending_approvals']);
        $this->assertSame(1, $summary['approved_count']);
        $this->assertSame(1, $summary['rejected_count']);
        $this->assertEquals((float) $approvedWaste->fresh()->total_loss, (float) $summary['total_financial_loss']);
        $this->assertEquals(4.0, (float) $summary['total_quantity_wasted']);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function service(): InventoryWasteService
    {
        return new InventoryWasteService(
            new InventoryMovementService(new InventoryService),
            new ProductBatchService,
            new ExpiryAlertService
        );
    }

    private function recordWaste(?int $batchId = null, float $quantity = 1.0, string $wasteType = 'damaged'): InventoryWaste
    {
        return Model::withoutEvents(fn () => $this->service()->recordWaste([
            'store_id' => 1,
            'product_id' => 1,
            'batch_id' => $batchId,
            'waste_type' => $wasteType,
            'quantity_wasted' => $quantity,
            'reason' => 'test',
        ]));
    }

    private function createBatch(array $overrides = []): ProductBatch
    {
        // created_at/updated_at aren't mass-assignable on ProductBatch — pull them out
        // and apply via a raw update afterwards so tests can control insertion ordering.
        $timestampOverrides = array_intersect_key($overrides, array_flip(['created_at', 'updated_at']));
        $overrides = array_diff_key($overrides, $timestampOverrides);

        $batch = Model::withoutEvents(fn () => ProductBatch::create(array_merge([
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

        if ($timestampOverrides) {
            DB::connection('tenant')->table('product_batches')->where('id', $batch->id)->update($timestampOverrides);
            $batch->refresh();
        }

        return $batch;
    }

    private function createExpiryAlert(int $batchId): ExpiryAlert
    {
        return Model::withoutEvents(fn () => ExpiryAlert::create([
            'batch_id' => $batchId,
            'alert_level' => 'warning',
            'alert_date' => now()->toDateString(),
            'days_until_expiry' => 5,
            'is_resolved' => false,
        ]));
    }

    private function seedInventory(float $qtyOnHand): void
    {
        DB::connection('tenant')->table('inventory')->insert([
            'store_id' => 1,
            'product_id' => 1,
            'product_variant_id' => null,
            'quantity_on_hand' => $qtyOnHand,
            'quantity_reserved' => 0,
            'quantity_available' => $qtyOnHand,
            'quantity_damaged' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function freshInventory(): Inventory
    {
        return Inventory::where('store_id', 1)->where('product_id', 1)->firstOrFail();
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
            'base_uom_id' => 1,
            'base_selling_price' => 100.0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function dropTestTables(): void
    {
        foreach ([
            'expiry_alerts',
            'inventory_waste',
            'inventory_movements',
            'inventory',
            'product_batches',
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
            $table->decimal('base_selling_price', 10, 2)->default(0);
            $table->timestamps();
            $table->softDeletes();
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

        Schema::connection($conn)->create('inventory_waste', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('batch_id')->nullable();
            $table->string('waste_type');
            $table->decimal('quantity_wasted', 15, 4);
            $table->decimal('cost_per_base_uom', 15, 2);
            $table->decimal('total_loss', 15, 2);
            $table->date('waste_date');
            $table->text('reason')->nullable();
            $table->string('approval_status')->default('pending');
            $table->unsignedBigInteger('reported_by')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        Schema::connection($conn)->create('expiry_alerts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('batch_id');
            $table->string('alert_level');
            $table->date('alert_date');
            $table->integer('days_until_expiry')->nullable();
            $table->boolean('is_resolved')->default(false);
            $table->string('resolution_action')->nullable();
            $table->unsignedBigInteger('resolved_by')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }
}
