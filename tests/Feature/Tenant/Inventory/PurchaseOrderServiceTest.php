<?php

namespace Tests\Feature\Tenant\Inventory;

use App\Enums\Tenant\PurchaseOrderStatus;
use App\Jobs\Tenant\SendNotificationJob;
use App\Models\Tenant\PurchaseOrder;
use App\Models\Tenant\PurchaseOrderItem;
use App\Models\Tenant\Supplier;
use App\Services\Tenant\Inventory\PurchaseOrderService;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Stancl\Tenancy\Contracts\Tenant as TenantContract;
use Tests\TestCase;

class PurchaseOrderServiceTest extends TestCase
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
    // createPurchaseOrder()
    // =========================================================================

    public function test_create_purchase_order_happy_path_with_tax_rate_id(): void
    {
        $po = Model::withoutEvents(fn () => $this->service()->createPurchaseOrder([
            'supplier_id' => 1,
            'store_id' => 1,
            'items' => [
                ['product_id' => 1, 'quantity_ordered' => 10, 'uom_id' => 1, 'unit_cost' => 100.0, 'tax_rate_id' => 1],
            ],
        ]));

        $this->assertSame(PurchaseOrderStatus::DRAFT, $po->status);
        $this->assertEquals(1000.0, (float) $po->subtotal);
        $this->assertEquals(160.0, (float) $po->tax_amount);
        $this->assertEquals(1160.0, (float) $po->total_amount);
        $this->assertEquals(0.0, (float) $po->amount_paid);

        $item = PurchaseOrderItem::first();
        $this->assertSame(1, $item->tax_rate_id);
        $this->assertEquals(160.0, (float) $item->tax_amount);
        $this->assertEquals(1000.0, (float) $item->subtotal);
        $this->assertEquals(0.0, (float) $item->quantity_received);

        $supplier = Supplier::find(1);
        $this->assertEquals(1160.0, (float) $supplier->outstanding_balance);
    }

    public function test_create_purchase_order_computes_tax_from_raw_percentage(): void
    {
        $po = Model::withoutEvents(fn () => $this->service()->createPurchaseOrder([
            'supplier_id' => 1,
            'store_id' => 1,
            'items' => [
                ['product_id' => 1, 'quantity_ordered' => 5, 'uom_id' => 1, 'unit_cost' => 50.0, 'tax_rate' => 10],
            ],
        ]));

        $item = PurchaseOrderItem::first();
        $this->assertNull($item->tax_rate_id);
        $this->assertEquals(25.0, (float) $item->tax_amount);
        $this->assertEquals(275.0, (float) $po->total_amount);
    }

    public function test_create_purchase_order_with_no_tax_specified_defaults_to_zero_tax(): void
    {
        // Regression: previously crashed with a NOT NULL constraint violation on
        // tax_rate_id before the migration made the column nullable.
        $po = Model::withoutEvents(fn () => $this->service()->createPurchaseOrder([
            'supplier_id' => 1,
            'store_id' => 1,
            'items' => [
                ['product_id' => 1, 'quantity_ordered' => 2, 'uom_id' => 1, 'unit_cost' => 200.0],
            ],
        ]));

        $item = PurchaseOrderItem::first();
        $this->assertNull($item->tax_rate_id);
        $this->assertEquals(0.0, (float) $item->tax_amount);
        $this->assertEquals(400.0, (float) $po->total_amount);
    }

    public function test_create_purchase_order_throws_when_product_not_allocated_to_store(): void
    {
        $this->expectException(\RuntimeException::class);

        Model::withoutEvents(fn () => $this->service()->createPurchaseOrder([
            'supplier_id' => 1,
            'store_id' => 1,
            'items' => [
                ['product_id' => 2, 'quantity_ordered' => 1, 'uom_id' => 1, 'unit_cost' => 10.0],
            ],
        ]));
    }

    public function test_purchase_order_numbers_are_sequential(): void
    {
        $data = [
            'supplier_id' => 1,
            'store_id' => 1,
            'items' => [
                ['product_id' => 1, 'quantity_ordered' => 1, 'uom_id' => 1, 'unit_cost' => 10.0],
            ],
        ];

        $first = Model::withoutEvents(fn () => $this->service()->createPurchaseOrder($data));
        $second = Model::withoutEvents(fn () => $this->service()->createPurchaseOrder($data));

        $firstSeq = (int) substr($first->po_number, -4);
        $secondSeq = (int) substr($second->po_number, -4);

        $this->assertSame($firstSeq + 1, $secondSeq);
    }

    // =========================================================================
    // updatePurchaseOrder()
    // =========================================================================

    public function test_update_purchase_order_only_allowed_while_draft(): void
    {
        $po = $this->createDraftPo(quantity: 10, unitCost: 100.0);
        Model::withoutEvents(fn () => $this->service()->sendPurchaseOrder($po->id));

        $this->expectException(\RuntimeException::class);

        Model::withoutEvents(fn () => $this->service()->updatePurchaseOrder($po->id, ['notes' => 'changed']));
    }

    public function test_update_purchase_order_replacing_items_adjusts_supplier_balance_by_delta(): void
    {
        $po = $this->createDraftPo(quantity: 10, unitCost: 100.0); // total 1000
        $this->assertEquals(1000.0, (float) Supplier::find(1)->outstanding_balance);

        // Increase: new total 2000, delta +1000
        Model::withoutEvents(fn () => $this->service()->updatePurchaseOrder($po->id, [
            'items' => [
                ['product_id' => 1, 'quantity_ordered' => 20, 'uom_id' => 1, 'unit_cost' => 100.0],
            ],
        ]));

        $po->refresh();
        $this->assertEquals(2000.0, (float) $po->total_amount);
        $this->assertEquals(2000.0, (float) Supplier::find(1)->outstanding_balance);

        // Decrease: new total 500, delta -1500
        Model::withoutEvents(fn () => $this->service()->updatePurchaseOrder($po->id, [
            'items' => [
                ['product_id' => 1, 'quantity_ordered' => 5, 'uom_id' => 1, 'unit_cost' => 100.0],
            ],
        ]));

        $po->refresh();
        $this->assertEquals(500.0, (float) $po->total_amount);
        $this->assertEquals(500.0, (float) Supplier::find(1)->outstanding_balance);
    }

    public function test_update_purchase_order_header_only_leaves_items_and_totals_untouched(): void
    {
        $po = $this->createDraftPo(quantity: 10, unitCost: 100.0);

        Model::withoutEvents(fn () => $this->service()->updatePurchaseOrder($po->id, ['notes' => 'header only update']));

        $po->refresh();
        $this->assertSame('header only update', $po->notes);
        $this->assertEquals(1000.0, (float) $po->total_amount);
        $this->assertSame(1, PurchaseOrderItem::where('purchase_order_id', $po->id)->count());
    }

    // =========================================================================
    // sendPurchaseOrder()
    // =========================================================================

    public function test_send_purchase_order_moves_draft_to_sent(): void
    {
        $po = $this->createDraftPo(quantity: 1, unitCost: 10.0);

        $sent = Model::withoutEvents(fn () => $this->service()->sendPurchaseOrder($po->id));

        $this->assertSame(PurchaseOrderStatus::SENT, $sent->status);
    }

    public function test_send_purchase_order_emails_supplier_when_email_is_present(): void
    {
        Queue::fake();
        DB::connection('tenant')->table('suppliers')->where('id', 1)->update([
            'email' => 'supplier@example.com',
        ]);

        $po = $this->createDraftPo(quantity: 2, unitCost: 25.0);

        $sent = Model::withoutEvents(fn () => $this->service()->sendPurchaseOrder($po->id));

        Queue::assertPushed(SendNotificationJob::class, function (SendNotificationJob $job) use ($sent) {
            return $job->channel === 'email'
                && $job->recipient === 'supplier@example.com'
                && $job->metadata['notification_type'] === 'purchase_order_sent'
                && $job->metadata['purchase_order_id'] === $sent->id
                && str_contains($job->message['subject'], $sent->po_number)
                && str_contains($job->message['body'], 'Allocated Product x 2 pcs');
        });
    }

    public function test_send_purchase_order_throws_if_not_draft(): void
    {
        $po = $this->createDraftPo(quantity: 1, unitCost: 10.0);
        Model::withoutEvents(fn () => $this->service()->sendPurchaseOrder($po->id));

        $this->expectException(\RuntimeException::class);

        Model::withoutEvents(fn () => $this->service()->sendPurchaseOrder($po->id));
    }

    // =========================================================================
    // cancelPurchaseOrder()
    // =========================================================================

    public function test_cancel_from_draft_reverses_supplier_balance(): void
    {
        $po = $this->createDraftPo(quantity: 10, unitCost: 100.0); // total 1000
        $this->assertEquals(1000.0, (float) Supplier::find(1)->outstanding_balance);

        $cancelled = Model::withoutEvents(fn () => $this->service()->cancelPurchaseOrder($po->id));

        $this->assertSame(PurchaseOrderStatus::CANCELLED, $cancelled->status);
        $this->assertEquals(0.0, (float) Supplier::find(1)->outstanding_balance);
    }

    public function test_cancel_from_partially_received_throws(): void
    {
        $po = $this->createDraftPo(quantity: 1, unitCost: 10.0);
        Model::withoutEvents(fn () => $po->update(['status' => PurchaseOrderStatus::PARTIALLY_RECEIVED]));

        $this->expectException(\RuntimeException::class);

        Model::withoutEvents(fn () => $this->service()->cancelPurchaseOrder($po->id));
    }

    // =========================================================================
    // getStorePurchaseOrders()
    // =========================================================================

    public function test_get_store_purchase_orders_filters_by_store_and_status(): void
    {
        $storeOneDraft = $this->createDraftPo(quantity: 1, unitCost: 10.0, storeId: 1);
        $storeOneSent = $this->createDraftPo(quantity: 1, unitCost: 10.0, storeId: 1);
        Model::withoutEvents(fn () => $this->service()->sendPurchaseOrder($storeOneSent->id));
        $this->createDraftPo(quantity: 1, unitCost: 10.0, storeId: 2);

        $storeOneResults = $this->service()->getStorePurchaseOrders(storeId: 1);
        $storeOneSentOnly = $this->service()->getStorePurchaseOrders(storeId: 1, status: PurchaseOrderStatus::SENT);

        $this->assertCount(2, $storeOneResults);
        $this->assertCount(1, $storeOneSentOnly);
        $this->assertSame($storeOneSent->id, $storeOneSentOnly->first()->id);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function service(): PurchaseOrderService
    {
        return new PurchaseOrderService;
    }

    private function createDraftPo(float $quantity, float $unitCost, int $storeId = 1): PurchaseOrder
    {
        return Model::withoutEvents(fn () => $this->service()->createPurchaseOrder([
            'supplier_id' => 1,
            'store_id' => $storeId,
            'items' => [
                ['product_id' => 1, 'quantity_ordered' => $quantity, 'uom_id' => 1, 'unit_cost' => $unitCost],
            ],
        ]));
    }

    private function seedBaseData(): void
    {
        $conn = 'tenant';

        DB::connection($conn)->table('stores')->insert([
            ['id' => 1, 'name' => 'Main Store', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'Second Store', 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::connection($conn)->table('suppliers')->insert([
            'id' => 1,
            'name' => 'Test Supplier',
            'outstanding_balance' => 0,
            'total_orders' => 0,
            'is_active' => true,
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
            ['id' => 1, 'name' => 'Allocated Product', 'base_uom_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'Unallocated Product', 'base_uom_id' => 1, 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::connection($conn)->table('store_products')->insert([
            ['store_id' => 1, 'product_id' => 1, 'store_selling_price' => 150.0, 'is_available' => true, 'min_stock_level' => 0, 'created_at' => now(), 'updated_at' => now()],
            ['store_id' => 2, 'product_id' => 1, 'store_selling_price' => 150.0, 'is_available' => true, 'min_stock_level' => 0, 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::connection($conn)->table('tax_rates')->insert([
            'id' => 1,
            'tax_name' => 'VAT',
            'rate' => 16.00,
            'effective_from' => now()->subYear()->toDateString(),
            'is_active' => true,
            'is_default' => true,
            'created_at' => now(),
        ]);
    }

    private function dropTestTables(): void
    {
        foreach ([
            'purchase_order_items',
            'purchase_orders',
            'tax_rates',
            'store_products',
            'product_variants',
            'products',
            'units_of_measure',
            'suppliers',
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

        Schema::connection($conn)->create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->default('Test Supplier');
            $table->string('email')->nullable();
            $table->decimal('outstanding_balance', 15, 2)->default(0);
            $table->unsignedInteger('total_orders')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
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

        Schema::connection($conn)->create('store_products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('product_variant_id')->nullable();
            $table->decimal('store_selling_price', 15, 2)->nullable();
            $table->boolean('is_available')->default(true);
            $table->decimal('min_stock_level', 15, 4)->default(0);
            $table->timestamps();
        });

        Schema::connection($conn)->create('tax_rates', function (Blueprint $table) {
            $table->id();
            $table->string('tax_name', 50);
            $table->decimal('rate', 5, 2);
            $table->date('effective_from');
            $table->date('effective_until')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->timestamp('created_at')->nullable();
        });

        Schema::connection($conn)->create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('po_number')->unique();
            $table->unsignedBigInteger('supplier_id');
            $table->unsignedBigInteger('store_id');
            $table->date('order_date');
            $table->date('expected_delivery_date')->nullable();
            $table->string('status')->default('draft');
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('shipping_cost', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->string('payment_status')->default('unpaid');
            $table->decimal('amount_paid', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
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
            $table->decimal('quantity_ordered', 15, 4);
            $table->decimal('quantity_received', 15, 4)->default(0);
            $table->decimal('quantity_ordered_in_base_uom', 15, 4);
            $table->decimal('quantity_received_in_base_uom', 15, 4)->default(0);
            $table->decimal('unit_cost', 15, 2);
            $table->decimal('unit_cost_in_base_uom', 15, 2);
            $table->unsignedBigInteger('tax_rate_id')->nullable();
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('subtotal', 15, 2);
            $table->string('status')->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }
}
