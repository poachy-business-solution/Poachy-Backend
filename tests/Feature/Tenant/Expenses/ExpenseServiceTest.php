<?php

namespace Tests\Feature\Tenant\Expenses;

use App\Models\Tenant\Budget;
use App\Models\Tenant\ExpenseCategory;
use App\Repositories\Tenant\BudgetRepository;
use App\Repositories\Tenant\ExpenseCategoryRepository;
use App\Repositories\Tenant\ExpenseRepository;
use App\Services\Tenant\Expenses\BudgetService;
use App\Services\Tenant\Expenses\ExpenseService;
use Carbon\Carbon;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Stancl\Tenancy\Contracts\Tenant as TenantContract;
use Tests\TestCase;

class ExpenseServiceTest extends TestCase
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

        DB::connection('tenant')->table('stores')->insert(['id' => 1, 'name' => 'Main Store', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::connection('tenant')->table('users')->insert(['id' => 1, 'name' => 'Employee', 'created_at' => now(), 'updated_at' => now()]);

        Auth::setUser($this->fakeUser());
        Storage::fake('public');
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
        };
    }

    private function makeService(): ExpenseService
    {
        return new ExpenseService(
            new ExpenseRepository,
            new ExpenseCategoryRepository,
            new BudgetService(new BudgetRepository, new ExpenseCategoryRepository),
        );
    }

    private function createCategory(array $overrides = []): ExpenseCategory
    {
        return ExpenseCategory::create(array_merge([
            'name' => 'Category '.uniqid(),
            'code' => 'CODE-'.uniqid(),
        ], $overrides));
    }

    private function baseExpenseData(array $overrides = []): array
    {
        if (! isset($overrides['category_id'])) {
            $overrides['category_id'] = $this->createCategory()->id;
        }

        return array_merge([
            'store_id' => 1,
            'amount' => 500,
            'description' => 'Test expense',
            'expense_date' => now()->toDateString(),
            'payment_method' => 'cash',
        ], $overrides);
    }

    // =========================================================================
    // createExpense()
    // =========================================================================

    public function test_create_expense_auto_resolves_store_when_only_one_active(): void
    {
        $expense = $this->makeService()->createExpense($this->baseExpenseData(['store_id' => null]));

        $this->assertSame(1, $expense->store_id);
    }

    public function test_create_expense_throws_when_multiple_stores_and_none_specified(): void
    {
        DB::connection('tenant')->table('stores')->insert(['id' => 2, 'name' => 'Second Store', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Multiple stores exist');

        $this->makeService()->createExpense($this->baseExpenseData(['store_id' => null]));
    }

    public function test_create_expense_throws_when_category_not_found(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('category not found');

        $this->makeService()->createExpense($this->baseExpenseData(['category_id' => 999999]));
    }

    public function test_create_expense_throws_when_category_inactive(): void
    {
        $category = $this->createCategory(['is_active' => false]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('category is inactive');

        $this->makeService()->createExpense($this->baseExpenseData(['category_id' => $category->id]));
    }

    public function test_create_expense_defaults_to_pending_when_category_requires_approval(): void
    {
        $category = $this->createCategory(['requires_approval' => true]);

        $expense = $this->makeService()->createExpense($this->baseExpenseData(['category_id' => $category->id]));

        $this->assertSame('pending', $expense->approval_status->value);
        $this->assertNull($expense->approved_by);
    }

    public function test_create_expense_auto_approves_when_category_does_not_require_approval(): void
    {
        $category = $this->createCategory(['requires_approval' => false]);

        $expense = $this->makeService()->createExpense($this->baseExpenseData(['category_id' => $category->id]));

        $this->assertSame('approved', $expense->approval_status->value);
        $this->assertSame(1, $expense->approved_by);
        $this->assertNotNull($expense->approved_at);
    }

    public function test_create_expense_updates_budget_when_auto_approved(): void
    {
        $category = $this->createCategory(['requires_approval' => false]);
        Budget::create([
            'budget_name' => 'Test Budget', 'category_id' => $category->id, 'store_id' => 1,
            'period_type' => 'monthly',
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
            'budget_amount' => 1000,
        ]);

        $this->makeService()->createExpense($this->baseExpenseData(['category_id' => $category->id, 'amount' => 400]));

        $budget = Budget::first();
        $this->assertEquals(400, $budget->spent_amount);
    }

    // =========================================================================
    // updateExpense()
    // =========================================================================

    public function test_update_expense_throws_for_unknown_id(): void
    {
        $this->expectException(\Exception::class);

        $this->makeService()->updateExpense(999999, ['amount' => 100]);
    }

    public function test_update_expense_throws_when_not_pending(): void
    {
        $category = $this->createCategory(['requires_approval' => false]);
        $expense = $this->makeService()->createExpense($this->baseExpenseData(['category_id' => $category->id]));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('only pending expenses can be edited');

        $this->makeService()->updateExpense($expense->id, ['amount' => 100]);
    }

    public function test_update_expense_throws_for_non_positive_amount(): void
    {
        $category = $this->createCategory(['requires_approval' => true]);
        $expense = $this->makeService()->createExpense($this->baseExpenseData(['category_id' => $category->id]));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('greater than zero');

        $this->makeService()->updateExpense($expense->id, ['amount' => 0]);
    }

    public function test_update_expense_throws_for_future_date(): void
    {
        $category = $this->createCategory(['requires_approval' => true]);
        $expense = $this->makeService()->createExpense($this->baseExpenseData(['category_id' => $category->id]));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('cannot be in the future');

        $this->makeService()->updateExpense($expense->id, ['expense_date' => now()->addDay()->toDateString()]);
    }

    public function test_update_expense_ignores_non_allowed_fields(): void
    {
        $category = $this->createCategory(['requires_approval' => true]);
        $otherCategory = $this->createCategory();
        $expense = $this->makeService()->createExpense($this->baseExpenseData(['category_id' => $category->id]));

        $updated = $this->makeService()->updateExpense($expense->id, ['category_id' => $otherCategory->id, 'amount' => 750]);

        $this->assertSame($category->id, $updated->category_id);
        $this->assertEquals(750, $updated->amount);
    }

    // =========================================================================
    // approveExpense() / rejectExpense()
    // =========================================================================

    public function test_approve_expense_throws_when_not_pending(): void
    {
        $category = $this->createCategory(['requires_approval' => false]);
        $expense = $this->makeService()->createExpense($this->baseExpenseData(['category_id' => $category->id]));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Only pending expenses can be approved');

        $this->makeService()->approveExpense($expense->id);
    }

    public function test_approve_expense_throws_when_receipt_required_but_missing(): void
    {
        $category = $this->createCategory(['requires_approval' => true, 'requires_receipt' => true]);
        $expense = $this->makeService()->createExpense($this->baseExpenseData(['category_id' => $category->id]));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('requires a receipt');

        $this->makeService()->approveExpense($expense->id);
    }

    public function test_approve_expense_succeeds_and_updates_budget(): void
    {
        $category = $this->createCategory(['requires_approval' => true]);
        Budget::create([
            'budget_name' => 'Test Budget', 'category_id' => $category->id, 'store_id' => 1,
            'period_type' => 'monthly',
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
            'budget_amount' => 1000,
        ]);
        $expense = $this->makeService()->createExpense($this->baseExpenseData(['category_id' => $category->id, 'amount' => 300]));

        $approved = $this->makeService()->approveExpense($expense->id);

        $this->assertSame('approved', $approved->approval_status->value);
        $this->assertEquals(300, Budget::first()->spent_amount);
    }

    public function test_reject_expense_throws_when_reason_empty(): void
    {
        $category = $this->createCategory(['requires_approval' => true]);
        $expense = $this->makeService()->createExpense($this->baseExpenseData(['category_id' => $category->id]));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('reason is required');

        $this->makeService()->rejectExpense($expense->id, '   ');
    }

    public function test_reject_expense_succeeds(): void
    {
        $category = $this->createCategory(['requires_approval' => true]);
        $expense = $this->makeService()->createExpense($this->baseExpenseData(['category_id' => $category->id]));

        $rejected = $this->makeService()->rejectExpense($expense->id, 'Missing documentation');

        $this->assertSame('rejected', $rejected->approval_status->value);
        $this->assertSame('Missing documentation', $rejected->rejection_reason);
    }

    // =========================================================================
    // setRecurrence() / updateRecurrence() / cancelRecurrence()
    // =========================================================================

    public function test_set_recurrence_throws_when_category_not_recurring_eligible(): void
    {
        $category = $this->createCategory(['requires_approval' => false, 'is_recurring_eligible' => false]);
        $expense = $this->makeService()->createExpense($this->baseExpenseData(['category_id' => $category->id]));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('does not allow recurring');

        $this->makeService()->setRecurrence($expense->id, [
            'recurrence_frequency' => 'monthly', 'recurrence_interval' => 1,
            'recurrence_start_date' => now()->toDateString(),
        ]);
    }

    public function test_set_recurrence_computes_next_occurrence_date(): void
    {
        $category = $this->createCategory(['requires_approval' => false, 'is_recurring_eligible' => true]);
        $expense = $this->makeService()->createExpense($this->baseExpenseData(['category_id' => $category->id]));
        $start = now()->startOfMonth()->toDateString();

        $updated = $this->makeService()->setRecurrence($expense->id, [
            'recurrence_frequency' => 'monthly', 'recurrence_interval' => 1,
            'recurrence_start_date' => $start,
        ]);

        $this->assertTrue($updated->is_recurring);
        $this->assertSame(
            now()->startOfMonth()->addMonth()->toDateString(),
            $updated->next_occurrence_date->toDateString(),
        );
    }

    public function test_set_recurrence_throws_when_already_recurring(): void
    {
        $category = $this->createCategory(['requires_approval' => false, 'is_recurring_eligible' => true]);
        $expense = $this->makeService()->createExpense($this->baseExpenseData(['category_id' => $category->id]));
        $this->makeService()->setRecurrence($expense->id, [
            'recurrence_frequency' => 'monthly', 'recurrence_interval' => 1,
            'recurrence_start_date' => now()->toDateString(),
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('already set as recurring');

        $this->makeService()->setRecurrence($expense->id, [
            'recurrence_frequency' => 'weekly', 'recurrence_interval' => 1,
            'recurrence_start_date' => now()->toDateString(),
        ]);
    }

    public function test_update_recurrence_recalculates_next_occurrence_with_new_frequency(): void
    {
        // Regression test: updateRecurrence() used to pass the raw request string
        // straight into calculateNextOccurrence()'s match() (which compares against
        // RecurrenceFrequency enum cases), so setting a new frequency always threw
        // "Invalid recurrence frequency" instead of recalculating.
        $category = $this->createCategory(['requires_approval' => false, 'is_recurring_eligible' => true]);
        $expense = $this->makeService()->createExpense($this->baseExpenseData(['category_id' => $category->id]));
        $this->makeService()->setRecurrence($expense->id, [
            'recurrence_frequency' => 'monthly', 'recurrence_interval' => 1,
            'recurrence_start_date' => now()->startOfMonth()->toDateString(),
        ]);

        $updated = $this->makeService()->updateRecurrence($expense->id, ['recurrence_frequency' => 'weekly']);

        $this->assertSame('weekly', $updated->recurrence_frequency->value);
        $this->assertNotNull($updated->next_occurrence_date);
    }

    public function test_update_recurrence_throws_when_not_a_recurring_parent(): void
    {
        $category = $this->createCategory(['requires_approval' => false]);
        $expense = $this->makeService()->createExpense($this->baseExpenseData(['category_id' => $category->id]));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('not a recurring expense parent');

        $this->makeService()->updateRecurrence($expense->id, ['recurrence_interval' => 2]);
    }

    public function test_cancel_recurrence_stops_future_instances(): void
    {
        $category = $this->createCategory(['requires_approval' => false, 'is_recurring_eligible' => true]);
        $expense = $this->makeService()->createExpense($this->baseExpenseData(['category_id' => $category->id]));
        $this->makeService()->setRecurrence($expense->id, [
            'recurrence_frequency' => 'monthly', 'recurrence_interval' => 1,
            'recurrence_start_date' => now()->toDateString(),
        ]);

        $cancelled = $this->makeService()->cancelRecurrence($expense->id);

        $this->assertFalse($cancelled->is_recurring);
        $this->assertNull($cancelled->next_occurrence_date);
    }

    // =========================================================================
    // generateRecurrenceInstance()
    // =========================================================================

    public function test_generate_recurrence_instance_creates_linked_expense(): void
    {
        $category = $this->createCategory(['requires_approval' => false, 'is_recurring_eligible' => true]);
        $parent = $this->makeService()->createExpense($this->baseExpenseData(['category_id' => $category->id, 'amount' => 200]));
        $parent = $this->makeService()->setRecurrence($parent->id, [
            'recurrence_frequency' => 'monthly', 'recurrence_interval' => 1,
            'recurrence_start_date' => now()->toDateString(),
        ]);

        $instance = $this->makeService()->generateRecurrenceInstance($parent);

        $this->assertSame($parent->id, $instance->parent_expense_id);
        $this->assertEquals(200, $instance->amount);
        $this->assertStringContainsString('Auto-generated', $instance->description);
    }

    public function test_generate_recurrence_instance_throws_when_recurrence_ended(): void
    {
        $category = $this->createCategory(['requires_approval' => false, 'is_recurring_eligible' => true]);
        $parent = $this->makeService()->createExpense($this->baseExpenseData(['category_id' => $category->id]));
        $parent = $this->makeService()->setRecurrence($parent->id, [
            'recurrence_frequency' => 'monthly', 'recurrence_interval' => 1,
            'recurrence_start_date' => now()->subMonths(2)->toDateString(),
            'recurrence_end_date' => now()->subMonth()->toDateString(),
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('has ended');

        $this->makeService()->generateRecurrenceInstance($parent);
    }

    // =========================================================================
    // deleteExpense()
    // =========================================================================

    public function test_delete_expense_throws_for_unknown_id(): void
    {
        $this->expectException(\Exception::class);

        $this->makeService()->deleteExpense(999999);
    }

    public function test_delete_expense_removes_receipt_file(): void
    {
        $category = $this->createCategory(['requires_approval' => true]);
        $expense = $this->makeService()->createExpense($this->baseExpenseData(['category_id' => $category->id]));
        $this->makeService()->uploadReceipt($expense->id, UploadedFile::fake()->create('receipt.pdf', 100, 'application/pdf'));
        $path = $expense->fresh()->receipt_path;

        $this->makeService()->deleteExpense($expense->id);

        Storage::disk('public')->assertMissing($path);
    }

    // =========================================================================
    // uploadReceipt() / deleteReceipt()
    // =========================================================================

    public function test_upload_receipt_throws_for_oversized_file(): void
    {
        $category = $this->createCategory(['requires_approval' => true]);
        $expense = $this->makeService()->createExpense($this->baseExpenseData(['category_id' => $category->id]));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('cannot exceed 5MB');

        $this->makeService()->uploadReceipt($expense->id, UploadedFile::fake()->create('big.pdf', 6000, 'application/pdf'));
    }

    public function test_upload_receipt_throws_for_disallowed_mime(): void
    {
        $category = $this->createCategory(['requires_approval' => true]);
        $expense = $this->makeService()->createExpense($this->baseExpenseData(['category_id' => $category->id]));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('must be a PDF, JPG, or PNG');

        $this->makeService()->uploadReceipt($expense->id, UploadedFile::fake()->create('doc.txt', 10, 'text/plain'));
    }

    public function test_upload_receipt_replaces_old_receipt(): void
    {
        $category = $this->createCategory(['requires_approval' => true]);
        $expense = $this->makeService()->createExpense($this->baseExpenseData(['category_id' => $category->id]));
        $this->makeService()->uploadReceipt($expense->id, UploadedFile::fake()->create('first.pdf', 100, 'application/pdf'));
        $oldPath = $expense->fresh()->receipt_path;

        // Stored filename is expense_number + now()->timestamp (second precision) — advance
        // the clock so the second upload doesn't collide with the first's filename.
        Carbon::setTestNow(now()->addSecond());
        $updated = $this->makeService()->uploadReceipt($expense->id, UploadedFile::fake()->create('second.pdf', 100, 'application/pdf'));
        Carbon::setTestNow();

        $this->assertNotSame($oldPath, $updated->receipt_path);
        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($updated->receipt_path);
    }

    public function test_delete_receipt_throws_when_none_exists(): void
    {
        $category = $this->createCategory(['requires_approval' => true]);
        $expense = $this->makeService()->createExpense($this->baseExpenseData(['category_id' => $category->id]));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('No receipt to delete');

        $this->makeService()->deleteReceipt($expense->id);
    }

    public function test_delete_receipt_removes_file_and_clears_path(): void
    {
        $category = $this->createCategory(['requires_approval' => true]);
        $expense = $this->makeService()->createExpense($this->baseExpenseData(['category_id' => $category->id]));
        $this->makeService()->uploadReceipt($expense->id, UploadedFile::fake()->create('receipt.pdf', 100, 'application/pdf'));

        $updated = $this->makeService()->deleteReceipt($expense->id);

        $this->assertNull($updated->receipt_path);
    }

    // =========================================================================
    // getPendingApproval() / getAnalytics()
    // =========================================================================

    public function test_get_pending_approval_returns_only_pending(): void
    {
        $pendingCategory = $this->createCategory(['requires_approval' => true]);
        $approvedCategory = $this->createCategory(['requires_approval' => false]);
        $this->makeService()->createExpense($this->baseExpenseData(['category_id' => $pendingCategory->id]));
        $this->makeService()->createExpense($this->baseExpenseData(['category_id' => $approvedCategory->id]));

        $result = $this->makeService()->getPendingApproval();

        $this->assertCount(1, $result);
    }

    public function test_get_analytics_aggregates_by_category_and_payment_method(): void
    {
        $category = $this->createCategory(['requires_approval' => false]);
        $this->makeService()->createExpense($this->baseExpenseData(['category_id' => $category->id, 'amount' => 100]));
        $this->makeService()->createExpense($this->baseExpenseData(['category_id' => $category->id, 'amount' => 200]));

        $analytics = $this->makeService()->getAnalytics();

        $this->assertEquals(300, $analytics['total_amount']);
        $this->assertEquals(2, $analytics['total_count']);
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
