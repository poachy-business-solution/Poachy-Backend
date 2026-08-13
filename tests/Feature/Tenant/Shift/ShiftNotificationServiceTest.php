<?php

namespace Tests\Feature\Tenant\Shift;

use App\Enums\Tenant\ShiftStatus;
use App\Events\Tenant\SaleCompleted;
use App\Jobs\Tenant\SendNotificationJob;
use App\Listeners\Tenant\UpdateShiftSalesSummary;
use App\Models\Tenant\Sale;
use App\Models\Tenant\Shift;
use App\Models\Tenant\ShiftAssignment;
use App\Models\Tenant\Store;
use App\Models\Tenant\User;
use App\Services\Tenant\Sales\ShiftSalesSummaryService;
use App\Services\Tenant\Shift\ShiftAssignmentService;
use App\Services\Tenant\Shift\ShiftNotificationService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Stancl\Tenancy\Contracts\Tenant as TenantContract;
use Tests\TestCase;

class ShiftNotificationServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'tenant');
        Config::set('database.connections.tenant.database', 'poachy_test');
        Config::set('shift.cash_variance_threshold', 100);
        Config::set('shift.send_shift_reminders', true);
        DB::purge('tenant');
        DB::connection('tenant')->statement('SET foreign_key_checks = 0');

        $this->dropTestTables();
        $this->createMinimalSchema();

        $fakeTenant = new \stdClass;
        $fakeTenant->id = 'test-tenant';
        app()->instance(TenantContract::class, $fakeTenant);

        Carbon::setTestNow('2026-07-30 14:00:00');
        Cache::flush();
        Queue::fake();

        $this->seedUsersAndStore();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        $this->dropTestTables();
        DB::connection('tenant')->statement('SET foreign_key_checks = 1');
        Mockery::close();

        parent::tearDown();
    }

    public function test_send_upcoming_shift_reminders_emails_assigned_employee_once(): void
    {
        $shift = $this->createShift(['scheduled_start_time' => '15:00', 'scheduled_end_time' => '23:00']);
        $this->createAssignment($shift, ['shift_date' => now()->toDateString(), 'user_id' => 1]);

        $sent = (new ShiftNotificationService)->sendUpcomingShiftReminders(2);
        $sentAgain = (new ShiftNotificationService)->sendUpcomingShiftReminders(2);

        $this->assertSame(1, $sent);
        $this->assertSame(0, $sentAgain);
        Queue::assertPushed(SendNotificationJob::class, 1);
        $this->assertNotificationQueued('employee@test.com', 'shift_reminder');
    }

    public function test_auto_mark_no_show_notifies_owner_and_store_manager(): void
    {
        $shift = $this->createShift(['scheduled_start_time' => '12:00', 'scheduled_end_time' => '20:00']);
        $this->createAssignment($shift, ['shift_date' => now()->toDateString(), 'user_id' => 1]);

        $count = (new ShiftAssignmentService)->autoMarkNoShow(30);

        $this->assertSame(1, $count);
        $this->assertNotificationQueued('owner@test.com', 'shift_no_show');
        $this->assertNotificationQueued('manager@test.com', 'shift_no_show');
    }

    public function test_clock_out_notifies_approval_needed_and_cash_variance(): void
    {
        $shift = $this->createShift();
        $assignment = $this->createAssignment($shift, [
            'shift_date' => now()->toDateString(),
            'status' => ShiftStatus::IN_PROGRESS,
            'actual_start' => now()->subHours(8),
            'opening_cash' => 500,
        ]);

        (new ShiftAssignmentService)->clockOut($assignment, 700);

        $this->assertNotificationQueued('owner@test.com', 'shift_needs_approval');
        $this->assertNotificationQueued('manager@test.com', 'shift_needs_approval');
        $this->assertNotificationQueued('owner@test.com', 'shift_cash_variance');
        $this->assertNotificationQueued('manager@test.com', 'shift_cash_variance');
    }

    public function test_shift_summary_failure_listener_notifies_owner_and_store_manager(): void
    {
        $shift = $this->createShift();
        $assignment = $this->createAssignment($shift, ['shift_date' => now()->toDateString()]);
        $sale = Model::withoutEvents(fn () => Sale::create([
            'sale_number' => 'SALE-001',
            'store_id' => 1,
            'shift_assignment_id' => $assignment->id,
            'sale_date' => now(),
            'subtotal' => 100,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total_amount' => 100,
            'amount_paid' => 100,
            'amount_due' => 0,
            'payment_status' => 'paid',
            'payment_method' => 'cash',
        ]));

        $listener = new UpdateShiftSalesSummary(Mockery::mock(ShiftSalesSummaryService::class));
        $listener->failed(new SaleCompleted($sale), new \RuntimeException('summary exploded'));

        $this->assertNotificationQueued('owner@test.com', 'shift_summary_failure');
        $this->assertNotificationQueued('manager@test.com', 'shift_summary_failure');
    }

    private function assertNotificationQueued(string $email, string $type): void
    {
        Queue::assertPushed(SendNotificationJob::class, function (SendNotificationJob $job) use ($email, $type) {
            return $job->channel === 'email'
                && $job->recipient === $email
                && $job->metadata['notification_type'] === $type;
        });
    }

    private function seedUsersAndStore(): void
    {
        Model::withoutEvents(function () {
            User::create(['id' => 1, 'name' => 'Employee', 'email' => 'employee@test.com', 'password' => 'secret', 'is_active' => true]);
            User::create(['id' => 2, 'name' => 'Owner', 'email' => 'owner@test.com', 'password' => 'secret', 'is_active' => true]);
            User::create(['id' => 3, 'name' => 'Manager', 'email' => 'manager@test.com', 'password' => 'secret', 'is_active' => true]);
            Store::create(['id' => 1, 'name' => 'Main Store', 'code' => 'MAIN', 'manager_id' => 3]);
        });

        $this->assignRole(2, 'owner');
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

    private function createShift(array $overrides = []): Shift
    {
        return Model::withoutEvents(fn () => Shift::create(array_merge([
            'shift_name' => 'Morning Shift',
            'store_id' => 1,
            'scheduled_start_time' => '08:00',
            'scheduled_end_time' => '16:00',
            'duration_minutes' => 480,
        ], $overrides)));
    }

    private function createAssignment(Shift $shift, array $overrides = []): ShiftAssignment
    {
        return Model::withoutEvents(fn () => ShiftAssignment::create(array_merge([
            'shift_id' => $shift->id,
            'store_id' => 1,
            'user_id' => 1,
            'shift_date' => now()->toDateString(),
            'status' => ShiftStatus::SCHEDULED,
        ], $overrides)));
    }

    private function createMinimalSchema(): void
    {
        $conn = 'tenant';

        Schema::connection($conn)->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
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
            $table->string('name')->default('Test Store');
            $table->string('code')->nullable();
            $table->unsignedBigInteger('manager_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::connection($conn)->create('shifts', function (Blueprint $table) {
            $table->id();
            $table->string('shift_name');
            $table->unsignedBigInteger('store_id')->nullable();
            $table->time('scheduled_start_time');
            $table->time('scheduled_end_time');
            $table->integer('duration_minutes');
            $table->boolean('is_active')->default(true);
            $table->json('applicable_days')->nullable();
            $table->timestamps();
        });

        Schema::connection($conn)->create('shift_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shift_id');
            $table->unsignedBigInteger('store_id');
            $table->unsignedBigInteger('user_id');
            $table->date('shift_date');
            $table->timestamp('actual_start')->nullable();
            $table->timestamp('actual_end')->nullable();
            $table->integer('actual_duration_minutes')->nullable();
            $table->string('status')->default('scheduled');
            $table->decimal('opening_cash', 15, 2)->nullable();
            $table->decimal('closing_cash', 15, 2)->nullable();
            $table->string('cash_variance_reason')->nullable();
            $table->text('notes')->nullable();
            $table->text('issues_reported')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        Schema::connection($conn)->create('sales', function (Blueprint $table) {
            $table->id();
            $table->string('sale_number')->nullable();
            $table->unsignedBigInteger('store_id')->nullable();
            $table->unsignedBigInteger('shift_assignment_id')->nullable();
            $table->timestamp('sale_date')->nullable();
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->decimal('amount_paid', 15, 2)->default(0);
            $table->decimal('amount_due', 15, 2)->default(0);
            $table->string('payment_status')->default('paid');
            $table->string('payment_method')->default('cash');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::connection($conn)->create('sale_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sale_id');
            $table->decimal('amount', 15, 2);
            $table->string('payment_method');
            $table->timestamps();
        });

        Schema::connection($conn)->create('shift_sales_summary', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shift_assignment_id');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    private function dropTestTables(): void
    {
        foreach ([
            'shift_sales_summary',
            'sale_payments',
            'sales',
            'shift_assignments',
            'shifts',
            'stores',
            'model_has_roles',
            'roles',
            'users',
        ] as $table) {
            Schema::connection('tenant')->dropIfExists($table);
        }
    }
}
