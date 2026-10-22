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
use App\Mail\Central\Marketplace\MarketplaceOrderLifecycleMail;
use App\Models\CentralPaymentLog;
use App\Models\MarketplaceCustomer;
use App\Models\MarketplaceOrder;
use App\Models\MarketplaceOrderPayment;
use App\Models\MarketplaceRefund;
use App\Models\UnmatchedMpesaPayment;
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
use Illuminate\Support\Facades\Mail;
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
        MarketplaceRefund::query()->whereIn('order_id', $orderIds)->delete();
        UnmatchedMpesaPayment::query()->where('transaction_id', 'like', 'T-%')->delete();
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
        $order = $this->createOrder(paymentOverrides: ['payment_method' => 'bank_transfer']);

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

        // Recorded as received, and the money really goes back (an M-Pesa reversal of that receipt).
        $payment = $order->payments()->first()->fresh();
        $this->assertSame(['completed', 'MPESA-REC-2'], [$payment->payment_status->value, $payment->provider_reference]);
        $refund = MarketplaceRefund::query()->where('order_id', $order->id)->sole();
        $this->assertSame(['late_payment', 'mpesa_reversal', 500.0], [$refund->kind, $refund->method, (float) $refund->amount]);
        $this->assertNotNull($refund->conversation_id);
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
        $this->assertSame(['completed', 'C2B-TXN-TERMINAL'], [$payment->payment_status->value, $payment->provider_reference]);
        $this->assertSame('late_payment', MarketplaceRefund::query()->where('order_id', $order->id)->sole()->kind);
    }

    // =========================================================================
    // confirmPayment() / handlePaymentFailure() / initiateRefund()
    // =========================================================================

    public function test_confirm_payment_fires_payment_completed_and_dispatches_job(): void
    {
        Mail::fake();
        $order = $this->createOrder();
        $payment = $order->payments()->first();

        $this->service()->confirmPayment($payment, 'TXN-1', 'REC-1');

        $this->assertSame('confirmed', $order->fresh()->order_status->value);
        Event::assertDispatched(PaymentCompleted::class, fn ($e) => $e->payment->id === $payment->id);
        Queue::assertPushed(ProcessPaymentConfirmation::class, fn ($job) => $job->orderId === $order->id);
        Mail::assertQueued(MarketplaceOrderLifecycleMail::class, function (MarketplaceOrderLifecycleMail $mail) use ($order) {
            return $mail->lifecycleType === 'payment_confirmed'
                && $mail->order->id === $order->id
                && $mail->hasTo($this->user->email);
        });
    }

    public function test_handle_payment_failure_fires_payment_failed(): void
    {
        Mail::fake();
        $order = $this->createOrder();
        $payment = $order->payments()->first();

        $this->service()->handlePaymentFailure($payment, 'Timeout', 'CODE1');

        $this->assertSame('failed', $payment->fresh()->payment_status->value);
        Event::assertDispatched(PaymentFailed::class, fn ($e) => $e->payment->id === $payment->id);
        Mail::assertQueued(MarketplaceOrderLifecycleMail::class, function (MarketplaceOrderLifecycleMail $mail) use ($order) {
            return $mail->lifecycleType === 'payment_failed'
                && $mail->order->id === $order->id
                && $mail->context['reason'] === 'Timeout'
                && $mail->hasTo($this->user->email);
        });
    }

    public function test_a_payment_after_the_deadline_cancelled_the_order_is_sent_back(): void
    {
        $order = $this->createOrder(paymentOverrides: ['payment_status' => MarketplacePaymentStatus::Processing, 'transaction_reference' => 'CKO-LATE']);
        $this->service()->handlePaymentTimeout($order);
        $this->assertSame('failed', $order->payments()->first()->fresh()->payment_status->value);

        $this->service()->processPaymentWebhook(['transaction_reference' => 'CKO-LATE', 'status' => 'success', 'provider_reference' => 'REC-LATE']);
        $this->service()->processPaymentWebhook(['transaction_reference' => 'CKO-LATE', 'status' => 'success', 'provider_reference' => 'REC-LATE']); // Safaricom repeats

        $this->assertSame('cancelled', $order->fresh()->order_status->value);
        $this->assertSame(1, MarketplaceRefund::query()->where('order_id', $order->id)->where('kind', 'late_payment')->count());
    }

    // =========================================================================
    // Money that matches no open order is held for finance, never lost
    // =========================================================================

    public function test_a_short_paybill_payment_does_not_confirm_the_order(): void
    {
        $order = $this->createOrder(paymentOverrides: ['payment_method' => 'mpesa_paybill']);

        $result = $this->service()->processC2BConfirmation(['bill_ref_number' => $order->order_number, 'transaction_id' => 'T-SHORT', 'amount' => 1.0, 'phone' => '254712345678', 'first_name' => 'Wanjiku']);

        $this->assertNull($result);
        $this->assertSame('pending', $order->fresh()->order_status->value);
        $this->assertSame('pending', $order->payments()->first()->fresh()->payment_status->value);
        $held = UnmatchedMpesaPayment::query()->where('transaction_id', 'T-SHORT')->sole();
        $this->assertSame(['marketplace', $order->order_number, 1.0, 'open'], [$held->source, $held->order_number, $held->amount, $held->status]);
        $this->assertStringContainsString('less than the order total', $held->note);
    }

    public function test_an_overpaid_paybill_payment_confirms_and_holds_the_extra(): void
    {
        $order = $this->createOrder(paymentOverrides: ['payment_method' => 'mpesa_paybill']);

        $this->service()->processC2BConfirmation(['bill_ref_number' => $order->order_number, 'transaction_id' => 'T-OVER', 'amount' => 650.0, 'phone' => '254712345678']);

        $this->assertSame('confirmed', $order->fresh()->order_status->value);
        $this->assertSame(150.0, UnmatchedMpesaPayment::query()->where('transaction_id', 'T-OVER-X')->sole()->amount);
    }

    public function test_an_order_with_cents_can_be_paid_in_whole_shillings(): void
    {
        $order = $this->createOrder(['total_amount' => 500.5], paymentOverrides: ['payment_method' => 'mpesa_paybill', 'amount' => 500.5]);

        $this->service()->processC2BConfirmation(['bill_ref_number' => $order->order_number, 'transaction_id' => 'T-CENTS', 'amount' => 501.0, 'phone' => '254712345678']);

        $this->assertSame('confirmed', $order->fresh()->order_status->value);
        $this->assertFalse(UnmatchedMpesaPayment::query()->where('transaction_id', 'like', 'T-CENTS%')->exists());
    }

    public function test_a_second_paybill_payment_or_an_unknown_order_number_is_held(): void
    {
        $order = $this->createOrder(paymentOverrides: ['payment_method' => 'mpesa_paybill']);
        $this->service()->processC2BConfirmation(['bill_ref_number' => $order->order_number, 'transaction_id' => 'T-FIRST', 'amount' => 500.0, 'phone' => '254712345678']);

        $this->service()->processC2BConfirmation(['bill_ref_number' => $order->order_number, 'transaction_id' => 'T-AGAIN', 'amount' => 500.0, 'phone' => '254712345678']);
        $this->service()->processC2BConfirmation(['bill_ref_number' => 'MKT-TYPO', 'transaction_id' => 'T-TYPO', 'amount' => 300.0, 'phone' => '254712345678']);
        $this->service()->processC2BConfirmation(['bill_ref_number' => 'MKT-TYPO', 'transaction_id' => 'T-TYPO', 'amount' => 300.0, 'phone' => '254712345678']); // repeated

        $this->assertSame('Order was already paid', UnmatchedMpesaPayment::query()->where('transaction_id', 'T-AGAIN')->sole()->note);
        $this->assertSame(1, UnmatchedMpesaPayment::query()->where('transaction_id', 'T-TYPO')->count());
        $this->assertSame('T-FIRST', $order->payments()->first()->fresh()->provider_reference);
    }

    public function test_approving_an_earlier_stk_prompt_still_pays_the_order(): void
    {
        $order = $this->createOrder();
        $mpesa = Mockery::mock(MpesaService::class);
        $mpesa->shouldReceive('initiateSTKPush')->twice()->andReturn(
            ['checkout_request_id' => 'CKO-FIRST', 'merchant_request_id' => 'M1'],
            ['checkout_request_id' => 'CKO-SECOND', 'merchant_request_id' => 'M2'],
        );
        $service = $this->service($mpesa);
        $service->initiatePayment($order, ['phone_number' => '254712345678']);
        $order->payments()->first()->update(['initiated_at' => now()->subMinutes(2)]);
        $service->initiatePayment($order->fresh(), ['phone_number' => '254712345678']);

        $service->processPaymentWebhook(['transaction_reference' => 'CKO-FIRST', 'status' => 'success', 'provider_reference' => 'T-REC-1']);
        $this->assertSame('confirmed', $order->fresh()->order_status->value);

        // The buyer approved the second prompt too: a second receipt is held to be sent back.
        $service->processPaymentWebhook(['transaction_reference' => 'CKO-SECOND', 'status' => 'success', 'provider_reference' => 'T-REC-2', 'amount' => 500.0]);
        $this->assertSame('Second payment for an order already paid', UnmatchedMpesaPayment::query()->where('transaction_id', 'T-REC-2')->sole()->note);
        $this->assertSame('T-REC-1', $order->payments()->first()->fresh()->provider_reference);
    }

    public function test_an_stk_payment_for_an_unknown_request_is_held(): void
    {
        try {
            $this->service()->processPaymentWebhook(['transaction_reference' => 'CKO-NOBODY', 'status' => 'success', 'provider_reference' => 'T-REC-NOBODY', 'amount' => 90.0]);
            $this->fail('Expected ModelNotFoundException');
        } catch (ModelNotFoundException) {
        }

        $this->assertSame(90.0, UnmatchedMpesaPayment::query()->where('transaction_id', 'T-REC-NOBODY')->sole()->amount);
    }

    public function test_the_deadline_timer_does_not_cancel_an_order_paid_while_it_ran(): void
    {
        $order = $this->createOrder(paymentOverrides: ['payment_status' => MarketplacePaymentStatus::Processing, 'transaction_reference' => 'CKO-RACE']);
        $stale = $order->fresh(); // what MonitorPaymentDeadlines loaded before the callback landed
        $this->service()->processPaymentWebhook(['transaction_reference' => 'CKO-RACE', 'status' => 'success', 'provider_reference' => 'REC-RACE']);

        $this->service()->handlePaymentTimeout($stale);

        $this->assertSame('confirmed', $order->fresh()->order_status->value);
        $this->assertSame('completed', $order->payments()->first()->fresh()->payment_status->value);
        Queue::assertNotPushed(ProcessOrderCancellation::class);
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
        Mail::fake();
        $order = $this->createOrder(paymentOverrides: [
            'payment_status' => MarketplacePaymentStatus::Pending,
        ]);

        $this->service()->handlePaymentTimeout($order);

        $fresh = $order->fresh();
        $this->assertSame('cancelled', $fresh->order_status->value);
        $this->assertSame('released', $fresh->reservation_status->value);
        $this->assertSame('failed', $order->payments()->first()->fresh()->payment_status->value);
        Queue::assertPushed(ProcessOrderCancellation::class, fn ($job) => $job->orderId === $order->id);
        Mail::assertQueued(MarketplaceOrderLifecycleMail::class, function (MarketplaceOrderLifecycleMail $mail) use ($order) {
            return $mail->lifecycleType === 'payment_timeout'
                && $mail->order->id === $order->id
                && $mail->hasTo($this->user->email);
        });
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
