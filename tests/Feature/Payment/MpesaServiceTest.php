<?php

namespace Tests\Feature\Payment;

use App\Exceptions\MpesaException;
use App\Services\Shared\Mpesa\MpesaService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MpesaServiceTest extends TestCase
{
    private MpesaService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new MpesaService();

        Config::set('mpesa.environment', 'sandbox');
        Config::set('mpesa.sandbox.base_url', 'https://sandbox.safaricom.co.ke');
        Config::set('mpesa.sandbox.consumer_key', 'test-key');
        Config::set('mpesa.sandbox.consumer_secret', 'test-secret');
        Config::set('mpesa.sandbox.shortcode', '174379');
        Config::set('mpesa.sandbox.passkey', 'test-passkey');
        Config::set('mpesa.stk_callback_url', 'https://example.com/stk-callback');
        Config::set('mpesa.c2b_validation_url', 'https://example.com/c2b/validate');
        Config::set('mpesa.c2b_confirmation_url', 'https://example.com/c2b/confirm');
    }

    // =========================================================================
    // getActiveCredentials()
    // =========================================================================

    public function test_get_active_credentials_returns_sandbox_config(): void
    {
        $creds = $this->service->getActiveCredentials();

        $this->assertSame('test-key', $creds['consumer_key']);
        $this->assertSame('174379', $creds['shortcode']);
    }

    public function test_get_active_credentials_returns_production_config_in_production(): void
    {
        Config::set('mpesa.environment', 'production');
        Config::set('mpesa.production.consumer_key', 'prod-key');
        Config::set('mpesa.production.shortcode', '999999');

        $creds = $this->service->getActiveCredentials();

        $this->assertSame('prod-key', $creds['consumer_key']);
        $this->assertSame('999999', $creds['shortcode']);
    }

    // =========================================================================
    // getAccessToken() — caching
    // =========================================================================

    public function test_access_token_is_fetched_and_cached(): void
    {
        Cache::forget('mpesa_access_token_sandbox');

        Http::fake([
            '*/oauth/v1/generate*' => Http::response(['access_token' => 'tok-123'], 200),
            '*/mpesa/stkpush/*'    => Http::response(['CheckoutRequestID' => 'ws_CO_1', 'MerchantRequestID' => 'MR-1'], 200),
        ]);

        $this->service->initiateSTKPush('0712345678', 100, 'REF', 'Pay');
        $this->service->initiateSTKPush('0712345678', 200, 'REF2', 'Pay2');

        // 1 OAuth call + 2 STK calls = 3 total
        Http::assertSentCount(3);
    }

    public function test_access_token_throws_on_oauth_failure(): void
    {
        Cache::forget('mpesa_access_token_sandbox');

        Http::fake([
            '*/oauth/v1/generate*' => Http::response(['error' => 'invalid_client'], 401),
        ]);

        $this->expectException(MpesaException::class);
        $this->expectExceptionMessage('Failed to obtain M-Pesa access token.');

        $this->service->initiateSTKPush('0712345678', 100, 'REF', 'Pay');
    }

    // =========================================================================
    // initiateSTKPush()
    // =========================================================================

    public function test_stk_push_returns_checkout_and_merchant_ids(): void
    {
        Cache::forget('mpesa_access_token_sandbox');

        Http::fake([
            '*/oauth/v1/generate*' => Http::response(['access_token' => 'tok'], 200),
            '*/mpesa/stkpush/*'    => Http::response([
                'CheckoutRequestID'  => 'ws_CO_TEST_123',
                'MerchantRequestID'  => 'MR-TEST-456',
                'ResponseCode'       => '0',
                'ResponseDescription' => 'Success',
            ], 200),
        ]);

        $result = $this->service->initiateSTKPush('0712345678', 1000.0, 'TEST-REF', 'Test Pay');

        $this->assertSame('ws_CO_TEST_123', $result['checkout_request_id']);
        $this->assertSame('MR-TEST-456', $result['merchant_request_id']);
    }

    public function test_stk_push_throws_mpesa_exception_on_daraja_error(): void
    {
        Cache::forget('mpesa_access_token_sandbox');

        Http::fake([
            '*/oauth/v1/generate*' => Http::response(['access_token' => 'tok'], 200),
            '*/mpesa/stkpush/*'    => Http::response([
                'errorCode'    => '500.001.1001',
                'errorMessage' => 'Invalid Access Token',
            ], 400),
        ]);

        $this->expectException(MpesaException::class);
        $this->expectExceptionMessage('Invalid Access Token');

        $this->service->initiateSTKPush('0712345678', 1000.0, 'REF', 'Pay');
    }

    public function test_stk_push_uses_custom_callback_url(): void
    {
        Cache::forget('mpesa_access_token_sandbox');

        Http::fake([
            '*/oauth/v1/generate*' => Http::response(['access_token' => 'tok'], 200),
            '*/mpesa/stkpush/*'    => Http::response(['CheckoutRequestID' => 'ws_X', 'MerchantRequestID' => 'MR-X'], 200),
        ]);

        $this->service->initiateSTKPush('0712345678', 500, 'REF', 'Pay', 'https://custom.example.com/cb');

        Http::assertSent(fn($req) => str_contains($req->url(), 'stkpush')
            && $req['CallBackURL'] === 'https://custom.example.com/cb');
    }

    public function test_stk_push_normalizes_phone_number(): void
    {
        Cache::forget('mpesa_access_token_sandbox');

        Http::fake([
            '*/oauth/v1/generate*' => Http::response(['access_token' => 'tok'], 200),
            '*/mpesa/stkpush/*'    => Http::response(['CheckoutRequestID' => 'ws_X', 'MerchantRequestID' => 'MR-X'], 200),
        ]);

        $this->service->initiateSTKPush('+254 712 345 678', 100, 'REF', 'Pay');

        Http::assertSent(fn($req) => str_contains($req->url(), 'stkpush')
            && $req['PhoneNumber'] === '254712345678');
    }

    // =========================================================================
    // registerC2BUrls()
    // =========================================================================

    public function test_register_c2b_urls_succeeds(): void
    {
        Cache::forget('mpesa_access_token_sandbox');

        Http::fake([
            '*/oauth/v1/generate*'          => Http::response(['access_token' => 'tok'], 200),
            '*/mpesa/c2b/v1/registerurl*'   => Http::response([
                'ResponseCode'        => '0',
                'ResponseDescription' => 'Success',
                'CustomerMessage'     => 'Success',
            ], 200),
        ]);

        $result = $this->service->registerC2BUrls(
            'https://example.com/validate',
            'https://example.com/confirm',
        );

        $this->assertSame('0', $result['ResponseCode']);
    }

    public function test_register_c2b_urls_throws_on_failure(): void
    {
        Cache::forget('mpesa_access_token_sandbox');

        Http::fake([
            '*/oauth/v1/generate*'         => Http::response(['access_token' => 'tok'], 200),
            '*/mpesa/c2b/v1/registerurl*'  => Http::response([
                'errorCode'    => '400.002.02',
                'errorMessage' => 'Bad request',
            ], 400),
        ]);

        $this->expectException(MpesaException::class);

        $this->service->registerC2BUrls('https://example.com/v', 'https://example.com/c');
    }

    // =========================================================================
    // parseSTKCallbackPayload()
    // =========================================================================

    public function test_parse_stk_callback_success(): void
    {
        $payload = [
            'Body' => [
                'stkCallback' => [
                    'CheckoutRequestID' => 'ws_CO_123',
                    'ResultCode'        => 0,
                    'ResultDesc'        => 'Success',
                    'CallbackMetadata'  => [
                        'Item' => [
                            ['Name' => 'Amount',             'Value' => 1000.0],
                            ['Name' => 'MpesaReceiptNumber', 'Value' => 'LHG31AA5TX'],
                            ['Name' => 'TransactionDate',    'Value' => 20260620120000],
                            ['Name' => 'PhoneNumber',        'Value' => 254712345678],
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->service->parseSTKCallbackPayload($payload);

        $this->assertSame('ws_CO_123', $result['transaction_reference']);
        $this->assertSame('success', $result['status']);
        $this->assertSame('LHG31AA5TX', $result['provider_reference']);
        $this->assertNull($result['failure_reason']);
    }

    public function test_parse_stk_callback_failure(): void
    {
        $payload = [
            'Body' => [
                'stkCallback' => [
                    'CheckoutRequestID' => 'ws_CO_456',
                    'ResultCode'        => 1032,
                    'ResultDesc'        => 'Request cancelled by user',
                ],
            ],
        ];

        $result = $this->service->parseSTKCallbackPayload($payload);

        $this->assertSame('failed', $result['status']);
        $this->assertSame('Request cancelled by user', $result['failure_reason']);
        $this->assertSame('1032', $result['failure_code']);
        $this->assertNull($result['provider_reference']);
    }

    // =========================================================================
    // parseC2BPayload()
    // =========================================================================

    public function test_parse_c2b_payload_extracts_all_fields(): void
    {
        $payload = [
            'TransactionType'    => 'Pay Bill',
            'TransID'            => 'LHG31AA5TX',
            'TransTime'          => '20260620120000',
            'TransAmount'        => '2500.00',
            'BusinessShortCode'  => '174379',
            'BillRefNumber'      => 'POA00001',
            'MSISDN'             => '254712345678',
            'FirstName'          => 'John',
            'LastName'           => 'Doe',
            'OrgAccountBalance'  => '100000.00',
        ];

        $result = $this->service->parseC2BPayload($payload);

        $this->assertSame('LHG31AA5TX', $result['transaction_id']);
        $this->assertSame(2500.0, $result['amount']);
        $this->assertSame('POA00001', $result['bill_ref_number']);
        $this->assertSame('254712345678', $result['phone']);
        $this->assertSame('John', $result['first_name']);
        $this->assertSame('100000.00', $result['org_account_balance']);
    }

    // =========================================================================
    // buildC2BValidationResponse() / buildC2BConfirmationResponse()
    // =========================================================================

    public function test_build_c2b_validation_response_accept(): void
    {
        $response = $this->service->buildC2BValidationResponse(true);
        $this->assertSame('0', $response['ResultCode']);
        $this->assertSame('Accepted', $response['ResultDesc']);
    }

    public function test_build_c2b_validation_response_reject(): void
    {
        $response = $this->service->buildC2BValidationResponse(false, 'Invalid Account');
        $this->assertSame('C2B00011', $response['ResultCode']);
        $this->assertSame('Invalid Account', $response['ResultDesc']);
    }

    public function test_build_c2b_confirmation_response(): void
    {
        $response = $this->service->buildC2BConfirmationResponse();
        $this->assertSame(0, $response['ResultCode']);
        $this->assertSame('Success', $response['ResultDesc']);
    }
}
