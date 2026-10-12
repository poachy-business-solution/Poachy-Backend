<?php

namespace Tests\Feature\Payment;

use App\Exceptions\CardPaymentException;
use App\Exceptions\MpesaException;
use App\Services\Shared\Card\CardGateway;
use App\Services\Shared\Card\Gateways\FakeCardGateway;
use App\Services\Shared\Card\Gateways\PaystackCardGateway;
use App\Services\Shared\Mpesa\Gateways\DarajaMpesaGateway;
use App\Services\Shared\Mpesa\Gateways\FakeMpesaGateway;
use App\Services\Shared\Mpesa\MpesaGateway;
use App\Services\Shared\Mpesa\MpesaService;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * M-Pesa and Paystack gateways: driver selection, the fake drivers (no network, ever),
 * the real drivers' request shapes (Http::fake), and the simulate commands that deliver
 * provider callbacks to our own routes.
 */
class PaymentGatewaysTest extends TestCase
{
    /** @var array<int, array{path: string, body: array<string, mixed>, raw: string, signature: string|null}> */
    private array $received = [];

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        Config::set('mpesa.environment', 'sandbox');
        Config::set('mpesa.sandbox.shortcode', '174379');

        foreach (['stk-test', 'c2b-validate-test', 'c2b-confirm-test', 'b2c-result-test', 'paystack-test'] as $path) {
            Route::post("/_test/{$path}", function (Request $request) use ($path) {
                $this->received[] = [
                    'path' => $path,
                    'body' => $request->json()->all(),
                    'raw' => $request->getContent(),
                    'signature' => $request->header('X-Paystack-Signature'),
                ];

                return $path === 'c2b-validate-test'
                    ? response()->json(['ResultCode' => $request->json('BillRefNumber') === 'POA00001' ? '0' : 'C2B00012', 'ResultDesc' => 'x'])
                    : response()->json(['ok' => true]);
            });
        }
    }

    /** Unique per run: the fake store lives in Redis and outlives a single test. */
    private function ref(string $name): string
    {
        static $suffix = null;
        $suffix ??= uniqid();

        return "{$name}-{$suffix}";
    }

    // ---- driver selection ------------------------------------------------------

    public function test_fake_drivers_are_the_default(): void
    {
        $this->assertInstanceOf(FakeMpesaGateway::class, app(MpesaGateway::class));
        $this->assertInstanceOf(FakeCardGateway::class, app(CardGateway::class));
    }

    public function test_real_drivers_are_used_only_when_configured(): void
    {
        Config::set('mpesa.driver', 'daraja');
        Config::set('paystack.driver', 'paystack');

        $this->assertInstanceOf(DarajaMpesaGateway::class, app(MpesaGateway::class));
        $this->assertInstanceOf(PaystackCardGateway::class, app(CardGateway::class));
    }

    // ---- fake M-Pesa -----------------------------------------------------------

    public function test_mpesa_service_stk_push_goes_through_the_fake_without_network(): void
    {
        $result = app(MpesaService::class)->initiateSTKPush('0712 345 678', 99.4, 'POA00001-LONGREF', 'Top up', 'http://localhost/_test/stk-test');

        $this->assertStringStartsWith('ws_CO_FAKE_', $result['checkout_request_id']);
        $stored = FakeMpesaGateway::find('stk', $result['checkout_request_id']);
        $this->assertSame('254712345678', $stored['phone']);
        $this->assertSame(100, $stored['amount']); // rounded up like Daraja
        $this->assertSame('POA00001-LON', $stored['account_reference']); // 12-char Daraja limit
        $this->assertSame($result['checkout_request_id'], FakeMpesaGateway::latestId('stk'));
        Http::assertNothingSent();
    }

    public function test_fake_stk_fails_for_the_test_phone(): void
    {
        $this->expectException(MpesaException::class);

        app(MpesaService::class)->initiateSTKPush('0700999999', 100, 'REF', 'Pay', 'http://localhost/_test/stk-test');
    }

    public function test_fake_stk_status_reports_pending_then_the_simulated_result(): void
    {
        $gateway = new FakeMpesaGateway;
        $id = $gateway->stkPush('254712345678', 100, 'REF', 'Pay', 'http://localhost/_test/stk-test')['checkout_request_id'];

        $this->assertNull($gateway->stkStatus($id)['result_code']);

        $this->artisan('poachy:simulate-mpesa', ['type' => 'stk', 'id' => $id, '--fail' => true])->assertSuccessful();

        $this->assertSame(1032, $gateway->stkStatus($id)['result_code']);
    }

    public function test_simulated_stk_success_is_a_callback_our_parser_accepts(): void
    {
        $id = app(MpesaService::class)->initiateSTKPush('0712345678', 250, 'REF', 'Pay', 'http://localhost/_test/stk-test')['checkout_request_id'];

        $this->artisan('poachy:simulate-mpesa', ['type' => 'stk', '--latest' => true])->assertSuccessful();

        $this->assertCount(1, $this->received);
        $parsed = app(MpesaService::class)->parseSTKCallbackPayload($this->received[0]['body']);
        $this->assertSame($id, $parsed['transaction_reference']);
        $this->assertSame('success', $parsed['status']);
        $this->assertStringStartsWith('FK', $parsed['provider_reference']);
    }

    public function test_simulated_stk_failure_is_parsed_as_failed(): void
    {
        app(MpesaService::class)->initiateSTKPush('0712345678', 250, 'REF', 'Pay', 'http://localhost/_test/stk-test');

        $this->artisan('poachy:simulate-mpesa', ['type' => 'stk', '--latest' => true, '--fail' => true, '--code' => '1037'])->assertSuccessful();

        $parsed = app(MpesaService::class)->parseSTKCallbackPayload($this->received[0]['body']);
        $this->assertSame('failed', $parsed['status']);
        $this->assertSame('1037', $parsed['failure_code']);
    }

    public function test_simulated_c2b_validates_then_confirms(): void
    {
        Config::set('mpesa.c2b_validation_url', 'http://localhost/_test/c2b-validate-test');
        Config::set('mpesa.c2b_confirmation_url', 'http://localhost/_test/c2b-confirm-test');

        $this->artisan('poachy:simulate-mpesa', ['type' => 'c2b', '--account' => 'POA00001', '--amount' => '500', '--phone' => '0712345678'])->assertSuccessful();

        $this->assertSame(['c2b-validate-test', 'c2b-confirm-test'], array_column($this->received, 'path'));
        $parsed = app(MpesaService::class)->parseC2BPayload($this->received[1]['body']);
        $this->assertSame('POA00001', $parsed['bill_ref_number']);
        $this->assertSame(500.0, $parsed['amount']);
        $this->assertSame('254712345678', $parsed['phone']);
        $this->assertSame('0.00', $parsed['org_account_balance']);
    }

    public function test_simulated_c2b_stops_when_validation_rejects(): void
    {
        Config::set('mpesa.c2b_validation_url', 'http://localhost/_test/c2b-validate-test');
        Config::set('mpesa.c2b_confirmation_url', 'http://localhost/_test/c2b-confirm-test');

        $this->artisan('poachy:simulate-mpesa', ['type' => 'c2b', '--account' => 'NOPE', '--amount' => '500'])->assertSuccessful();

        $this->assertSame(['c2b-validate-test'], array_column($this->received, 'path'));
    }

    public function test_b2c_is_recorded_and_its_result_delivered(): void
    {
        $sent = (new FakeMpesaGateway)->b2c('254712345678', 1499.9, 'Payout 12', 'Daily payout', 'http://localhost/_test/b2c-result-test', 'http://localhost/_test/b2c-result-test');

        $this->artisan('poachy:simulate-mpesa', ['type' => 'b2c', 'id' => $sent['conversation_id']])->assertSuccessful();

        $result = $this->received[0]['body']['Result'];
        $this->assertSame(0, $result['ResultCode']);
        $this->assertSame($sent['conversation_id'], $result['ConversationID']);
        $amount = collect($result['ResultParameters']['ResultParameter'])->firstWhere('Key', 'TransactionAmount')['Value'];
        $this->assertSame(1499, $amount); // payouts round down
    }

    public function test_reversal_failure_result_is_delivered(): void
    {
        (new FakeMpesaGateway)->reverse('FK12345678', 300, 'Refund', 'http://localhost/_test/b2c-result-test', 'http://localhost/_test/b2c-result-test');

        $this->artisan('poachy:simulate-mpesa', ['type' => 'reversal', '--latest' => true, '--fail' => true])->assertSuccessful();

        $this->assertSame(2001, $this->received[0]['body']['Result']['ResultCode']);
    }

    public function test_simulate_mpesa_refuses_to_run_against_the_real_driver(): void
    {
        Config::set('mpesa.driver', 'daraja');

        $this->artisan('poachy:simulate-mpesa', ['type' => 'stk', '--latest' => true])->assertFailed();
    }

    // ---- real Daraja driver (request shapes only, Http::fake) ------------------

    public function test_daraja_b2c_reversal_and_status_send_the_documented_requests(): void
    {
        Config::set('mpesa.sandbox.initiator_name', 'apitest');
        Config::set('mpesa.sandbox.security_credential', 'cred');
        Http::fake([
            '*/oauth/v1/generate*' => Http::response(['access_token' => 't']),
            '*/b2c/v3/paymentrequest' => Http::response(['ConversationID' => 'AG_1', 'OriginatorConversationID' => 'O_1', 'ResponseCode' => '0']),
            '*/reversal/v1/request' => Http::response(['ConversationID' => 'AG_2', 'OriginatorConversationID' => 'O_2', 'ResponseCode' => '0']),
            '*/stkpushquery/v1/query' => Http::response(['ResultCode' => '1032', 'ResultDesc' => 'Cancelled']),
        ]);
        $gateway = new DarajaMpesaGateway;

        $this->assertSame('AG_1', $gateway->b2c('254712345678', 1000.7, 'Payout', 'Daily payout', 'https://x/r', 'https://x/t')['conversation_id']);
        $this->assertSame('AG_2', $gateway->reverse('QK1', 50, 'Refund', 'https://x/r', 'https://x/t')['conversation_id']);
        $this->assertSame(['result_code' => 1032, 'result_desc' => 'Cancelled'], $gateway->stkStatus('ws_CO_1'));

        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/b2c/v3/paymentrequest')
            && $r['CommandID'] === 'BusinessPayment' && $r['Amount'] === 1000 && $r['PartyB'] === '254712345678' && $r['InitiatorName'] === 'apitest');
        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/reversal/v1/request')
            && $r['CommandID'] === 'TransactionReversal' && $r['TransactionID'] === 'QK1' && $r['ReceiverParty'] === '174379');
    }

    // ---- fake Paystack ---------------------------------------------------------

    public function test_fake_card_initialize_verify_and_refund_rules(): void
    {
        $card = app(CardGateway::class);

        $init = $card->initialize('buyer@example.com', 1500, $this->ref('ORD-1'), 'http://localhost/back');
        $this->assertSame($this->ref('ORD-1'), $init['reference']);
        $this->assertStringStartsWith('https://checkout.paystack.test/', $init['authorization_url']);
        $this->assertSame('pending', $card->verify($this->ref('ORD-1'))['status']);

        try {
            $card->refund($this->ref('ORD-1'));
            $this->fail('Refunding an unpaid transaction must fail.');
        } catch (CardPaymentException) {
        }

        $this->artisan('poachy:simulate-paystack', ['reference' => $this->ref('ORD-1'), '--no-webhook' => true])->assertSuccessful();
        $this->assertSame('success', $card->verify($this->ref('ORD-1'))['status']);

        $this->assertSame(500.0, $card->refund($this->ref('ORD-1'), 500, 'one item')['amount']);
        $this->assertSame(1000.0, $card->refund($this->ref('ORD-1'))['amount']); // the rest

        $this->expectException(CardPaymentException::class);
        $card->refund($this->ref('ORD-1'), 1);
    }

    public function test_fake_card_rejects_duplicate_references_and_the_test_email(): void
    {
        $card = app(CardGateway::class);
        $card->initialize('a@example.com', 10, $this->ref('DUP'), 'http://localhost/back');

        try {
            $card->initialize('a@example.com', 10, $this->ref('DUP'), 'http://localhost/back');
            $this->fail('Duplicate reference must fail.');
        } catch (CardPaymentException) {
        }

        $this->expectException(CardPaymentException::class);
        $card->initialize('fail@example.com', 10, 'X', 'http://localhost/back');
    }

    public function test_simulated_paystack_webhook_is_signed_and_verifiable(): void
    {
        Config::set('paystack.webhook_url', 'http://localhost/_test/paystack-test');
        $card = app(CardGateway::class);
        $card->initialize('buyer@example.com', 1234.5, $this->ref('ORD-9'), 'http://localhost/back', ['order_id' => 9]);

        $this->artisan('poachy:simulate-paystack', ['--latest' => true])->assertSuccessful();

        $hit = $this->received[0];
        $this->assertTrue($card->verifyWebhookSignature($hit['raw'], $hit['signature']));
        $this->assertFalse($card->verifyWebhookSignature($hit['raw'].' ', $hit['signature']));
        $this->assertFalse($card->verifyWebhookSignature($hit['raw'], null));
        $this->assertSame('charge.success', $hit['body']['event']);
        $this->assertSame(123450, $hit['body']['data']['amount']); // subunits
        $this->assertSame(9, $hit['body']['data']['metadata']['order_id']);
    }

    public function test_simulated_paystack_decline_sends_charge_failed(): void
    {
        Config::set('paystack.webhook_url', 'http://localhost/_test/paystack-test');
        app(CardGateway::class)->initialize('buyer@example.com', 100, $this->ref('ORD-F'), 'http://localhost/back');

        $this->artisan('poachy:simulate-paystack', ['reference' => $this->ref('ORD-F'), '--fail' => true])->assertSuccessful();

        $this->assertSame('charge.failed', $this->received[0]['body']['event']);
        $this->assertSame('failed', app(CardGateway::class)->verify($this->ref('ORD-F'))['status']);
    }

    // ---- real Paystack driver (request shapes only, Http::fake) ----------------

    public function test_paystack_driver_converts_to_subunits_and_checks_signatures(): void
    {
        Config::set('paystack.secret_key', 'sk_test_x');
        Http::fake([
            'api.paystack.co/transaction/initialize' => Http::response(['status' => true, 'data' => ['authorization_url' => 'https://checkout.paystack.com/abc', 'access_code' => 'abc', 'reference' => 'R1']]),
            'api.paystack.co/transaction/verify/R1' => Http::response(['status' => true, 'data' => ['reference' => 'R1', 'status' => 'success', 'amount' => 150050, 'currency' => 'KES', 'paid_at' => '2026-10-12T10:00:00Z', 'channel' => 'card', 'gateway_response' => 'Successful']]),
            'api.paystack.co/refund' => Http::response(['status' => true, 'data' => ['id' => 77, 'status' => 'pending', 'amount' => 50000]]),
        ]);
        $gateway = new PaystackCardGateway;

        $this->assertSame('abc', $gateway->initialize('b@example.com', 1500.5, 'R1', 'https://poachy.test/back')['access_code']);
        $this->assertSame(1500.5, $gateway->verify('R1')['amount']);
        $this->assertSame('77', $gateway->refund('R1', 500)['refund_id']);

        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/transaction/initialize') && $r['amount'] === 150050 && $r['currency'] === 'KES'
            && $r->hasHeader('Authorization', 'Bearer sk_test_x'));
        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/refund') && $r['amount'] === 50000 && $r['transaction'] === 'R1');

        $body = '{"event":"charge.success"}';
        $this->assertTrue($gateway->verifyWebhookSignature($body, hash_hmac('sha512', $body, 'sk_test_x')));
        $this->assertFalse($gateway->verifyWebhookSignature($body, hash_hmac('sha512', $body, 'other')));
    }

    public function test_paystack_driver_surfaces_api_errors(): void
    {
        Http::fake(['api.paystack.co/*' => Http::response(['status' => false, 'message' => 'Invalid key'], 401)]);

        $this->expectException(CardPaymentException::class);
        $this->expectExceptionMessage('Invalid key');

        (new PaystackCardGateway)->verify('R1');
    }
}
