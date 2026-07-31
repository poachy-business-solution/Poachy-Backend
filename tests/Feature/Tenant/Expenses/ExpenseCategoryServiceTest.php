<?php

namespace Tests\Feature\Tenant\Expenses;

use App\Models\Tenant\Expense;
use App\Models\Tenant\ExpenseCategory;
use App\Repositories\Tenant\ExpenseCategoryRepository;
use App\Services\Tenant\Expenses\ExpenseCategoryService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Stancl\Tenancy\Contracts\Tenant as TenantContract;
use Tests\TestCase;

class ExpenseCategoryServiceTest extends TestCase
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

        Cache::tags(['tenant', 'test-tenant', 'expense_categories'])->flush();
    }

    protected function tearDown(): void
    {
        $this->dropTestTables();
        DB::connection('tenant')->statement('SET foreign_key_checks = 1');
        parent::tearDown();
    }

    private function makeService(): ExpenseCategoryService
    {
        return new ExpenseCategoryService(new ExpenseCategoryRepository);
    }

    private function createCategory(array $overrides = []): ExpenseCategory
    {
        return ExpenseCategory::create(array_merge([
            'name' => 'Category '.uniqid(),
            'code' => 'CODE-'.uniqid(),
        ], $overrides));
    }

    // =========================================================================
    // createCategory()
    // =========================================================================

    public function test_create_category_generates_code_when_not_provided(): void
    {
        $category = $this->makeService()->createCategory(['name' => 'Office Supplies']);

        $this->assertSame('OFFICE_SUPPLIES', $category->code);
    }

    public function test_create_category_uppercases_provided_code(): void
    {
        $category = $this->makeService()->createCategory(['name' => 'Rent', 'code' => 'rent']);

        $this->assertSame('RENT', $category->code);
    }

    public function test_create_category_dedupes_generated_code_on_collision(): void
    {
        $this->createCategory(['name' => 'Utilities', 'code' => 'UTILITIES']);

        $category = $this->makeService()->createCategory(['name' => 'Utilities']);

        $this->assertSame('UTILITIES_1', $category->code);
    }

    public function test_create_category_throws_when_parent_not_found(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Parent category not found');

        $this->makeService()->createCategory(['name' => 'Sub', 'code' => 'SUB', 'parent_id' => 999999]);
    }

    public function test_create_category_throws_when_parent_inactive(): void
    {
        $parent = $this->createCategory(['is_active' => false]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('inactive category as parent');

        $this->makeService()->createCategory(['name' => 'Sub', 'code' => 'SUB', 'parent_id' => $parent->id]);
    }

    public function test_create_category_succeeds_with_active_parent(): void
    {
        $parent = $this->createCategory(['is_active' => true]);

        $child = $this->makeService()->createCategory(['name' => 'Sub', 'code' => 'SUB', 'parent_id' => $parent->id]);

        $this->assertSame($parent->id, $child->parent_id);
    }

    // =========================================================================
    // updateCategory()
    // =========================================================================

    public function test_update_category_throws_for_unknown_id(): void
    {
        $this->expectException(\Exception::class);

        $this->makeService()->updateCategory(999999, ['name' => 'X']);
    }

    public function test_update_category_uppercases_code(): void
    {
        $category = $this->createCategory();

        $updated = $this->makeService()->updateCategory($category->id, ['code' => 'newcode']);

        $this->assertSame('NEWCODE', $updated->code);
    }

    public function test_update_category_throws_on_circular_reference(): void
    {
        $grandparent = $this->createCategory();
        $parent = $this->createCategory(['parent_id' => $grandparent->id]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('circular reference');

        $this->makeService()->updateCategory($grandparent->id, ['parent_id' => $parent->id]);
    }

    public function test_update_category_allows_unrelated_parent_change(): void
    {
        $categoryA = $this->createCategory();
        $categoryB = $this->createCategory();

        $updated = $this->makeService()->updateCategory($categoryB->id, ['parent_id' => $categoryA->id]);

        $this->assertSame($categoryA->id, $updated->parent_id);
    }

    // =========================================================================
    // deleteCategory()
    // =========================================================================

    public function test_delete_category_throws_when_it_has_expenses(): void
    {
        $category = $this->createCategory();
        Expense::create([
            'expense_number' => 'EXP-TEST-'.uniqid(),
            'category_id' => $category->id,
            'amount' => 100,
            'description' => 'Test expense',
            'expense_date' => now()->toDateString(),
            'payment_method' => 'cash',
            'created_by' => 1,
        ]);

        $this->expectException(\Exception::class);

        $this->makeService()->deleteCategory($category->id);
    }

    public function test_delete_category_throws_when_it_has_children(): void
    {
        $parent = $this->createCategory();
        $this->createCategory(['parent_id' => $parent->id]);

        $this->expectException(\Exception::class);

        $this->makeService()->deleteCategory($parent->id);
    }

    public function test_delete_category_succeeds_when_clean(): void
    {
        $category = $this->createCategory();

        $result = $this->makeService()->deleteCategory($category->id);

        $this->assertTrue($result);
        $this->assertDatabaseMissing('expense_categories', ['id' => $category->id], connection: 'tenant');
    }

    // =========================================================================
    // toggleActiveStatus()
    // =========================================================================

    public function test_toggle_active_status_flips_both_directions(): void
    {
        $category = $this->createCategory(['is_active' => true]);
        $service = $this->makeService();

        $off = $service->toggleActiveStatus($category->id);
        $this->assertFalse($off->is_active);

        $on = $service->toggleActiveStatus($category->id);
        $this->assertTrue($on->is_active);
    }

    // =========================================================================
    // getCategoryChildren() / getRecurringEligibleCategories()
    // =========================================================================

    public function test_get_category_children_returns_direct_children_only(): void
    {
        $parent = $this->createCategory();
        $child = $this->createCategory(['parent_id' => $parent->id]);
        $this->createCategory(['parent_id' => $child->id]); // grandchild

        $result = $this->makeService()->getCategoryChildren($parent->id);

        $this->assertCount(1, $result);
        $this->assertSame($child->id, $result->first()->id);
    }

    public function test_get_recurring_eligible_categories_filters_correctly(): void
    {
        $this->createCategory(['is_recurring_eligible' => true]);
        $this->createCategory(['is_recurring_eligible' => false]);

        $result = $this->makeService()->getRecurringEligibleCategories();

        $this->assertCount(1, $result);
    }

    // =========================================================================
    // reorderCategories()
    // =========================================================================

    public function test_reorder_categories_updates_display_order(): void
    {
        $categoryA = $this->createCategory(['display_order' => 0]);
        $categoryB = $this->createCategory(['display_order' => 1]);

        $this->makeService()->reorderCategories([
            $categoryA->id => 2,
            $categoryB->id => 1,
        ]);

        $this->assertSame(2, $categoryA->fresh()->display_order);
        $this->assertSame(1, $categoryB->fresh()->display_order);
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
