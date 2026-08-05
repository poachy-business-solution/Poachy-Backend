<?php

namespace Database\Seeders\Demo;

use App\Enums\Tenant\DayOfWeek;
use App\Enums\Tenant\ShiftStatus;
use App\Models\Tenant\Shift;
use App\Models\Tenant\ShiftAssignment;
use App\Models\Tenant\Store;
use App\Models\Tenant\User;
use App\Services\Tenant\Shift\ShiftAssignmentService;
use App\Services\Tenant\Shift\ShiftService;
use App\Services\Tenant\Shift\ShiftSwapService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class DemoShiftSeeder extends Seeder
{
    /** @var array<int, ShiftAssignment> keyed by days-ago, CBD morning shift (cashier1) */
    protected array $morningAssignments = [];

    /** @var array<int, ShiftAssignment> keyed by days-ago, CBD evening shift (cashier2) */
    protected array $eveningAssignments = [];

    public function run(
        ShiftService $shiftService,
        ShiftAssignmentService $assignmentService,
        ShiftSwapService $shiftSwapService,
    ): void {
        $cbd = Store::mainStore()->firstOrFail();
        $westlands = Store::branches()->firstOrFail();
        $allDays = array_column(DayOfWeek::cases(), 'value');

        $morningShift = $shiftService->createShift([
            'shift_name' => 'CBD Morning Shift', 'store_id' => $cbd->id,
            'scheduled_start_time' => '08:00', 'scheduled_end_time' => '16:00',
            'duration_minutes' => 480, 'applicable_days' => $allDays, 'is_active' => true,
        ]);

        $eveningShift = $shiftService->createShift([
            'shift_name' => 'CBD Evening Shift', 'store_id' => $cbd->id,
            'scheduled_start_time' => '16:00', 'scheduled_end_time' => '20:00',
            'duration_minutes' => 240, 'applicable_days' => $allDays, 'is_active' => true,
        ]);

        $branchShift = $shiftService->createShift([
            'shift_name' => 'Westlands Day Shift', 'store_id' => $westlands->id,
            'scheduled_start_time' => '09:00', 'scheduled_end_time' => '18:00',
            'duration_minutes' => 540, 'applicable_days' => $allDays, 'is_active' => true,
        ]);

        $cashier1 = User::where('email', DemoStaffSeeder::ACCOUNTS[2]['email'])->firstOrFail();
        $cashier2 = User::where('email', DemoStaffSeeder::ACCOUNTS[3]['email'])->firstOrFail();
        $manager = User::where('email', DemoStaffSeeder::ACCOUNTS[1]['email'])->firstOrFail();

        // Last 9 days: completed shifts, backdated through the real clock-in/out flow.
        foreach (range(9, 1) as $daysAgo) {
            $date = now()->subDays($daysAgo);

            $this->morningAssignments[$daysAgo] = $this->runCompletedShift(
                $assignmentService, $morningShift, $cbd, $cashier1, $date, 8, 16, 2000
            );

            $this->eveningAssignments[$daysAgo] = $this->runCompletedShift(
                $assignmentService, $eveningShift, $cbd, $cashier2, $date, 16, 20, 1500
            );

            if ($daysAgo <= 5) {
                $this->runCompletedShift($assignmentService, $branchShift, $westlands, $manager, $date, 9, 18, 3000);
            }
        }

        // Today: still-active shifts for Phase 7 sales to attach to.
        $assignmentService->clockIn(
            $assignmentService->assignShift([
                'shift_id' => $morningShift->id, 'store_id' => $cbd->id, 'user_id' => $cashier1->id,
                'shift_date' => now()->toDateString(), 'status' => ShiftStatus::SCHEDULED,
            ]),
            2000
        );

        $assignmentService->clockIn(
            $assignmentService->assignShift([
                'shift_id' => $eveningShift->id, 'store_id' => $cbd->id, 'user_id' => $cashier2->id,
                'shift_date' => now()->toDateString(), 'status' => ShiftStatus::SCHEDULED,
            ]),
            1500
        );

        $assignmentService->clockIn(
            $assignmentService->assignShift([
                'shift_id' => $branchShift->id, 'store_id' => $westlands->id, 'user_id' => $manager->id,
                'shift_date' => now()->toDateString(), 'status' => ShiftStatus::SCHEDULED,
            ]),
            3000
        );

        // No-show: cashier2 was tapped to cover an extra CBD morning slot on a day they
        // had no other assignment, and didn't show. (Dates outside the main 1-9 day loop
        // so neither cashier already has a conflicting assignment that day.)
        ShiftAssignment::create([
            'shift_id' => $morningShift->id, 'store_id' => $cbd->id, 'user_id' => $cashier2->id,
            'shift_date' => now()->subDays(11)->toDateString(), 'status' => ShiftStatus::NO_SHOW,
        ]);

        // Cancelled: cashier2 was scheduled for one-off branch coverage, cancelled ahead of time.
        $cancelled = $assignmentService->assignShift([
            'shift_id' => $branchShift->id, 'store_id' => $westlands->id, 'user_id' => $cashier2->id,
            'shift_date' => now()->subDays(12)->toDateString(), 'status' => ShiftStatus::SCHEDULED,
        ]);
        $assignmentService->cancelAssignment($cancelled, 'Branch did not need extra coverage that day.');

        // Swap: cashier1 and cashier2 traded their shift-2-days-ago assignments.
        $shiftSwapService->executeSwap([
            'requester_assignment_id' => $this->morningAssignments[2]->id,
            'target_assignment_id' => $this->eveningAssignments[2]->id,
            'reason' => 'Cashier1 had a family commitment and traded shifts with Cashier2.',
            'manager_id' => $manager->id,
            'manager_note' => 'Approved — no scheduling conflicts.',
        ]);

        $this->command->info('✓ Shifts: 3 shift templates, '.(count($this->morningAssignments) * 2 + 5).'+ assignments, 1 swap');
    }

    protected function runCompletedShift(
        ShiftAssignmentService $assignmentService,
        Shift $shift,
        Store $store,
        User $user,
        Carbon $date,
        int $startHour,
        int $endHour,
        float $openingCash,
    ): ShiftAssignment {
        $assignment = $this->runAt($date->copy()->setTime($startHour, 0), fn () => $assignmentService->assignShift([
            'shift_id' => $shift->id,
            'store_id' => $store->id,
            'user_id' => $user->id,
            'shift_date' => $date->toDateString(),
            'status' => ShiftStatus::SCHEDULED,
        ]));

        $assignment = $this->runAt(
            $date->copy()->setTime($startHour, random_int(0, 10)),
            fn () => $assignmentService->clockIn($assignment, $openingCash)
        );

        return $this->runAt(
            $date->copy()->setTime($endHour, random_int(0, 15)),
            fn () => $assignmentService->clockOut($assignment, $openingCash + random_int(3000, 15000))
        );
    }

    protected function runAt(Carbon $when, callable $callback): mixed
    {
        Carbon::setTestNow($when);

        try {
            return $callback();
        } finally {
            Carbon::setTestNow();
        }
    }
}
