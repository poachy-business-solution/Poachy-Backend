<?php

namespace Tests\Feature\Tenant\Expenses;

use App\Models\Tenant\Budget;
use App\Models\Tenant\Expense;
use App\Models\Tenant\ExpenseCategory;
use App\Repositories\Tenant\BudgetRepository;
use App\Repositories\Tenant\ExpenseCategoryRepository;
use App\Services\Tenant\Expenses\BudgetService;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Stancl\Tenancy\Contracts\Tenant as TenantContract;
use Tests\TestCase;

class BudgetServiceTest extends TestCase
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

        DB::connection('tenant')->table('stores')->insert([
            ['id' => 1, 'name' => 'Main Store', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'Second Store', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::connection('tenant')->table('users')->insert(['id' => 1, 'name' => 'Manager', 'created_at' => now(), 'updated_at' => now()]);

        Auth::setUser($this->fakeUser());
    }

    protected function tearDown(): void
    {
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

    private function makeBudgetService(): BudgetService
    {
        return new BudgetService(new BudgetRepository, new ExpenseCategoryRepository);
    }

    private function createCategory(array $overrides = []): ExpenseCategory
    {
        return ExpenseCategory::create(array_merge([
            'name' => 'Category '.uniqid(),
            'code' => 'CODE-'.uniqid(),
        ], $overrides));
    }

    private function createBudget(array $overrides = []): Budget
    {
        if (! isset($overrides['category_id'])) {
            $overrides['category_id'] = $this->createCategory()->id;
        }

        return Budget::create(array_merge([
            'budget_name' => 'Budget '.uniqid(),
            'store_id' => 1,
            'period_type' => 'monthly',
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
            'budget_amount' => 1000,
            'alert_threshold_percentage' => 80,
        ], $overrides));
    }

    private function createApprovedExpense(int $categoryId, ?int $storeId, float $amount, ?string $date = null): Expense
    {
        return Expense::create([
            'expense_number' => 'EXP-'.uniqid(),
            'category_id' => $categoryId,
            'store_id' => $storeId,
            'amount' => $amount,
            'description' => 'Test expense',
            'expense_date' => $date ?? now()->toDateString(),
            'payment_method' => 'cash',
            'approval_status' => 'approved',
            'created_by' => 1,
        ]);
    }

    // =========================================================================
    // createBudget()
    // =========================================================================

    public function test_create_budget_throws_when_category_inactive(): void
    {
        $category = $this->createCategory(['is_active' => false]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('category is inactive');

        $this->makeBudgetService()->createBudget([
            'category_id' => $category->id, 'store_id' => 1, 'period_type' => 'monthly',
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
            'budget_amount' => 1000,
        ]);
    }

    public function test_create_budget_throws_when_end_before_start(): void
    {
        $category = $this->createCategory();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('end date must be after start date');

        $this->makeBudgetService()->createBudget([
            'category_id' => $category->id, 'store_id' => 1, 'period_type' => 'monthly',
            'period_start' => now()->toDateString(),
            'period_end' => now()->subDay()->toDateString(),
            'budget_amount' => 1000,
        ]);
    }

    public function test_create_budget_throws_on_overlap_for_same_category_and_store(): void
    {
        $category = $this->createCategory();
        $this->createBudget(['category_id' => $category->id, 'store_id' => 1]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('already exists for this category');

        $this->makeBudgetService()->createBudget([
            'category_id' => $category->id, 'store_id' => 1, 'period_type' => 'monthly',
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
            'budget_amount' => 500,
        ]);
    }

    public function test_create_budget_allows_same_period_for_different_store(): void
    {
        $category = $this->createCategory();
        $this->createBudget(['category_id' => $category->id, 'store_id' => 1]);

        $budget = $this->makeBudgetService()->createBudget([
            'budget_name' => 'Second Store Budget',
            'category_id' => $category->id, 'store_id' => 2, 'period_type' => 'monthly',
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
            'budget_amount' => 500,
        ]);

        $this->assertSame(2, $budget->store_id);
    }

    // =========================================================================
    // updateBudget()
    // =========================================================================

    public function test_update_budget_throws_for_unknown_id(): void
    {
        $this->expectException(\Exception::class);

        $this->makeBudgetService()->updateBudget(999999, ['budget_amount' => 500]);
    }

    public function test_update_budget_throws_on_new_overlap(): void
    {
        $category = $this->createCategory();
        $this->createBudget(['category_id' => $category->id, 'store_id' => 1, 'period_start' => now()->startOfMonth()->toDateString(), 'period_end' => now()->endOfMonth()->toDateString()]);
        $budget = $this->createBudget([
            'category_id' => $category->id, 'store_id' => 1,
            'period_start' => now()->addMonth()->startOfMonth()->toDateString(),
            'period_end' => now()->addMonth()->endOfMonth()->toDateString(),
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('overlap');

        $this->makeBudgetService()->updateBudget($budget->id, [
            'period_start' => now()->startOfMonth()->toDateString(),
        ]);
    }

    public function test_update_budget_succeeds_with_valid_data(): void
    {
        $budget = $this->createBudget(['budget_amount' => 1000]);

        $updated = $this->makeBudgetService()->updateBudget($budget->id, ['budget_amount' => 2000]);

        $this->assertEquals(2000, $updated->budget_amount);
    }

    // =========================================================================
    // deleteBudget()
    // =========================================================================

    public function test_delete_budget_succeeds(): void
    {
        $budget = $this->createBudget();

        $result = $this->makeBudgetService()->deleteBudget($budget->id);

        $this->assertTrue($result);
        $this->assertDatabaseMissing('budgets', ['id' => $budget->id], connection: 'tenant');
    }

    // =========================================================================
    // recalculateBudgetForExpense() / recalculateBudget()
    // =========================================================================

    public function test_recalculate_budget_for_expense_updates_spent_amount(): void
    {
        $category = $this->createCategory();
        $budget = $this->createBudget(['category_id' => $category->id, 'store_id' => 1, 'budget_amount' => 1000]);
        $expense = $this->createApprovedExpense($category->id, 1, 300);

        $this->makeBudgetService()->recalculateBudgetForExpense($expense);

        $fresh = $budget->fresh();
        $this->assertEquals(300, $fresh->spent_amount);
        $this->assertEquals(700, $fresh->remaining_amount);
    }

    public function test_recalculate_budget_for_expense_is_noop_when_no_matching_budget(): void
    {
        $category = $this->createCategory();
        $expense = $this->createApprovedExpense($category->id, 1, 300);

        // No exception, simply no budget to update
        $this->makeBudgetService()->recalculateBudgetForExpense($expense);

        $this->assertTrue(true);
    }

    public function test_recalculate_budget_triggers_alert_when_threshold_crossed(): void
    {
        $category = $this->createCategory();
        $budget = $this->createBudget(['category_id' => $category->id, 'store_id' => 1, 'budget_amount' => 1000, 'alert_threshold_percentage' => 80]);
        $this->createApprovedExpense($category->id, 1, 850);

        $updated = $this->makeBudgetService()->recalculateBudget($budget->id);

        $this->assertTrue($updated->alert_triggered);
        $this->assertNotNull($updated->alert_triggered_at);
    }

    public function test_recalculate_budget_clears_alert_when_spending_drops_back_below_threshold(): void
    {
        // Regression test: recalculate() used to only ever set alert_triggered = true
        // and never clear it — an expense getting deleted/rejected after triggering an
        // alert left the budget permanently flagged even once spending was fine again.
        $category = $this->createCategory();
        $budget = $this->createBudget(['category_id' => $category->id, 'store_id' => 1, 'budget_amount' => 1000, 'alert_threshold_percentage' => 80]);
        $expense = $this->createApprovedExpense($category->id, 1, 850);
        $this->makeBudgetService()->recalculateBudget($budget->id);
        $this->assertTrue($budget->fresh()->alert_triggered);

        $expense->update(['approval_status' => 'rejected']);
        $updated = $this->makeBudgetService()->recalculateBudget($budget->id);

        $this->assertFalse($updated->alert_triggered);
        $this->assertNull($updated->alert_triggered_at);
    }

    // =========================================================================
    // getBudgetExpenses() / getCurrentActiveBudgets() / getBudgetsWithAlerts() / getOverBudgetBudgets()
    // =========================================================================

    public function test_get_budget_expenses_returns_only_approved_expenses_in_period(): void
    {
        $category = $this->createCategory();
        $budget = $this->createBudget(['category_id' => $category->id, 'store_id' => 1]);
        $this->createApprovedExpense($category->id, 1, 100);
        Expense::create([
            'expense_number' => 'EXP-PENDING-'.uniqid(),
            'category_id' => $category->id, 'store_id' => 1, 'amount' => 200,
            'description' => 'Pending', 'expense_date' => now()->toDateString(),
            'payment_method' => 'cash', 'approval_status' => 'pending', 'created_by' => 1,
        ]);

        $result = $this->makeBudgetService()->getBudgetExpenses($budget->id);

        $this->assertCount(1, $result);
    }

    public function test_get_current_active_budgets_returns_budgets_within_today(): void
    {
        $this->createBudget(['period_start' => now()->subMonth()->toDateString(), 'period_end' => now()->addMonth()->toDateString()]);
        $this->createBudget(['period_start' => now()->addMonths(2)->toDateString(), 'period_end' => now()->addMonths(3)->toDateString()]);

        $result = $this->makeBudgetService()->getCurrentActiveBudgets();

        $this->assertCount(1, $result);
    }

    public function test_get_budgets_with_alerts_filters_correctly(): void
    {
        $category = $this->createCategory();
        $budget = $this->createBudget(['category_id' => $category->id, 'store_id' => 1, 'budget_amount' => 1000]);
        $this->createBudget();
        $this->createApprovedExpense($category->id, 1, 900);
        $this->makeBudgetService()->recalculateBudget($budget->id);

        $result = $this->makeBudgetService()->getBudgetsWithAlerts();

        $this->assertCount(1, $result);
    }

    public function test_get_over_budget_budgets_filters_correctly(): void
    {
        $category = $this->createCategory();
        $budget = $this->createBudget(['category_id' => $category->id, 'store_id' => 1, 'budget_amount' => 500]);
        $this->createBudget();
        $this->createApprovedExpense($category->id, 1, 600);
        $this->makeBudgetService()->recalculateBudget($budget->id);

        $result = $this->makeBudgetService()->getOverBudgetBudgets();

        $this->assertCount(1, $result);
    }

    // =========================================================================
    // Schema helpers
    // =========================================================================

    private function dropTestTables(): void
    {
        foreach (['budgets', 'expenses', 'expense_categories', 'users', 'stores'] as $table) {
            Schema::connection('tenant')->dropIfExists($table);
        }
    }

    private function createMinimalSchema(): void
    {
        $conn = 'tenant';

        Schema::connection($conn)->create('stores', function (Blueprint $table) {
            $table->id();
            $table->string('name')->default('Test Store');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::connection($conn)->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->timestamps();
        });

        Schema::connection($conn)->create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->text('description')->nullable();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->boolean('is_recurring_eligible')->default(false);
            $table->boolean('requires_receipt')->default(false);
            $table->boolean('requires_approval')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('display_order')->default(0);
            $table->timestamps();
        });

        Schema::connection($conn)->create('expenses', function (Blueprint $table) {
            $table->id();
            $table->string('expense_number')->unique();
            $table->unsignedBigInteger('store_id')->nullable();
            $table->unsignedBigInteger('category_id');
            $table->decimal('amount', 15, 2);
            $table->text('description');
            $table->date('expense_date');
            $table->string('payment_method');
            $table->string('payment_reference')->nullable();
            $table->string('payment_status')->default('pending');
            $table->string('receipt_path')->nullable();
            $table->string('receipt_number')->nullable();
            $table->boolean('is_recurring')->default(false);
            $table->string('recurrence_frequency')->nullable();
            $table->integer('recurrence_interval')->default(1);
            $table->date('recurrence_start_date')->nullable();
            $table->date('recurrence_end_date')->nullable();
            $table->date('next_occurrence_date')->nullable();
            $table->unsignedBigInteger('parent_expense_id')->nullable();
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->string('approval_status')->default('approved');
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::connection($conn)->create('budgets', function (Blueprint $table) {
            $table->id();
            $table->string('budget_name');
            $table->unsignedBigInteger('store_id')->nullable();
            $table->unsignedBigInteger('category_id');
            $table->string('period_type');
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('budget_amount', 15, 2);
            $table->decimal('spent_amount', 15, 2)->default(0);
            $table->decimal('remaining_amount', 15, 2)->default(0);
            $table->decimal('committed_amount', 15, 2)->default(0);
            $table->decimal('alert_threshold_percentage', 5, 2)->default(80);
            $table->boolean('alert_triggered')->default(false);
            $table->timestamp('alert_triggered_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
    }
}
