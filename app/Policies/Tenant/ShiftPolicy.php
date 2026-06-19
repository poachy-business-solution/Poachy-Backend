<?php

namespace App\Policies\Tenant;

use App\Models\Tenant\Shift;
use App\Models\Tenant\User;

class ShiftPolicy
{
    /**
     * Determine if the user can view any shifts.
     */
    public function viewAny(User $user): bool
    {
        // All authenticated tenant users can view shifts
        return true;
    }

    /**
     * Determine if the user can view the shift.
     */
    public function view(User $user, Shift $shift): bool
    {
        // All authenticated tenant users can view shifts
        return true;
    }

    /**
     * Determine if the user can create shifts.
     */
    public function create(User $user): bool
    {
        // Only managers, admins, and owners can create shifts
        return $user->hasAnyRole(['manager', 'admin', 'owner']);
    }

    /**
     * Determine if the user can update the shift.
     */
    public function update(User $user, Shift $shift): bool
    {
        // Only managers, admins, and owners can update shifts
        return $user->hasAnyRole(['manager', 'admin', 'owner']);
    }

    /**
     * Determine if the user can delete the shift.
     */
    public function delete(User $user, Shift $shift): bool
    {
        // Only admins and owners can delete shifts
        return $user->hasAnyRole(['admin', 'owner']);
    }

    /**
     * Determine if the user can restore the shift.
     */
    public function restore(User $user, Shift $shift): bool
    {
        // Only admins and owners can restore shifts
        return $user->hasAnyRole(['admin', 'owner']);
    }

    /**
     * Determine if the user can permanently delete the shift.
     */
    public function forceDelete(User $user, Shift $shift): bool
    {
        // Only owners can force delete shifts
        return $user->hasRole('owner');
    }
}
