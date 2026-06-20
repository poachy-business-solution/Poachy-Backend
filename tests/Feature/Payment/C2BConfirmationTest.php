<?php

namespace Tests\Feature\Payment;

use App\Enums\Central\SubscriptionPaymentStatus;
use App\Models\BusinessSubscription;
use App\Models\SubscriptionPayment;
use App\Models\SubscriptionPlan;
use App\Services\Central\Marketplace\MpesaService as OldMpesaService;
use App\Services\Central\Subscription\SubscriptionPaymentService;
use App\Services\Shared\Mpesa\MpesaService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class C2BConfirmationTest extends TestCase
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
        DB::purge('central');
        DB::connection('central')->statement('SET foreign_key_checks = 0');

        $this->plan = SubscriptionPlan::on('central')->create([
            'name'               => 'C2B Confirm Plan',
            'slug'               => 'c2b-confirm-plan-' . uniqid(),
            'price'              => 2500.00,
            'billing_cycle_days' => 30,
            'is_active'          => true,
            'is_featured'        => false,
        ]);

        DB::connection('central')->table('tenants')->insertOrIgnore([
            'id'                    => 'c2b-confirm-tenant',
            'mpesa_paybill_account' => 'POA99002',
            'created_at'            => now(),
            'updated_at'            => now(),
        ]);
    }

    protected function tearDown(): void
    {
        DB::connection('central')->statement('SET foreign_key_checks = 0');
        SubscriptionPayment::on('central')->where('tenant_id', 'c2b-confirm-tenant')->forceDelete();
        BusinessSubscription::on('central')->where('tenant_id', 'c2b-confirm-tenant')->forceDelete();
        SubscriptionPlan::on('central')->where('id', $this->plan->id)->forceDelete();
        DB::connection('central')->table('tenants')->where('id', 'c2b-confirm-tenant')->delete();
        DB::connection('central')->statement('SET foreign_key_checks = 1');
        Mockery::close();
        parent::tearDown();
    }

    private function makeService(): SubscriptionPaymentService
    {
        return new SubscriptionPaymentService(Mockery::mock(MpesaService::class));
    }

    private function makeC2BPayload(array $overrides = []): array
    {
        return array_merge([
            'transaction_id'   => 'C2B_TXN_' . uniqid(),
            'transaction_type' => 'Pay Bill',
            'transaction_time' => '20260620120000',
            'amount'           => 2500.0,
            'shortcode'        => '174379',
            'bill_ref_number'  => 'POA99002',
            'phone'            => '254712345678',
            'first_name'       => 'John',
            'last_name'        => 'Doe',
            'org_account_balance' => '50000.00',
        ], $overrides);
    }

    // =========================================================================
    // processC2BConfirmation()
    // =========================================================================

    public function test_c2b_confirmation_creates_payment_record_and_activates_subscription(): void
    {
        $service = $this->makeService();
        $txnId   = 'C2B_SUCCESS_' . uniqid();

        $result = $service->processC2BConfirmation($this->makeC2BPayload([
            'transaction_id' => $txnId,
        ]));

        $this->assertSame(SubscriptionPaymentStatus::Completed, $result->payment_status);
        $this->assertSame($txnId, $result->transaction_reference);
        $this->assertSame($txnId, $result->provider_reference);
        $this->assertSame('c2b', $result->payment_type);
        $this->assertSame('POA99002', $result->bill_ref_number);
        $this->assertNotNull($result->business_subscription_id);

        $subscription = BusinessSubscription::on('central')->find($result->business_subscription_id);
        $this->assertNotNull($subscription);
        $this->assertSame('active', $subscription->status);
        $this->assertSame('mpesa', $subscription->payment_method);
        $this->assertSame($txnId, $subscription->payment_reference);
    }

    public function test_c2b_confirmation_sets_correct_subscription_end_date(): void
    {
        $service = $this->makeService();
        $before  = now();

        $service->processC2BConfirmation($this->makeC2BPayload(['transaction_id' => 'C2B_DATE_' . uniqid()]));

        $subscription = BusinessSubscription::on('central')
            ->where('tenant_id', 'c2b-confirm-tenant')
            ->latest()
            ->first();

        $expectedEnd = $before->copy()->addDays($this->plan->billing_cycle_days)->toDateString();
        $this->assertSame($expectedEnd, $subscription->end_date->toDateString());
    }

    public function test_c2b_confirmation_is_idempotent_on_duplicate_transaction_id(): void
    {
        $service = $this->makeService();
        $txnId   = 'C2B_IDEM_' . uniqid();

        $first  = $service->processC2BConfirmation($this->makeC2BPayload(['transaction_id' => $txnId]));
        $second = $service->processC2BConfirmation($this->makeC2BPayload(['transaction_id' => $txnId]));

        $this->assertSame($first->id, $second->id);

        // Only one BusinessSubscription should have been created
        $count = BusinessSubscription::on('central')
            ->where('tenant_id', 'c2b-confirm-tenant')
            ->where('payment_reference', $txnId)
            ->count();

        $this->assertSame(1, $count);
    }

    public function test_c2b_confirmation_stores_phone_from_msisdn(): void
    {
        $service = $this->makeService();

        $result = $service->processC2BConfirmation($this->makeC2BPayload([
            'transaction_id' => 'C2B_PHONE_' . uniqid(),
            'phone'          => '254798765432',
        ]));

        $this->assertSame('254798765432', $result->customer_phone);
    }
}
