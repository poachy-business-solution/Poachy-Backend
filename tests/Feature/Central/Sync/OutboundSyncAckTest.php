<?php

namespace Tests\Feature\Central\Sync;

use App\Enums\Central\MarketplacePaymentStatus;
use App\Enums\Central\OrderStatus;
use App\Enums\Central\ReservationStatus;
use App\Models\MarketplaceCustomer;
use App\Models\MarketplaceOrder;
use App\Models\MarketplaceOrderPayment;
use App\Models\MarketplaceRefund;
use App\Models\SyncQueueOutbound;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * A shop answering "failed" to a central message is the outcome, not a transient error: central
 * never re-sends it, and a paid order the shop could not record is cancelled (the buyer refunded).
 */
class OutboundSyncAckTest extends TestCase
{
    private string $tenantId;

    private User $user;

    private MarketplaceCustomer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('tenancy.database.central_connection', 'central');
        Config::set('database.connections.central.database', env('CENTRAL_DB_DATABASE', 'poachy'));
        Config::set('services.central_api.token', 'central-test-token');
        Config::set('mpesa.driver', 'fake');
        DB::purge('central');
        DB::setDefaultConnection('central');
        Queue::fake();

        $this->tenantId = 'ack-'.Str::lower(Str::random(10));
        DB::connection('central')->table('tenants')->insert(['id' => $this->tenantId, 'created_at' => now(), 'updated_at' => now()]);
        $this->user = User::on('central')->create(['name' => 'Ack Buyer', 'email' => 'ack-'.uniqid().'@test.com', 'password' => bcrypt('x'), 'user_type' => 'customer']);
        $this->customer = MarketplaceCustomer::on('central')->create(['user_id' => $this->user->id, 'customer_number' => 'MKT-'.uniqid(), 'phone' => '0712345678', 'phone_verified' => true]);
    }

    protected function tearDown(): void
    {
        DB::connection('central')->statement('SET foreign_key_checks = 0');
        $orderIds = MarketplaceOrder::on('central')->where('tenant_id', $this->tenantId)->pluck('id');
        MarketplaceRefund::query()->whereIn('order_id', $orderIds)->delete();
        SyncQueueOutbound::on('central')->where('tenant_id', $this->tenantId)->delete();
        MarketplaceOrderPayment::on('central')->whereIn('order_id', $orderIds)->forceDelete();
        MarketplaceOrder::on('central')->whereIn('id', $orderIds)->forceDelete();
        $this->customer->forceDelete();
        $this->user->forceDelete();
        DB::connection('central')->table('tenants')->where('id', $this->tenantId)->delete();
        DB::connection('central')->statement('SET foreign_key_checks = 1');
        parent::tearDown();
    }

    public function test_a_failed_answer_is_final_and_never_re_sent(): void
    {
        $order = $this->order(paid: false);
        $entry = $this->entry($order, 'reserve_inventory');

        $this->ack($entry, 'failed', 'Insufficient stock for Wall Clock')->assertStatus(202);

        $entry->refresh();
        $this->assertSame(['failed', $entry->max_retries], [$entry->status, $entry->retry_count]);
        // What sync:process-outbound picks up again: nothing.
        $this->assertFalse(SyncQueueOutbound::on('central')->whereKey($entry->id)->where('status', 'failed')->whereColumn('retry_count', '<', 'max_retries')->exists());
        $this->assertSame(OrderStatus::Pending, $order->fresh()->order_status);
    }

    public function test_a_paid_order_the_shop_could_not_record_is_cancelled_and_refunded(): void
    {
        $order = $this->order(paid: true);
        $entry = $this->entry($order, 'payment_confirmed');

        $this->ack($entry, 'failed', 'No active reservations found')->assertStatus(202);

        $this->assertSame(OrderStatus::Cancelled, $order->fresh()->order_status);
        $refund = MarketplaceRefund::query()->where('order_id', $order->id)->sole();
        $this->assertSame(['cancellation', 'mpesa_reversal'], [$refund->kind, $refund->method]);

        // A repeated answer changes nothing more.
        $this->ack($entry, 'failed', 'again')->assertStatus(202);
        $this->assertSame(1, MarketplaceRefund::query()->where('order_id', $order->id)->count());
    }

    private function order(bool $paid): MarketplaceOrder
    {
        $order = MarketplaceOrder::on('central')->create([
            'order_number' => 'MKT-A-'.strtoupper(uniqid()), 'customer_id' => $this->customer->id, 'tenant_id' => $this->tenantId, 'merchant_name' => 'Ack Duka',
            'subtotal' => 900, 'tax_amount' => 0, 'discount_amount' => 0, 'delivery_fee' => 0, 'total_amount' => 900, 'fulfillment_type' => 'pickup',
            'order_status' => $paid ? OrderStatus::Confirmed : OrderStatus::Pending, 'reservation_status' => ReservationStatus::Confirmed,
        ]);
        MarketplaceOrderPayment::on('central')->create([
            'order_id' => $order->id, 'payment_method' => 'mpesa', 'amount' => 900,
            'payment_status' => $paid ? MarketplacePaymentStatus::Completed : MarketplacePaymentStatus::Pending,
            'transaction_reference' => 'CKO-'.uniqid(), 'provider_reference' => $paid ? 'REC'.strtoupper(Str::random(7)) : null, 'completed_at' => $paid ? now() : null,
        ]);

        return $order;
    }

    private function entry(MarketplaceOrder $order, string $action): SyncQueueOutbound
    {
        return SyncQueueOutbound::on('central')->create([
            'tenant_id' => $this->tenantId, 'syncable_type' => 'marketplace_order', 'syncable_id' => $order->id, 'action' => $action,
            'payload' => ['order_id' => $order->id], 'metadata' => ['order_id' => $order->id, 'order_number' => $order->order_number],
            'priority' => 1, 'status' => 'delivered', 'max_retries' => 10, 'retry_count' => 0, 'backoff_strategy' => 'exponential',
            'idempotency_key' => (string) Str::uuid(),
        ]);
    }

    private function ack(SyncQueueOutbound $entry, string $status, string $reason): \Illuminate\Testing\TestResponse
    {
        return $this->withToken('central-test-token')->postJson('/api/v1/central/sync/inbound/outbound-sync-ack', [
            'outbound_sync_id' => $entry->id, 'tenant_id' => $this->tenantId, 'status' => $status, 'reason' => $reason,
        ]);
    }
}
