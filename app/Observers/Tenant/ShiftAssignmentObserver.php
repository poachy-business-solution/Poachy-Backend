<?php

namespace App\Observers\Tenant;

use App\Events\Tenant\ShiftStarted;
use App\Events\Tenant\ShiftEnded;
use App\Events\Tenant\ShiftCancelled;
use App\Events\Tenant\ShiftApproved;
use App\Models\Tenant\ShiftAssignment;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ShiftAssignmentObserver
{
    /**
     * Handle the ShiftAssignment "creating" event.
     */
    public function creating(ShiftAssignment $assignment): void
    {
        // Validate no overlapping shifts for the user on same day
        if (config('shift.prevent_overlapping_shifts', true)) {
            $this->validateNoOverlappingShifts($assignment);
        }

        // Validate not assigned to multiple stores on same day
        if (config('shift.prevent_multi_store_same_day', true)) {
            $this->validateNoMultiStoreAssignments($assignment);
        }
    }

    /**
     * Handle the ShiftAssignment "created" event.
     */
    public function created(ShiftAssignment $assignment): void
    {
        $this->logAudit($assignment, 'created', null, $assignment->toArray());

        Log::info('Shift assignment created', [
            'assignment_id' => $assignment->id,
            'user_id' => $assignment->user_id,
            'shift_date' => $assignment->shift_date,
            'shift_id' => $assignment->shift_id,
            'tenant_id' => tenant()->id,
        ]);
    }

    /**
     * Handle the ShiftAssignment "updating" event.
     */
    public function updating(ShiftAssignment $assignment): void
    {
        // Auto-calculate actual duration if times changed
        if ($assignment->isDirty(['actual_start', 'actual_end'])) {
            if ($assignment->actual_start && $assignment->actual_end) {
                $assignment->calculateActualDuration();
            }
        }

        // Detect status changes and dispatch events
        if ($assignment->isDirty('status')) {
            $this->handleStatusChange($assignment);
        }

        // Detect approval
        if ($assignment->isDirty('approved_by') && $assignment->approved_by !== null) {
            // Validate not self-approval
            if (config('shift.prevent_self_approval', true)) {
                if ($assignment->approved_by === $assignment->user_id) {
                    throw new \Exception('Users cannot approve their own shifts');
                }
            }
        }
    }

    /**
     * Handle the ShiftAssignment "updated" event.
     */
    public function updated(ShiftAssignment $assignment): void
    {
        $changes = $assignment->getChanges();

        $this->logAudit($assignment, 'updated', $assignment->getOriginal(), $changes);

        // Log specific important changes
        if (isset($changes['status'])) {
            Log::info('Shift status changed', [
                'assignment_id' => $assignment->id,
                'old_status' => $assignment->getOriginal('status'),
                'new_status' => $assignment->status,
                'user_id' => $assignment->user_id,
                'tenant_id' => tenant()->id,
            ]);
        }

        if (isset($changes['approved_by'])) {
            Log::info('Shift approved', [
                'assignment_id' => $assignment->id,
                'user_id' => $assignment->user_id,
                'approved_by' => $assignment->approved_by,
                'tenant_id' => tenant()->id,
            ]);
        }
    }

    /**
     * Handle the ShiftAssignment "deleted" event.
     */
    public function deleted(ShiftAssignment $assignment): void
    {
        $this->logAudit($assignment, 'deleted', $assignment->toArray(), null);
    }

    /**
     * Handle the ShiftAssignment "restored" event.
     */
    public function restored(ShiftAssignment $assignment): void
    {
        $this->logAudit($assignment, 'restored', null, $assignment->toArray());
    }

    /**
     * Validate no overlapping shifts for same user
     */
    protected function validateNoOverlappingShifts(ShiftAssignment $assignment): void
    {
        // Allow back-to-back shifts if configured
        $allowBackToBack = config('shift.allow_back_to_back_shifts', true);
        $minRestHours = config('shift.minimum_rest_hours_between_shifts', 0);

        // Get the shift details
        $shift = $assignment->shift ?? \App\Models\Tenant\Shift::find($assignment->shift_id);

        if (!$shift) {
            return;
        }

        // Check for existing assignments on same day
        $existingAssignment = ShiftAssignment::where('user_id', $assignment->user_id)
            ->whereDate('shift_date', $assignment->shift_date)
            ->when($assignment->id, function ($query, $id) {
                return $query->where('id', '!=', $id);
            })
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->with('shift')
            ->first();

        if (!$existingAssignment) {
            return;
        }

        // If back-to-back is allowed, check if shifts actually overlap
        if ($allowBackToBack) {
            $newStart = \Carbon\Carbon::parse($shift->scheduled_start_time);
            $newEnd = \Carbon\Carbon::parse($shift->scheduled_end_time);

            $existingStart = \Carbon\Carbon::parse($existingAssignment->shift->scheduled_start_time);
            $existingEnd = \Carbon\Carbon::parse($existingAssignment->shift->scheduled_end_time);

            // Handle overnight shifts
            if ($newEnd->lessThan($newStart)) {
                $newEnd->addDay();
            }
            if ($existingEnd->lessThan($existingStart)) {
                $existingEnd->addDay();
            }

            // Check for actual time overlap
            $hasOverlap = $newStart->lessThan($existingEnd) && $newEnd->greaterThan($existingStart);

            if (!$hasOverlap) {
                // Check minimum rest period if configured
                if ($minRestHours > 0) {
                    $timeBetween = min(
                        abs($newStart->diffInHours($existingEnd)),
                        abs($existingStart->diffInHours($newEnd))
                    );

                    if ($timeBetween < $minRestHours) {
                        throw new \Exception("Minimum rest period of {$minRestHours} hours between shifts not met");
                    }
                }

                return; // No overlap, allow assignment
            }
        }

        throw new \Exception('User already has a shift assigned on this date');
    }

    /**
     * Validate user not assigned to multiple stores on same day
     */
    protected function validateNoMultiStoreAssignments(ShiftAssignment $assignment): void
    {
        $existingDifferentStore = ShiftAssignment::where('user_id', $assignment->user_id)
            ->whereDate('shift_date', $assignment->shift_date)
            ->where('store_id', '!=', $assignment->store_id)
            ->when($assignment->id, function ($query, $id) {
                return $query->where('id', '!=', $id);
            })
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->exists();

        if ($existingDifferentStore) {
            throw new \Exception('User cannot be assigned to multiple stores on the same day');
        }
    }

    /**
     * Handle status changes and dispatch appropriate events
     */
    protected function handleStatusChange(ShiftAssignment $assignment): void
    {
        $oldStatus = $assignment->getOriginal('status');
        $newStatus = $assignment->status->value;

        // Dispatch events based on status changes
        match ($newStatus) {
            'in_progress' => event(new ShiftStarted($assignment)),
            'completed' => event(new ShiftEnded($assignment)),
            'cancelled' => event(new ShiftCancelled($assignment)),
            default => null,
        };

        // Check if shift was approved
        if ($assignment->isDirty('approved_by') && $assignment->approved_by !== null) {
            event(new ShiftApproved($assignment));
        }
    }

    /**
     * Log audit trail
     */
    protected function logAudit(ShiftAssignment $assignment, string $action, ?array $oldValues, ?array $newValues): void
    {
        // \App\Models\Tenant\AuditLog::create([
        //     'user_id' => Auth::id(),
        //     'user_name' => Auth::user()?->name,
        //     'ip_address' => request()->ip(),
        //     'action' => $action,
        //     'model_type' => ShiftAssignment::class,
        //     'model_id' => $assignment->id,
        //     'old_values' => $oldValues,
        //     'new_values' => $newValues,
        //     'description' => $this->generateDescription($action, $assignment),
        //     'tags' => 'shift_assignment',
        // ]);
    }

    /**
     * Generate human-readable description
     */
    protected function generateDescription(string $action, ShiftAssignment $assignment): string
    {
        $user = Auth::user();
        $userName = $user ? $user->name : 'System';
        $assignedUser = $assignment->user?->name ?? 'User #' . $assignment->user_id;
        $shiftName = $assignment->shift?->shift_name ?? 'Shift #' . $assignment->shift_id;

        return match ($action) {
            'created' => "{$userName} assigned {$assignedUser} to {$shiftName} on {$assignment->shift_date->format('Y-m-d')}",
            'updated' => "{$userName} updated shift assignment for {$assignedUser}",
            'deleted' => "{$userName} deleted shift assignment for {$assignedUser}",
            'restored' => "{$userName} restored shift assignment for {$assignedUser}",
            default => "{$userName} performed {$action} on shift assignment",
        };
    }
}
