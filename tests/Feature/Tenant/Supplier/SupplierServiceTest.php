<?php

namespace Tests\Feature\Tenant\Supplier;

use App\Enums\Tenant\PaymentTerms;
use App\Enums\Tenant\SupplierType;
use App\Models\Tenant\Supplier;
use App\Repositories\Tenant\SupplierRepository;
use App\Services\Tenant\Supplier\SupplierService;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Stancl\Tenancy\Contracts\Tenant as TenantContract;
use Tests\TestCase;

class SupplierServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'tenant');
        Config::set('database.connections.tenant.database', 'poachy_test');
        DB::purge('tenant');
        DB::connection('tenant')->statement('SET foreign_key_checks = 0');

        $this->dropTestTables();
        $this->createMinimalSchema();

        // The `array` cache driver persists across tests within the same process;
        // Cache::tags(...)->remember() keys are per-filter-combination, so a stale
        // hit from a previous test's fixtures would otherwise leak in here.
        Cache::flush();

        $fakeTenant = new \stdClass;
        $fakeTenant->id = 'test-tenant';
        app()->instance(TenantContract::class, $fakeTenant);

        Auth::setUser(new class implements Authenticatable
        {
            public function getAuthIdentifierName()
            {
                return 'id';
            }

            public function getAuthIdentifier()
            {
                return 1;
            }

            public function getAuthPasswordName()
            {
                return 'password';
            }

            public function getAuthPassword()
            {
                return '';
            }

            public function getRememberToken()
            {
                return null;
            }

            public function setRememberToken($value) {}

            public function getRememberTokenName()
            {
                return 'remember_token';
            }
        });
    }

    protected function tearDown(): void
    {
        $this->dropTestTables();
        DB::connection('tenant')->statement('SET foreign_key_checks = 1');
        parent::tearDown();
    }

    // =========================================================================
    // createSupplierPersonalDetails()
    // =========================================================================

    public function test_create_supplier_personal_details_happy_path_applies_defaults(): void
    {
        $supplier = Model::withoutEvents(fn () => $this->service()->createSupplierPersonalDetails([
            'name' => 'Acme Distributors',
            'supplier_type' => SupplierType::DISTRIBUTOR->value,
            'email' => 'acme@example.com',
        ]));

        $this->assertSame('Acme Distributors', $supplier->name);
        $this->assertEquals(0.0, (float) $supplier->credit_limit);
        $this->assertEquals(0.0, (float) $supplier->outstanding_balance);
        $this->assertSame(PaymentTerms::COD, $supplier->payment_terms);
        $this->assertTrue($supplier->is_active);
    }

    public function test_create_supplier_throws_on_duplicate_name(): void
    {
        Model::withoutEvents(fn () => $this->service()->createSupplierPersonalDetails([
            'name' => 'Acme Distributors',
            'supplier_type' => SupplierType::DISTRIBUTOR->value,
        ]));

        $this->expectException(ValidationException::class);

        Model::withoutEvents(fn () => $this->service()->createSupplierPersonalDetails([
            'name' => 'Acme Distributors',
            'supplier_type' => SupplierType::WHOLESALER->value,
        ]));
    }

    public function test_create_supplier_throws_on_duplicate_email(): void
    {
        Model::withoutEvents(fn () => $this->service()->createSupplierPersonalDetails([
            'name' => 'Acme Distributors',
            'supplier_type' => SupplierType::DISTRIBUTOR->value,
            'email' => 'shared@example.com',
        ]));

        $this->expectException(ValidationException::class);

        Model::withoutEvents(fn () => $this->service()->createSupplierPersonalDetails([
            'name' => 'Different Name Ltd',
            'supplier_type' => SupplierType::WHOLESALER->value,
            'email' => 'shared@example.com',
        ]));
    }

    public function test_soft_deleted_supplier_name_and_email_do_not_block_reuse(): void
    {
        $first = Model::withoutEvents(fn () => $this->service()->createSupplierPersonalDetails([
            'name' => 'Acme Distributors',
            'supplier_type' => SupplierType::DISTRIBUTOR->value,
            'email' => 'acme@example.com',
        ]));

        Model::withoutEvents(fn () => $first->delete());

        $second = Model::withoutEvents(fn () => $this->service()->createSupplierPersonalDetails([
            'name' => 'Acme Distributors',
            'supplier_type' => SupplierType::WHOLESALER->value,
            'email' => 'acme@example.com',
        ]));

        $this->assertNotSame($first->id, $second->id);
        $this->assertSame('Acme Distributors', $second->name);
    }

    // =========================================================================
    // updateSupplierPersonalDetails()
    // =========================================================================

    public function test_update_supplier_personal_details_excludes_self_from_uniqueness_check(): void
    {
        $supplier = Model::withoutEvents(fn () => $this->service()->createSupplierPersonalDetails([
            'name' => 'Alpha Supplies',
            'supplier_type' => SupplierType::DISTRIBUTOR->value,
            'email' => 'alpha@example.com',
        ]));

        $updated = Model::withoutEvents(fn () => $this->service()->updateSupplierPersonalDetails($supplier->id, [
            'name' => 'Alpha Supplies',
            'email' => 'alpha@example.com',
            'contact_person' => 'New Contact',
        ]));

        $this->assertSame('New Contact', $updated->contact_person);
    }

    public function test_update_supplier_personal_details_throws_when_not_found(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Model::withoutEvents(fn () => $this->service()->updateSupplierPersonalDetails(9999, [
            'name' => 'Does Not Exist',
        ]));
    }

    // =========================================================================
    // updateSupplierFinancialDetails()
    // =========================================================================

    public function test_update_supplier_financial_details_skips_uniqueness_validation(): void
    {
        $supplier = $this->createSupplier(['credit_limit' => 0]);

        $updated = Model::withoutEvents(fn () => $this->service()->updateSupplierFinancialDetails($supplier->id, [
            'credit_limit' => 5000,
            'payment_terms' => PaymentTerms::NET_30->value,
        ]));

        $this->assertEquals(5000.0, (float) $updated->credit_limit);
        $this->assertSame(PaymentTerms::NET_30, $updated->payment_terms);
    }

    // =========================================================================
    // toggleActiveStatus()
    // =========================================================================

    public function test_toggle_active_status_flips_both_directions(): void
    {
        $supplier = $this->createSupplier(['is_active' => true]);

        $deactivated = Model::withoutEvents(fn () => $this->service()->toggleActiveStatus($supplier->id));
        $this->assertFalse($deactivated['is_active']);
        $this->assertSame('Supplier deactivated successfully', $deactivated['message']);

        $reactivated = Model::withoutEvents(fn () => $this->service()->toggleActiveStatus($supplier->id));
        $this->assertTrue($reactivated['is_active']);
        $this->assertSame('Supplier activated successfully', $reactivated['message']);
    }

    // =========================================================================
    // getSupplierFinancialSummary()
    // =========================================================================

    public function test_financial_summary_computes_totals_and_credit_utilization(): void
    {
        $supplier = $this->createSupplier(['credit_limit' => 10000, 'outstanding_balance' => 800]);

        $this->insertPurchaseOrder($supplier->id, status: 'sent', totalAmount: 1000);
        $this->insertPurchaseOrder($supplier->id, status: 'draft', totalAmount: 5000); // excluded
        $this->insertPurchaseOrder($supplier->id, status: 'cancelled', totalAmount: 5000); // excluded
        $this->insertSupplierPayment($supplier->id, amount: 200);

        $summary = $this->service()->getSupplierFinancialSummary($supplier->id);

        $this->assertEquals(1000.0, $summary['total_purchases']);
        $this->assertEquals(200.0, $summary['total_paid']);
        $this->assertEquals(800.0, $summary['outstanding_balance']);
        $this->assertEquals(800.0, $summary['calculated_outstanding']);
        $this->assertFalse($summary['balance_mismatch']);
        $this->assertEquals(8.0, $summary['credit_utilization_percent']);
        $this->assertEquals(9200.0, $summary['credit_available']);
        $this->assertSame(1, $summary['payment_count']);
        $this->assertSame(1, $summary['active_purchase_orders']);
    }

    public function test_financial_summary_flags_balance_mismatch(): void
    {
        $supplier = $this->createSupplier(['credit_limit' => 10000, 'outstanding_balance' => 999]);

        $this->insertPurchaseOrder($supplier->id, status: 'sent', totalAmount: 1000);
        $this->insertSupplierPayment($supplier->id, amount: 200);
        // calculated_outstanding = 1000 - 200 = 800, but supplier.outstanding_balance is 999.

        $summary = $this->service()->getSupplierFinancialSummary($supplier->id);

        $this->assertEquals(800.0, $summary['calculated_outstanding']);
        $this->assertEquals(999.0, $summary['outstanding_balance']);
        $this->assertTrue($summary['balance_mismatch']);
    }

    public function test_credit_limit_is_not_enforced_confirmed_behavior(): void
    {
        $supplier = $this->createSupplier(['credit_limit' => 1000, 'outstanding_balance' => 0]);

        $updated = Model::withoutEvents(fn () => $this->service()->updateSupplierFinancialDetails($supplier->id, [
            'outstanding_balance' => 1500,
        ]));

        // No exception — credit_limit is informational only, unlike CreditService's
        // enforcement for customers.
        $this->assertEquals(1500.0, (float) $updated->outstanding_balance);

        $summary = $this->service()->getSupplierFinancialSummary($supplier->id);
        $this->assertEquals(150.0, $summary['credit_utilization_percent']);
    }

    // =========================================================================
    // Supplier::getTotalOutstandingAttribute() — regression for the fixed bug
    // =========================================================================

    public function test_total_outstanding_attribute_sums_amount_due_across_unpaid_pos(): void
    {
        $supplier = $this->createSupplier();

        // amount_due = total_amount - amount_paid
        $this->insertPurchaseOrder($supplier->id, status: 'sent', paymentStatus: 'unpaid', totalAmount: 1000, amountPaid: 0);
        $this->insertPurchaseOrder($supplier->id, status: 'confirmed', paymentStatus: 'partially_paid', totalAmount: 500, amountPaid: 200);
        $this->insertPurchaseOrder($supplier->id, status: 'received', paymentStatus: 'paid', totalAmount: 700, amountPaid: 700); // excluded

        // Previously this would have thrown "Unknown column 'amount_due'" —
        // amount_due is a PHP accessor on PurchaseOrder, not a real column.
        $total = $supplier->refresh()->total_outstanding;

        $this->assertEquals(1300.0, $total);
    }

    // =========================================================================
    // getAllSuppliers()
    // =========================================================================

    public function test_get_all_suppliers_filters_by_is_active_supplier_type_and_search(): void
    {
        $this->createSupplier(['name' => 'Active Dist', 'supplier_type' => SupplierType::DISTRIBUTOR->value, 'is_active' => true]);
        $this->createSupplier(['name' => 'Inactive Dist', 'supplier_type' => SupplierType::DISTRIBUTOR->value, 'is_active' => false]);
        $zenith = $this->createSupplier(['name' => 'Zenith Manufacturing', 'supplier_type' => SupplierType::MANUFACTURER->value, 'is_active' => true]);

        $activeOnly = $this->service()->getAllSuppliers(['is_active' => true]);
        $this->assertCount(2, $activeOnly);

        $distributorsOnly = $this->service()->getAllSuppliers(['supplier_type' => SupplierType::DISTRIBUTOR->value]);
        $this->assertCount(2, $distributorsOnly);

        $searchResult = $this->service()->getAllSuppliers(['search' => 'Zenith']);
        $this->assertCount(1, $searchResult);
        $this->assertSame($zenith->id, $searchResult->first()->id);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function service(): SupplierService
    {
        return new SupplierService(new SupplierRepository);
    }

    private function createSupplier(array $overrides = []): Supplier
    {
        return Model::withoutEvents(fn () => Supplier::create(array_merge([
            'name' => 'Test Supplier '.uniqid(),
            'supplier_type' => SupplierType::DISTRIBUTOR->value,
            'credit_limit' => 0,
            'outstanding_balance' => 0,
            'payment_terms' => PaymentTerms::COD->value,
            'is_active' => true,
        ], $overrides)));
    }

    private function insertPurchaseOrder(int $supplierId, string $status = 'sent', string $paymentStatus = 'unpaid', float $totalAmount = 0, float $amountPaid = 0): void
    {
        DB::connection('tenant')->table('purchase_orders')->insert([
            'po_number' => 'PO-'.uniqid(),
            'supplier_id' => $supplierId,
            'status' => $status,
            'payment_status' => $paymentStatus,
            'total_amount' => $totalAmount,
            'amount_paid' => $amountPaid,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertSupplierPayment(int $supplierId, float $amount, ?int $purchaseOrderId = null): void
    {
        DB::connection('tenant')->table('supplier_payments')->insert([
            'payment_number' => 'PAY-SUP-'.uniqid(),
            'supplier_id' => $supplierId,
            'purchase_order_id' => $purchaseOrderId,
            'payment_date' => now()->toDateString(),
            'amount' => $amount,
            'payment_method' => 'cash',
            'created_by' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function dropTestTables(): void
    {
        foreach ([
            'supplier_payments',
            'purchase_orders',
            'suppliers',
        ] as $table) {
            Schema::connection('tenant')->dropIfExists($table);
        }
    }

    private function createMinimalSchema(): void
    {
        $conn = 'tenant';

        Schema::connection($conn)->create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('supplier_type');
            $table->string('contact_person')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->decimal('credit_limit', 12, 2)->default(0);
            $table->decimal('outstanding_balance', 12, 2)->default(0);
            $table->string('payment_terms')->default('cod');
            $table->string('tax_id')->nullable();
            $table->string('registration_number')->nullable();
            $table->json('bank_account_details')->nullable();
            $table->decimal('rating', 3, 2)->default(0);
            $table->integer('total_orders')->default(0);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::connection($conn)->create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('po_number')->unique();
            $table->unsignedBigInteger('supplier_id');
            $table->unsignedBigInteger('store_id')->nullable();
            $table->string('status')->default('draft');
            $table->string('payment_status')->default('unpaid');
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->decimal('amount_paid', 15, 2)->default(0);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::connection($conn)->create('supplier_payments', function (Blueprint $table) {
            $table->id();
            $table->string('payment_number')->unique();
            $table->unsignedBigInteger('supplier_id');
            $table->unsignedBigInteger('purchase_order_id')->nullable();
            $table->date('payment_date');
            $table->decimal('amount', 15, 2);
            $table->string('payment_method')->nullable();
            $table->string('reference_number')->nullable();
            $table->text('notes')->nullable();
            $table->string('receipt_path')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
    }
}
