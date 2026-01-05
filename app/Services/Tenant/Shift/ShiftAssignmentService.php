<?php

namespace App\Services\Tenant\Shift;

use App\Enums\Tenant\ShiftStatus;
use App\Events\Tenant\ShiftApproved;
use App\Events\Tenant\ShiftCancelled;
use App\Events\Tenant\ShiftEnded;
use App\Events\Tenant\ShiftStarted;
use App\Models\Tenant\Shift;
use App\Models\Tenant\ShiftAssignment;
use App\Models\Tenant\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ShiftAssignmentService
{
    /**
     * Cache TTL for assignment data (in seconds)
     */
    protected int $cacheTTL = 1800; // 30 minutes

    /**
     * Create a single shift assignment
     */
    public function assignShift(array $data): ShiftAssignment
    {
        try {
            DB::beginTransaction();

            $assignment = ShiftAssignment::create($data);

            $this->clearAssignmentCache();

            DB::commit();

            Log::info('Shift assignment created', [
                'assignment_id' => $assignment->id,
                'user_id' => $assignment->user_id,
                'shift_date' => $assignment->shift_date->toDateString(),
                'tenant_id' => tenant()->id,
            ]);

            return $assignment->load(['shift', 'store', 'user']);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to create shift assignment', [
                'error' => $e->getMessage(),
                'data' => $data,
                'tenant_id' => tenant()->id,
            ]);

            throw $e;
        }
    }

    /**
     * Bulk assign shifts with recurrence pattern
     */
    public function bulkAssignShift(
        Shift $shift,
        array $userIds,
        Carbon $startDate,
        Carbon $endDate,
        string $recurrencePattern = 'weekly',
        ?array $recurrenceDays = null
    ): Collection {
        try {
            DB::beginTransaction();

            $assignments = collect();
            $dates = $this->generateRecurrenceDates($shift, $startDate, $endDate, $recurrencePattern, $recurrenceDays);

            foreach ($userIds as $userId) {
                foreach ($dates as $date) {
                    // Skip if user already has assignment on this date for this shift
                    $exists = ShiftAssignment::where('user_id', $userId)
                        ->where('shift_id', $shift->id)
                        ->whereDate('shift_date', $date)
                        ->exists();

                    if ($exists) {
                        continue;
                    }

                    $assignment = ShiftAssignment::create([
                        'shift_id' => $shift->id,
                        'store_id' => $shift->store_id ?? $shift->store_id, // Use shift's store
                        'user_id' => $userId,
                        'shift_date' => $date,
                        'status' => ShiftStatus::SCHEDULED,
                    ]);

                    $assignments->push($assignment);
                }
            }

            $this->clearAssignmentCache();

            DB::commit();

            Log::info('Bulk shift assignments created', [
                'shift_id' => $shift->id,
                'user_count' => count($userIds),
                'assignments_created' => $assignments->count(),
                'date_range' => [$startDate->toDateString(), $endDate->toDateString()],
                'tenant_id' => tenant()->id,
            ]);

            // convert to an Eloquent collection once
            $eloquentCollection = ShiftAssignment::whereIn('id', $assignments->pluck('id'))->get();
            return $eloquentCollection->load(['shift', 'store', 'user']);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to create bulk shift assignments', [
                'shift_id' => $shift->id,
                'error' => $e->getMessage(),
                'tenant_id' => tenant()->id,
            ]);

            throw $e;
        }
    }

    /**
     * Update a shift assignment
     */
    public function updateAssignment(ShiftAssignment $assignment, array $data): ShiftAssignment
    {
        try {
            DB::beginTransaction();

            $assignment->update($data);

            $this->clearAssignmentCache();

            DB::commit();

            Log::info('Shift assignment updated', [
                'assignment_id' => $assignment->id,
                'changes' => $assignment->getChanges(),
                'tenant_id' => tenant()->id,
            ]);

            return $assignment->fresh(['shift', 'store', 'user']);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to update shift assignment', [
                'assignment_id' => $assignment->id,
                'error' => $e->getMessage(),
                'tenant_id' => tenant()->id,
            ]);

            throw $e;
        }
    }

    /**
     * Cancel a shift assignment
     */
    public function cancelAssignment(ShiftAssignment $assignment, string $reason): ShiftAssignment
    {
        try {
            DB::beginTransaction();

            $assignment->update([
                'status' => ShiftStatus::CANCELLED,
                'notes' => $assignment->notes
                    ? $assignment->notes . "\n\nCancellation Reason: " . $reason
                    : "Cancellation Reason: " . $reason,
            ]);

            $this->clearAssignmentCache();

            DB::commit();

            // Fire cancellation event
            event(new ShiftCancelled($assignment, $reason));

            Log::info('Shift assignment cancelled', [
                'assignment_id' => $assignment->id,
                'reason' => $reason,
                'tenant_id' => tenant()->id,
            ]);

            return $assignment->fresh(['shift', 'store', 'user']);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to cancel shift assignment', [
                'assignment_id' => $assignment->id,
                'error' => $e->getMessage(),
                'tenant_id' => tenant()->id,
            ]);

            throw $e;
        }
    }

    /**
     * Clock in to a shift
     */
    public function clockIn(ShiftAssignment $assignment, float $openingCash, ?string $notes = null): ShiftAssignment
    {
        try {
            DB::beginTransaction();

            $assignment->update([
                'status' => ShiftStatus::IN_PROGRESS,
                'actual_start' => now(),
                'opening_cash' => $openingCash,
                'notes' => $notes ? ($assignment->notes ? $assignment->notes . "\n\n" . $notes : $notes) : $assignment->notes,
            ]);

            $this->clearAssignmentCache();

            DB::commit();

            // Fire shift started event
            event(new ShiftStarted($assignment));

            Log::info('User clocked in to shift', [
                'assignment_id' => $assignment->id,
                'user_id' => $assignment->user_id,
                'opening_cash' => $openingCash,
                'tenant_id' => tenant()->id,
            ]);

            return $assignment->fresh(['shift', 'store', 'user']);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to clock in', [
                'assignment_id' => $assignment->id,
                'error' => $e->getMessage(),
                'tenant_id' => tenant()->id,
            ]);

            throw $e;
        }
    }

    /**
     * Clock out of a shift
     */
    public function clockOut(
        ShiftAssignment $assignment,
        float $closingCash,
        ?string $notes = null,
        ?string $issuesReported = null,
        ?string $cashVarianceReason = null
    ): ShiftAssignment {
        try {
            DB::beginTransaction();

            $updateData = [
                'status' => ShiftStatus::COMPLETED,
                'actual_end' => now(),
                'closing_cash' => $closingCash,
            ];

            if ($notes) {
                $updateData['notes'] = $assignment->notes
                    ? $assignment->notes . "\n\n" . $notes
                    : $notes;
            }

            if ($issuesReported) {
                $updateData['issues_reported'] = $issuesReported;
            }

            if ($cashVarianceReason) {
                $updateData['cash_variance_reason'] = $cashVarianceReason;
            }

            $assignment->update($updateData);

            // Auto-approve if cash variance is below threshold and config allows
            if (config('shift.auto_approve_below_variance_threshold', true)) {
                $variance = abs($assignment->cash_variance ?? 0);
                $threshold = config('shift.cash_variance_threshold', 100);

                if ($variance < $threshold) {
                    $assignment->update([
                        'approved_by' => null, // System auto-approval
                        'approved_at' => now(),
                    ]);
                }
            }

            $this->clearAssignmentCache();

            DB::commit();

            // Fire shift ended event
            event(new ShiftEnded($assignment));

            Log::info('User clocked out of shift', [
                'assignment_id' => $assignment->id,
                'user_id' => $assignment->user_id,
                'closing_cash' => $closingCash,
                'cash_variance' => $assignment->cash_variance,
                'duration_minutes' => $assignment->actual_duration_minutes,
                'tenant_id' => tenant()->id,
            ]);

            return $assignment->fresh(['shift', 'store', 'user', 'salesSummary']);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to clock out', [
                'assignment_id' => $assignment->id,
                'error' => $e->getMessage(),
                'tenant_id' => tenant()->id,
            ]);

            throw $e;
        }
    }

    /**
     * Approve a shift
     */
    public function approveShift(ShiftAssignment $assignment, User $approver, ?string $notes = null): ShiftAssignment
    {
        try {
            DB::beginTransaction();

            $updateData = [
                'approved_by' => $approver->id,
                'approved_at' => now(),
            ];

            if ($notes) {
                $updateData['notes'] = $assignment->notes
                    ? $assignment->notes . "\n\nApproval Notes: " . $notes
                    : "Approval Notes: " . $notes;
            }

            $assignment->update($updateData);

            $this->clearAssignmentCache();

            DB::commit();

            // Fire approval event
            event(new ShiftApproved($assignment));

            Log::info('Shift approved', [
                'assignment_id' => $assignment->id,
                'user_id' => $assignment->user_id,
                'approved_by' => $approver->id,
                'tenant_id' => tenant()->id,
            ]);

            return $assignment->fresh(['shift', 'store', 'user', 'approver']);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to approve shift', [
                'assignment_id' => $assignment->id,
                'error' => $e->getMessage(),
                'tenant_id' => tenant()->id,
            ]);

            throw $e;
        }
    }

    /**
     * Get assignments for a specific user within date range
     */
    public function getAssignmentsForUser(int $userId, Carbon $startDate, Carbon $endDate, ?array $filters = []): Collection
    {
        $query = ShiftAssignment::forUser($userId)
            ->forDateRange($startDate, $endDate)
            ->with(['shift', 'store', 'salesSummary']);

        // Apply filters
        if (isset($filters['status'])) {
            if (is_array($filters['status'])) {
                $query->whereIn('status', $filters['status']);
            } else {
                $query->where('status', $filters['status']);
            }
        }

        if (isset($filters['store_id'])) {
            $query->forStore($filters['store_id']);
        }

        return $query->orderBy('shift_date', 'desc')
            ->orderBy('actual_start', 'desc')
            ->get();
    }

    /**
     * Get assignments for a specific store on a date
     */
    public function getAssignmentsForStore(int $storeId, Carbon $date, ?array $filters = []): Collection
    {
        $query = ShiftAssignment::forStore($storeId)
            ->forDate($date)
            ->with(['shift', 'user', 'salesSummary']);

        // Apply filters
        if (isset($filters['status'])) {
            if (is_array($filters['status'])) {
                $query->whereIn('status', $filters['status']);
            } else {
                $query->where('status', $filters['status']);
            }
        }

        if (isset($filters['shift_id'])) {
            $query->forShift($filters['shift_id']);
        }

        return $query->orderBy('actual_start')->get();
    }

    /**
     * Check for overlapping shifts for a user on a specific date
     */
    public function checkOverlappingShifts(int $userId, Carbon $date, ?int $excludeAssignmentId = null): bool
    {
        $query = ShiftAssignment::forUser($userId)
            ->forDate($date)
            ->whereNotIn('status', [ShiftStatus::CANCELLED, ShiftStatus::NO_SHOW]);

        if ($excludeAssignmentId) {
            $query->where('id', '!=', $excludeAssignmentId);
        }

        return $query->exists();
    }

    /**
     * Get upcoming assignments for a user
     */
    public function getUpcomingAssignments(int $userId, int $daysAhead = 7): Collection
    {
        $startDate = now();
        $endDate = now()->addDays($daysAhead);

        return ShiftAssignment::forUser($userId)
            ->forDateRange($startDate, $endDate)
            ->scheduled()
            ->with(['shift', 'store'])
            ->orderBy('shift_date')
            ->get();
    }

    /**
     * Get assignments needing approval
     */
    public function getAssignmentsNeedingApproval(?int $storeId = null, ?int $limit = null): Collection
    {
        $query = ShiftAssignment::needingApproval()
            ->with(['shift', 'store', 'user', 'salesSummary']);

        if ($storeId) {
            $query->forStore($storeId);
        }

        if ($limit) {
            $query->limit($limit);
        }

        return $query->orderBy('updated_at', 'desc')->get();
    }

    /**
     * Get overdue shifts (should have started but no clock-in)
     */
    public function getOverdueShifts(?int $storeId = null, int $gracePeriodMinutes = 15): Collection
    {
        $query = ShiftAssignment::overdue($gracePeriodMinutes)
            ->with(['shift', 'store', 'user']);

        if ($storeId) {
            $query->forStore($storeId);
        }

        return $query->get();
    }

    /**
     * Auto-mark overdue shifts as no-show
     */
    public function autoMarkNoShow(int $gracePeriodMinutes = 30): int
    {
        if (!config('shift.auto_mark_no_show', true)) {
            return 0;
        }

        try {
            DB::beginTransaction();

            $overdueAssignments = $this->getOverdueShifts(null, $gracePeriodMinutes);
            $count = 0;

            foreach ($overdueAssignments as $assignment) {
                $assignment->update([
                    'status' => ShiftStatus::NO_SHOW,
                    'notes' => $assignment->notes
                        ? $assignment->notes . "\n\nAuto-marked as no-show after {$gracePeriodMinutes} minute grace period."
                        : "Auto-marked as no-show after {$gracePeriodMinutes} minute grace period.",
                ]);

                $count++;
            }

            $this->clearAssignmentCache();

            DB::commit();

            if ($count > 0) {
                Log::info('Auto-marked overdue shifts as no-show', [
                    'count' => $count,
                    'grace_period_minutes' => $gracePeriodMinutes,
                    'tenant_id' => tenant()->id,
                ]);
            }

            return $count;
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to auto-mark no-show shifts', [
                'error' => $e->getMessage(),
                'tenant_id' => tenant()->id,
            ]);

            throw $e;
        }
    }

    /**
     * Get assignment statistics for a date range
     */
    public function getAssignmentStatistics(Carbon $startDate, Carbon $endDate, ?int $storeId = null): array
    {
        $query = ShiftAssignment::forDateRange($startDate, $endDate);

        if ($storeId) {
            $query->forStore($storeId);
        }

        $baseQuery = clone $query;

        return [
            'total_assignments' => (clone $baseQuery)->count(),
            'scheduled' => (clone $baseQuery)->scheduled()->count(),
            'in_progress' => (clone $baseQuery)->inProgress()->count(),
            'completed' => (clone $baseQuery)->completed()->count(),
            'cancelled' => (clone $baseQuery)->cancelled()->count(),
            'no_show' => (clone $baseQuery)->noShow()->count(),
            'approved' => (clone $baseQuery)->approved()->count(),
            'pending_approval' => (clone $baseQuery)->needingApproval()->count(),
            'with_cash_variance' => (clone $baseQuery)->withCashVariance()->count(),
            'with_overtime' => (clone $baseQuery)->completed()
                ->whereRaw('actual_duration_minutes > (SELECT duration_minutes FROM shifts WHERE shifts.id = shift_assignments.shift_id)')
                ->count(),
        ];
    }

    /**
     * Generate recurrence dates based on pattern
     */
    protected function generateRecurrenceDates(
        Shift $shift,
        Carbon $startDate,
        Carbon $endDate,
        string $pattern,
        ?array $recurrenceDays = null
    ): array {
        $dates = [];
        $current = $startDate->copy();

        while ($current->lte($endDate)) {
            $shouldInclude = false;

            switch ($pattern) {
                case 'daily':
                    $shouldInclude = $shift->isApplicableOn($current);
                    break;

                case 'weekly':
                    $shouldInclude = $shift->isApplicableOn($current);
                    break;

                case 'custom':
                    if ($recurrenceDays && $shift->isApplicableOn($current)) {
                        $dayOfWeek = \App\Enums\Tenant\DayOfWeek::fromCarbonDayOfWeek($current->dayOfWeek);
                        $shouldInclude = in_array($dayOfWeek->value, $recurrenceDays);
                    }
                    break;
            }

            if ($shouldInclude) {
                $dates[] = $current->copy();
            }

            $current->addDay();
        }

        return $dates;
    }

    // ========================================
    // CACHE MANAGEMENT
    // ========================================

    /**
     * Clear all assignment-related cache
     */
    protected function clearAssignmentCache(): void
    {
        Cache::tags(['tenant', tenant()->id, 'shift_assignments'])->flush();
    }
}
