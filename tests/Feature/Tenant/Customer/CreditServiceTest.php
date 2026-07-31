<?php

namespace Tests\Feature\Tenant\Customer;

use App\Enums\Tenant\PaymentMethod;
use App\Models\Tenant\Customer;
use App\Models\Tenant\CustomerCreditTransaction;
use App\Models\Tenant\TenantConfiguration;
use App\Services\Tenant\Sales\CreditService;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Stancl\Tenancy\Contracts\Tenant as TenantContract;
use Tests\TestCase;

class CreditServiceTest extends TestCase
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

        DB::connection('tenant')->table('users')->insert(['id' => 1, 'name' => 'Cashier', 'created_at' => now(), 'updated_at' => now()]);

        Cache::tags(['tenant', 'test-tenant', 'config'])->flush();
        Auth::setUser($this->fakeUser());
        \Carbon\Carbon::setTestNow('2026-07-30 14:00:00');
    }

    protected function tearDown(): void
    {
        \Carbon\Carbon::setTestNow();
        $this->dropTestTables();
        DB::connection('tenant')->statement('SET foreign_key_checks = 1');
        parent::tearDown();
    }

    private function fakeUser(): Authenticatable
    {
        return new class implements Authenticatable
        {
            public function getAuthIdentifierName() { return 'id'; }
            public function getAuthIdentifier() { return 1; }
            public function getAuthPasswordName() { return 'password'; }
            public function getAuthPassword() { return ''; }
            public function getRememberToken() { return null; }
            public function setRememberToken($value) {}
            public function getRememberTokenName() { return 'remember_token'; }
        };
    }

    private function makeService(): CreditService
    {
        return new CreditService();
    }

    private function enableCredit(array $overrides = []): void
    {
        TenantConfiguration::set('credit_enabled', true);
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
    // isEnabled() / getDefaultCreditLimit() / getGracePeriodDays()
    // =========================================================================

    public function test_is_enabled_reflects_configuration(): void
    {
        $this->assertFalse($this->makeService()->isEnabled());

        $this->enableCredit();

        $this->assertTrue($this->makeService()->isEnabled());
    }

    public function test_get_default_credit_limit_uses_configured_value(): void
    {
        $this->enableCredit(['credit_default_limit' => 5000]);

        $this->assertEquals(5000, $this->makeService()->getDefaultCreditLimit());
    }

    public function test_get_grace_period_days_uses_configured_value(): void
    {
        $this->enableCredit(['credit_grace_period_days' => 14]);

        $this->assertSame(14, $this->makeService()->getGracePeriodDays());
    }

    // =========================================================================
    // validateCreditSale()
    // =========================================================================

    public function test_validate_credit_sale_invalid_when_disabled(): void
    {
        $customer = $this->createCustomer(['credit_limit' => 1000]);

        $result = $this->makeService()->validateCreditSale($customer, 500);

        $this->assertFalse($result['valid']);
    }

    public function test_validate_credit_sale_invalid_when_customer_inactive(): void
    {
        $this->enableCredit();
        $customer = $this->createCustomer(['credit_limit' => 1000, 'is_active' => false]);

        $result = $this->makeService()->validateCreditSale($customer, 500);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('inactive', $result['message']);
    }

    public function test_validate_credit_sale_invalid_when_exceeds_limit(): void
    {
        $this->enableCredit();
        $customer = $this->createCustomer(['credit_limit' => 1000, 'current_debt' => 800]);

        $result = $this->makeService()->validateCreditSale($customer, 500);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('Credit limit exceeded', $result['message']);
    }

    public function test_validate_credit_sale_valid_within_limit(): void
    {
        $this->enableCredit();
        $customer = $this->createCustomer(['credit_limit' => 1000, 'current_debt' => 200]);

        $result = $this->makeService()->validateCreditSale($customer, 300);

        $this->assertTrue($result['valid']);
        $this->assertEquals(500, $result['new_balance']);
        $this->assertEquals(500, $result['remaining_credit']);
    }

    // =========================================================================
    // recordCreditSale()
    // =========================================================================

    public function test_record_credit_sale_throws_when_disabled(): void
    {
        $customer = $this->createCustomer();

        $this->expectException(\RuntimeException::class);

        $this->makeService()->recordCreditSale($customer, 300, 'Sale', 1);
    }

    public function test_record_credit_sale_throws_and_fires_event_when_limit_exceeded(): void
    {
        Event::fake([\App\Events\Tenant\CreditLimitExceeded::class]);
        $this->enableCredit();
        $customer = $this->createCustomer(['credit_limit' => 100, 'current_debt' => 50]);

        $this->expectException(\RuntimeException::class);

        try {
            $this->makeService()->recordCreditSale($customer, 100, 'Sale', 1);
        } finally {
            Event::assertDispatchedTimes(\App\Events\Tenant\CreditLimitExceeded::class, 1);
        }
    }

    public function test_record_credit_sale_increments_debt_and_fires_event(): void
    {
        Event::fake([\App\Events\Tenant\CreditSaleCreated::class]);
        $this->enableCredit();
        $customer = $this->createCustomer(['credit_limit' => 1000, 'current_debt' => 100]);

        $transaction = $this->makeService()->recordCreditSale($customer, 300, 'Sale', 1);

        $this->assertEquals(400, $customer->fresh()->current_debt);
        $this->assertEquals(400, $transaction->balance_after);
        Event::assertDispatchedTimes(\App\Events\Tenant\CreditSaleCreated::class, 1);
    }

    // =========================================================================
    // recordPayment()
    // =========================================================================

    public function test_record_payment_throws_when_disabled(): void
    {
        $customer = $this->createCustomer(['current_debt' => 300]);

        $this->expectException(\RuntimeException::class);

        $this->makeService()->recordPayment($customer, 100, PaymentMethod::CASH);
    }

    public function test_record_payment_throws_for_non_positive_amount(): void
    {
        $this->enableCredit();
        $customer = $this->createCustomer(['current_debt' => 300]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('greater than zero');

        $this->makeService()->recordPayment($customer, 0, PaymentMethod::CASH);
    }

    public function test_record_payment_throws_when_exceeds_debt(): void
    {
        $this->enableCredit();
        $customer = $this->createCustomer(['current_debt' => 100]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('exceeds current debt');

        $this->makeService()->recordPayment($customer, 200, PaymentMethod::CASH);
    }

    public function test_record_payment_decrements_debt(): void
    {
        $this->enableCredit();
        $customer = $this->createCustomer(['current_debt' => 500]);

        $transaction = $this->makeService()->recordPayment($customer, 200, PaymentMethod::CASH);

        $this->assertEquals(300, $customer->fresh()->current_debt);
        $this->assertEquals(-200, $transaction->amount);
        $this->assertEquals(300, $transaction->balance_after);
    }

    // =========================================================================
    // recordAdjustment() / writeOff()
    // =========================================================================

    public function test_record_adjustment_increases_debt_for_positive_amount(): void
    {
        $this->enableCredit();
        $customer = $this->createCustomer(['current_debt' => 100]);

        $this->makeService()->recordAdjustment($customer, 50, 'Late fee');

        $this->assertEquals(150, $customer->fresh()->current_debt);
    }

    public function test_record_adjustment_decreases_debt_for_negative_amount(): void
    {
        $this->enableCredit();
        $customer = $this->createCustomer(['current_debt' => 100]);

        $this->makeService()->recordAdjustment($customer, -30, 'Goodwill discount');

        $this->assertEquals(70, $customer->fresh()->current_debt);
    }

    public function test_write_off_throws_when_exceeds_debt(): void
    {
        $this->enableCredit();
        $customer = $this->createCustomer(['current_debt' => 100]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('exceeds current debt');

        $this->makeService()->writeOff($customer, 200, 'Uncollectable');
    }

    public function test_write_off_reduces_debt(): void
    {
        $this->enableCredit();
        $customer = $this->createCustomer(['current_debt' => 500]);

        $this->makeService()->writeOff($customer, 500, 'Bad debt');

        $this->assertEquals(0, $customer->fresh()->current_debt);
    }

    // =========================================================================
    // getOverdueCustomers() / getCreditSummary()
    // =========================================================================

    public function test_get_overdue_customers_returns_empty_when_disabled(): void
    {
        $this->assertCount(0, $this->makeService()->getOverdueCustomers());
    }

    public function test_get_overdue_customers_finds_customers_past_grace_period(): void
    {
        $this->enableCredit(['credit_grace_period_days' => 30]);
        $customer = $this->createCustomer(['credit_limit' => 1000, 'current_debt' => 300]);
        $transaction = CustomerCreditTransaction::create([
            'customer_id' => $customer->id, 'transaction_type' => 'sale_on_credit',
            'amount' => 300, 'balance_after' => 300, 'created_by' => 1,
        ]);
        // created_at isn't mass-assignable — backdate it directly to simulate an old sale.
        $transaction->forceFill(['created_at' => now()->subDays(45)])->save();

        $result = $this->makeService()->getOverdueCustomers();

        $this->assertCount(1, $result);
    }

    public function test_get_credit_summary_reports_disabled_state(): void
    {
        $customer = $this->createCustomer();

        $summary = $this->makeService()->getCreditSummary($customer);

        $this->assertFalse($summary['enabled']);
    }

    public function test_get_credit_summary_computes_available_credit(): void
    {
        $this->enableCredit();
        $customer = $this->createCustomer(['credit_limit' => 1000, 'current_debt' => 300]);

        $summary = $this->makeService()->getCreditSummary($customer);

        $this->assertTrue($summary['enabled']);
        $this->assertEquals(700, $summary['available_credit']);
    }

    // =========================================================================
    // calculateSummary() / calculateCustomerDebtSummary()
    // =========================================================================

    public function test_calculate_summary_aggregates_sales_and_payments(): void
    {
        $this->enableCredit();
        $customer = $this->createCustomer(['current_debt' => 200]);
        CustomerCreditTransaction::create(['customer_id' => $customer->id, 'transaction_type' => 'sale_on_credit', 'amount' => 300, 'balance_after' => 300, 'created_by' => 1]);
        CustomerCreditTransaction::create(['customer_id' => $customer->id, 'transaction_type' => 'payment', 'amount' => -100, 'balance_after' => 200, 'created_by' => 1]);

        $summary = $this->makeService()->calculateSummary([]);

        $this->assertEquals(300, $summary['total_credit_sales']);
        $this->assertEquals(100, $summary['total_payments']);
        $this->assertSame(1, $summary['unique_credit_customers']);
    }

    public function test_calculate_customer_debt_summary_computes_utilization(): void
    {
        $this->enableCredit();
        $customer = $this->createCustomer(['credit_limit' => 1000, 'current_debt' => 500]);
        CustomerCreditTransaction::create(['customer_id' => $customer->id, 'transaction_type' => 'sale_on_credit', 'amount' => 600, 'balance_after' => 600, 'created_by' => 1]);
        CustomerCreditTransaction::create(['customer_id' => $customer->id, 'transaction_type' => 'payment', 'amount' => -100, 'balance_after' => 500, 'created_by' => 1]);

        $summary = $this->makeService()->calculateCustomerDebtSummary($customer->id, $customer);

        $this->assertEquals(600, $summary['total_credit_sales']);
        $this->assertEquals(100, $summary['total_payments']);
        $this->assertEquals(500, $summary['available_credit']);
        $this->assertEquals(50, $summary['credit_utilization']);
    }

    // =========================================================================
    // Schema helpers
    // =========================================================================

    private function dropTestTables(): void
    {
        foreach (['customer_credit_transactions', 'customers', 'tenant_configurations', 'users'] as $table) {
            Schema::connection('tenant')->dropIfExists($table);
        }
    }

    private function createMinimalSchema(): void
    {
        $conn = 'tenant';

        Schema::connection($conn)->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->timestamps();
        });

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

        Schema::connection($conn)->create('customer_credit_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id');
            $table->string('transaction_type');
            $table->decimal('amount', 15, 2);
            $table->decimal('balance_after', 15, 2);
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('payment_method')->nullable();
            $table->string('payment_reference')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by');
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
