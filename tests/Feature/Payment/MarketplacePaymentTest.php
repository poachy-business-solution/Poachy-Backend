<?php

namespace Tests\Feature\Payment;

use App\Enums\Central\MarketplacePaymentMethod;
use App\Exceptions\MpesaException;
use App\Services\Shared\Mpesa\MpesaService;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class MarketplacePaymentTest extends TestCase
{
    // =========================================================================
    // MarketplacePaymentMethod enum
    // =========================================================================

    public function test_mpesa_paybill_enum_case_exists(): void
    {
        $this->assertNotNull(MarketplacePaymentMethod::MpesaPaybill);
        $this->assertSame('mpesa_paybill', MarketplacePaymentMethod::MpesaPaybill->value);
    }

    public function test_mpesa_paybill_label_is_human_readable(): void
    {
        $this->assertSame('M-Pesa Paybill', MarketplacePaymentMethod::MpesaPaybill->label());
    }

    public function test_mpesa_paybill_does_not_require_online_payment(): void
    {
        // Paybill doesn't trigger an STK push — no online initiation needed from our side
        $this->assertFalse(MarketplacePaymentMethod::MpesaPaybill->requiresOnlinePayment());
    }

    public function test_mpesa_stk_requires_online_payment(): void
    {
        $this->assertTrue(MarketplacePaymentMethod::Mpesa->requiresOnlinePayment());
    }

    public function test_mpesa_paybill_is_paybill(): void
    {
        $this->assertTrue(MarketplacePaymentMethod::MpesaPaybill->isPaybill());
        $this->assertFalse(MarketplacePaymentMethod::Mpesa->isPaybill());
        $this->assertFalse(MarketplacePaymentMethod::CashOnDelivery->isPaybill());
    }

    public function test_all_payment_method_values_are_unique(): void
    {
        $values = MarketplacePaymentMethod::values();
        $this->assertCount(count($values), array_unique($values));
    }

    // =========================================================================
    // MpesaException error codes
    // =========================================================================

    public function test_mpesa_exception_stk_push_failed_factory(): void
    {
        $e = MpesaException::stkPushFailed('STK push failed', ['order_id' => 1]);

        $this->assertInstanceOf(MpesaException::class, $e);
        $this->assertSame('STK_PUSH_FAILED', $e->darajaErrorCode);
        $this->assertSame(['order_id' => 1], $e->context);
    }

    public function test_mpesa_exception_oauth_failed_factory(): void
    {
        $e = MpesaException::oauthFailed('{"error":"invalid_client"}');

        $this->assertSame('OAUTH_FAILED', $e->darajaErrorCode);
        $this->assertSame('Failed to obtain M-Pesa access token.', $e->getMessage());
    }

    public function test_mpesa_exception_c2b_registration_failed_factory(): void
    {
        $e = MpesaException::c2bRegistrationFailed('ShortCode not found');

        $this->assertSame('C2B_REGISTRATION_FAILED', $e->darajaErrorCode);
        $this->assertStringContainsString('ShortCode not found', $e->getMessage());
    }

    // =========================================================================
    // MpesaService — dual credential config
    // =========================================================================

    public function test_mpesa_service_uses_sandbox_credentials_in_sandbox_mode(): void
    {
        Config::set('mpesa.environment', 'sandbox');
        Config::set('mpesa.sandbox.consumer_key', 'sandbox-key');
        Config::set('mpesa.sandbox.shortcode', '174379');

        $service = new MpesaService();
        $creds   = $service->getActiveCredentials();

        $this->assertSame('sandbox-key', $creds['consumer_key']);
        $this->assertSame('174379', $creds['shortcode']);
    }

    public function test_mpesa_service_uses_production_credentials_in_production_mode(): void
    {
        Config::set('mpesa.environment', 'production');
        Config::set('mpesa.production.consumer_key', 'prod-key');
        Config::set('mpesa.production.shortcode', '888888');

        $service = new MpesaService();
        $creds   = $service->getActiveCredentials();

        $this->assertSame('prod-key', $creds['consumer_key']);
        $this->assertSame('888888', $creds['shortcode']);
    }
}
