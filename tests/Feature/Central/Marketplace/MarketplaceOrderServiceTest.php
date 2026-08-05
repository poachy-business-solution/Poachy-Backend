<?php

namespace Tests\Feature\Central\Marketplace;

use App\Enums\Central\MarketplacePaymentStatus;
use App\Enums\Central\OrderStatus;
use App\Enums\Central\ReservationStatus;
use App\Jobs\Central\ProcessOrderCancellation;
use App\Models\MarketplaceCustomer;
use App\Models\MarketplaceOrder;
use App\Models\MarketplaceOrderPayment;
use App\Models\User;
use App\Services\Central\Marketplace\MarketplaceOrderService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MarketplaceOrderServiceTest extends TestCase
{
    private string $tenantId;

    private User $user;

    private MarketplaceCustomer $customer;

    private MarketplaceCustomer $otherCustomer;

    private MarketplaceOrderService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('tenancy.database.central_connection', 'central');
        Config::set('database.connections.central.host', env('CENTRAL_DB_HOST', '127.0.0.1'));
        Config::set('database.connections.central.port', env('CENTRAL_DB_PORT', '3306'));
        Config::set('database.connections.central.database', env('CENTRAL_DB_DATABASE', 'poachy'));
        Config::set('database.connections.central.username', env('CENTRAL_DB_USERNAME', 'root'));
        Config::set('database.connections.central.password', env('CENTRAL_DB_PASSWORD', ''));
        DB::purge('central');
        DB::connection('central')->statement('SET foreign_key_checks = 0');

        Queue::fake();

        $this->tenantId = 'order-test-'.uniqid();
        DB::connection('central')->table('tenants')->insertOrIgnore([
            'id' => $this->tenantId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->user = User::on('central')->create([
            'name' => 'Order Test Customer',
            'email' => 'order-customer-'.uniqid().'@test.com',
            'password' => bcrypt('password'),
            'user_type' => 'customer',
        ]);

        $this->customer = MarketplaceCustomer::on('central')->create([
            'user_id' => $this->user->id,
            'customer_number' => 'MKT-'.uniqid(),
            'phone' => '0712'.rand(100000, 999999),
            'phone_verified' => true,
        ]);

        $otherUser = User::on('central')->create([
            'name' => 'Other Customer',
            'email' => 'order-other-'.uniqid().'@test.com',
            'password' => bcrypt('password'),
            'user_type' => 'customer',
        ]);

        $this->otherCustomer = MarketplaceCustomer::on('central')->create([
            'user_id' => $otherUser->id,
            'customer_number' => 'MKT-'.uniqid(),
            'phone' => '0712'.rand(100000, 999999),
            'phone_verified' => true,
        ]);

        $this->service = new MarketplaceOrderService;
    }

    protected function tearDown(): void
    {
        DB::connection('central')->statement('SET foreign_key_checks = 0');

        $orderIds = MarketplaceOrder::on('central')->where('tenant_id', $this->tenantId)->pluck('id');
        MarketplaceOrderPayment::on('central')->whereIn('order_id', $orderIds)->forceDelete();
        MarketplaceOrder::on('central')->where('tenant_id', $this->tenantId)->forceDelete();
        MarketplaceCustomer::on('central')->whereIn('id', [$this->customer->id, $this->otherCustomer->id])->forceDelete();
        User::on('central')->whereIn('id', [$this->user->id, $this->otherCustomer->user_id])->forceDelete();
        DB::connection('central')->table('tenants')->where('id', $this->tenantId)->delete();

        DB::connection('central')->statement('SET foreign_key_checks = 1');
        parent::tearDown();
    }

    // =========================================================================
    // listCustomerOrders()
    // =========================================================================

    public function test_list_customer_orders_scopes_to_the_given_customer(): void
    {
        $this->createOrder();
        $this->createOrder(customerId: $this->otherCustomer->id);

        $result = $this->service->listCustomerOrders($this->customer->id);

        $this->assertSame(1, $result->total());
    }

    public function test_list_customer_orders_filters_by_status(): void
    {
        $this->createOrder(['order_status' => OrderStatus::Pending]);
        $this->createOrder(['order_status' => OrderStatus::Completed]);

        $result = $this->service->listCustomerOrders($this->customer->id, ['order_status' => 'completed']);

        $this->assertSame(1, $result->total());
        $this->assertSame('completed', $result->first()->order_status->value);
    }

    public function test_list_customer_orders_ignores_invalid_status_filter(): void
    {
        $this->createOrder();

        $result = $this->service->listCustomerOrders($this->customer->id, ['order_status' => 'not-a-real-status']);

        $this->assertSame(1, $result->total());
    }

    /**
     * sort_by/sort_direction previously went straight into orderBy() with no
     * validation — an unknown column name would throw a raw QueryException
     * instead of just being ignored, unlike order_status above.
     */
    public function test_list_customer_orders_falls_back_to_created_at_for_unknown_sort_by(): void
    {
        $this->createOrder();

        $result = $this->service->listCustomerOrders($this->customer->id, ['sort_by' => 'DROP TABLE orders']);

        $this->assertSame(1, $result->total());
    }

    public function test_list_customer_orders_falls_back_to_desc_for_unknown_sort_direction(): void
    {
        $this->createOrder();

        $result = $this->service->listCustomerOrders($this->customer->id, ['sort_direction' => 'sideways']);

        $this->assertSame(1, $result->total());
    }

    public function test_list_customer_orders_accepts_a_whitelisted_sort_by(): void
    {
        $this->createOrder(['total_amount' => 100]);
        $this->createOrder(['total_amount' => 500]);

        $result = $this->service->listCustomerOrders($this->customer->id, [
            'sort_by' => 'total_amount', 'sort_direction' => 'asc',
        ]);

        $this->assertEquals(100, $result->first()->total_amount);
    }

    // =========================================================================
    // getOrderDetails() / getOrderByNumber()
    // =========================================================================

    public function test_get_order_details_returns_order_for_owning_customer(): void
    {
        $order = $this->createOrder();

        $found = $this->service->getOrderDetails($order->id, $this->customer->id);

        $this->assertSame($order->id, $found->id);
    }

    public function test_get_order_details_throws_for_non_owning_customer(): void
    {
        $order = $this->createOrder();

        $this->expectException(ModelNotFoundException::class);

        $this->service->getOrderDetails($order->id, $this->otherCustomer->id);
    }

    public function test_get_order_by_number_returns_order_for_owning_customer(): void
    {
        $order = $this->createOrder();

        $found = $this->service->getOrderByNumber($order->order_number, $this->customer->id);

        $this->assertSame($order->id, $found->id);
    }

    // =========================================================================
    // updateOrderStatus()
    // =========================================================================

    public function test_update_order_status_allows_valid_transition(): void
    {
        $order = $this->createOrder(['order_status' => OrderStatus::Pending]);

        $updated = $this->service->updateOrderStatus($order, OrderStatus::Confirmed);

        $this->assertSame(OrderStatus::Confirmed, $updated->order_status);
    }

    public function test_update_order_status_throws_for_invalid_transition(): void
    {
        $order = $this->createOrder(['order_status' => OrderStatus::Pending]);

        $this->expectException(\RuntimeException::class);

        $this->service->updateOrderStatus($order, OrderStatus::Completed);
    }

    // =========================================================================
    // cancelOrder()
    // =========================================================================

    public function test_cancel_order_succeeds_and_releases_pending_reservation(): void
    {
        $order = $this->createOrder([
            'order_status' => OrderStatus::Pending,
            'reservation_status' => ReservationStatus::Pending,
        ]);

        $cancelled = $this->service->cancelOrder($order, 'Changed my mind', $this->customer->id);

        $this->assertSame(OrderStatus::Cancelled, $cancelled->order_status);
        $this->assertSame(ReservationStatus::Released, $cancelled->reservation_status);
        $this->assertSame('Changed my mind', $cancelled->cancellation_reason);
        Queue::assertPushed(ProcessOrderCancellation::class, fn ($job) => $job->orderId === $order->id);
    }

    public function test_cancel_order_releases_confirmed_reservation_too(): void
    {
        // order_status stays Pending here — a tenant can confirm a reservation
        // (hold stock) before the customer has actually paid, so this is a
        // genuinely safe pre-payment combination, unlike order_status=Confirmed
        // below (which only happens after payment completes).
        $order = $this->createOrder([
            'order_status' => OrderStatus::Pending,
            'reservation_status' => ReservationStatus::Confirmed,
        ]);

        $cancelled = $this->service->cancelOrder($order, 'No longer needed');

        $this->assertSame(ReservationStatus::Released, $cancelled->reservation_status);
    }

    public function test_cancel_order_marks_payment_refunded_once_order_has_been_paid_for(): void
    {
        // order_status only ever reaches Confirmed via confirmPayment(), which
        // runs after payment completes. cancelOrder() must flag the payment as
        // refunded here (bookkeeping only) and still dispatch the cancellation
        // sync — ProcessInboundCancellationSync is what actually reverses the
        // tenant-side inventory/batch deductions once it detects the synced sale.
        $order = $this->createOrder([
            'order_status' => OrderStatus::Confirmed,
            'reservation_status' => ReservationStatus::Confirmed,
        ]);

        $payment = MarketplaceOrderPayment::on('central')->create([
            'order_id' => $order->id,
            'payment_method' => 'mpesa',
            'amount' => $order->total_amount,
            'payment_status' => MarketplacePaymentStatus::Completed,
            'completed_at' => now(),
        ]);

        $cancelled = $this->service->cancelOrder($order, 'No longer needed');

        $this->assertSame(OrderStatus::Cancelled, $cancelled->order_status);

        $payment->refresh();
        $this->assertTrue($payment->is_refunded);
        $this->assertEquals((float) $order->total_amount, (float) $payment->refunded_amount);
        $this->assertSame(MarketplacePaymentStatus::Refunded, $payment->payment_status);

        Queue::assertPushed(ProcessOrderCancellation::class, fn ($job) => $job->orderId === $order->id);
    }

    public function test_cancel_order_leaves_payment_untouched_when_never_paid(): void
    {
        $order = $this->createOrder([
            'order_status' => OrderStatus::Pending,
            'reservation_status' => ReservationStatus::Pending,
        ]);

        $payment = MarketplaceOrderPayment::on('central')->create([
            'order_id' => $order->id,
            'payment_method' => 'mpesa',
            'amount' => $order->total_amount,
            'payment_status' => MarketplacePaymentStatus::Failed,
        ]);

        $this->service->cancelOrder($order, 'No longer needed');

        $payment->refresh();
        $this->assertFalse($payment->is_refunded);
        $this->assertSame(MarketplacePaymentStatus::Failed, $payment->payment_status);
    }

    public function test_cancel_order_throws_when_order_cannot_be_cancelled(): void
    {
        $order = $this->createOrder(['order_status' => OrderStatus::Completed]);

        $this->expectException(\RuntimeException::class);

        $this->service->cancelOrder($order, 'Too late');
    }

    public function test_cancel_order_throws_for_already_cancelled_order(): void
    {
        $order = $this->createOrder(['order_status' => OrderStatus::Cancelled]);

        $this->expectException(\RuntimeException::class);

        $this->service->cancelOrder($order, 'Already cancelled');
    }

    // =========================================================================
    // confirmOrderFromTenant()
    // =========================================================================

    public function test_confirm_order_from_tenant_confirms_pending_reservation(): void
    {
        $order = $this->createOrder(['reservation_status' => ReservationStatus::Pending]);

        $confirmed = $this->service->confirmOrderFromTenant($order->id, []);

        $this->assertSame(ReservationStatus::Confirmed, $confirmed->reservation_status);
        $this->assertNotNull($confirmed->reservation_confirmed_at);
    }

    public function test_confirm_order_from_tenant_ignores_non_pending_reservation(): void
    {
        $order = $this->createOrder(['reservation_status' => ReservationStatus::Confirmed]);

        $result = $this->service->confirmOrderFromTenant($order->id, []);

        $this->assertSame(ReservationStatus::Confirmed, $result->reservation_status);
    }

    // =========================================================================
    // handleReservationFailure()
    // =========================================================================

    public function test_handle_reservation_failure_marks_failed_and_cancels_order(): void
    {
        $order = $this->createOrder([
            'order_status' => OrderStatus::Pending,
            'reservation_status' => ReservationStatus::Pending,
        ]);

        $this->service->handleReservationFailure($order->id, ['reason' => 'Out of stock at tenant']);

        $fresh = $order->fresh();
        $this->assertSame(ReservationStatus::Failed, $fresh->reservation_status);
        $this->assertSame(OrderStatus::Cancelled, $fresh->order_status);
        $this->assertStringContainsString('Out of stock at tenant', $fresh->cancellation_reason);
    }

    public function test_handle_reservation_failure_is_noop_when_already_terminal(): void
    {
        $order = $this->createOrder(['reservation_status' => ReservationStatus::Released]);

        $this->service->handleReservationFailure($order->id, ['reason' => 'whatever']);

        $this->assertSame(ReservationStatus::Released, $order->fresh()->reservation_status);
    }

    // =========================================================================
    // handleReservationExpiry()
    // =========================================================================

    public function test_handle_reservation_expiry_marks_expired_and_cancels_order(): void
    {
        $order = $this->createOrder([
            'order_status' => OrderStatus::Pending,
            'reservation_status' => ReservationStatus::Pending,
        ]);

        $this->service->handleReservationExpiry($order);

        $fresh = $order->fresh();
        $this->assertSame(ReservationStatus::Expired, $fresh->reservation_status);
        $this->assertSame(OrderStatus::Cancelled, $fresh->order_status);
    }

    public function test_handle_reservation_expiry_is_noop_when_not_pending(): void
    {
        $order = $this->createOrder(['reservation_status' => ReservationStatus::Confirmed]);

        $this->service->handleReservationExpiry($order);

        $this->assertSame(ReservationStatus::Confirmed, $order->fresh()->reservation_status);
        $this->assertSame(OrderStatus::Pending, $order->fresh()->order_status);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function createOrder(array $overrides = [], ?int $customerId = null): MarketplaceOrder
    {
        return MarketplaceOrder::on('central')->create(array_merge([
            'order_number' => 'ORD-'.uniqid(),
            'customer_id' => $customerId ?? $this->customer->id,
            'tenant_id' => $this->tenantId,
            'merchant_name' => 'Test Merchant',
            'subtotal' => 500.0,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'delivery_fee' => 0,
            'total_amount' => 500.0,
            'fulfillment_type' => 'pickup',
            'order_status' => OrderStatus::Pending,
            'reservation_status' => ReservationStatus::Pending,
            'payment_deadline_at' => now()->addMinutes(30),
        ], $overrides));
    }
}
