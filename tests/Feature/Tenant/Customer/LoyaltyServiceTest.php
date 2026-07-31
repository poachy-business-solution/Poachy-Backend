<?php

namespace Tests\Feature\Tenant\Customer;

use App\Models\Tenant\Customer;
use App\Models\Tenant\LoyaltyTransaction;
use App\Models\Tenant\TenantConfiguration;
use App\Services\Tenant\Sales\LoyaltyService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Stancl\Tenancy\Contracts\Tenant as TenantContract;
use Tests\TestCase;

class LoyaltyServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'tenant');
        Config::set('database.connections.tenant.database', 'poachy_test');
        DB::purge('tenant');
        DB::connection('tenant')->statement('SET foreign_key_checks = 0');

        $this->createMinimalSchema();

        $fakeTenant = new \stdClass;
        $fakeTenant->id = 'test-tenant';
        app()->instance(TenantContract::class, $fakeTenant);

        Cache::tags(['tenant', 'test-tenant', 'config'])->flush();
        \Carbon\Carbon::setTestNow('2026-07-30 14:00:00');
    }

    protected function tearDown(): void
    {
        \Carbon\Carbon::setTestNow();
        $this->dropTestTables();
        DB::connection('tenant')->statement('SET foreign_key_checks = 1');
        parent::tearDown();
    }

    private function makeService(): LoyaltyService
    {
        return new LoyaltyService();
    }

    private function enableLoyalty(array $overrides = []): void
    {
        TenantConfiguration::set('loyalty_enabled', true);
        foreach ($overrides as $key => $value) {
            TenantConfiguration::set($key, $value);
        }
    }

    private function createCustomer(array $overrides = []): Customer
    {
        return Customer::create(array_merge([
            'name' => 'Customer '.uniqid(),
            'phone' => '0712'.rand(100000, 999999),
        ], $overrides));
    }

    // =========================================================================
    // isEnabled() / calculatePointsEarned() / calculateRedemptionValue()
    // =========================================================================

    public function test_is_enabled_reflects_configuration(): void
    {
        $this->assertFalse($this->makeService()->isEnabled());

        $this->enableLoyalty();

        $this->assertTrue($this->makeService()->isEnabled());
    }

    public function test_calculate_points_earned_is_zero_when_disabled(): void
    {
        $this->assertEquals(0, $this->makeService()->calculatePointsEarned(1000, 1));
    }

    public function test_calculate_points_earned_is_zero_without_customer_id(): void
    {
        $this->enableLoyalty();

        $this->assertEquals(0, $this->makeService()->calculatePointsEarned(1000, null));
    }

    public function test_calculate_points_earned_uses_configured_rate(): void
    {
        $this->enableLoyalty(['loyalty_earning_rate' => 0.02]);

        $this->assertEquals(20, $this->makeService()->calculatePointsEarned(1000, 1));
    }

    public function test_calculate_redemption_value_uses_configured_rate(): void
    {
        $this->enableLoyalty(['loyalty_redemption_rate' => 0.5]);

        $this->assertEquals(50, $this->makeService()->calculateRedemptionValue(100));
    }

    // =========================================================================
    // validateRedemption()
    // =========================================================================

    public function test_validate_redemption_invalid_when_disabled(): void
    {
        $customer = $this->createCustomer(['loyalty_points' => 500]);

        $result = $this->makeService()->validateRedemption($customer, 200);

        $this->assertFalse($result['valid']);
    }

    public function test_validate_redemption_invalid_below_minimum(): void
    {
        $this->enableLoyalty(['loyalty_min_redemption_points' => 100]);
        $customer = $this->createCustomer(['loyalty_points' => 500]);

        $result = $this->makeService()->validateRedemption($customer, 50);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('Minimum', $result['message']);
    }

    public function test_validate_redemption_invalid_when_insufficient_points(): void
    {
        $this->enableLoyalty(['loyalty_min_redemption_points' => 10]);
        $customer = $this->createCustomer(['loyalty_points' => 50]);

        $result = $this->makeService()->validateRedemption($customer, 100);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('Insufficient points', $result['message']);
    }

    public function test_validate_redemption_valid_with_sufficient_points(): void
    {
        $this->enableLoyalty(['loyalty_min_redemption_points' => 10, 'loyalty_redemption_rate' => 1]);
        $customer = $this->createCustomer(['loyalty_points' => 500]);

        $result = $this->makeService()->validateRedemption($customer, 100);

        $this->assertTrue($result['valid']);
        $this->assertEquals(100, $result['redemption_value']);
    }

    // =========================================================================
    // awardPoints() / redeemPoints()
    // =========================================================================

    public function test_award_points_throws_when_disabled(): void
    {
        $customer = $this->createCustomer();

        $this->expectException(\RuntimeException::class);

        $this->makeService()->awardPoints($customer, 50, 'Sale', 1);
    }

    public function test_award_points_increments_balance_and_sets_expiry(): void
    {
        $this->enableLoyalty(['loyalty_points_expiry_days' => 180]);
        $customer = $this->createCustomer(['loyalty_points' => 100]);

        $transaction = $this->makeService()->awardPoints($customer, 50, 'Sale', 1);

        $this->assertEquals(150, $customer->fresh()->loyalty_points);
        $this->assertEquals(150, $transaction->balance_after);
        $this->assertSame(now()->addDays(180)->toDateString(), $transaction->expires_at->toDateString());
    }

    public function test_award_points_fires_loyalty_points_earned_event(): void
    {
        Event::fake([\App\Events\Tenant\LoyaltyPointsEarned::class]);
        $this->enableLoyalty();
        $customer = $this->createCustomer();

        $this->makeService()->awardPoints($customer, 50, 'Sale', 1);

        Event::assertDispatchedTimes(\App\Events\Tenant\LoyaltyPointsEarned::class, 1);
    }

    public function test_redeem_points_throws_when_disabled(): void
    {
        $customer = $this->createCustomer(['loyalty_points' => 500]);

        $this->expectException(\RuntimeException::class);

        $this->makeService()->redeemPoints($customer, 100, 'Sale', 1);
    }

    public function test_redeem_points_throws_when_validation_fails(): void
    {
        $this->enableLoyalty(['loyalty_min_redemption_points' => 5]);
        $customer = $this->createCustomer(['loyalty_points' => 3]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Insufficient points');

        $this->makeService()->redeemPoints($customer, 5, 'Sale', 1);
    }

    public function test_redeem_points_decrements_balance(): void
    {
        $this->enableLoyalty(['loyalty_min_redemption_points' => 10]);
        $customer = $this->createCustomer(['loyalty_points' => 500]);

        $transaction = $this->makeService()->redeemPoints($customer, 100, 'Sale', 1);

        $this->assertEquals(400, $customer->fresh()->loyalty_points);
        $this->assertEquals(-100, $transaction->points);
        $this->assertEquals(400, $transaction->balance_after);
    }

    // =========================================================================
    // processSaleLoyalty()
    // =========================================================================

    public function test_process_sale_loyalty_returns_zeros_when_disabled(): void
    {
        $customer = $this->createCustomer();

        $result = $this->makeService()->processSaleLoyalty($customer, 1000, 0, 'Sale', 1, 'SALE-001');

        $this->assertEquals(0, $result['points_earned']);
        $this->assertEquals(0, $result['points_redeemed']);
    }

    public function test_process_sale_loyalty_redeems_then_earns(): void
    {
        $this->enableLoyalty(['loyalty_earning_rate' => 0.01, 'loyalty_redemption_rate' => 1, 'loyalty_min_redemption_points' => 10]);
        $customer = $this->createCustomer(['loyalty_points' => 200]);

        $result = $this->makeService()->processSaleLoyalty($customer, 1000, 100, 'Sale', 1, 'SALE-001');

        $this->assertEquals(100, $result['points_redeemed']);
        $this->assertEquals(10, $result['points_earned']);
        // 200 - 100 (redeemed) + 10 (earned) = 110
        $this->assertEquals(110, $customer->fresh()->loyalty_points);
        $this->assertCount(2, $result['transactions']);
    }

    // =========================================================================
    // expireOldPoints() / getExpiringPoints()
    // =========================================================================

    public function test_expire_old_points_deducts_expired_earned_points(): void
    {
        $this->enableLoyalty();
        $customer = $this->createCustomer(['loyalty_points' => 100]);
        LoyaltyTransaction::create([
            'customer_id' => $customer->id, 'transaction_type' => 'earned',
            'points' => 100, 'balance_after' => 100,
            'expires_at' => now()->subDay()->toDateString(),
        ]);

        $count = $this->makeService()->expireOldPoints();

        $this->assertSame(1, $count);
        $this->assertEquals(0, $customer->fresh()->loyalty_points);
    }

    public function test_expire_old_points_ignores_non_expired_transactions(): void
    {
        $this->enableLoyalty();
        $customer = $this->createCustomer(['loyalty_points' => 100]);
        LoyaltyTransaction::create([
            'customer_id' => $customer->id, 'transaction_type' => 'earned',
            'points' => 100, 'balance_after' => 100,
            'expires_at' => now()->addDay()->toDateString(),
        ]);

        $count = $this->makeService()->expireOldPoints();

        $this->assertSame(0, $count);
        $this->assertEquals(100, $customer->fresh()->loyalty_points);
    }

    public function test_get_expiring_points_sums_within_window(): void
    {
        $this->enableLoyalty();
        $customer = $this->createCustomer();
        LoyaltyTransaction::create([
            'customer_id' => $customer->id, 'transaction_type' => 'earned',
            'points' => 30, 'balance_after' => 30,
            'expires_at' => now()->addDays(10)->toDateString(),
        ]);
        LoyaltyTransaction::create([
            'customer_id' => $customer->id, 'transaction_type' => 'earned',
            'points' => 40, 'balance_after' => 70,
            'expires_at' => now()->addDays(60)->toDateString(),
        ]);

        $result = $this->makeService()->getExpiringPoints($customer, 30);

        $this->assertEquals(30, $result);
    }

    // =========================================================================
    // calculateSummary() / calculateCustomerBalanceSummary()
    // =========================================================================

    public function test_calculate_summary_aggregates_earned_redeemed_and_expired(): void
    {
        $this->enableLoyalty();
        $customer = $this->createCustomer();
        LoyaltyTransaction::create(['customer_id' => $customer->id, 'transaction_type' => 'earned', 'points' => 100, 'balance_after' => 100]);
        LoyaltyTransaction::create(['customer_id' => $customer->id, 'transaction_type' => 'redeemed', 'points' => -30, 'balance_after' => 70]);
        LoyaltyTransaction::create(['customer_id' => $customer->id, 'transaction_type' => 'expired', 'points' => -10, 'balance_after' => 60]);

        $summary = $this->makeService()->calculateSummary([]);

        $this->assertEquals(100, $summary['total_points_earned']);
        $this->assertEquals(30, $summary['total_points_redeemed']);
        $this->assertEquals(10, $summary['total_points_expired']);
        $this->assertEquals(60, $summary['net_points_outstanding']);
        $this->assertSame(1, $summary['unique_customers']);
    }

    public function test_calculate_customer_balance_summary_computes_current_balance(): void
    {
        $this->enableLoyalty();
        $customer = $this->createCustomer();
        LoyaltyTransaction::create(['customer_id' => $customer->id, 'transaction_type' => 'earned', 'points' => 100, 'balance_after' => 100]);
        LoyaltyTransaction::create(['customer_id' => $customer->id, 'transaction_type' => 'redeemed', 'points' => -20, 'balance_after' => 80]);

        $summary = $this->makeService()->calculateCustomerBalanceSummary($customer->id);

        $this->assertEquals(100, $summary['total_earned']);
        $this->assertEquals(20, $summary['total_redeemed']);
        $this->assertEquals(80, $summary['current_balance']);
    }

    // =========================================================================
    // Schema helpers
    // =========================================================================

    private function dropTestTables(): void
    {
        foreach (['loyalty_transactions', 'customers', 'tenant_configurations'] as $table) {
            Schema::connection('tenant')->dropIfExists($table);
        }
    }

    private function createMinimalSchema(): void
    {
        $conn = 'tenant';

        Schema::connection($conn)->create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('customer_number')->unique();
            $table->string('name');
            $table->string('email')->nullable()->unique();
            $table->string('phone')->unique();
            $table->string('customer_type')->default('walk_in');
            $table->decimal('loyalty_points', 15, 2)->default(0);
            $table->decimal('total_lifetime_purchases', 15, 2)->default(0);
            $table->integer('total_visits')->default(0);
            $table->decimal('credit_limit', 15, 2)->default(0);
            $table->decimal('current_debt', 15, 2)->default(0);
            $table->decimal('store_credit_balance', 15, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('accepts_marketing')->default(false);
            $table->timestamp('registered_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::connection($conn)->create('loyalty_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id');
            $table->string('transaction_type');
            $table->decimal('points', 15, 2);
            $table->decimal('balance_after', 15, 2);
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->text('description')->nullable();
            $table->date('expires_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::connection($conn)->create('tenant_configurations', function (Blueprint $table) {
            $table->id();
            $table->string('config_key')->unique();
            $table->json('config_value')->nullable();
            $table->string('config_type')->default('general');
            $table->string('config_group')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }
}
