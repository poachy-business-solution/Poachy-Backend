<?php

namespace Tests\Feature\Tenant\Shift;

use App\Enums\Tenant\ShiftStatus;
use App\Events\Tenant\ShiftEnded;
use App\Events\Tenant\ShiftStarted;
use App\Models\Tenant\Shift;
use App\Models\Tenant\ShiftAssignment;
use App\Models\Tenant\User;
use App\Services\Tenant\Shift\ShiftAssignmentService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Stancl\Tenancy\Contracts\Tenant as TenantContract;
use Tests\TestCase;

class ShiftAssignmentServiceTest extends TestCase
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

        Cache::tags(['tenant', 'test-tenant', 'shift_assignments'])->flush();

        DB::connection('tenant')->table('stores')->insert([
            ['id' => 1, 'name' => 'Main Store', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'Second Store', 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::connection('tenant')->table('users')->insert([
            ['id' => 1, 'name' => 'Employee One', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'Employee Two', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'name' => 'Manager One', 'created_at' => now(), 'updated_at' => now()],
        ]);

        \Carbon\Carbon::setTestNow('2026-07-30 14:00:00');
    }

    protected function tearDown(): void
    {
        \Carbon\Carbon::setTestNow();
        $this->dropTestTables();
        DB::connection('tenant')->statement('SET foreign_key_checks = 1');
        parent::tearDown();
    }

    private function makeService(): ShiftAssignmentService
    {
        return new ShiftAssignmentService();
    }

    private function createShift(array $overrides = []): Shift
    {
        return Shift::create(array_merge([
            'shift_name' => 'Shift '.uniqid(),
            'store_id' => 1,
            'scheduled_start_time' => '08:00',
            'scheduled_end_time' => '16:00',
        ], $overrides));
    }

    private function createAssignment(array $overrides = []): ShiftAssignment
    {
        if (! isset($overrides['shift_id'])) {
            $overrides['shift_id'] = $this->createShift()->id;
        }

        return ShiftAssignment::create(array_merge([
            'store_id' => 1,
            'user_id' => 1,
            'shift_date' => now()->addDay()->toDateString(),
            'status' => ShiftStatus::SCHEDULED,
        ], $overrides));
    }

    // =========================================================================
    // assignShift()
    // =========================================================================

    public function test_assign_shift_creates_assignment(): void
    {
        $shift = $this->createShift();

        $assignment = $this->makeService()->assignShift([
            'shift_id' => $shift->id, 'store_id' => 1, 'user_id' => 1,
            'shift_date' => now()->addDay()->toDateString(),
        ]);

        $this->assertSame(ShiftStatus::SCHEDULED, $assignment->status);
    }

    public function test_assign_shift_throws_when_overlapping_shift_exists_for_user(): void
    {
        $shiftA = $this->createShift(['scheduled_start_time' => '08:00', 'scheduled_end_time' => '16:00']);
        $shiftB = $this->createShift(['scheduled_start_time' => '10:00', 'scheduled_end_time' => '18:00']);
        $date = now()->addDay()->toDateString();
        $this->makeService()->assignShift(['shift_id' => $shiftA->id, 'store_id' => 1, 'user_id' => 1, 'shift_date' => $date]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('already has a shift assigned');

        $this->makeService()->assignShift(['shift_id' => $shiftB->id, 'store_id' => 1, 'user_id' => 1, 'shift_date' => $date]);
    }

    public function test_assign_shift_allows_back_to_back_non_overlapping_shifts(): void
    {
        $shiftA = $this->createShift(['scheduled_start_time' => '08:00', 'scheduled_end_time' => '16:00']);
        $shiftB = $this->createShift(['scheduled_start_time' => '16:00', 'scheduled_end_time' => '22:00']);
        $date = now()->addDay()->toDateString();
        $this->makeService()->assignShift(['shift_id' => $shiftA->id, 'store_id' => 1, 'user_id' => 1, 'shift_date' => $date]);

        $second = $this->makeService()->assignShift(['shift_id' => $shiftB->id, 'store_id' => 1, 'user_id' => 1, 'shift_date' => $date]);

        $this->assertSame($shiftB->id, $second->shift_id);
    }

    public function test_assign_shift_throws_when_user_assigned_to_different_store_same_day(): void
    {
        $shift = $this->createShift();
        $date = now()->addDay()->toDateString();
        $this->makeService()->assignShift(['shift_id' => $shift->id, 'store_id' => 1, 'user_id' => 1, 'shift_date' => $date]);

        $otherStoreShift = $this->createShift(['store_id' => 2, 'scheduled_start_time' => '18:00', 'scheduled_end_time' => '22:00']);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('multiple stores');

        $this->makeService()->assignShift(['shift_id' => $otherStoreShift->id, 'store_id' => 2, 'user_id' => 1, 'shift_date' => $date]);
    }

    // =========================================================================
    // bulkAssignShift()
    // =========================================================================

    public function test_bulk_assign_shift_creates_assignments_for_applicable_days_only(): void
    {
        $shift = $this->createShift(['applicable_days' => ['monday']]);
        $monday = \Carbon\Carbon::parse('next monday');
        $sunday = $monday->copy()->addDays(6);

        $assignments = $this->makeService()->bulkAssignShift($shift, [1], $monday, $sunday, 'weekly');

        $this->assertCount(1, $assignments);
        $this->assertTrue(\Carbon\Carbon::parse($assignments->first()->shift_date)->isMonday());
    }

    public function test_bulk_assign_shift_skips_existing_assignment_for_same_user_date_shift(): void
    {
        $shift = $this->createShift(['applicable_days' => null]);
        $date = \Carbon\Carbon::parse('next monday');
        ShiftAssignment::create([
            'shift_id' => $shift->id, 'store_id' => 1, 'user_id' => 1, 'shift_date' => $date->toDateString(),
        ]);

        $assignments = $this->makeService()->bulkAssignShift($shift, [1], $date, $date, 'daily');

        $this->assertCount(0, $assignments);
    }

    // =========================================================================
    // updateAssignment() / cancelAssignment()
    // =========================================================================

    public function test_update_assignment_updates_fields(): void
    {
        $assignment = $this->createAssignment();

        $updated = $this->makeService()->updateAssignment($assignment, ['notes' => 'Updated note']);

        $this->assertSame('Updated note', $updated->notes);
    }

    public function test_cancel_assignment_sets_cancelled_status_and_appends_reason(): void
    {
        $assignment = $this->createAssignment();

        $cancelled = $this->makeService()->cancelAssignment($assignment, 'Employee unavailable');

        $this->assertSame(ShiftStatus::CANCELLED, $cancelled->status);
        $this->assertStringContainsString('Employee unavailable', $cancelled->notes);
    }

    // =========================================================================
    // clockIn() / clockOut()
    // =========================================================================

    public function test_clock_in_transitions_to_in_progress_and_fires_event_exactly_once(): void
    {
        Event::fake([ShiftStarted::class]);
        $assignment = $this->createAssignment(['shift_date' => now()->toDateString()]);

        $clocked = $this->makeService()->clockIn($assignment, 500.0);

        $this->assertSame(ShiftStatus::IN_PROGRESS, $clocked->status);
        $this->assertEquals(500.0, (float) $clocked->opening_cash);
        $this->assertNotNull($clocked->actual_start);
        Event::assertDispatchedTimes(ShiftStarted::class, 1);
    }

    public function test_clock_out_transitions_to_completed_and_fires_event_exactly_once(): void
    {
        Event::fake([ShiftEnded::class]);
        $assignment = $this->createAssignment(['shift_date' => now()->toDateString()]);
        $this->makeService()->clockIn($assignment, 500.0);

        $clocked = $this->makeService()->clockOut($assignment->fresh(), 500.0);

        $this->assertSame(ShiftStatus::COMPLETED, $clocked->status);
        $this->assertEquals(500.0, (float) $clocked->closing_cash);
        Event::assertDispatchedTimes(ShiftEnded::class, 1);
    }

    public function test_clock_out_auto_approves_when_cash_variance_below_threshold(): void
    {
        $assignment = $this->createAssignment(['shift_date' => now()->toDateString()]);
        $this->makeService()->clockIn($assignment, 500.0);

        $clocked = $this->makeService()->clockOut($assignment->fresh(), 500.0);

        $this->assertNotNull($clocked->approved_at);
        $this->assertNull($clocked->approved_by);
    }

    public function test_clock_out_does_not_auto_approve_when_variance_exceeds_threshold(): void
    {
        $assignment = $this->createAssignment(['shift_date' => now()->toDateString()]);
        $this->makeService()->clockIn($assignment, 500.0);

        $clocked = $this->makeService()->clockOut($assignment->fresh(), 1000.0);

        $this->assertNull($clocked->approved_at);
    }

    // =========================================================================
    // approveShift()
    // =========================================================================

    public function test_approve_shift_sets_approver_and_timestamp(): void
    {
        $assignment = $this->createAssignment(['shift_date' => now()->toDateString()]);
        $this->makeService()->clockIn($assignment, 500.0);
        $this->makeService()->clockOut($assignment->fresh(), 1000.0);
        $manager = User::find(3);

        $approved = $this->makeService()->approveShift($assignment->fresh(), $manager, 'Confirmed with employee');

        $this->assertSame(3, $approved->approved_by);
        $this->assertNotNull($approved->approved_at);
        $this->assertStringContainsString('Confirmed with employee', $approved->notes);
    }

    // =========================================================================
    // Queries
    // =========================================================================

    public function test_check_overlapping_shifts_detects_existing_assignment(): void
    {
        $assignment = $this->createAssignment();

        $this->assertTrue($this->makeService()->checkOverlappingShifts(1, \Carbon\Carbon::parse($assignment->shift_date)));
    }

    public function test_check_overlapping_shifts_excludes_given_assignment_id(): void
    {
        $assignment = $this->createAssignment();

        $this->assertFalse($this->makeService()->checkOverlappingShifts(1, \Carbon\Carbon::parse($assignment->shift_date), $assignment->id));
    }

    public function test_get_upcoming_assignments_returns_scheduled_within_window(): void
    {
        $this->createAssignment(['shift_date' => now()->addDays(2)->toDateString()]);
        $this->createAssignment(['shift_date' => now()->addDays(20)->toDateString()]);

        $result = $this->makeService()->getUpcomingAssignments(1, 7);

        $this->assertCount(1, $result);
    }

    public function test_get_assignments_needing_approval_returns_completed_unapproved(): void
    {
        $assignment = $this->createAssignment(['shift_date' => now()->toDateString()]);
        $this->makeService()->clockIn($assignment, 500.0);
        $this->makeService()->clockOut($assignment->fresh(), 1000.0);

        $result = $this->makeService()->getAssignmentsNeedingApproval();

        $this->assertCount(1, $result);
    }

    public function test_auto_mark_no_show_marks_overdue_scheduled_assignments(): void
    {
        $shift = $this->createShift(['scheduled_start_time' => '08:00', 'scheduled_end_time' => '16:00']);
        $assignment = ShiftAssignment::create([
            'shift_id' => $shift->id, 'store_id' => 1, 'user_id' => 1,
            'shift_date' => now()->subDay()->toDateString(),
            'status' => ShiftStatus::SCHEDULED,
        ]);

        $count = $this->makeService()->autoMarkNoShow(30);

        $this->assertSame(1, $count);
        $this->assertSame(ShiftStatus::NO_SHOW, $assignment->fresh()->status);
    }

    public function test_auto_mark_no_show_does_not_touch_shifts_within_grace_period(): void
    {
        $shift = $this->createShift([
            'scheduled_start_time' => now()->addMinutes(5)->format('H:i'),
            'scheduled_end_time' => now()->addHours(8)->format('H:i'),
        ]);
        $assignment = ShiftAssignment::create([
            'shift_id' => $shift->id, 'store_id' => 1, 'user_id' => 1,
            'shift_date' => now()->toDateString(),
            'status' => ShiftStatus::SCHEDULED,
        ]);

        $count = $this->makeService()->autoMarkNoShow(30);

        $this->assertSame(0, $count);
        $this->assertSame(ShiftStatus::SCHEDULED, $assignment->fresh()->status);
    }

    public function test_get_assignment_statistics_computes_counts(): void
    {
        // Distinct users — same user/date/store would trip the overlap guard.
        $this->createAssignment(['user_id' => 1, 'status' => ShiftStatus::SCHEDULED]);
        $this->createAssignment(['user_id' => 2, 'status' => ShiftStatus::CANCELLED]);
        $this->createAssignment(['user_id' => 3, 'status' => ShiftStatus::NO_SHOW]);

        $stats = $this->makeService()->getAssignmentStatistics(now()->subDays(2), now()->addDays(2));

        $this->assertSame(3, $stats['total_assignments']);
        $this->assertSame(1, $stats['scheduled']);
        $this->assertSame(1, $stats['cancelled']);
        $this->assertSame(1, $stats['no_show']);
    }

    // =========================================================================
    // Schema helpers
    // =========================================================================

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
