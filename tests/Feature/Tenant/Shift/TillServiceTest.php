<?php

namespace Tests\Feature\Tenant\Shift;

use App\Enums\Tenant\ShiftStatus;
use App\Events\Tenant\ShiftStarted;
use App\Models\Tenant\Shift;
use App\Models\Tenant\ShiftAssignment;
use App\Services\Tenant\Shift\ShiftAssignmentService;
use App\Services\Tenant\Shift\TillService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Stancl\Tenancy\Contracts\Tenant as TenantContract;
use Tests\TestCase;

class TillServiceTest extends TestCase
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

        $fakeTenant = new \stdClass;
        $fakeTenant->id = 'test-tenant';
        app()->instance(TenantContract::class, $fakeTenant);

        Cache::tags(['tenant', 'test-tenant', 'shift_assignments'])->flush();
        Event::fake([ShiftStarted::class]);

        DB::connection('tenant')->table('stores')->insert([
            ['id' => 1, 'name' => 'Main Store', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'Second Store', 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::connection('tenant')->table('users')->insert([
            ['id' => 1, 'name' => 'Cashier One', 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Mid-morning, well past any 08:00 shift's 75-minute clock-in window.
        Carbon::setTestNow('2026-10-08 11:30:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        $this->dropTestTables();
        DB::connection('tenant')->statement('SET foreign_key_checks = 1');
        parent::tearDown();
    }

    private function makeService(): TillService
    {
        return new TillService(new ShiftAssignmentService);
    }

    public function test_opens_a_till_with_nothing_scheduled(): void
    {
        $result = $this->makeService()->open(1, 1, 2000.0);

        $assignment = $result['assignment'];
        $this->assertFalse($result['already_open']);
        $this->assertSame(ShiftStatus::IN_PROGRESS, $assignment->status);
        $this->assertEquals(2000.0, (float) $assignment->opening_cash);
        $this->assertSame(TillService::TILL_SHIFT_NAME, $assignment->shift->shift_name);
        $this->assertSame('2026-10-08', $assignment->shift_date->toDateString());
    }

    public function test_reuses_one_till_shift_definition_per_store(): void
    {
        $this->makeService()->open(1, 1, 0.0);
        DB::connection('tenant')->table('users')->insert(['id' => 2, 'name' => 'Cashier Two', 'created_at' => now(), 'updated_at' => now()]);
        $this->makeService()->open(2, 1, 0.0);

        $this->assertSame(1, Shift::query()->where('shift_name', TillService::TILL_SHIFT_NAME)->count());
    }

    public function test_uses_a_scheduled_assignment_even_after_its_clock_in_window(): void
    {
        $shift = Shift::create([
            'shift_name' => 'Morning',
            'store_id' => 1,
            'scheduled_start_time' => '08:00',
            'scheduled_end_time' => '16:00',
        ]);
        $scheduled = ShiftAssignment::create([
            'shift_id' => $shift->id,
            'store_id' => 1,
            'user_id' => 1,
            'shift_date' => '2026-10-08',
            'status' => ShiftStatus::SCHEDULED,
        ]);

        $result = $this->makeService()->open(1, 1, 500.0);

        $this->assertSame($scheduled->id, $result['assignment']->id);
        $this->assertSame(ShiftStatus::IN_PROGRESS, $result['assignment']->status);
        $this->assertSame(0, Shift::query()->where('shift_name', TillService::TILL_SHIFT_NAME)->count());
    }

    public function test_opening_twice_at_the_same_store_returns_the_open_till(): void
    {
        $first = $this->makeService()->open(1, 1, 2000.0);
        $second = $this->makeService()->open(1, 1, 9999.0);

        $this->assertTrue($second['already_open']);
        $this->assertSame($first['assignment']->id, $second['assignment']->id);
        $this->assertEquals(2000.0, (float) $second['assignment']->opening_cash);
    }

    public function test_refuses_a_second_till_at_another_store(): void
    {
        $this->makeService()->open(1, 1, 0.0);

        try {
            $this->makeService()->open(1, 2, 0.0);
            $this->fail('Expected a validation error.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Main Store', $e->errors()['store_id'][0]);
        }
    }

    public function test_refuses_to_reopen_a_till_closed_today(): void
    {
        $opened = $this->makeService()->open(1, 1, 0.0)['assignment'];
        $opened->update(['status' => ShiftStatus::COMPLETED, 'closing_cash' => 0, 'actual_end' => now()]);

        $this->expectException(ValidationException::class);
        $this->makeService()->open(1, 1, 0.0);
    }

    public function test_route_is_registered_for_any_signed_in_user(): void
    {
        $route = collect(RouteFacade::getRoutes())->first(
            fn ($route) => $route->uri() === 'api/v1/tenant/tills/open' && in_array('POST', $route->methods(), true)
        );

        $this->assertNotNull($route);
        $this->assertNotContains('role:owner|manager|admin,tenant', $route->middleware());
    }

    private function dropTestTables(): void
    {
        foreach (['shift_sales_summary', 'shift_assignments', 'shifts', 'sale_payments', 'sales', 'users', 'stores'] as $table) {
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

        Schema::connection($conn)->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->timestamps();
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

            $table->unique(['user_id', 'shift_date', 'shift_id'], 'unique_user_shift_per_day');
        });

        Schema::connection($conn)->create('sales', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shift_assignment_id')->nullable();
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
}
