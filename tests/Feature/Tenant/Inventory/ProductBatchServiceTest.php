<?php

namespace Tests\Feature\Tenant\Inventory;

use App\Models\Tenant\ProductBatch;
use App\Services\Tenant\Inventory\ProductBatchService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Stancl\Tenancy\Contracts\Tenant as TenantContract;
use Tests\TestCase;

class ProductBatchServiceTest extends TestCase
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
    // depleteBatchesFIFO()
    // =========================================================================

    public function test_single_batch_covers_requested_quantity(): void
    {
        $this->createBatch(['purchase_order_id' => 1, 'quantity_remaining_in_base_uom' => 50.0, 'cost_per_base_uom' => 10.0]);

        $result = $this->service()->depleteBatchesFIFO(storeId: 1, productId: 1, variantId: null, quantityInBaseUom: 20.0);

        $this->assertEquals(200.0, $result['total_cost']);
        $this->assertEquals(10.0, $result['average_cost']);
        $this->assertCount(1, $result['depletions']);

        $batch = ProductBatch::first();
        $this->assertEquals(30.0, (float) $batch->quantity_remaining_in_base_uom);
    }

    public function test_depletes_across_multiple_batches_in_fifo_order(): void
    {
        $first = $this->createBatch(['purchase_order_id' => 1, 'quantity_remaining_in_base_uom' => 10.0, 'cost_per_base_uom' => 5.0]);
        $second = $this->createBatch(['purchase_order_id' => 2, 'quantity_remaining_in_base_uom' => 20.0, 'cost_per_base_uom' => 8.0]);

        $result = $this->service()->depleteBatchesFIFO(storeId: 1, productId: 1, variantId: null, quantityInBaseUom: 15.0);

        // First batch fully depleted (10), remaining 5 taken from second.
        $this->assertEquals(10.0, $result['depletions'][$first->id]);
        $this->assertEquals(5.0, $result['depletions'][$second->id]);
        $this->assertEquals(10 * 5.0 + 5 * 8.0, $result['total_cost']);

        $this->assertEquals(0.0, (float) $first->fresh()->quantity_remaining_in_base_uom);
        $this->assertEquals(15.0, (float) $second->fresh()->quantity_remaining_in_base_uom);
    }

    public function test_fifo_ordering_is_by_purchase_order_id_not_created_at(): void
    {
        // Batch with the HIGHER purchase_order_id is created first (earlier created_at)...
        $newerPo = $this->createBatch([
            'purchase_order_id' => 2,
            'quantity_remaining_in_base_uom' => 10.0,
            'created_at' => now()->subMinute(),
        ]);
        // ...while the batch with the LOWER purchase_order_id is created second (later created_at).
        $olderPo = $this->createBatch([
            'purchase_order_id' => 1,
            'quantity_remaining_in_base_uom' => 10.0,
            'created_at' => now(),
        ]);

        $result = $this->service()->depleteBatchesFIFO(storeId: 1, productId: 1, variantId: null, quantityInBaseUom: 5.0);

        // Depletion should hit the lower purchase_order_id batch first, regardless of created_at.
        $this->assertArrayHasKey($olderPo->id, $result['depletions']);
        $this->assertArrayNotHasKey($newerPo->id, $result['depletions']);
    }

    public function test_expired_and_depleted_batches_are_excluded(): void
    {
        $this->createBatch(['purchase_order_id' => 1, 'quantity_remaining_in_base_uom' => 0.0]);
        $this->createBatch(['purchase_order_id' => 2, 'is_expired' => true, 'quantity_remaining_in_base_uom' => 50.0]);
        $available = $this->createBatch(['purchase_order_id' => 3, 'quantity_remaining_in_base_uom' => 30.0]);

        $result = $this->service()->depleteBatchesFIFO(storeId: 1, productId: 1, variantId: null, quantityInBaseUom: 10.0);

        $this->assertSame([$available->id => 10.0], $result['depletions']);
    }

    public function test_insufficient_total_quantity_throws_and_rolls_back_partial_depletion(): void
    {
        $batch = $this->createBatch(['purchase_order_id' => 1, 'quantity_remaining_in_base_uom' => 10.0]);

        try {
            $this->service()->depleteBatchesFIFO(storeId: 1, productId: 1, variantId: null, quantityInBaseUom: 100.0);
            $this->fail('Expected RuntimeException was not thrown.');
        } catch (\RuntimeException) {
            // expected
        }

        $this->assertEquals(10.0, (float) $batch->fresh()->quantity_remaining_in_base_uom);
    }

    public function test_depletion_is_scoped_to_store_product_and_variant(): void
    {
        $this->createBatch(['store_id' => 2, 'purchase_order_id' => 1, 'quantity_remaining_in_base_uom' => 50.0]);
        $this->createBatch(['product_id' => 2, 'purchase_order_id' => 1, 'quantity_remaining_in_base_uom' => 50.0]);
        $this->createBatch(['product_variant_id' => 5, 'purchase_order_id' => 1, 'quantity_remaining_in_base_uom' => 50.0]);

        // None of the seeded batches match (store_id=1, product_id=1, variant_id=null),
        // so the service should find zero eligible batches and throw.
        $this->expectException(\RuntimeException::class);

        $this->service()->depleteBatchesFIFO(storeId: 1, productId: 1, variantId: null, quantityInBaseUom: 5.0);
    }

    // =========================================================================
    // restoreBatchQuantity()
    // =========================================================================

    public function test_restores_quantity_within_received_limit(): void
    {
        $batch = $this->createBatch([
            'quantity_received_in_base_uom' => 100.0,
            'quantity_remaining_in_base_uom' => 40.0,
        ]);

        $restored = $this->service()->restoreBatchQuantity($batch->id, 20.0);

        $this->assertEquals(60.0, (float) $restored->quantity_remaining_in_base_uom);
    }

    public function test_restoring_more_than_received_throws_and_leaves_batch_unchanged(): void
    {
        $batch = $this->createBatch([
            'quantity_received_in_base_uom' => 100.0,
            'quantity_remaining_in_base_uom' => 90.0,
        ]);

        try {
            $this->service()->restoreBatchQuantity($batch->id, 20.0);
            $this->fail('Expected RuntimeException was not thrown.');
        } catch (\RuntimeException) {
            // expected
        }

        $this->assertEquals(90.0, (float) $batch->fresh()->quantity_remaining_in_base_uom);
    }

    // =========================================================================
    // getBatchesForProduct()
    // =========================================================================

    public function test_get_batches_for_product_only_available_filters_depleted_and_expired(): void
    {
        $available = $this->createBatch(['purchase_order_id' => 1, 'quantity_remaining_in_base_uom' => 10.0]);
        $this->createBatch(['purchase_order_id' => 2, 'quantity_remaining_in_base_uom' => 0.0]);
        $this->createBatch(['purchase_order_id' => 3, 'is_expired' => true, 'quantity_remaining_in_base_uom' => 10.0]);

        $all = $this->service()->getBatchesForProduct(storeId: 1, productId: 1, variantId: null, onlyAvailable: false);
        $onlyAvailable = $this->service()->getBatchesForProduct(storeId: 1, productId: 1, variantId: null, onlyAvailable: true);

        $this->assertCount(3, $all);
        $this->assertCount(1, $onlyAvailable);
        $this->assertSame($available->id, $onlyAvailable->first()->id);
    }

    // =========================================================================
    // markExpiredBatches()
    // =========================================================================

    public function test_marks_past_expiry_batches_as_expired(): void
    {
        $expired = $this->createBatch(['expiry_date' => now()->subDay()->toDateString(), 'is_expired' => false]);
        $notYetExpired = $this->createBatch(['expiry_date' => now()->addDay()->toDateString(), 'is_expired' => false]);
        $noExpiry = $this->createBatch(['expiry_date' => null, 'is_expired' => false]);

        $count = Model::withoutEvents(fn () => $this->service()->markExpiredBatches());

        $this->assertSame(1, $count);
        $this->assertTrue($expired->fresh()->is_expired);
        $this->assertFalse($notYetExpired->fresh()->is_expired);
        $this->assertFalse($noExpiry->fresh()->is_expired);
    }

    public function test_mark_expired_batches_scoped_to_store(): void
    {
        $storeOne = $this->createBatch(['store_id' => 1, 'expiry_date' => now()->subDay()->toDateString(), 'is_expired' => false]);
        $storeTwo = $this->createBatch(['store_id' => 2, 'expiry_date' => now()->subDay()->toDateString(), 'is_expired' => false]);

        $count = Model::withoutEvents(fn () => $this->service()->markExpiredBatches(storeId: 1));

        $this->assertSame(1, $count);
        $this->assertTrue($storeOne->fresh()->is_expired);
        $this->assertFalse($storeTwo->fresh()->is_expired);
    }

    // =========================================================================
    // getExpiringSoonBatches()
    // =========================================================================

    public function test_get_expiring_soon_batches_filters_correctly(): void
    {
        $withinWindow = $this->createBatch(['expiry_date' => now()->addDays(10)->toDateString(), 'quantity_remaining_in_base_uom' => 5.0]);
        $this->createBatch(['expiry_date' => now()->addDays(60)->toDateString(), 'quantity_remaining_in_base_uom' => 5.0]);
        $this->createBatch(['expiry_date' => now()->addDays(10)->toDateString(), 'is_expired' => true, 'quantity_remaining_in_base_uom' => 5.0]);
        $this->createBatch(['expiry_date' => now()->addDays(10)->toDateString(), 'quantity_remaining_in_base_uom' => 0.0]);
        $this->createBatch(['expiry_date' => null, 'quantity_remaining_in_base_uom' => 5.0]);

        $results = $this->service()->getExpiringSoonBatches(storeId: 1, daysThreshold: 30);

        $this->assertCount(1, $results);
        $this->assertSame($withinWindow->id, $results->first()->id);
    }

    // =========================================================================
    // calculateCOGS()
    // =========================================================================

    public function test_calculate_cogs_computes_cost_without_mutating_batches(): void
    {
        $first = $this->createBatch(['purchase_order_id' => 1, 'quantity_remaining_in_base_uom' => 10.0, 'cost_per_base_uom' => 5.0]);
        $second = $this->createBatch(['purchase_order_id' => 2, 'quantity_remaining_in_base_uom' => 20.0, 'cost_per_base_uom' => 8.0]);

        $cogs = $this->service()->calculateCOGS(storeId: 1, productId: 1, variantId: null, quantityInBaseUom: 15.0);

        $this->assertEquals(10 * 5.0 + 5 * 8.0, $cogs);
        $this->assertEquals(10.0, (float) $first->fresh()->quantity_remaining_in_base_uom);
        $this->assertEquals(20.0, (float) $second->fresh()->quantity_remaining_in_base_uom);
    }

    public function test_calculate_cogs_throws_when_insufficient_quantity(): void
    {
        $this->createBatch(['purchase_order_id' => 1, 'quantity_remaining_in_base_uom' => 5.0]);

        $this->expectException(\RuntimeException::class);

        $this->service()->calculateCOGS(storeId: 1, productId: 1, variantId: null, quantityInBaseUom: 50.0);
    }

    // =========================================================================
    // getInventoryValuation()
    // =========================================================================

    public function test_get_inventory_valuation_aggregates_correctly(): void
    {
        $this->createBatch(['purchase_order_id' => 1, 'quantity_remaining_in_base_uom' => 10.0, 'cost_per_base_uom' => 5.0]);
        $this->createBatch(['purchase_order_id' => 2, 'quantity_remaining_in_base_uom' => 20.0, 'cost_per_base_uom' => 8.0]);
        $this->createBatch(['purchase_order_id' => 3, 'is_expired' => true, 'quantity_remaining_in_base_uom' => 100.0, 'cost_per_base_uom' => 1.0]);
        $this->createBatch(['purchase_order_id' => 4, 'quantity_remaining_in_base_uom' => 0.0, 'cost_per_base_uom' => 1.0]);

        $valuation = $this->service()->getInventoryValuation(storeId: 1);

        $this->assertEquals(30.0, $valuation['total_quantity']);
        $this->assertEquals(10 * 5.0 + 20 * 8.0, $valuation['total_value']);
        $this->assertEquals((10 * 5.0 + 20 * 8.0) / 30.0, $valuation['average_cost']);
        $this->assertSame(2, $valuation['batch_count']);
    }

    public function test_get_inventory_valuation_filters_by_product(): void
    {
        $this->createBatch(['product_id' => 1, 'purchase_order_id' => 1, 'quantity_remaining_in_base_uom' => 10.0, 'cost_per_base_uom' => 5.0]);
        $this->createBatch(['product_id' => 2, 'purchase_order_id' => 2, 'quantity_remaining_in_base_uom' => 20.0, 'cost_per_base_uom' => 8.0]);

        $valuation = $this->service()->getInventoryValuation(storeId: 1, productId: 1);

        $this->assertEquals(10.0, $valuation['total_quantity']);
        $this->assertSame(1, $valuation['batch_count']);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function service(): ProductBatchService
    {
        return new ProductBatchService;
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
            'quantity_received_in_base_uom' => 100.0,
            'quantity_remaining_in_base_uom' => 100.0,
            'cost_per_purchase_uom' => 100.0,
            'cost_per_base_uom' => 10.0,
            'total_cost' => 1000.0,
            'manufacture_date' => null,
            'expiry_date' => null,
            'is_expired' => false,
            'supplier_id' => null,
        ], $overrides)));
    }

    private function seedBaseData(): void
    {
        $conn = 'tenant';

        DB::connection($conn)->table('stores')->insert([
            ['id' => 1, 'name' => 'Main Store', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'Second Store', 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::connection($conn)->table('products')->insert([
            ['id' => 1, 'name' => 'Product One', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'Product Two', 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::connection($conn)->table('purchase_orders')->insert([
            ['id' => 1, 'po_number' => 'PO-1', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'po_number' => 'PO-2', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'po_number' => 'PO-3', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 4, 'po_number' => 'PO-4', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    private function dropTestTables(): void
    {
        foreach ([
            'product_batches',
            'purchase_orders',
            'suppliers',
            'product_variants',
            'products',
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

        Schema::connection($conn)->create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name')->default('Test Product');
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

        Schema::connection($conn)->create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->default('Test Supplier');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::connection($conn)->create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('po_number')->unique();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::connection($conn)->create('product_batches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('product_variant_id')->nullable();
            $table->unsignedBigInteger('purchase_order_id');
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
    }
}
