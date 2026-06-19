<?php

namespace App\Policies\Tenant;

use App\Models\Tenant\ShiftAssignment;
use App\Models\Tenant\User;

class ShiftAssignmentPolicy
{
    /**
     * Determine if the user can view any shift assignments.
     */
    public function viewAny(User $user): bool
    {
        // All authenticated tenant users can view assignments
        // (but will be filtered to own assignments for non-managers in controller)
        return true;
    }

    /**
     * Determine if the user can view the shift assignment.
     */
    public function view(User $user, ShiftAssignment $assignment): bool
    {
        // Users can view their own assignments
        if ($assignment->user_id === $user->id) {
            return true;
        }

        // Managers, admins, and owners can view all assignments
        return $user->hasAnyRole(['manager', 'admin', 'owner']);
    }

    /**
     * Determine if the user can create shift assignments.
     */
    public function create(User $user): bool
    {
        // Only managers, admins, and owners can create assignments
        return $user->hasAnyRole(['manager', 'admin', 'owner']);
    }

    /**
     * Determine if the user can update the shift assignment.
     */
    public function update(User $user, ShiftAssignment $assignment): bool
    {
        // Only managers, admins, and owners can update assignments
        return $user->hasAnyRole(['manager', 'admin', 'owner']);
    }

    /**
     * Determine if the user can delete/cancel the shift assignment.
     */
    public function delete(User $user, ShiftAssignment $assignment): bool
    {
        // Users can cancel their own future assignments
        if ($assignment->user_id === $user->id && $assignment->shift_date->isFuture()) {
            return true;
        }

        // Managers, admins, and owners can cancel any assignment
        return $user->hasAnyRole(['manager', 'admin', 'owner']);
    }

    /**
     * Determine if the user can clock in to the shift.
     */
    public function clockIn(User $user, ShiftAssignment $assignment): bool
    {
        // Users can only clock in to their own shifts
        return $assignment->user_id === $user->id
            && $assignment->canClockIn();
    }

    /**
     * Determine if the user can clock out of the shift.
     */
    public function clockOut(User $user, ShiftAssignment $assignment): bool
    {
        // Users can only clock out of their own shifts
        return $assignment->user_id === $user->id
            && $assignment->canClockOut();
    }

    /**
     * Determine if the user can approve the shift assignment.
     */
    public function approve(User $user, ShiftAssignment $assignment): bool
    {
        // Cannot approve own shift
        if ($assignment->user_id === $user->id) {
            return false;
        }

        // Only managers, admins, and owners can approve shifts
        if (!$user->hasAnyRole(['manager', 'admin', 'owner'])) {
            return false;
        }

        // Shift must be in completed status
        return $assignment->canBeApproved();
    }

    /**
     * Determine if the user can restore the shift assignment.
     */
    public function restore(User $user, ShiftAssignment $assignment): bool
    {
        // Only admins and owners can restore assignments
        return $user->hasAnyRole(['admin', 'owner']);
    }

    /**
     * Determine if the user can permanently delete the shift assignment.
     */
    public function forceDelete(User $user, ShiftAssignment $assignment): bool
    {
        // Only owners can force delete assignments
        return $user->hasRole('owner');
    }
}
