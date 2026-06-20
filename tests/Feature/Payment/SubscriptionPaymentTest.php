<?php

namespace Tests\Feature\Payment;

use App\Enums\Central\SubscriptionPaymentStatus;
use App\Exceptions\MpesaException;
use App\Models\BusinessSubscription;
use App\Models\SubscriptionPayment;
use App\Models\SubscriptionPlan;
use App\Services\Central\Subscription\SubscriptionPaymentService;
use App\Services\Shared\Mpesa\MpesaService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class SubscriptionPaymentTest extends TestCase
{
    private SubscriptionPlan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('tenancy.database.central_connection', 'central');
        Config::set('database.connections.central.host', env('CENTRAL_DB_HOST', '127.0.0.1'));
        Config::set('database.connections.central.port', env('CENTRAL_DB_PORT', '3306'));
        Config::set('database.connections.central.database', env('CENTRAL_DB_DATABASE', 'poachy'));
        Config::set('database.connections.central.username', env('CENTRAL_DB_USERNAME', 'root'));
        Config::set('database.connections.central.password', env('CENTRAL_DB_PASSWORD', ''));
        Config::set('mpesa.account_prefix', 'POA');
        Config::set('mpesa.subscription_stk_callback_url', 'https://example.com/sub-stk-callback');
        DB::purge('central');
        DB::connection('central')->statement('SET foreign_key_checks = 0');

        $this->plan = SubscriptionPlan::on('central')->create([
            'name'               => 'STK Test Plan',
            'slug'               => 'stk-test-plan-' . uniqid(),
            'price'              => 1500.00,
            'billing_cycle_days' => 30,
            'is_active'          => true,
            'is_featured'        => false,
        ]);

        DB::connection('central')->table('tenants')->insertOrIgnore([
            'id'                    => 'stk-test-tenant',
            'mpesa_paybill_account' => 'POA88001',
            'created_at'            => now(),
            'updated_at'            => now(),
        ]);

        DB::connection('central')->table('business_details')->updateOrInsert(
            ['tenant_id' => 'stk-test-tenant'],
            [
                'business_name'        => 'STK Test Biz',
                'business_phone'       => '0712345678',
                'business_type_id'     => 1,
                'business_category_id' => 1,
                'status'               => 'active',
                'created_at'           => now(),
                'updated_at'           => now(),
            ],
        );
    }

    protected function tearDown(): void
    {
        DB::connection('central')->statement('SET foreign_key_checks = 0');
        SubscriptionPayment::on('central')->where('tenant_id', 'stk-test-tenant')->forceDelete();
        BusinessSubscription::on('central')->where('tenant_id', 'stk-test-tenant')->forceDelete();
        DB::connection('central')->table('business_details')->where('tenant_id', 'stk-test-tenant')->delete();
        DB::connection('central')->table('tenants')->where('id', 'stk-test-tenant')->delete();
        SubscriptionPlan::on('central')->where('id', $this->plan->id)->forceDelete();
        DB::connection('central')->statement('SET foreign_key_checks = 1');
        Mockery::close();
        parent::tearDown();
    }

    private function makeService(?MpesaService $mpesa = null): SubscriptionPaymentService
    {
        return new SubscriptionPaymentService($mpesa ?? Mockery::mock(MpesaService::class));
    }

    // =========================================================================
    // initiateSTKPayment()
    // =========================================================================

    public function test_initiate_stk_creates_processing_record(): void
    {
        $mpesa = Mockery::mock(MpesaService::class);
        $mpesa->shouldReceive('initiateSTKPush')
            ->once()
            ->andReturn(['checkout_request_id' => 'ws_CO_STK_1', 'merchant_request_id' => 'MR-STK-1']);
        $mpesa->shouldReceive('getActiveCredentials')
            ->andReturn(['shortcode' => '174379']);

        $result = $this->makeService($mpesa)->initiateSTKPayment('stk-test-tenant', $this->plan->id);

        $this->assertSame('STK push sent. Please complete payment on your phone.', $result['message']);

        $payment = $result['payment'];
        $this->assertSame(SubscriptionPaymentStatus::Processing, $payment->payment_status);
        $this->assertSame('stk', $payment->payment_type);
        $this->assertSame('ws_CO_STK_1', $payment->transaction_reference);
    }

    public function test_initiate_stk_respects_60_second_cooldown(): void
    {
        SubscriptionPayment::on('central')->create([
            'tenant_id'             => 'stk-test-tenant',
            'subscription_plan_id'  => $this->plan->id,
            'customer_phone'        => '254712345678',
            'amount'                => 1500.00,
            'payment_status'        => SubscriptionPaymentStatus::Processing,
            'payment_type'          => 'stk',
            'transaction_reference' => 'ws_CO_EXISTING',
            'initiated_at'          => now()->subSeconds(30),
        ]);

        $mpesa = Mockery::mock(MpesaService::class);
        $mpesa->shouldNotReceive('initiateSTKPush');

        $result = $this->makeService($mpesa)->initiateSTKPayment('stk-test-tenant', $this->plan->id);

        $this->assertStringContainsString('already sent', $result['message']);
        $this->assertGreaterThan(0, $result['instructions']['wait_seconds']);
    }

    public function test_initiate_stk_marks_payment_failed_when_mpesa_throws(): void
    {
        $mpesa = Mockery::mock(MpesaService::class);
        $mpesa->shouldReceive('initiateSTKPush')
            ->once()
            ->andThrow(MpesaException::stkPushFailed('STK failed'));

        $this->expectException(MpesaException::class);

        try {
            $this->makeService($mpesa)->initiateSTKPayment('stk-test-tenant', $this->plan->id);
        } catch (MpesaException $e) {
            $failed = SubscriptionPayment::on('central')
                ->where('tenant_id', 'stk-test-tenant')
                ->where('payment_status', SubscriptionPaymentStatus::Failed)
                ->first();

            $this->assertNotNull($failed);
            $this->assertSame('stk', $failed->payment_type);

            throw $e;
        }
    }

    public function test_initiate_stk_throws_for_inactive_plan(): void
    {
        $this->plan->update(['is_active' => false]);

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        $this->makeService()->initiateSTKPayment('stk-test-tenant', $this->plan->id);
    }

    // =========================================================================
    // processSTKCallback()
    // =========================================================================

    public function test_process_stk_callback_success_activates_subscription(): void
    {
        $payment = SubscriptionPayment::on('central')->create([
            'tenant_id'             => 'stk-test-tenant',
            'subscription_plan_id'  => $this->plan->id,
            'customer_phone'        => '254712345678',
            'amount'                => 1500.00,
            'payment_status'        => SubscriptionPaymentStatus::Processing,
            'payment_type'          => 'stk',
            'transaction_reference' => 'ws_CO_CALLBACK',
        ]);

        $result = $this->makeService()->processSTKCallback([
            'transaction_reference' => 'ws_CO_CALLBACK',
            'status'                => 'success',
            'provider_reference'    => 'LHG31AA5TX',
            'failure_reason'        => null,
            'failure_code'          => null,
        ]);

        $result->refresh();
        $this->assertSame(SubscriptionPaymentStatus::Completed, $result->payment_status);
        $this->assertSame('LHG31AA5TX', $result->provider_reference);
        $this->assertNotNull($result->business_subscription_id);

        $subscription = BusinessSubscription::on('central')->find($result->business_subscription_id);
        $this->assertSame('active', $subscription->status);
    }

    public function test_process_stk_callback_failure_marks_payment_failed(): void
    {
        SubscriptionPayment::on('central')->create([
            'tenant_id'             => 'stk-test-tenant',
            'subscription_plan_id'  => $this->plan->id,
            'customer_phone'        => '254712345678',
            'amount'                => 1500.00,
            'payment_status'        => SubscriptionPaymentStatus::Processing,
            'payment_type'          => 'stk',
            'transaction_reference' => 'ws_CO_FAILED',
        ]);

        $result = $this->makeService()->processSTKCallback([
            'transaction_reference' => 'ws_CO_FAILED',
            'status'                => 'failed',
            'provider_reference'    => null,
            'failure_reason'        => 'Request cancelled by user',
            'failure_code'          => '1032',
        ]);

        $result->refresh();
        $this->assertSame(SubscriptionPaymentStatus::Failed, $result->payment_status);
        $this->assertSame('Request cancelled by user', $result->failure_reason);
        $this->assertNull($result->business_subscription_id);
    }

    public function test_process_stk_callback_is_idempotent(): void
    {
        SubscriptionPayment::on('central')->create([
            'tenant_id'             => 'stk-test-tenant',
            'subscription_plan_id'  => $this->plan->id,
            'customer_phone'        => '254712345678',
            'amount'                => 1500.00,
            'payment_status'        => SubscriptionPaymentStatus::Completed,
            'payment_type'          => 'stk',
            'transaction_reference' => 'ws_CO_IDEM',
            'provider_reference'    => 'RECEIPT_IDEM',
            'completed_at'          => now(),
        ]);

        $result1 = $this->makeService()->processSTKCallback([
            'transaction_reference' => 'ws_CO_IDEM',
            'status'                => 'success',
            'provider_reference'    => 'RECEIPT_IDEM',
            'failure_reason'        => null,
            'failure_code'          => null,
        ]);

        $result2 = $this->makeService()->processSTKCallback([
            'transaction_reference' => 'ws_CO_IDEM',
            'status'                => 'success',
            'provider_reference'    => 'RECEIPT_IDEM',
            'failure_reason'        => null,
            'failure_code'          => null,
        ]);

        $this->assertSame($result1->id, $result2->id);
    }

    // =========================================================================
    // getPaybillInstructions()
    // =========================================================================

    public function test_get_paybill_instructions_returns_shortcode_and_account_number(): void
    {
        $mpesa = Mockery::mock(MpesaService::class);
        $mpesa->shouldReceive('getActiveCredentials')
            ->andReturn(['shortcode' => '174379', 'base_url' => 'https://sandbox.safaricom.co.ke', 'consumer_key' => '', 'consumer_secret' => '', 'passkey' => '']);

        $instructions = $this->makeService($mpesa)->getPaybillInstructions('stk-test-tenant');

        $this->assertSame('174379', $instructions['shortcode']);
        $this->assertSame('POA88001', $instructions['account_number']);
        $this->assertIsArray($instructions['plans']);
        $this->assertNotEmpty($instructions['plans']);
    }

    // =========================================================================
    // getLatestPayment()
    // =========================================================================

    public function test_get_latest_payment_returns_most_recent(): void
    {
        SubscriptionPayment::on('central')->create([
            'tenant_id'            => 'stk-test-tenant',
            'subscription_plan_id' => $this->plan->id,
            'customer_phone'       => '254712345678',
            'amount'               => 1500.00,
            'payment_status'       => SubscriptionPaymentStatus::Failed,
            'payment_type'         => 'stk',
        ]);

        $latest = SubscriptionPayment::on('central')->create([
            'tenant_id'            => 'stk-test-tenant',
            'subscription_plan_id' => $this->plan->id,
            'customer_phone'       => '254712345678',
            'amount'               => 1500.00,
            'payment_status'       => SubscriptionPaymentStatus::Processing,
            'payment_type'         => 'stk',
        ]);

        $result = $this->makeService()->getLatestPayment('stk-test-tenant');

        $this->assertSame($latest->id, $result->id);
    }

    public function test_get_latest_payment_returns_null_when_no_records(): void
    {
        $this->assertNull($this->makeService()->getLatestPayment('nonexistent-tenant'));
    }
}
