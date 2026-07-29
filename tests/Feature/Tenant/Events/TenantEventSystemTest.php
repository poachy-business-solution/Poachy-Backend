<?php

namespace Tests\Feature\Tenant\Events;

use App\Enums\Tenant\BudgetPeriodType;
use App\Enums\Tenant\ExpenseStatus;
use App\Enums\Tenant\PaymentMethod;
use App\Events\Tenant\BudgetAlertTriggered;
use App\Events\Tenant\BudgetExceeded;
use App\Events\Tenant\ExpenseApproved;
use App\Jobs\Tenant\SendNotificationJob;
use App\Listeners\Tenant\Concerns\ResolvesNotificationRecipients;
use App\Listeners\Tenant\NotifyBudgetExceededListener;
use App\Listeners\Tenant\NotifyExpenseApprovedListener;
use App\Models\Tenant\Budget;
use App\Models\Tenant\Expense;
use App\Models\Tenant\ExpenseCategory;
use App\Models\Tenant\Store;
use App\Models\Tenant\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Stancl\Tenancy\Contracts\Tenant as TenantContract;
use Tests\TestCase;

class TenantEventSystemTest extends TestCase
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
    }

    protected function tearDown(): void
    {
        $this->dropTestTables();
        DB::connection('tenant')->statement('SET foreign_key_checks = 1');
        parent::tearDown();
    }

    // =========================================================================
    // ResolvesNotificationRecipients trait
    // =========================================================================

    public function test_get_manager_and_owners_returns_store_manager_plus_owners(): void
    {
        $manager = $this->createUser('Store Manager');
        $owner = $this->createUser('The Owner');
        $this->assignRole($owner->id, 'owner');
        $store = $this->createStore($manager->id);

        $recipients = (new NotificationRecipientsTestDouble)->callGetManagerAndOwners($store->id);

        $this->assertTrue($recipients->contains('id', $manager->id));
        $this->assertTrue($recipients->contains('id', $owner->id));
        $this->assertCount(2, $recipients);
    }

    public function test_get_manager_and_owners_returns_only_owners_for_null_store_id(): void
    {
        $owner = $this->createUser('The Owner');
        $this->assignRole($owner->id, 'owner');

        $recipients = (new NotificationRecipientsTestDouble)->callGetManagerAndOwners(null);

        $this->assertCount(1, $recipients);
        $this->assertTrue($recipients->contains('id', $owner->id));
    }

    public function test_get_single_user_returns_empty_collection_for_null_id(): void
    {
        $recipients = (new NotificationRecipientsTestDouble)->callGetSingleUser(null);

        $this->assertTrue($recipients->isEmpty());
    }

    public function test_get_single_user_returns_the_matching_user(): void
    {
        $user = $this->createUser('Requester');

        $recipients = (new NotificationRecipientsTestDouble)->callGetSingleUser($user->id);

        $this->assertCount(1, $recipients);
        $this->assertSame($user->id, $recipients->first()->id);
    }

    // =========================================================================
    // Listeners
    // =========================================================================

    public function test_notify_budget_exceeded_listener_dispatches_to_manager_and_owners(): void
    {
        Bus::fake();

        $manager = $this->createUser('Store Manager');
        $owner = $this->createUser('The Owner');
        $this->assignRole($owner->id, 'owner');
        $store = $this->createStore($manager->id);
        $category = $this->createExpenseCategory();
        $creator = $this->createUser('Creator');

        $budget = $this->createBudget($store->id, $category->id, $creator->id, [
            'budget_amount' => 1000.00,
            'spent_amount' => 1200.00,
        ]);

        (new NotifyBudgetExceededListener)->handle(new BudgetExceeded($budget));

        Bus::assertDispatched(SendNotificationJob::class, function (SendNotificationJob $job) use ($manager) {
            return $job->recipient === $manager->email;
        });
        Bus::assertDispatched(SendNotificationJob::class, function (SendNotificationJob $job) use ($owner) {
            return $job->recipient === $owner->email;
        });
    }

    public function test_notify_expense_approved_listener_dispatches_to_the_creator_only(): void
    {
        Bus::fake();

        $creator = $this->createUser('Creator');
        $manager = $this->createUser('Store Manager');
        $store = $this->createStore($manager->id);
        $category = $this->createExpenseCategory();

        $expense = $this->createExpense($store->id, $category->id, $creator->id, [
            'approval_status' => ExpenseStatus::APPROVED,
            'approved_by' => $creator->id,
            'approved_at' => now(),
        ]);

        (new NotifyExpenseApprovedListener)->handle(new ExpenseApproved($expense));

        Bus::assertDispatched(SendNotificationJob::class, function (SendNotificationJob $job) use ($creator) {
            return $job->recipient === $creator->email;
        });
        Bus::assertDispatchedTimes(SendNotificationJob::class, 1);
    }

    // =========================================================================
    // Dedup-fix regressions — the actual bugs this pass exists to avoid
    // =========================================================================

    public function test_approving_an_expense_fires_expense_approved_exactly_once(): void
    {
        Event::fake([ExpenseApproved::class]);

        $creator = $this->createUser('Creator');
        $store = $this->createStore($creator->id);
        $category = $this->createExpenseCategory();

        $expense = $this->createExpense($store->id, $category->id, $creator->id, [
            'approval_status' => ExpenseStatus::PENDING,
        ]);

        // Mirrors exactly what ExpenseRepository::approve() does — the single real
        // code path that transitions an expense to approved.
        $expense->update([
            'approval_status' => ExpenseStatus::APPROVED,
            'approved_by' => $creator->id,
            'approved_at' => now(),
        ]);

        Event::assertDispatchedTimes(ExpenseApproved::class, 1);
    }

    public function test_budget_crossing_alert_threshold_fires_budget_alert_triggered_exactly_once(): void
    {
        Event::fake([BudgetAlertTriggered::class]);

        $creator = $this->createUser('Creator');
        $store = $this->createStore($creator->id);
        $category = $this->createExpenseCategory();

        $budget = $this->createBudget($store->id, $category->id, $creator->id, [
            'alert_triggered' => false,
        ]);

        // Mirrors exactly what Budget::recalculate() does when threshold is crossed.
        $budget->update([
            'alert_triggered' => true,
            'alert_triggered_at' => now(),
        ]);

        Event::assertDispatchedTimes(BudgetAlertTriggered::class, 1);
    }

    // =========================================================================
    // Fixture helpers
    // =========================================================================

    private function createUser(string $name): User
    {
        return Model::withoutEvents(fn () => User::create([
            'name' => $name,
            'email' => strtolower(str_replace(' ', '.', $name)).'-'.uniqid().'@test.com',
            'password' => 'secret',
        ]));
    }

    private function assignRole(int $userId, string $roleName): void
    {
        $roleId = DB::connection('tenant')->table('roles')->insertGetId([
            'name' => $roleName,
            'guard_name' => 'tenant',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::connection('tenant')->table('model_has_roles')->insert([
            'role_id' => $roleId,
            'model_type' => User::class,
            'model_id' => $userId,
        ]);
    }

    private function createStore(?int $managerId): Store
    {
        return Model::withoutEvents(fn () => Store::create([
            'name' => 'Test Store '.uniqid(),
            'code' => 'STORE-'.uniqid(),
            'address' => '123 Test Street',
            'manager_id' => $managerId,
        ]));
    }

    private function createExpenseCategory(): ExpenseCategory
    {
        return Model::withoutEvents(fn () => ExpenseCategory::create([
            'name' => 'Test Category',
            'code' => 'CAT-'.uniqid(),
        ]));
    }

    private function createExpense(?int $storeId, int $categoryId, int $createdBy, array $overrides = []): Expense
    {
        return Model::withoutEvents(fn () => Expense::create(array_merge([
            'expense_number' => 'EXP-'.uniqid(),
            'store_id' => $storeId,
            'category_id' => $categoryId,
            'amount' => 500.00,
            'description' => 'Test expense',
            'expense_date' => now()->toDateString(),
            'payment_method' => PaymentMethod::CASH,
            'approval_status' => ExpenseStatus::APPROVED,
            'created_by' => $createdBy,
        ], $overrides)));
    }

    private function createBudget(?int $storeId, int $categoryId, int $createdBy, array $overrides = []): Budget
    {
        return Model::withoutEvents(fn () => Budget::create(array_merge([
            'budget_name' => 'Test Budget '.uniqid(),
            'store_id' => $storeId,
            'category_id' => $categoryId,
            'period_type' => BudgetPeriodType::MONTHLY,
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
            'budget_amount' => 1000.00,
            'spent_amount' => 0.00,
            'alert_threshold_percentage' => 80.00,
            'created_by' => $createdBy,
        ], $overrides)));
    }

    // =========================================================================
    // Schema helpers
    // =========================================================================

    private function dropTestTables(): void
    {
        foreach ([
            'budgets',
            'expenses',
            'expense_categories',
            'model_has_roles',
            'roles',
            'stores',
            'tenant_configurations',
            'users',
        ] as $table) {
            Schema::connection('tenant')->dropIfExists($table);
        }
    }

    private function createMinimalSchema(): void
    {
        $conn = 'tenant';

        Schema::connection($conn)->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password')->default('');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::connection($conn)->create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
        });

        Schema::connection($conn)->create('model_has_roles', function (Blueprint $table) {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->primary(['role_id', 'model_id', 'model_type'], 'model_has_roles_primary');
        });

        Schema::connection($conn)->create('stores', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->text('address');
            $table->unsignedBigInteger('manager_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::connection($conn)->create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->timestamps();
        });

        Schema::connection($conn)->create('expenses', function (Blueprint $table) {
            $table->id();
            $table->string('expense_number')->unique();
            $table->unsignedBigInteger('store_id')->nullable();
            $table->unsignedBigInteger('category_id');
            $table->decimal('amount', 15, 2);
            $table->text('description')->nullable();
            $table->date('expense_date');
            $table->string('payment_method')->default('cash');
            $table->string('payment_status')->default('pending');
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
            $table->string('period_type')->default('monthly');
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
            $table->unsignedBigInteger('created_by');
            $table->timestamps();
        });

        Schema::connection($conn)->create('tenant_configurations', function (Blueprint $table) {
            $table->id();
            $table->string('config_key')->unique();
            $table->json('config_value');
            $table->string('config_type')->default('general');
            $table->string('config_group')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }
}

/**
 * Thin double exposing the protected trait methods for direct unit testing.
 */
class NotificationRecipientsTestDouble
{
    use ResolvesNotificationRecipients;

    public function callGetManagerAndOwners(?int $storeId): Collection
    {
        return $this->getManagerAndOwners($storeId);
    }

    public function callGetSingleUser(?int $userId): Collection
    {
        return $this->getSingleUser($userId);
    }
}
