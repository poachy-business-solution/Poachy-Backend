<?php

namespace Tests\Feature\Tenant\Shift;

use App\Models\Tenant\Shift;
use App\Models\Tenant\ShiftAssignment;
use App\Models\Tenant\ShiftSwapRequest;
use App\Services\Tenant\Shift\ShiftSwapService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Stancl\Tenancy\Contracts\Tenant as TenantContract;
use Tests\TestCase;

class ShiftSwapServiceTest extends TestCase
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

        DB::connection('tenant')->table('stores')->insert(['id' => 1, 'name' => 'Main Store', 'created_at' => now(), 'updated_at' => now()]);
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

    private function makeService(): ShiftSwapService
    {
        return new ShiftSwapService();
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

    private function createAssignment(int $userId, string $date, ?int $shiftId = null): ShiftAssignment
    {
        return ShiftAssignment::create([
            'shift_id' => $shiftId ?? $this->createShift()->id,
            'store_id' => 1,
            'user_id' => $userId,
            'shift_date' => $date,
        ]);
    }

    // =========================================================================
    // executeSwap()
    // =========================================================================

    public function test_execute_swap_exchanges_user_ids_between_assignments(): void
    {
        $requesterAssignment = $this->createAssignment(1, now()->addDay()->toDateString());
        $targetAssignment = $this->createAssignment(2, now()->addDays(2)->toDateString());

        $swap = $this->makeService()->executeSwap([
            'requester_assignment_id' => $requesterAssignment->id,
            'target_assignment_id' => $targetAssignment->id,
            'reason' => 'Doctor appointment',
            'manager_id' => 3,
        ]);

        $this->assertSame(2, $requesterAssignment->fresh()->user_id);
        $this->assertSame(1, $targetAssignment->fresh()->user_id);
        $this->assertSame(1, $swap->requester_id);
        $this->assertSame(2, $swap->target_user_id);
        $this->assertNotNull($swap->swapped_at);
    }

    public function test_execute_swap_throws_when_swapping_with_same_user(): void
    {
        $requesterAssignment = $this->createAssignment(1, now()->addDay()->toDateString());
        $targetAssignment = $this->createAssignment(1, now()->addDays(2)->toDateString());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('same user');

        $this->makeService()->executeSwap([
            'requester_assignment_id' => $requesterAssignment->id,
            'target_assignment_id' => $targetAssignment->id,
            'reason' => 'Test',
            'manager_id' => 3,
        ]);
    }

    public function test_execute_swap_throws_when_target_user_already_has_conflicting_assignment(): void
    {
        $sharedShift = $this->createShift();
        $requesterAssignment = $this->createAssignment(1, now()->addDay()->toDateString(), $sharedShift->id);
        $targetAssignment = $this->createAssignment(2, now()->addDays(2)->toDateString());
        // Target user already assigned to the requester's exact shift+date
        $this->createAssignment(2, $requesterAssignment->shift_date->toDateString(), $sharedShift->id);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('already has a shift assignment');

        $this->makeService()->executeSwap([
            'requester_assignment_id' => $requesterAssignment->id,
            'target_assignment_id' => $targetAssignment->id,
            'reason' => 'Test',
            'manager_id' => 3,
        ]);
    }

    public function test_execute_swap_throws_when_requester_already_has_conflicting_assignment(): void
    {
        $sharedShift = $this->createShift();
        $requesterAssignment = $this->createAssignment(1, now()->addDay()->toDateString());
        $targetAssignment = $this->createAssignment(2, now()->addDays(2)->toDateString(), $sharedShift->id);
        // Requester user already assigned to the target's exact shift+date
        $this->createAssignment(1, $targetAssignment->shift_date->toDateString(), $sharedShift->id);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('already has a shift assignment');

        $this->makeService()->executeSwap([
            'requester_assignment_id' => $requesterAssignment->id,
            'target_assignment_id' => $targetAssignment->id,
            'reason' => 'Test',
            'manager_id' => 3,
        ]);
    }

    // =========================================================================
    // getSwapRequestsForUser() / getAllSwapRequests() / getSwapRequestById()
    // =========================================================================

    public function test_get_swap_requests_for_user_matches_either_side(): void
    {
        $requesterAssignment = $this->createAssignment(1, now()->addDay()->toDateString());
        $targetAssignment = $this->createAssignment(2, now()->addDays(2)->toDateString());
        $this->makeService()->executeSwap([
            'requester_assignment_id' => $requesterAssignment->id,
            'target_assignment_id' => $targetAssignment->id,
            'reason' => 'Test', 'manager_id' => 3,
        ]);

        $forRequester = $this->makeService()->getSwapRequestsForUser(1);
        $forTarget = $this->makeService()->getSwapRequestsForUser(2);
        $forUnrelated = $this->makeService()->getSwapRequestsForUser(999);

        $this->assertCount(1, $forRequester);
        $this->assertCount(1, $forTarget);
        $this->assertCount(0, $forUnrelated);
    }

    public function test_get_all_swap_requests_filters_by_store(): void
    {
        DB::connection('tenant')->table('stores')->insert(['id' => 2, 'name' => 'Other Store', 'created_at' => now(), 'updated_at' => now()]);
        $requesterAssignment = $this->createAssignment(1, now()->addDay()->toDateString());
        $targetAssignment = $this->createAssignment(2, now()->addDays(2)->toDateString());
        $this->makeService()->executeSwap([
            'requester_assignment_id' => $requesterAssignment->id,
            'target_assignment_id' => $targetAssignment->id,
            'reason' => 'Test', 'manager_id' => 3,
        ]);

        $resultForStore1 = $this->makeService()->getAllSwapRequests(1);
        $resultForStore2 = $this->makeService()->getAllSwapRequests(2);

        $this->assertCount(1, $resultForStore1);
        $this->assertCount(0, $resultForStore2);
    }

    public function test_get_swap_request_by_id_returns_null_for_unknown_id(): void
    {
        $this->assertNull($this->makeService()->getSwapRequestById(999999));
    }

    // =========================================================================
    // getSwapStatistics()
    // =========================================================================

    public function test_get_swap_statistics_counts_total(): void
    {
        $requesterAssignment = $this->createAssignment(1, now()->addDay()->toDateString());
        $targetAssignment = $this->createAssignment(2, now()->addDays(2)->toDateString());
        $this->makeService()->executeSwap([
            'requester_assignment_id' => $requesterAssignment->id,
            'target_assignment_id' => $targetAssignment->id,
            'reason' => 'Test', 'manager_id' => 3,
        ]);

        $stats = $this->makeService()->getSwapStatistics();

        $this->assertSame(1, $stats['total_swaps']);
        $this->assertSame(1, $stats['swaps_this_month']);
        $this->assertSame(1, $stats['swaps_this_week']);
    }

    // =========================================================================
    // Schema helpers
    // =========================================================================

    private function dropTestTables(): void
    {
        foreach (['shift_swap_requests', 'shift_assignments', 'shifts', 'users', 'stores'] as $table) {
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

        Schema::connection($conn)->create('shift_swap_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('requester_assignment_id');
            $table->unsignedBigInteger('target_assignment_id');
            $table->unsignedBigInteger('requester_id');
            $table->unsignedBigInteger('target_user_id');
            $table->text('reason');
            $table->unsignedBigInteger('manager_id')->nullable();
            $table->text('manager_note')->nullable();
            $table->timestamp('swapped_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }
}
