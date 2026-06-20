<?php

namespace Tests\Feature\Payment;

use App\Models\MarketplaceOrder;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Services\Shared\Mpesa\MpesaC2BRouterService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class C2BValidationTest extends TestCase
{
    private MpesaC2BRouterService $router;

    private SubscriptionPlan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        // Force central connection to MySQL
        Config::set('tenancy.database.central_connection', 'central');
        Config::set('database.connections.central.host', env('CENTRAL_DB_HOST', '127.0.0.1'));
        Config::set('database.connections.central.port', env('CENTRAL_DB_PORT', '3306'));
        Config::set('database.connections.central.database', env('CENTRAL_DB_DATABASE', 'poachy'));
        Config::set('database.connections.central.username', env('CENTRAL_DB_USERNAME', 'root'));
        Config::set('database.connections.central.password', env('CENTRAL_DB_PASSWORD', ''));
        Config::set('mpesa.account_prefix', 'POA');
        DB::purge('central');
        DB::connection('central')->statement('SET foreign_key_checks = 0');

        $this->router = new MpesaC2BRouterService();

        $this->plan = SubscriptionPlan::on('central')->create([
            'name'               => 'C2B Test Plan',
            'slug'               => 'c2b-test-plan-' . uniqid(),
            'price'              => 2500.00,
            'billing_cycle_days' => 30,
            'is_active'          => true,
            'is_featured'        => false,
        ]);

        DB::connection('central')->table('tenants')->insertOrIgnore([
            'id'                    => 'c2b-val-tenant',
            'mpesa_paybill_account' => 'POA99001',
            'created_at'            => now(),
            'updated_at'            => now(),
        ]);
    }

    protected function tearDown(): void
    {
        DB::connection('central')->statement('SET foreign_key_checks = 0');
        SubscriptionPlan::on('central')->where('id', $this->plan->id)->forceDelete();
        DB::connection('central')->table('tenants')->where('id', 'c2b-val-tenant')->delete();
        DB::connection('central')->statement('SET foreign_key_checks = 1');
        Mockery::close();
        parent::tearDown();
    }

    // =========================================================================
    // Subscription validation
    // =========================================================================

    public function test_accepts_valid_subscription_account_and_matching_amount(): void
    {
        $response = $this->router->handleValidation([
            'bill_ref_number' => 'POA99001',
            'amount'          => 2500.00,
        ]);

        $this->assertSame('0', $response['ResultCode']);
        $this->assertSame('Accepted', $response['ResultDesc']);
    }

    public function test_rejects_unknown_subscription_account_number(): void
    {
        $response = $this->router->handleValidation([
            'bill_ref_number' => 'POA99999',
            'amount'          => 2500.00,
        ]);

        $this->assertSame('C2B00011', $response['ResultCode']);
        $this->assertStringContainsStringIgnoringCase('invalid', $response['ResultDesc']);
    }

    public function test_rejects_subscription_when_no_plan_matches_amount(): void
    {
        $response = $this->router->handleValidation([
            'bill_ref_number' => 'POA99001',
            'amount'          => 9999.00, // no plan at this price
        ]);

        $this->assertSame('C2B00012', $response['ResultCode']);
    }

    public function test_accepts_amount_within_tolerance(): void
    {
        $response = $this->router->handleValidation([
            'bill_ref_number' => 'POA99001',
            'amount'          => 2500.005, // within 0.01 tolerance
        ]);

        $this->assertSame('0', $response['ResultCode']);
    }

    // =========================================================================
    // Routing logic
    // =========================================================================

    public function test_routes_poa_prefix_to_subscription_handler(): void
    {
        // POA prefix → subscription path → should look up tenant, not order
        $response = $this->router->handleValidation([
            'bill_ref_number' => 'POA99001',
            'amount'          => 2500.00,
        ]);

        // If it routed to subscription path correctly, it should have found the tenant and accepted
        $this->assertSame('0', $response['ResultCode']);
    }

    public function test_routes_non_poa_prefix_to_marketplace_handler(): void
    {
        // Non-POA prefix → marketplace path → should look up order, not tenant
        $response = $this->router->handleValidation([
            'bill_ref_number' => 'MKT-ORD-2026-NONEXISTENT',
            'amount'          => 500.00,
        ]);

        // Order doesn't exist, so it should reject
        $this->assertSame('C2B00011', $response['ResultCode']);
    }
}
