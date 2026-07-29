<?php

namespace Tests\Feature\Payment;

use App\Enums\Central\MarketplacePaymentStatus;
use App\Enums\Central\OrderStatus;
use App\Enums\Central\ReservationStatus;
use App\Events\Central\Marketplace\PaymentAttempted;
use App\Events\Central\Marketplace\PaymentCompleted;
use App\Events\Central\Marketplace\PaymentFailed;
use App\Http\Controllers\Api\Central\Mpesa\MpesaC2BController;
use App\Jobs\Central\ProcessOrderCancellation;
use App\Jobs\Central\ProcessPaymentConfirmation;
use App\Models\CentralPaymentLog;
use App\Models\MarketplaceCustomer;
use App\Models\MarketplaceOrder;
use App\Models\MarketplaceOrderPayment;
use App\Models\User;
use App\Services\Central\Marketplace\MarketplacePaymentService;
use App\Services\Central\Marketplace\MarketplaceProductService;
use App\Services\Shared\Mpesa\MpesaC2BRouterService;
use App\Services\Shared\Mpesa\MpesaService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class MarketplacePaymentServiceTest extends TestCase
{
    private string $tenantId;

    private User $user;

    private MarketplaceCustomer $customer;

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
        Event::fake([PaymentAttempted::class, PaymentCompleted::class, PaymentFailed::class]);

        $this->tenantId = 'mpesa-test-'.uniqid();
        DB::connection('central')->table('tenants')->insertOrIgnore([
            'id' => $this->tenantId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->user = User::on('central')->create([
            'name' => 'Test Customer',
            'email' => 'mpesa-customer-'.uniqid().'@test.com',
            'password' => bcrypt('password'),
            'user_type' => 'customer',
        ]);

        $this->customer = MarketplaceCustomer::on('central')->create([
            'user_id' => $this->user->id,
            'customer_number' => 'MKT-'.uniqid(),
            'phone' => '0712'.rand(100000, 999999),
            'phone_verified' => true,
        ]);
    }

    protected function tearDown(): void
    {
        DB::connection('central')->statement('SET foreign_key_checks = 0');

        $orderIds = MarketplaceOrder::on('central')->where('tenant_id', $this->tenantId)->pluck('id');
        $paymentIds = MarketplaceOrderPayment::on('central')->whereIn('order_id', $orderIds)->pluck('id');

        CentralPaymentLog::on('central')->where('payable_type', 'marketplace_order_payment')->whereIn('payable_id', $paymentIds)->delete();
        MarketplaceOrderPayment::on('central')->whereIn('order_id', $orderIds)->forceDelete();
        MarketplaceOrder::on('central')->where('tenant_id', $this->tenantId)->forceDelete();
        MarketplaceCustomer::on('central')->where('id', $this->customer->id)->forceDelete();
        User::on('central')->where('id', $this->user->id)->forceDelete();
        DB::connection('central')->table('tenants')->where('id', $this->tenantId)->delete();

        DB::connection('central')->statement('SET foreign_key_checks = 1');
        Mockery::close();
        parent::tearDown();
    }

    // =========================================================================
    // initiatePayment() — routing, STK, Paybill, COD
    // =========================================================================

    public function test_initiate_payment_stk_happy_path(): void
    {
        $order = $this->createOrder();
        $mpesaMock = Mockery::mock(MpesaService::class);
        $mpesaMock->shouldReceive('initiateSTKPush')->once()->andReturn([
            'checkout_request_id' => 'CKO123',
            'merchant_request_id' => 'MER123',
        ]);

        $result = $this->service($mpesaMock)->initiatePayment($order, ['phone_number' => '254712345678']);

        $this->assertSame('CKO123', $result['instructions']['checkout_request_id']);
        $payment = $order->payments()->first()->fresh();
        $this->assertSame('processing', $payment->payment_status->value);
        $this->assertSame('CKO123', $payment->transaction_reference);
        $this->assertSame('MER123', $payment->payment_metadata['merchant_request_id']);
        Event::assertDispatched(PaymentAttempted::class);
    }

    public function test_initiate_payment_throws_when_cannot_accept_payment(): void
    {
        $order = $this->createOrder(['reservation_status' => ReservationStatus::Pending]);

        $this->expectException(\RuntimeException::class);

        $this->service()->initiatePayment($order, ['phone_number' => '254712345678']);
    }

    public function test_initiate_payment_throws_when_no_pending_payment_record(): void
    {
        $order = $this->createOrder(withPayment: false);

        $this->expectException(\RuntimeException::class);

        $this->service()->initiatePayment($order, ['phone_number' => '254712345678']);
    }

    public function test_initiate_stk_throws_when_phone_number_missing(): void
    {
        $order = $this->createOrder();

        $this->expectException(\RuntimeException::class);

        $this->service()->initiatePayment($order, []);
    }

    public function test_initiate_stk_returns_please_wait_without_second_push(): void
    {
        $order = $this->createOrder(paymentOverrides: [
            'payment_status' => MarketplacePaymentStatus::Processing,
            'initiated_at' => now()->subSeconds(10),
        ]);
        $mpesaMock = Mockery::mock(MpesaService::class);
        $mpesaMock->shouldNotReceive('initiateSTKPush');

        $result = $this->service($mpesaMock)->initiatePayment($order, ['phone_number' => '254712345678']);

        $this->assertStringContainsString('already sent', $result['message']);
    }

    public function test_initiate_paybill_returns_instructions_without_http_call(): void
    {
        $order = $this->createOrder(paymentOverrides: ['payment_method' => 'mpesa_paybill']);
        $mpesaMock = Mockery::mock(MpesaService::class);
        $mpesaMock->shouldReceive('getActiveCredentials')->once()->andReturn(['shortcode' => '174379']);
        $mpesaMock->shouldNotReceive('initiateSTKPush');

        $result = $this->service($mpesaMock)->initiatePayment($order, []);

        $this->assertSame('174379', $result['instructions']['business_number']);
        $this->assertSame($order->order_number, $result['instructions']['account_number']);
        $this->assertSame(1, CentralPaymentLog::on('central')->where('event', 'paybill_instructions_issued')->count());
    }

    public function test_initiate_cash_on_delivery_confirms_immediately(): void
    {
        $order = $this->createOrder(paymentOverrides: ['payment_method' => 'cash_on_delivery']);

        $this->service()->initiatePayment($order, []);

        $payment = $order->payments()->first()->fresh();
        $this->assertSame('completed', $payment->payment_status->value);
        $this->assertSame('confirmed', $order->fresh()->order_status->value);
        Queue::assertPushed(ProcessPaymentConfirmation::class, fn ($job) => $job->orderId === $order->id);
    }

    public function test_initiate_payment_throws_for_unsupported_method(): void
    {
        $order = $this->createOrder(paymentOverrides: ['payment_method' => 'card']);

        $this->expectException(\RuntimeException::class);

        $this->service()->initiatePayment($order, []);
    }

    // =========================================================================
    // processPaymentWebhook() / processSTKCallback()
    // =========================================================================

    public function test_success_webhook_confirms_payment_and_order(): void
    {
        $order = $this->createOrder(paymentOverrides: [
            'payment_status' => MarketplacePaymentStatus::Processing,
            'transaction_reference' => 'CKO-SUCCESS-1',
        ]);

        $this->service()->processPaymentWebhook([
            'transaction_reference' => 'CKO-SUCCESS-1',
            'status' => 'success',
            'provider_reference' => 'MPESA-REC-1',
        ]);

        $payment = $order->payments()->first()->fresh();
        $this->assertSame('completed', $payment->payment_status->value);
        $this->assertSame('MPESA-REC-1', $payment->provider_reference);
        $this->assertSame('confirmed', $order->fresh()->order_status->value);
        Queue::assertPushed(ProcessPaymentConfirmation::class, fn ($job) => $job->orderId === $order->id);
    }

    public function test_duplicate_webhook_for_completed_payment_is_ignored(): void
    {
        $order = $this->createOrder(paymentOverrides: [
            'payment_status' => MarketplacePaymentStatus::Completed,
            'transaction_reference' => 'CKO-DUP-1',
        ]);

        $result = $this->service()->processPaymentWebhook([
            'transaction_reference' => 'CKO-DUP-1',
            'status' => 'success',
        ]);

        $this->assertSame($order->payments()->first()->id, $result->id);
        Queue::assertNotPushed(ProcessPaymentConfirmation::class);
    }

    public function test_webhook_throws_when_transaction_reference_not_found(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->service()->processPaymentWebhook([
            'transaction_reference' => 'CKO-DOES-NOT-EXIST',
            'status' => 'success',
        ]);
    }

    public function test_failure_webhook_marks_payment_failed_without_touching_order(): void
    {
        $order = $this->createOrder(paymentOverrides: [
            'payment_status' => MarketplacePaymentStatus::Processing,
            'transaction_reference' => 'CKO-FAIL-1',
        ]);

        $this->service()->processPaymentWebhook([
            'transaction_reference' => 'CKO-FAIL-1',
            'status' => 'failed',
            'failure_reason' => 'Insufficient funds',
            'failure_code' => '1032',
        ]);

        $payment = $order->payments()->first()->fresh();
        $this->assertSame('failed', $payment->payment_status->value);
        $this->assertSame('Insufficient funds', $payment->failure_reason);
        $this->assertSame('pending', $order->fresh()->order_status->value);
        Event::assertDispatched(PaymentFailed::class);
    }

    public function test_success_webhook_for_terminal_order_triggers_refund_instead_of_confirm(): void
    {
        $order = $this->createOrder([
            'order_status' => OrderStatus::Cancelled,
        ], paymentOverrides: [
            'payment_status' => MarketplacePaymentStatus::Processing,
            'transaction_reference' => 'CKO-TERMINAL-1',
        ]);

        $this->service()->processPaymentWebhook([
            'transaction_reference' => 'CKO-TERMINAL-1',
            'status' => 'success',
            'provider_reference' => 'MPESA-REC-2',
        ]);

        $payment = $order->payments()->first()->fresh();
        $this->assertSame('refunded', $payment->payment_status->value);
        $this->assertSame('cancelled', $order->fresh()->order_status->value);
        Queue::assertNotPushed(ProcessPaymentConfirmation::class);
    }

    // =========================================================================
    // processC2BConfirmation()
    // =========================================================================

    public function test_c2b_confirmation_confirms_payment_by_order_number(): void
    {
        $order = $this->createOrder(paymentOverrides: ['payment_method' => 'mpesa_paybill']);

        $this->service()->processC2BConfirmation([
            'bill_ref_number' => $order->order_number,
            'transaction_id' => 'C2B-TXN-1',
            'amount' => (float) $order->total_amount,
            'phone' => '254712345678',
        ]);

        $payment = $order->payments()->first()->fresh();
        $this->assertSame('completed', $payment->payment_status->value);
        $this->assertSame('C2B-TXN-1', $payment->transaction_reference);
        $this->assertSame('C2B-TXN-1', $payment->provider_reference);
        $this->assertSame('confirmed', $order->fresh()->order_status->value);
    }

    public function test_c2b_confirmation_idempotent_on_provider_reference(): void
    {
        $order = $this->createOrder(paymentOverrides: [
            'payment_method' => 'mpesa_paybill',
            'payment_status' => MarketplacePaymentStatus::Completed,
            'provider_reference' => 'C2B-TXN-DUP',
        ]);

        $result = $this->service()->processC2BConfirmation([
            'bill_ref_number' => $order->order_number,
            'transaction_id' => 'C2B-TXN-DUP',
            'amount' => (float) $order->total_amount,
            'phone' => '254712345678',
        ]);

        $this->assertSame($order->payments()->first()->id, $result->id);
    }

    public function test_c2b_confirmation_for_terminal_order_triggers_refund(): void
    {
        $order = $this->createOrder([
            'order_status' => OrderStatus::Cancelled,
        ], paymentOverrides: ['payment_method' => 'mpesa_paybill']);

        $this->service()->processC2BConfirmation([
            'bill_ref_number' => $order->order_number,
            'transaction_id' => 'C2B-TXN-TERMINAL',
            'amount' => (float) $order->total_amount,
            'phone' => '254712345678',
        ]);

        $payment = $order->payments()->first()->fresh();
        $this->assertSame('refunded', $payment->payment_status->value);
    }

    // =========================================================================
    // confirmPayment() / handlePaymentFailure() / initiateRefund()
    // =========================================================================

    public function test_confirm_payment_fires_payment_completed_and_dispatches_job(): void
    {
        $order = $this->createOrder();
        $payment = $order->payments()->first();

        $this->service()->confirmPayment($payment, 'TXN-1', 'REC-1');

        $this->assertSame('confirmed', $order->fresh()->order_status->value);
        Event::assertDispatched(PaymentCompleted::class, fn ($e) => $e->payment->id === $payment->id);
        Queue::assertPushed(ProcessPaymentConfirmation::class, fn ($job) => $job->orderId === $order->id);
    }

    public function test_handle_payment_failure_fires_payment_failed(): void
    {
        $order = $this->createOrder();
        $payment = $order->payments()->first();

        $this->service()->handlePaymentFailure($payment, 'Timeout', 'CODE1');

        $this->assertSame('failed', $payment->fresh()->payment_status->value);
        Event::assertDispatched(PaymentFailed::class, fn ($e) => $e->payment->id === $payment->id);
    }

    public function test_initiate_refund_marks_payment_refunded(): void
    {
        $order = $this->createOrder();
        $payment = $order->payments()->first();

        $this->service()->initiateRefund($payment, 250.0);

        $fresh = $payment->fresh();
        $this->assertSame('refunded', $fresh->payment_status->value);
        $this->assertEquals(250.0, (float) $fresh->refunded_amount);
    }

    // =========================================================================
    // handlePaymentTimeout() — the cross-order bug fix
    // =========================================================================

    public function test_handle_payment_timeout_does_not_touch_unrelated_orders_processing_payment(): void
    {
        $timedOutOrder = $this->createOrder(paymentOverrides: [
            'payment_status' => MarketplacePaymentStatus::Processing,
        ]);
        $unrelatedOrder = $this->createOrder(paymentOverrides: [
            'payment_status' => MarketplacePaymentStatus::Processing,
        ]);

        $this->service()->handlePaymentTimeout($timedOutOrder);

        $this->assertSame('failed', $timedOutOrder->payments()->first()->fresh()->payment_status->value);
        // This is exactly what the old where()->orWhere() bug would have broken.
        $this->assertSame('processing', $unrelatedOrder->payments()->first()->fresh()->payment_status->value);
    }

    public function test_handle_payment_timeout_cancels_order_and_releases_reservation(): void
    {
        $order = $this->createOrder(paymentOverrides: [
            'payment_status' => MarketplacePaymentStatus::Pending,
        ]);

        $this->service()->handlePaymentTimeout($order);

        $fresh = $order->fresh();
        $this->assertSame('cancelled', $fresh->order_status->value);
        $this->assertSame('released', $fresh->reservation_status->value);
        $this->assertSame('failed', $order->payments()->first()->fresh()->payment_status->value);
        Queue::assertPushed(ProcessOrderCancellation::class, fn ($job) => $job->orderId === $order->id);
    }

    public function test_handle_payment_timeout_is_noop_when_order_already_terminal(): void
    {
        $order = $this->createOrder([
            'order_status' => OrderStatus::Cancelled,
        ], paymentOverrides: ['payment_status' => MarketplacePaymentStatus::Pending]);

        $this->service()->handlePaymentTimeout($order);

        $this->assertSame('pending', $order->payments()->first()->fresh()->payment_status->value);
        Queue::assertNotPushed(ProcessOrderCancellation::class);
    }

    public function test_handle_payment_timeout_is_noop_when_already_paid(): void
    {
        $order = $this->createOrder(paymentOverrides: ['payment_status' => MarketplacePaymentStatus::Completed]);

        $this->service()->handlePaymentTimeout($order);

        $this->assertNotSame('cancelled', $order->fresh()->order_status->value);
        Queue::assertNotPushed(ProcessOrderCancellation::class);
    }

    // =========================================================================
    // getPaymentStatus()
    // =========================================================================

    public function test_get_payment_status_returns_latest_payment(): void
    {
        $order = $this->createOrder();

        $status = $this->service()->getPaymentStatus($order);

        $this->assertSame($order->payments()->first()->id, $status->id);
    }

    // =========================================================================
    // Controller boundary
    // =========================================================================

    public function test_stk_callback_always_returns_success_to_safaricom_even_on_failure(): void
    {
        $mpesaMock = Mockery::mock(MpesaService::class);
        $mpesaMock->shouldReceive('parseSTKCallbackPayload')->andReturn(['transaction_reference' => 'whatever']);

        $paymentServiceMock = Mockery::mock(MarketplacePaymentService::class);
        $paymentServiceMock->shouldReceive('processSTKCallback')->andThrow(new \RuntimeException('boom'));
        app()->instance(MarketplacePaymentService::class, $paymentServiceMock);

        $controller = new MpesaC2BController($mpesaMock, Mockery::mock(MpesaC2BRouterService::class));
        $response = $controller->stkCallback(Request::create('/', 'POST', []));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(0, json_decode($response->getContent(), true)['ResultCode']);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function service(?MpesaService $mpesaMock = null): MarketplacePaymentService
    {
        return new MarketplacePaymentService(
            $mpesaMock ?? Mockery::mock(MpesaService::class),
            Mockery::mock(MarketplaceProductService::class),
        );
    }

    private function createOrder(array $orderOverrides = [], ?array $paymentOverrides = [], bool $withPayment = true): MarketplaceOrder
    {
        $order = MarketplaceOrder::on('central')->create(array_merge([
            'order_number' => 'ORD-'.uniqid(),
            'customer_id' => $this->customer->id,
            'tenant_id' => $this->tenantId,
            'merchant_name' => 'Test Merchant',
            'subtotal' => 500.0,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'delivery_fee' => 0,
            'total_amount' => 500.0,
            'fulfillment_type' => 'pickup',
            'order_status' => OrderStatus::Pending,
            'reservation_status' => ReservationStatus::Confirmed,
            'payment_deadline_at' => now()->addMinutes(30),
        ], $orderOverrides));

        if ($withPayment) {
            MarketplaceOrderPayment::on('central')->create(array_merge([
                'order_id' => $order->id,
                'payment_method' => 'mpesa',
                'amount' => $order->total_amount,
                'payment_status' => MarketplacePaymentStatus::Pending,
            ], $paymentOverrides ?? []));
        }

        return $order->fresh();
    }
}
