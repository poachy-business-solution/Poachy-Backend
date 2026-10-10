<?php

namespace Tests\Feature\Tenant\Drawer;

use App\Enums\Tenant\ExpenseStatus;
use App\Events\Tenant\ShiftStarted;
use App\Http\Requests\Tenant\Drawer\RecordCashMovementRequest;
use App\Models\Tenant\CashMovement;
use App\Models\Tenant\ExpenseCategory;
use App\Models\Tenant\User;
use App\Services\Tenant\Drawer\DrawerService;
use App\Services\Tenant\Expenses\ExpenseService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Tenant\Concerns\InteractsWithTenantAuthorization;
use Tests\TestCase;

class DrawerServiceTest extends TestCase
{
    use InteractsWithTenantAuthorization;

    private const TABLES = [
        'cash_movements', 'budgets', 'expenses', 'expense_categories', 'shift_sales_summary',
        'shift_assignments', 'shifts', 'sale_payments', 'sale_refunds', 'sales', 'tenant_configurations', 'audit_logs',
    ];

    private User $owner;

    private User $cashier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenantAuthorization();
        foreach (self::TABLES as $table) {
            Schema::connection('tenant')->dropIfExists($table);
        }
        $this->createDrawerSchema();

        Cache::tags(['tenant', 'test-tenant', 'shift_assignments'])->flush();
        Cache::tags(['tenant', 'test-tenant', 'config'])->flush();
        Event::fake([ShiftStarted::class]);
        Storage::fake('public');
        Carbon::setTestNow('2026-10-10 09:00:00');

        DB::connection('tenant')->table('stores')->insert([
            'id' => 1, 'name' => 'CBD', 'is_active' => true, 'is_main_store' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->owner = $this->makeTenantUserWithRole('owner', ['manage-expenses', 'record-expenses']);
        $this->cashier = $this->makeTenantUserWithRole('cashier', ['record-expenses']);
        ExpenseCategory::query()->create(['id' => 1, 'name' => 'Transport', 'code' => 'TRANSPORT', 'is_active' => true]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        foreach (self::TABLES as $table) {
            Schema::connection('tenant')->dropIfExists($table);
        }
        $this->tearDownTenantAuthorization();
        parent::tearDown();
    }

    public function test_a_cashier_till_cash_expense_waits_for_approval_and_lowers_expected_cash(): void
    {
        $session = $this->sellCash($this->cashier, 1000);

        $expense = $this->recordExpense($this->cashier, 300, 'till_cash');

        $this->assertSame(ExpenseStatus::PENDING, $expense->approval_status);
        $this->assertSame($session->id, $expense->shift_assignment_id);
        $this->assertSame('Transport', $expense->description);
        $this->assertEquals(700.0, $session->fresh()->expected_cash);
    }

    public function test_an_owner_expense_is_approved_at_once(): void
    {
        $expense = $this->recordExpense($this->owner, 150, 'till_cash');

        $this->assertSame(ExpenseStatus::APPROVED, $expense->approval_status);
        $this->assertSame($this->owner->id, $expense->approved_by);
    }

    public function test_expenses_not_paid_from_the_till_leave_the_drawer_alone(): void
    {
        $session = $this->sellCash($this->cashier, 1000);

        $mpesa = $this->recordExpense($this->cashier, 200, 'mpesa');
        $pocket = $this->recordExpense($this->cashier, 50, 'own_pocket');

        $this->assertNull($mpesa->shift_assignment_id);
        $this->assertSame('mpesa', $mpesa->payment_method->value);
        $this->assertSame('other', $pocket->payment_method->value);
        $this->assertEquals(1000.0, $session->fresh()->expected_cash);
    }

    public function test_a_rejected_till_expense_comes_back_into_expected_cash(): void
    {
        $session = $this->sellCash($this->cashier, 1000);
        $expense = $this->recordExpense($this->cashier, 300, 'till_cash');

        Auth::setUser($this->owner);
        app(ExpenseService::class)->rejectExpense($expense->id, 'Not a shop cost');

        $this->assertEquals(1000.0, $session->fresh()->expected_cash);
    }

    public function test_cash_put_in_and_taken_out_moves_expected_cash(): void
    {
        $session = $this->sellCash($this->owner, 1000);
        $drawer = app(DrawerService::class);

        $drawer->recordCashMovement($this->owner, ['store_id' => 1, 'reason' => 'float_top_up', 'amount' => 500]);
        $drawer->recordCashMovement($this->owner, ['store_id' => 1, 'reason' => 'owner_drawing', 'amount' => 1000]);
        $out = $drawer->recordCashMovement($this->owner, ['store_id' => 1, 'reason' => 'supplier_payment', 'amount' => 200, 'note' => 'Bread delivery']);

        $this->assertSame(CashMovement::DIRECTION_OUT, $out->direction);
        $this->assertSame($session->id, $out->shift_assignment_id);
        $this->assertEquals(300.0, $session->fresh()->expected_cash);
    }

    public function test_drawer_money_starts_a_till_if_none_is_open(): void
    {
        $movement = app(DrawerService::class)->recordCashMovement($this->cashier, ['store_id' => 1, 'reason' => 'float_top_up', 'amount' => 2000]);

        $this->assertNotNull($movement->shift_assignment_id);
        $this->assertEquals(2000.0, $movement->shiftAssignment->expected_cash);
    }

    public function test_only_owners_and_managers_record_taking_cash_home(): void
    {
        $this->assertFalse($this->cashMovementRequest($this->cashier, 'owner_drawing')->authorize());
        $this->assertTrue($this->cashMovementRequest($this->cashier, 'float_top_up')->authorize());
        $this->assertTrue($this->cashMovementRequest($this->owner, 'owner_drawing')->authorize());
    }

    public function test_a_receipt_photo_is_kept_with_the_expense(): void
    {
        $expense = app(DrawerService::class)->recordExpense(
            $this->cashier,
            ['store_id' => 1, 'category_id' => 1, 'amount' => 80, 'paid_from' => 'mpesa'],
            UploadedFile::fake()->image('receipt.jpg'),
        );

        $this->assertNotNull($expense->receipt_path);
        Storage::disk('public')->assertExists($expense->receipt_path);
    }

    private function recordExpense(User $user, float $amount, string $paidFrom): \App\Models\Tenant\Expense
    {
        Auth::setUser($user);

        return app(DrawerService::class)->recordExpense($user, [
            'store_id' => 1, 'category_id' => 1, 'amount' => $amount, 'paid_from' => $paidFrom,
        ]);
    }

    /**
     * A cash sale on the user's (automatically started) till.
     */
    private function sellCash(User $user, float $amount): \App\Models\Tenant\ShiftAssignment
    {
        $session = app(\App\Services\Tenant\Shift\TillService::class)->sessionForSale($user->id, 1);
        $saleId = DB::connection('tenant')->table('sales')->insertGetId([
            'shift_assignment_id' => $session->id, 'total_amount' => $amount, 'amount_paid' => $amount, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::connection('tenant')->table('sale_payments')->insert([
            'sale_id' => $saleId, 'amount' => $amount, 'payment_method' => 'cash', 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $session;
    }

    private function cashMovementRequest(User $user, string $reason): RecordCashMovementRequest
    {
        $request = RecordCashMovementRequest::create('/', 'POST', ['reason' => $reason]);
        $request->setUserResolver(fn () => $user);

        return $request;
    }

    private function createDrawerSchema(): void
    {
        $schema = Schema::connection('tenant');

        $schema->create('tenant_configurations', function (Blueprint $table) {
            $table->id();
            $table->string('config_key')->unique();
            $table->json('config_value')->nullable();
            $table->string('config_type')->nullable();
            $table->string('config_group')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        $schema->create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('auditable_type')->nullable();
            $table->unsignedBigInteger('auditable_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('event')->nullable();
            $table->string('action')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->text('description')->nullable();
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
        $schema->create('shifts', function (Blueprint $table) {
            $table->id();
            $table->string('shift_name');
            $table->unsignedBigInteger('store_id')->nullable();
            $table->time('scheduled_start_time');
            $table->time('scheduled_end_time');
            $table->integer('duration_minutes')->default(0);
            $table->boolean('is_active')->default(true);
            $table->json('applicable_days')->nullable();
            $table->timestamps();
        });
        $schema->create('shift_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shift_id');
            $table->unsignedBigInteger('store_id');
            $table->unsignedBigInteger('user_id');
            $table->date('shift_date');
            $table->timestamp('actual_start')->nullable();
            $table->timestamp('actual_end')->nullable();
            $table->integer('actual_duration_minutes')->nullable();
            $table->string('status')->default('scheduled');
            $table->boolean('started_automatically')->default(false);
            $table->boolean('closed_automatically')->default(false);
            $table->decimal('opening_cash', 15, 2)->nullable();
            $table->string('opening_cash_source', 20)->nullable();
            $table->decimal('closing_cash', 15, 2)->nullable();
            $table->decimal('cash_banked', 15, 2)->nullable();
            $table->string('cash_variance_reason')->nullable();
            $table->text('notes')->nullable();
            $table->text('issues_reported')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });
        $schema->create('shift_sales_summary', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shift_assignment_id');
            $table->timestamps();
            $table->softDeletes();
        });
        $schema->create('sales', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shift_assignment_id')->nullable();
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->decimal('amount_paid', 15, 2)->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
        $schema->create('sale_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sale_id');
            $table->decimal('amount', 15, 2);
            $table->string('payment_method');
            $table->timestamps();
        });
        $schema->create('sale_refunds', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id');
            $table->unsignedBigInteger('processed_by')->nullable();
            $table->string('refund_method');
            $table->string('status');
            $table->decimal('refund_amount', 15, 2);
            $table->timestamps();
        });
        $schema->create('expense_categories', function (Blueprint $table) {
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
        $schema->create('expenses', function (Blueprint $table) {
            $table->id();
            $table->string('expense_number')->unique();
            $table->unsignedBigInteger('store_id')->nullable();
            $table->unsignedBigInteger('shift_assignment_id')->nullable();
            $table->unsignedBigInteger('category_id');
            $table->decimal('amount', 15, 2);
            $table->text('description');
            $table->date('expense_date');
            $table->string('payment_method');
            $table->string('paid_from', 20)->nullable();
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
        $schema->create('budgets', function (Blueprint $table) {
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
        $schema->create('cash_movements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id');
            $table->unsignedBigInteger('shift_assignment_id')->nullable();
            $table->unsignedBigInteger('recorded_by');
            $table->string('direction', 3);
            $table->string('reason', 30);
            $table->decimal('amount', 15, 2);
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->string('note', 500)->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
        });
    }
}
