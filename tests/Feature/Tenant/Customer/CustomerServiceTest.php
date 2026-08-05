<?php

namespace Tests\Feature\Tenant\Customer;

use App\Enums\Tenant\CustomerType;
use App\Models\Tenant\Customer;
use App\Services\Tenant\Customer\CustomerService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Stancl\Tenancy\Contracts\Tenant as TenantContract;
use Tests\TestCase;

class CustomerServiceTest extends TestCase
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

        Cache::tags(['tenant', 'test-tenant', 'customers'])->flush();
    }

    protected function tearDown(): void
    {
        $this->dropTestTables();
        DB::connection('tenant')->statement('SET foreign_key_checks = 1');
        parent::tearDown();
    }

    private function makeService(): CustomerService
    {
        return new CustomerService;
    }

    private function createCustomer(array $overrides = []): Customer
    {
        return Customer::create(array_merge([
            'name' => 'Customer '.uniqid(),
            'phone' => '0712'.rand(100000, 999999),
        ], $overrides));
    }

    // =========================================================================
    // getPaginatedCustomers()
    // =========================================================================

    public function test_get_paginated_customers_filters_by_search(): void
    {
        $this->createCustomer(['name' => 'John Doe']);
        $this->createCustomer(['name' => 'Jane Smith']);

        $result = $this->makeService()->getPaginatedCustomers(['search' => 'John']);

        $this->assertSame(1, $result->total());
    }

    public function test_get_paginated_customers_filters_by_type(): void
    {
        $this->createCustomer(['customer_type' => CustomerType::VIP]);
        $this->createCustomer(['customer_type' => CustomerType::WALK_IN]);

        $result = $this->makeService()->getPaginatedCustomers(['customer_type' => 'vip']);

        $this->assertSame(1, $result->total());
    }

    public function test_get_paginated_customers_filters_by_active_status(): void
    {
        $this->createCustomer(['is_active' => true]);
        $this->createCustomer(['is_active' => false]);

        $result = $this->makeService()->getPaginatedCustomers(['is_active' => 'true']);

        $this->assertSame(1, $result->total());
    }

    public function test_get_paginated_customers_filters_by_has_debt(): void
    {
        $this->createCustomer(['current_debt' => 500]);
        $this->createCustomer(['current_debt' => 0]);

        $result = $this->makeService()->getPaginatedCustomers(['has_debt' => true]);

        $this->assertSame(1, $result->total());
    }

    // =========================================================================
    // getCustomerById()
    // =========================================================================

    public function test_get_customer_by_id_returns_null_for_unknown_id(): void
    {
        $this->assertNull($this->makeService()->getCustomerById(999999));
    }

    public function test_get_customer_by_id_returns_matching_customer(): void
    {
        $customer = $this->createCustomer();

        $found = $this->makeService()->getCustomerById($customer->id);

        $this->assertSame($customer->id, $found->id);
    }

    // =========================================================================
    // createCustomer() / updateCustomer()
    // =========================================================================

    public function test_create_customer_generates_customer_number(): void
    {
        $customer = $this->makeService()->createCustomer(['name' => 'New Customer', 'phone' => '0712345678']);

        $this->assertNotEmpty($customer->customer_number);
    }

    /**
     * customers.phone/.email are plain unique columns with no deleted_at
     * scoping, but StoreCustomerRequest's own uniqueness checks exclude
     * soft-deleted rows — so this scenario reaches the service as a raw DB
     * duplicate-entry error unless it's translated here.
     */
    public function test_create_customer_throws_clean_error_for_soft_deleted_customers_phone(): void
    {
        $deleted = $this->createCustomer(['phone' => '0712345678']);
        $deleted->delete();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('previously deleted customer');

        $this->makeService()->createCustomer(['name' => 'New Customer', 'phone' => '0712345678']);
    }

    public function test_create_customer_throws_clean_error_for_soft_deleted_customers_email(): void
    {
        $deleted = $this->createCustomer(['email' => 'reused@example.com']);
        $deleted->delete();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('previously deleted customer');

        $this->makeService()->createCustomer([
            'name' => 'New Customer', 'phone' => '0712'.rand(100000, 999999), 'email' => 'reused@example.com',
        ]);
    }

    public function test_update_customer_updates_fields(): void
    {
        $customer = $this->createCustomer(['name' => 'Old Name']);

        $updated = $this->makeService()->updateCustomer($customer, ['name' => 'New Name']);

        $this->assertSame('New Name', $updated->name);
    }

    // =========================================================================
    // deleteCustomer() / restoreCustomer()
    // =========================================================================

    public function test_delete_customer_soft_deletes(): void
    {
        $customer = $this->createCustomer();

        $result = $this->makeService()->deleteCustomer($customer);

        $this->assertTrue($result);
        $this->assertSoftDeleted('customers', ['id' => $customer->id], connection: 'tenant');
    }

    public function test_restore_customer_restores_trashed_customer(): void
    {
        $customer = $this->createCustomer();
        $this->makeService()->deleteCustomer($customer);

        $restored = $this->makeService()->restoreCustomer($customer->id);

        $this->assertNotNull($restored);
        $this->assertNull($restored->deleted_at);
    }

    public function test_restore_customer_returns_null_when_not_trashed(): void
    {
        $customer = $this->createCustomer();

        $this->assertNull($this->makeService()->restoreCustomer($customer->id));
    }

    public function test_restore_customer_returns_null_for_unknown_id(): void
    {
        $this->assertNull($this->makeService()->restoreCustomer(999999));
    }

    // =========================================================================
    // searchCustomers()
    // =========================================================================

    public function test_search_customers_excludes_inactive(): void
    {
        $this->createCustomer(['name' => 'Active Alice', 'is_active' => true]);
        $this->createCustomer(['name' => 'Inactive Alice', 'is_active' => false]);

        $result = $this->makeService()->searchCustomers('Alice');

        $this->assertCount(1, $result);
    }

    // =========================================================================
    // upgradeCustomerType()
    // =========================================================================

    public function test_upgrade_customer_type_succeeds_for_valid_upgrade(): void
    {
        $customer = $this->createCustomer(['customer_type' => CustomerType::WALK_IN]);

        $upgraded = $this->makeService()->upgradeCustomerType($customer, CustomerType::REGULAR);

        $this->assertSame(CustomerType::REGULAR, $upgraded->customer_type);
    }

    public function test_upgrade_customer_type_throws_for_invalid_downgrade(): void
    {
        $customer = $this->createCustomer(['customer_type' => CustomerType::VIP]);

        $this->expectException(\InvalidArgumentException::class);

        $this->makeService()->upgradeCustomerType($customer, CustomerType::REGULAR);
    }

    // =========================================================================
    // toggleCustomerStatus()
    // =========================================================================

    public function test_toggle_customer_status_flips_both_directions(): void
    {
        $customer = $this->createCustomer(['is_active' => true]);
        $service = $this->makeService();

        $off = $service->toggleCustomerStatus($customer);
        $this->assertFalse($off->is_active);

        $on = $service->toggleCustomerStatus($off);
        $this->assertTrue($on->is_active);
    }

    // =========================================================================
    // getMarketingEligibleCustomers() / getCustomerByPhone() / toggleMarketingConsent()
    // =========================================================================

    public function test_get_marketing_eligible_customers_filters_correctly(): void
    {
        $this->createCustomer(['accepts_marketing' => true, 'is_active' => true]);
        $this->createCustomer(['accepts_marketing' => false, 'is_active' => true]);
        $this->createCustomer(['accepts_marketing' => true, 'is_active' => false]);

        $result = $this->makeService()->getMarketingEligibleCustomers(paginate: false);

        $this->assertCount(1, $result);
    }

    public function test_get_customer_by_phone_returns_matching_customer(): void
    {
        // CustomerObserver::creating() normalizes phone to international format on save,
        // so query using the persisted value rather than the raw input.
        $customer = $this->createCustomer(['phone' => '0798765432']);

        $found = $this->makeService()->getCustomerByPhone($customer->phone);

        $this->assertSame($customer->id, $found->id);
    }

    public function test_toggle_marketing_consent_flips_and_reports_status(): void
    {
        $customer = $this->createCustomer(['accepts_marketing' => false]);

        $result = $this->makeService()->toggleMarketingConsent($customer->id);

        $this->assertTrue($result['accepts_marketing']);
        $this->assertStringContainsString('opted in', $result['message']);
    }

    // =========================================================================
    // getCustomerStats()
    // =========================================================================

    public function test_get_customer_stats_returns_expected_keys(): void
    {
        $customer = $this->createCustomer([
            'loyalty_points' => 150,
            'current_debt' => 200,
            'credit_limit' => 1000,
            'total_lifetime_purchases' => 5000,
            'total_visits' => 10,
        ]);

        $stats = $this->makeService()->getCustomerStats($customer);

        $this->assertEquals(150, $stats['loyalty_points']);
        $this->assertEquals(800, $stats['available_credit']);
        $this->assertEquals(200, $stats['current_debt']);
        $this->assertEquals(10, $stats['total_visits']);
    }

    // =========================================================================
    // Schema helpers
    // =========================================================================

    private function dropTestTables(): void
    {
        foreach (['customer_group_members', 'customer_groups', 'customers', 'stores'] as $table) {
            Schema::connection('tenant')->dropIfExists($table);
        }
    }

    private function createMinimalSchema(): void
    {
        $conn = 'tenant';

        Schema::connection($conn)->create('stores', function (Blueprint $table) {
            $table->id();
            $table->string('name')->default('Test Store');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::connection($conn)->create('customer_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('discount_percentage', 5, 2)->default(0);
            $table->boolean('requires_approval')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::connection($conn)->create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('customer_number')->unique();
            $table->string('name');
            $table->string('email')->nullable()->unique();
            $table->string('phone')->unique();
            $table->date('date_of_birth')->nullable();
            $table->text('address')->nullable();
            $table->string('customer_type')->default('walk_in');
            $table->decimal('loyalty_points', 15, 2)->default(0);
            $table->decimal('total_lifetime_purchases', 15, 2)->default(0);
            $table->integer('total_visits')->default(0);
            $table->unsignedBigInteger('preferred_store_id')->nullable();
            $table->decimal('credit_limit', 15, 2)->default(0);
            $table->decimal('current_debt', 15, 2)->default(0);
            $table->decimal('store_credit_balance', 15, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('accepts_marketing')->default(false);
            $table->timestamp('registered_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::connection($conn)->create('customer_group_members', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('group_id');
            $table->timestamp('joined_at')->nullable();
            $table->timestamps();
        });
    }
}
