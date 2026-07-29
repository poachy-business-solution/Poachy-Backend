<?php

namespace Tests\Feature\Subscription;

use App\Models\BusinessSubscription;
use App\Models\SubscriptionPlan;
use App\Services\Tenant\TenantAccessService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TenantAccessServiceSubscriptionTest extends TestCase
{
    private SubscriptionPlan $plan;

    private TenantAccessService $service;

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

        $this->service = new TenantAccessService;

        $this->plan = SubscriptionPlan::on('central')->create([
            'name' => 'Access Test Plan',
            'slug' => 'access-test-plan-'.uniqid(),
            'price' => 1000.00,
            'billing_cycle_days' => 30,
            'is_active' => true,
            'is_featured' => false,
        ]);

        DB::connection('central')->table('tenants')->insertOrIgnore([
            'id' => 'access-test-tenant',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::connection('central')->table('business_details')->updateOrInsert(
            ['tenant_id' => 'access-test-tenant'],
            [
                'business_name' => 'Access Test Biz',
                'business_phone' => '0712345111',
                'business_type_id' => 1,
                'business_category_id' => 1,
                'status' => 'active',
                'onboarded_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        // Guard against a stale cached result bleeding in from a previous test method.
        Cache::forget('tenant_access:access-test-tenant');
    }

    protected function tearDown(): void
    {
        DB::connection('central')->statement('SET foreign_key_checks = 0');
        BusinessSubscription::on('central')->where('tenant_id', 'access-test-tenant')->forceDelete();
        DB::connection('central')->table('business_details')->where('tenant_id', 'access-test-tenant')->delete();
        DB::connection('central')->table('tenants')->where('id', 'access-test-tenant')->delete();
        SubscriptionPlan::on('central')->where('id', $this->plan->id)->forceDelete();
        DB::connection('central')->statement('SET foreign_key_checks = 1');
        Cache::forget('tenant_access:access-test-tenant');
        parent::tearDown();
    }

    private function createSubscription(array $overrides = []): BusinessSubscription
    {
        return BusinessSubscription::on('central')->create(array_merge([
            'tenant_id' => 'access-test-tenant',
            'subscription_plan_id' => $this->plan->id,
            'start_date' => now()->subDays(30)->toDateString(),
            'end_date' => now()->addDays(10)->toDateString(),
            'amount_paid' => 1000.00,
            'status' => 'active',
        ], $overrides));
    }

    public function test_access_check_returns_renew_action_required_after_job_flips_subscription_to_expired(): void
    {
        $this->createSubscription(['status' => 'expired', 'end_date' => now()->subDay()->toDateString()]);

        $result = $this->service->checkTenantAccess('access-test-tenant');

        $this->assertFalse($result['allowed']);
        $this->assertSame('subscription_expired', $result['reason']);
        $this->assertSame('renew_subscription', $result['details']['action_required']);
    }

    public function test_access_check_returns_subscribe_action_required_when_no_subscription_row_ever_existed(): void
    {
        $result = $this->service->checkTenantAccess('access-test-tenant');

        $this->assertFalse($result['allowed']);
        $this->assertSame('no_active_subscription', $result['reason']);
        $this->assertSame('subscribe', $result['details']['action_required']);
    }

    public function test_access_check_treats_end_date_passed_but_status_still_active_as_expired(): void
    {
        $this->createSubscription(['status' => 'active', 'end_date' => now()->subDay()->toDateString()]);

        $result = $this->service->checkTenantAccess('access-test-tenant');

        $this->assertFalse($result['allowed']);
        $this->assertSame('subscription_expired', $result['reason']);
        $this->assertSame('renew_subscription', $result['details']['action_required']);
    }

    public function test_access_check_still_returns_active_for_valid_unexpired_subscription(): void
    {
        $this->createSubscription(['status' => 'active', 'end_date' => now()->addDays(10)->toDateString()]);

        $result = $this->service->checkTenantAccess('access-test-tenant');

        $this->assertTrue($result['allowed']);
    }

    public function test_access_check_handles_cancelled_subscription(): void
    {
        $this->createSubscription([
            'status' => 'cancelled',
            'end_date' => now()->addDays(10)->toDateString(),
            'cancelled_at' => now(),
        ]);

        $result = $this->service->checkTenantAccess('access-test-tenant');

        $this->assertFalse($result['allowed']);
        $this->assertSame('subscription_cancelled', $result['reason']);
        $this->assertSame('subscribe', $result['details']['action_required']);
    }
}
