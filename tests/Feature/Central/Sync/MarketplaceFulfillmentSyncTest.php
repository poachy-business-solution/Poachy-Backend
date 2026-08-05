<?php

namespace Tests\Feature\Central\Sync;

use App\DataTransferObjects\Sync\MarketplaceFulfillmentSyncDTO;
use App\Jobs\Central\ProcessInboundMarketplaceFulfillmentSync;
use App\Jobs\Central\UpdateTenantOrderMetricsJob;
use App\Models\MarketplaceOrder;
use App\Models\MarketplaceOrderDelivery;
use App\Models\SyncQueueInbound;
use App\Services\Central\Sync\MarketplaceFulfillmentSyncService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MarketplaceFulfillmentSyncTest extends TestCase
{
    private string $tenantId;

    private int $customerId;

    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('tenancy.database.central_connection', 'central');
        Config::set('database.connections.central.host', env('CENTRAL_DB_HOST', '127.0.0.1'));
        Config::set('database.connections.central.port', env('CENTRAL_DB_PORT', '3306'));
        Config::set('database.connections.central.database', env('CENTRAL_DB_DATABASE', 'poachy'));
        Config::set('database.connections.central.username', env('CENTRAL_DB_USERNAME', 'root'));
        Config::set('database.connections.central.password', env('CENTRAL_DB_PASSWORD', ''));
        Config::set('services.tenant_api.token', 'tenant-test-token');
        DB::purge('central');
        DB::setDefaultConnection('central');
        DB::connection('central')->statement('SET foreign_key_checks = 0');

        $this->tenantId = 'mkt-fulfil-test-'.uniqid();
        DB::connection('central')->table('tenants')->insertOrIgnore([
            'id' => $this->tenantId, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::connection('central')->table('domains')->insert([
            'domain' => 'mkt-fulfil-'.uniqid().'.poachy.test', 'tenant_id' => $this->tenantId,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $userId = DB::connection('central')->table('users')->insertGetId([
            'name' => 'Test Customer',
            'email' => uniqid().'@example.test',
            'password' => bcrypt('password'),
            'user_type' => 'customer',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->customerId = DB::connection('central')->table('marketplace_customers')->insertGetId([
            'customer_number' => 'MKT-CUST-'.uniqid(),
            'user_id' => $userId,
            'phone' => '+254700'.rand(100000, 999999),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->userId = $userId;
    }

    protected function tearDown(): void
    {
        DB::connection('central')->statement('SET foreign_key_checks = 0');
        SyncQueueInbound::on('central')->where('tenant_id', $this->tenantId)->delete();
        $orderIds = MarketplaceOrder::on('central')->where('tenant_id', $this->tenantId)->pluck('id');
        MarketplaceOrderDelivery::on('central')->whereIn('order_id', $orderIds)->delete();
        DB::connection('central')->table('marketplace_order_items')->whereIn('order_id', $orderIds)->delete();
        MarketplaceOrder::on('central')->where('tenant_id', $this->tenantId)->forceDelete();
        DB::connection('central')->table('marketplace_customers')->where('id', $this->customerId)->delete();
        DB::connection('central')->table('users')->where('id', $this->userId)->delete();
        DB::connection('central')->table('domains')->where('tenant_id', $this->tenantId)->delete();
        DB::connection('central')->table('tenants')->where('id', $this->tenantId)->delete();
        DB::connection('central')->statement('SET foreign_key_checks = 1');

        parent::tearDown();
    }

    private function makeApplyService(): MarketplaceFulfillmentSyncService
    {
        return app(MarketplaceFulfillmentSyncService::class);
    }

    private function createOrder(array $overrides = []): MarketplaceOrder
    {
        return Model::withoutEvents(fn () => MarketplaceOrder::on('central')->create(array_merge([
            'order_number' => 'MKT-ORD-'.uniqid(),
            'customer_id' => $this->customerId,
            'tenant_id' => $this->tenantId,
            'merchant_name' => 'Test Merchant',
            'subtotal' => 1000,
            'total_amount' => 1160,
            'fulfillment_type' => 'delivery',
            'order_status' => 'confirmed',
        ], $overrides)));
    }

    private function makeDto(MarketplaceOrder $order, array $overrides = []): MarketplaceFulfillmentSyncDTO
    {
        return MarketplaceFulfillmentSyncDTO::fromArray(array_merge([
            'tenant_id' => $this->tenantId,
            'sale_id' => 1,
            'central_order_id' => $order->id,
            'fulfillment_status' => 'preparing',
        ], $overrides));
    }

    // =========================================================================
    // MarketplaceFulfillmentSyncService::apply() — OrderStatus mapping
    // =========================================================================

    public function test_apply_confirmed_is_a_noop_since_order_is_already_confirmed(): void
    {
        $order = $this->createOrder(['order_status' => 'confirmed']);

        $result = $this->makeApplyService()->apply($this->makeDto($order, ['fulfillment_status' => 'confirmed']));

        $this->assertSame('confirmed', $result->order_status->value);
    }

    public function test_apply_preparing_transitions_order_to_processing(): void
    {
        $order = $this->createOrder(['order_status' => 'confirmed']);

        $result = $this->makeApplyService()->apply($this->makeDto($order, ['fulfillment_status' => 'preparing']));

        $this->assertSame('processing', $result->order_status->value);
    }

    public function test_apply_ready_transitions_to_ready_for_pickup_when_pickup_order(): void
    {
        $order = $this->createOrder(['order_status' => 'processing', 'fulfillment_type' => 'pickup']);

        $result = $this->makeApplyService()->apply($this->makeDto($order, ['fulfillment_status' => 'ready']));

        $this->assertSame('ready_for_pickup', $result->order_status->value);
    }

    public function test_apply_ready_transitions_to_out_for_delivery_when_delivery_order(): void
    {
        $order = $this->createOrder(['order_status' => 'processing', 'fulfillment_type' => 'delivery']);

        $result = $this->makeApplyService()->apply($this->makeDto($order, ['fulfillment_status' => 'ready']));

        $this->assertSame('out_for_delivery', $result->order_status->value);
    }

    public function test_apply_delivered_transitions_order_to_completed(): void
    {
        $order = $this->createOrder(['order_status' => 'out_for_delivery', 'fulfillment_type' => 'delivery']);

        $result = $this->makeApplyService()->apply($this->makeDto($order, ['fulfillment_status' => 'delivered']));

        $this->assertSame('completed', $result->order_status->value);
    }

    public function test_apply_cancelled_transitions_order_to_cancelled(): void
    {
        $order = $this->createOrder(['order_status' => 'confirmed']);

        $result = $this->makeApplyService()->apply($this->makeDto($order, ['fulfillment_status' => 'cancelled']));

        $this->assertSame('cancelled', $result->order_status->value);
    }

    public function test_apply_swallows_invalid_transition_instead_of_throwing(): void
    {
        // Order still Pending (payment not confirmed) — PREPARING maps to
        // Processing, but Pending->Processing isn't a valid transition.
        $order = $this->createOrder(['order_status' => 'pending']);

        $result = $this->makeApplyService()->apply($this->makeDto($order, ['fulfillment_status' => 'preparing']));

        // No exception thrown, order_status simply stays put.
        $this->assertSame('pending', $result->order_status->value);
    }

    public function test_apply_always_mirrors_item_level_fulfillment_status(): void
    {
        $order = $this->createOrder(['order_status' => 'confirmed']);
        // foreign_key_checks are disabled for this connection in setUp(), so a
        // non-existent product id is safe here and avoids a full product fixture.
        Model::withoutEvents(fn () => $order->items()->create([
            'marketplace_product_id' => 999999,
            'tenant_product_id' => 1,
            'product_name' => 'Test Product',
            'product_sku' => 'SKU-1',
            'uom_code' => 'pcs',
            'uom_name' => 'Piece',
            'quantity' => 1,
            'quantity_in_base_uom' => 1,
            'unit_price' => 1000,
            'tax_rate' => 0,
            'tax_amount' => 0,
            'subtotal' => 1000,
            'fulfillment_status' => 'confirmed',
        ]));

        $this->makeApplyService()->apply($this->makeDto($order, ['fulfillment_status' => 'preparing']));

        $this->assertSame('preparing', $order->items()->first()->fulfillment_status->value);
    }

    public function test_apply_throws_when_order_not_found(): void
    {
        $this->expectException(\RuntimeException::class);

        $this->makeApplyService()->apply(MarketplaceFulfillmentSyncDTO::fromArray([
            'tenant_id' => $this->tenantId,
            'sale_id' => 1,
            'central_order_id' => 999999999,
            'fulfillment_status' => 'preparing',
        ]));
    }

    public function test_apply_throws_when_tenant_id_mismatch(): void
    {
        $order = $this->createOrder();

        $this->expectException(\RuntimeException::class);

        $this->makeApplyService()->apply($this->makeDto($order, ['tenant_id' => 'someone-else']));
    }

    // =========================================================================
    // MarketplaceFulfillmentSyncService::apply() — Delivery tracking
    // =========================================================================

    public function test_apply_confirmed_creates_delivery_record_for_delivery_orders(): void
    {
        $order = $this->createOrder(['order_status' => 'confirmed', 'fulfillment_type' => 'delivery']);

        $this->makeApplyService()->apply($this->makeDto($order, ['fulfillment_status' => 'confirmed']));

        $this->assertNotNull($order->fresh()->delivery);
        $this->assertSame('pending', $order->fresh()->delivery->delivery_status->value);
    }

    public function test_apply_does_not_create_delivery_record_for_pickup_orders(): void
    {
        $order = $this->createOrder(['order_status' => 'confirmed', 'fulfillment_type' => 'pickup']);

        $this->makeApplyService()->apply($this->makeDto($order, ['fulfillment_status' => 'confirmed']));

        $this->assertNull($order->fresh()->delivery);
    }

    public function test_apply_assigns_courier_when_courier_data_present(): void
    {
        $order = $this->createOrder(['order_status' => 'confirmed', 'fulfillment_type' => 'delivery']);

        $this->makeApplyService()->apply($this->makeDto($order, [
            'fulfillment_status' => 'confirmed',
            'courier_company' => 'Sendy',
            'courier_name' => 'John Rider',
            'courier_phone' => '0700000000',
        ]));

        $delivery = $order->fresh()->delivery;
        $this->assertSame('assigned', $delivery->delivery_status->value);
        $this->assertSame('Sendy', $delivery->courier_company);
        $this->assertSame('John Rider', $delivery->courier_name);
    }

    public function test_apply_ready_sets_delivery_out_for_delivery(): void
    {
        $order = $this->createOrder(['order_status' => 'processing', 'fulfillment_type' => 'delivery']);
        $this->makeApplyService()->apply($this->makeDto($order, ['fulfillment_status' => 'confirmed']));

        $this->makeApplyService()->apply($this->makeDto($order->fresh(), ['fulfillment_status' => 'ready']));

        $this->assertSame('out_for_delivery', $order->fresh()->delivery->delivery_status->value);
    }

    public function test_apply_delivered_confirms_delivery_with_proof(): void
    {
        $order = $this->createOrder(['order_status' => 'out_for_delivery', 'fulfillment_type' => 'delivery']);

        $this->makeApplyService()->apply($this->makeDto($order, [
            'fulfillment_status' => 'delivered',
            'delivery_proof_type' => 'signature',
            'delivery_proof_data' => 'base64-signature-data',
            'received_by_name' => 'Jane Doe',
        ]));

        $delivery = $order->fresh()->delivery;
        $this->assertSame('delivered', $delivery->delivery_status->value);
        $this->assertSame('signature', $delivery->delivery_proof_type);
        $this->assertSame('Jane Doe', $delivery->received_by_name);
        $this->assertSame('completed', $order->fresh()->order_status->value);
    }

    public function test_apply_cancelled_marks_delivery_failed_when_not_yet_terminal(): void
    {
        $order = $this->createOrder(['order_status' => 'processing', 'fulfillment_type' => 'delivery']);
        $this->makeApplyService()->apply($this->makeDto($order, ['fulfillment_status' => 'confirmed']));

        $this->makeApplyService()->apply($this->makeDto($order->fresh(), ['fulfillment_status' => 'cancelled', 'notes' => 'Customer cancelled']));

        $delivery = $order->fresh()->delivery;
        $this->assertSame('failed', $delivery->delivery_status->value);
        $this->assertSame('Customer cancelled', $delivery->delivery_notes);
    }

    // =========================================================================
    // ProcessInboundMarketplaceFulfillmentSync — inbound processing + ACK
    // =========================================================================

    private function createInboundSync(MarketplaceOrder $order, array $overrides = []): SyncQueueInbound
    {
        return SyncQueueInbound::on('central')->create(array_merge([
            'tenant_id' => $this->tenantId,
            'syncable_type' => 'MarketplaceFulfillment',
            'tenant_syncable_id' => 1,
            'action' => 'update',
            'payload' => [
                'tenant_id' => $this->tenantId,
                'sale_id' => 1,
                'central_order_id' => $order->id,
                'fulfillment_status' => 'preparing',
            ],
            'metadata' => ['sync_queue_id_from_tenant' => 701],
            'priority' => 2,
            'received_at' => now(),
            'scheduled_at' => now(),
            'expires_at' => now()->addHours(24),
            'status' => 'pending',
            'retry_count' => 0,
            'max_retries' => 3,
            'backoff_strategy' => 'exponential',
            'idempotency_key' => 'idem-'.uniqid(),
            'payload_hash' => 'hash-'.uniqid(),
        ], $overrides));
    }

    public function test_inbound_job_applies_sync_and_acks_completed(): void
    {
        Http::fake(['*/marketplace-fulfillment-ack' => Http::response(['success' => true], 200)]);
        $order = $this->createOrder(['order_status' => 'confirmed']);
        $sync = $this->createInboundSync($order);

        (new ProcessInboundMarketplaceFulfillmentSync($sync->id))->handle(app(MarketplaceFulfillmentSyncService::class));

        $fresh = $sync->fresh();
        $this->assertSame('completed', $fresh->status);
        $this->assertSame('processing', $order->fresh()->order_status->value);
        $this->assertEquals($order->id, $fresh->central_record_id);

        Http::assertSent(function ($request) use ($order) {
            return str_contains($request->url(), 'marketplace-fulfillment-ack')
                && $request['outbound_sync_queue_id'] === 701
                && $request['status'] === 'completed'
                && $request['central_order_id'] === $order->id;
        });
    }

    public function test_inbound_job_marks_failed_and_does_not_ack_on_transient_failure(): void
    {
        Http::fake();
        // Order not found centrally — a real, non-permanent-looking failure the
        // apply layer throws for.
        $sync = $this->createInboundSync($this->createOrder(), ['payload' => [
            'tenant_id' => $this->tenantId, 'sale_id' => 1, 'central_order_id' => 999999999, 'fulfillment_status' => 'preparing',
        ]]);

        try {
            (new ProcessInboundMarketplaceFulfillmentSync($sync->id))->handle(app(MarketplaceFulfillmentSyncService::class));
            $this->fail('Expected exception was not thrown');
        } catch (\RuntimeException $e) {
            // expected — order not found
        }

        $fresh = $sync->fresh();
        $this->assertSame('pending', $fresh->status);
        $this->assertSame(1, $fresh->retry_count);
        Http::assertNothingSent();
    }

    public function test_inbound_job_acks_failed_after_max_retries(): void
    {
        Http::fake(['*/marketplace-fulfillment-ack' => Http::response(['success' => true], 200)]);
        $sync = $this->createInboundSync($this->createOrder(), [
            'payload' => ['tenant_id' => $this->tenantId, 'sale_id' => 1, 'central_order_id' => 999999999, 'fulfillment_status' => 'preparing'],
            'retry_count' => 3,
            'max_retries' => 3,
        ]);

        try {
            (new ProcessInboundMarketplaceFulfillmentSync($sync->id))->handle(app(MarketplaceFulfillmentSyncService::class));
            $this->fail('Expected exception was not thrown');
        } catch (\RuntimeException $e) {
            // expected
        }

        $this->assertSame('failed', $sync->fresh()->status);
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'marketplace-fulfillment-ack') && $request['status'] === 'failed';
        });
    }

    public function test_inbound_job_skips_when_already_completed(): void
    {
        Http::fake();
        $sync = $this->createInboundSync($this->createOrder(), ['status' => 'completed']);

        (new ProcessInboundMarketplaceFulfillmentSync($sync->id))->handle(app(MarketplaceFulfillmentSyncService::class));

        Http::assertNothingSent();
    }

    public function test_inbound_job_marks_stale_without_processing_when_expired(): void
    {
        Http::fake();
        $sync = $this->createInboundSync($this->createOrder(), ['expires_at' => now()->subHour()]);

        (new ProcessInboundMarketplaceFulfillmentSync($sync->id))->handle(app(MarketplaceFulfillmentSyncService::class));

        $this->assertSame('stale', $sync->fresh()->status);
        Http::assertNothingSent();
    }

    // =========================================================================
    // Regression: a DELIVERED sync ultimately triggers tenant order metrics
    // =========================================================================

    public function test_delivered_sync_dispatches_tenant_order_metrics_job(): void
    {
        Queue::fake();
        $order = $this->createOrder(['order_status' => 'out_for_delivery', 'fulfillment_type' => 'delivery']);

        // Deliberately not withoutEvents() here — the observer that dispatches
        // UpdateTenantOrderMetricsJob must fire naturally.
        $this->makeApplyService()->apply($this->makeDto($order, ['fulfillment_status' => 'delivered']));

        Queue::assertPushed(UpdateTenantOrderMetricsJob::class);
    }
}
