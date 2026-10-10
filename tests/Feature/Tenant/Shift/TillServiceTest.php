<?php

namespace Tests\Feature\Tenant\Shift;

use App\Enums\Tenant\ShiftStatus;
use App\Events\Tenant\ShiftStarted;
use App\Http\Requests\Tenant\Shift\ClockOutRequest;
use App\Models\Tenant\Shift;
use App\Models\Tenant\ShiftAssignment;
use App\Models\Tenant\User;
use App\Services\Tenant\Shift\ShiftAssignmentService;
use App\Services\Tenant\Shift\TillService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Stancl\Tenancy\Contracts\Tenant as TenantContract;
use Tests\Feature\Tenant\Concerns\CreatesDrawerCashTables;
use Tests\TestCase;

class TillServiceTest extends TestCase
{
    use CreatesDrawerCashTables;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'tenant');
        Config::set('database.connections.tenant.database', 'poachy_test');
        DB::purge('tenant');
        DB::connection('tenant')->statement('SET foreign_key_checks = 0');

        $this->dropDrawerCashTables();

        $this->dropTestTables();
        $this->createMinimalSchema();
        $this->createDrawerCashTables();

        $fakeTenant = new \stdClass;
        $fakeTenant->id = 'test-tenant';
        app()->instance(TenantContract::class, $fakeTenant);

        Cache::tags(['tenant', 'test-tenant', 'shift_assignments'])->flush();
        Cache::tags(['tenant', 'test-tenant', 'config'])->flush();
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

    public function test_a_closed_till_lets_a_new_session_start_the_same_day(): void
    {
        $opened = $this->makeService()->open(1, 1, 1000.0)['assignment'];
        $opened->update(['status' => ShiftStatus::COMPLETED, 'closing_cash' => 1200, 'cash_banked' => 700, 'actual_end' => now()]);

        Carbon::setTestNow('2026-10-08 13:00:00');
        $second = $this->makeService()->open(1, 1, null)['assignment'];

        $this->assertNotSame($opened->id, $second->id);
        $this->assertSame(ShiftStatus::IN_PROGRESS, $second->status);
        $this->assertEquals(500.0, (float) $second->opening_cash);
        $this->assertSame(ShiftAssignment::FLOAT_CARRIED_FORWARD, $second->opening_cash_source);
    }

    public function test_first_sale_starts_a_till_with_the_float_not_counted(): void
    {
        $session = $this->makeService()->sessionForSale(1, 1);

        $this->assertSame(ShiftStatus::IN_PROGRESS, $session->status);
        $this->assertTrue($session->started_automatically);
        $this->assertNull($session->opening_cash);
        $this->assertSame(ShiftAssignment::FLOAT_NOT_COUNTED, $session->opening_cash_source);
        $this->assertFalse($session->is_float_known);

        $this->recordCashSale($session->id, 300, 500);
        $this->assertEquals(300.0, $session->fresh()->expected_cash);
        $session->update(['closing_cash' => 250]);
        $this->assertNull($session->fresh()->cash_variance, 'No variance against an unknown float');
    }

    public function test_later_sales_join_the_open_session(): void
    {
        $first = $this->makeService()->sessionForSale(1, 1);
        $second = $this->makeService()->sessionForSale(1, 1);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, ShiftAssignment::query()->count());
    }

    public function test_float_carries_forward_counted_minus_banked(): void
    {
        DB::connection('tenant')->table('users')->insert(['id' => 2, 'name' => 'Cashier Two', 'created_at' => now(), 'updated_at' => now()]);
        $morning = $this->makeService()->open(1, 1, 2000.0)['assignment'];
        $morning->update(['status' => ShiftStatus::COMPLETED, 'closing_cash' => 5400, 'cash_banked' => 4000, 'actual_end' => now()]);

        $next = $this->makeService()->sessionForSale(2, 1);

        $this->assertEquals(1400.0, (float) $next->opening_cash);
        $this->assertSame(ShiftAssignment::FLOAT_CARRIED_FORWARD, $next->opening_cash_source);
        $this->assertTrue($next->is_float_known);
    }

    public function test_an_uncounted_close_carries_its_expected_cash_as_an_estimate(): void
    {
        $morning = $this->makeService()->open(1, 1, 1000.0)['assignment'];
        $this->recordCashSale($morning->id, 400, 400);
        $morning->update(['status' => ShiftStatus::COMPLETED, 'cash_banked' => 200, 'actual_end' => now()]);

        $carried = $this->makeService()->carriedFloat(1);

        $this->assertEquals(1200.0, $carried['amount']);
        $this->assertSame(ShiftAssignment::FLOAT_ESTIMATED, $carried['source']);
    }

    public function test_selling_at_another_branch_closes_the_first_session(): void
    {
        $first = $this->makeService()->sessionForSale(1, 1);
        $second = $this->makeService()->sessionForSale(1, 2);

        $this->assertSame(2, (int) $second->store_id);
        $this->assertSame(ShiftStatus::COMPLETED, $first->fresh()->status);
        $this->assertTrue($first->fresh()->closed_automatically);
    }

    public function test_an_offline_sale_joins_the_session_open_when_it_was_rung(): void
    {
        $session = $this->makeService()->open(1, 1, 500.0)['assignment'];
        $session->update(['status' => ShiftStatus::COMPLETED, 'closing_cash' => 500, 'actual_end' => Carbon::parse('2026-10-08 12:00:00')]);
        Carbon::setTestNow('2026-10-08 15:00:00');

        $found = $this->makeService()->sessionForSale(1, 1, Carbon::parse('2026-10-08 11:45:00'));

        $this->assertSame($session->id, $found->id);
    }

    public function test_an_offline_sale_rung_before_the_open_session_moves_its_start_back(): void
    {
        Carbon::setTestNow('2026-10-08 14:00:00');
        $session = $this->makeService()->sessionForSale(1, 1);

        $found = $this->makeService()->sessionForSale(1, 1, Carbon::parse('2026-10-08 13:10:00'));

        $this->assertSame($session->id, $found->id);
        $this->assertSame('2026-10-08 13:10:00', $found->fresh()->actual_start->toDateTimeString());
    }

    public function test_sessions_left_open_close_at_local_midnight(): void
    {
        $session = $this->makeService()->sessionForSale(1, 1);

        // 21:30 UTC on the 8th is 00:30 on the 9th in Nairobi.
        Carbon::setTestNow('2026-10-08 21:30:00');
        $this->assertSame('2026-10-09', $this->makeService()->localToday());
        $closed = $this->makeService()->autoCloseStaleSessions();

        $session->refresh();
        $this->assertSame(1, $closed);
        $this->assertSame(ShiftStatus::COMPLETED, $session->status);
        $this->assertTrue($session->closed_automatically);
        $this->assertNull($session->closing_cash);
        $this->assertSame('2026-10-08 20:59:59', $session->actual_end->toDateTimeString());
    }

    public function test_the_shop_day_follows_the_shop_timezone(): void
    {
        // 22:00 UTC on the 8th is already the 9th in Nairobi.
        Carbon::setTestNow('2026-10-08 22:00:00');
        $session = $this->makeService()->sessionForSale(1, 1);

        $this->assertSame('2026-10-09', $session->shift_date->toDateString());
    }

    public function test_cash_count_setting_defaults_off_and_can_be_switched_on(): void
    {
        $service = $this->makeService();
        $this->assertFalse($service->cashCountRequired());

        $service->setCashCountRequired(true);

        $this->assertTrue($service->cashCountRequired());
    }

    public function test_closing_without_a_count_is_allowed_unless_the_shop_requires_it(): void
    {
        $session = $this->makeService()->sessionForSale(1, 1);

        $this->assertFalse($this->clockOutValidator($session, [], cashier: true)->fails());

        $this->makeService()->setCashCountRequired(true);
        $validator = $this->clockOutValidator($session, [], cashier: true);
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('closing_cash', $validator->errors()->toArray());

        $this->assertFalse($this->clockOutValidator($session, [], cashier: false)->fails(), 'Owners may always skip');
    }

    public function test_cannot_bank_more_than_was_counted(): void
    {
        $session = $this->makeService()->open(1, 1, 1000.0)['assignment'];

        $validator = $this->clockOutValidator($session, ['closing_cash' => 1000, 'cash_banked' => 1500], cashier: true);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('cash_banked', $validator->errors()->toArray());
    }

    public function test_no_variance_reason_is_asked_for_against_an_unknown_float(): void
    {
        $session = $this->makeService()->sessionForSale(1, 1);
        $this->recordCashSale($session->id, 300, 300);

        $this->assertFalse($this->clockOutValidator($session, ['closing_cash' => 5000], cashier: true)->fails());

        DB::connection('tenant')->table('users')->insert(['id' => 2, 'name' => 'Cashier Two', 'created_at' => now(), 'updated_at' => now()]);
        $counted = $this->makeService()->open(2, 2, 1000.0)['assignment'];
        $this->assertTrue($this->clockOutValidator($counted, ['closing_cash' => 5000], cashier: true)->fails());
    }

    public function test_route_is_registered_for_any_signed_in_user(): void
    {
        $route = collect(RouteFacade::getRoutes())->first(
            fn ($route) => $route->uri() === 'api/v1/tenant/tills/open' && in_array('POST', $route->methods(), true)
        );

        $this->assertNotNull($route);
        $this->assertNotContains('role:owner|manager|admin,tenant', $route->middleware());
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function clockOutValidator(ShiftAssignment $session, array $data, bool $cashier): \Illuminate\Validation\Validator
    {
        $user = \Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('hasAnyRole')->andReturn(! $cashier);

        $request = ClockOutRequest::create('/', 'POST', $data);
        $request->setUserResolver(fn () => $user);
        $request->setRouteResolver(function () use ($session) {
            $route = new Route('POST', 'x/{assignment}', []);
            $route->bind(new Request);
            $route->setParameter('assignment', $session->fresh());

            return $route;
        });

        $validator = Validator::make($request->all(), $request->rules());
        $request->withValidator($validator);
        $validator->passes();

        return $validator;
    }

    private function recordCashSale(int $sessionId, float $total, float $tendered): void
    {
        $saleId = DB::connection('tenant')->table('sales')->insertGetId([
            'shift_assignment_id' => $sessionId,
            'total_amount' => $total,
            'amount_paid' => $tendered,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::connection('tenant')->table('sale_payments')->insert([
            'sale_id' => $saleId,
            'amount' => $tendered,
            'payment_method' => 'cash',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function dropTestTables(): void
    {
        foreach (['shift_sales_summary', 'shift_assignments', 'shifts', 'sale_payments', 'sale_refunds', 'sales', 'users', 'stores', 'tenant_configurations'] as $table) {
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

        Schema::connection($conn)->create('sales', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shift_assignment_id')->nullable();
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->decimal('amount_paid', 15, 2)->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::connection($conn)->create('sale_refunds', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id');
            $table->unsignedBigInteger('processed_by')->nullable();
            $table->string('refund_method');
            $table->string('status');
            $table->decimal('refund_amount', 15, 2);
            $table->timestamps();
        });

        Schema::connection($conn)->create('tenant_configurations', function (Blueprint $table) {
            $table->id();
            $table->string('config_key')->unique();
            $table->json('config_value')->nullable();
            $table->string('config_type')->nullable();
            $table->string('config_group')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
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
