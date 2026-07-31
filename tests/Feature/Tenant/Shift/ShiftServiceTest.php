<?php

namespace Tests\Feature\Tenant\Shift;

use App\Models\Tenant\Shift;
use App\Models\Tenant\ShiftAssignment;
use App\Services\Tenant\Shift\ShiftService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Stancl\Tenancy\Contracts\Tenant as TenantContract;
use Tests\TestCase;

class ShiftServiceTest extends TestCase
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

        Cache::tags(['tenant', 'test-tenant', 'shifts'])->flush();

        DB::connection('tenant')->table('stores')->insert(['id' => 1, 'name' => 'Main Store', 'created_at' => now(), 'updated_at' => now()]);
        DB::connection('tenant')->table('stores')->insert(['id' => 2, 'name' => 'Second Store', 'created_at' => now(), 'updated_at' => now()]);
        DB::connection('tenant')->table('users')->insert(['id' => 1, 'name' => 'Employee One', 'created_at' => now(), 'updated_at' => now()]);
    }

    protected function tearDown(): void
    {
        $this->dropTestTables();
        DB::connection('tenant')->statement('SET foreign_key_checks = 1');
        parent::tearDown();
    }

    private function makeService(): ShiftService
    {
        return new ShiftService();
    }

    private function createShift(array $overrides = []): Shift
    {
        return Shift::create(array_merge([
            'shift_name' => 'Morning Shift '.uniqid(),
            'store_id' => 1,
            'scheduled_start_time' => '08:00',
            'scheduled_end_time' => '16:00',
        ], $overrides));
    }

    // =========================================================================
    // createShift()
    // =========================================================================

    public function test_create_shift_computes_duration_minutes(): void
    {
        $shift = $this->makeService()->createShift([
            'shift_name' => 'Day Shift',
            'store_id' => 1,
            'scheduled_start_time' => '08:00',
            'scheduled_end_time' => '16:00',
        ]);

        $this->assertSame(480, $shift->duration_minutes);
    }

    public function test_create_shift_computes_duration_for_overnight_shift(): void
    {
        $shift = $this->makeService()->createShift([
            'shift_name' => 'Night Shift',
            'store_id' => 1,
            'scheduled_start_time' => '22:00',
            'scheduled_end_time' => '06:00',
        ]);

        $this->assertSame(480, $shift->duration_minutes);
    }

    public function test_create_shift_allows_null_store_id_for_company_wide_shift(): void
    {
        $shift = $this->makeService()->createShift([
            'shift_name' => 'Company Wide Shift',
            'store_id' => null,
            'scheduled_start_time' => '09:00',
            'scheduled_end_time' => '17:00',
        ]);

        $this->assertTrue($shift->is_company_wide);
    }

    // =========================================================================
    // updateShift()
    // =========================================================================

    public function test_update_shift_recalculates_duration_when_times_change(): void
    {
        $shift = $this->createShift();

        $updated = $this->makeService()->updateShift($shift, ['scheduled_end_time' => '18:00']);

        $this->assertSame(600, $updated->duration_minutes);
    }

    // =========================================================================
    // deleteShift()
    // =========================================================================

    public function test_delete_shift_throws_when_it_has_future_assignments(): void
    {
        $shift = $this->createShift();
        ShiftAssignment::create([
            'shift_id' => $shift->id, 'store_id' => 1, 'user_id' => 1,
            'shift_date' => now()->addDay()->toDateString(),
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('existing assignments');

        $this->makeService()->deleteShift($shift);
    }

    public function test_delete_shift_succeeds_when_no_assignments(): void
    {
        $shift = $this->createShift();

        $result = $this->makeService()->deleteShift($shift);

        $this->assertTrue($result);
        $this->assertDatabaseMissing('shifts', ['id' => $shift->id], connection: 'tenant');
    }

    public function test_delete_shift_throws_when_it_has_only_past_assignments(): void
    {
        // The real shift_assignments.shift_id FK is onDelete('restrict'), so a shift
        // with any assignment history — not just future ones — must be blocked with
        // a clean message rather than reaching the DB and raising a raw integrity error.
        $shift = $this->createShift();
        ShiftAssignment::create([
            'shift_id' => $shift->id, 'store_id' => 1, 'user_id' => 1,
            'shift_date' => now()->subDay()->toDateString(),
            'status' => 'completed',
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('existing assignments');

        $this->makeService()->deleteShift($shift);
    }

    // =========================================================================
    // getShiftsForStore()
    // =========================================================================

    public function test_get_shifts_for_store_includes_company_wide_shifts(): void
    {
        $this->createShift(['store_id' => 1]);
        $this->createShift(['store_id' => null]);
        $this->createShift(['store_id' => 2]);

        $result = $this->makeService()->getShiftsForStore(1);

        $this->assertCount(2, $result);
    }

    public function test_get_shifts_for_store_filters_by_active_status(): void
    {
        $this->createShift(['store_id' => 1, 'is_active' => true]);
        $this->createShift(['store_id' => 1, 'is_active' => false]);

        $result = $this->makeService()->getShiftsForStore(1, ['is_active' => true]);

        $this->assertCount(1, $result);
    }

    public function test_get_shifts_for_store_filters_by_search(): void
    {
        $this->createShift(['store_id' => 1, 'shift_name' => 'Opening Shift']);
        $this->createShift(['store_id' => 1, 'shift_name' => 'Closing Shift']);

        $result = $this->makeService()->getShiftsForStore(1, ['search' => 'Opening']);

        $this->assertCount(1, $result);
    }

    public function test_get_shifts_for_store_filters_by_day(): void
    {
        $this->createShift(['store_id' => 1, 'applicable_days' => ['monday']]);
        $this->createShift(['store_id' => 1, 'applicable_days' => ['tuesday']]);

        $result = $this->makeService()->getShiftsForStore(1, ['day' => 'monday']);

        $this->assertCount(1, $result);
    }

    public function test_get_shifts_for_store_filters_company_wide_only(): void
    {
        $this->createShift(['store_id' => 1]);
        $this->createShift(['store_id' => null]);

        $result = $this->makeService()->getShiftsForStore(1, ['company_wide' => true]);

        $this->assertCount(1, $result);
        $this->assertNull($result->first()->store_id);
    }

    // =========================================================================
    // getActiveShifts() / getShiftById() / getShiftsForDate()
    // =========================================================================

    public function test_get_active_shifts_excludes_inactive(): void
    {
        $this->createShift(['is_active' => true]);
        $this->createShift(['is_active' => false]);

        $result = $this->makeService()->getActiveShifts();

        $this->assertCount(1, $result);
    }

    public function test_get_shift_by_id_returns_null_for_unknown_id(): void
    {
        $this->assertNull($this->makeService()->getShiftById(999999));
    }

    public function test_get_shift_by_id_eager_loads_future_assignments(): void
    {
        $shift = $this->createShift();
        ShiftAssignment::create([
            'shift_id' => $shift->id, 'store_id' => 1, 'user_id' => 1,
            'shift_date' => now()->addDay()->toDateString(),
        ]);

        $found = $this->makeService()->getShiftById($shift->id);

        $this->assertTrue($found->relationLoaded('assignments'));
        $this->assertCount(1, $found->assignments);
    }

    public function test_get_shifts_for_date_returns_shifts_applicable_on_that_day(): void
    {
        $this->createShift(['applicable_days' => ['monday']]);
        $this->createShift(['applicable_days' => ['tuesday']]);

        $monday = \Carbon\Carbon::parse('next monday');
        $result = $this->makeService()->getShiftsForDate($monday);

        $this->assertCount(1, $result);
    }

    // =========================================================================
    // toggleActiveStatus()
    // =========================================================================

    public function test_toggle_active_status_flips_both_directions(): void
    {
        $shift = $this->createShift(['is_active' => true]);
        $service = $this->makeService();

        $off = $service->toggleActiveStatus($shift);
        $this->assertFalse($off->is_active);

        $on = $service->toggleActiveStatus($off);
        $this->assertTrue($on->is_active);
    }

    // =========================================================================
    // getShiftStatistics()
    // =========================================================================

    public function test_get_shift_statistics_computes_counts(): void
    {
        $this->createShift(['store_id' => 1, 'is_active' => true]);
        $this->createShift(['store_id' => null, 'is_active' => true]);
        $this->createShift(['store_id' => 1, 'is_active' => false]);

        $stats = $this->makeService()->getShiftStatistics();

        $this->assertSame(3, $stats['total_shifts']);
        $this->assertSame(2, $stats['active_shifts']);
        $this->assertSame(1, $stats['inactive_shifts']);
        $this->assertSame(1, $stats['company_wide_shifts']);
        $this->assertSame(2, $stats['store_specific_shifts']);
    }

    // =========================================================================
    // duplicateShift()
    // =========================================================================

    public function test_duplicate_shift_appends_copy_suffix_by_default(): void
    {
        $shift = $this->createShift(['shift_name' => 'Original Shift']);

        $duplicate = $this->makeService()->duplicateShift($shift);

        $this->assertSame('Original Shift (Copy)', $duplicate->shift_name);
        $this->assertSame($shift->store_id, $duplicate->store_id);
        $this->assertNotSame($shift->id, $duplicate->id);
    }

    public function test_duplicate_shift_uses_override_name_when_given(): void
    {
        $shift = $this->createShift(['shift_name' => 'Original Shift']);

        $duplicate = $this->makeService()->duplicateShift($shift, ['shift_name' => 'Weekend Shift']);

        $this->assertSame('Weekend Shift', $duplicate->shift_name);
    }

    // =========================================================================
    // Schema helpers
    // =========================================================================

    private function dropTestTables(): void
    {
        foreach (['shift_assignments', 'shifts', 'sale_payments', 'sales', 'users', 'stores'] as $table) {
            Schema::connection('tenant')->dropIfExists($table);
        }
    }

    private function createMinimalSchema(): void
    {
        $conn = 'tenant';

        Schema::connection($conn)->create('stores', function (Blueprint $table) {
            $table->id();
            $table->string('name')->default('Test Store');
            $table->string('code')->nullable();
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
        });

        Schema::connection($conn)->create('sale_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sale_id');
            $table->decimal('amount', 15, 2);
            $table->string('payment_method');
            $table->timestamps();
        });
    }
}
