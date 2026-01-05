<?php

namespace App\Policies\Tenant;

use App\Models\Tenant\ShiftSwapRequest;
use App\Models\Tenant\User;

class ShiftSwapRequestPolicy
{
    /**
     * Determine if the user can view any shift swap requests
     */
    public function viewAny(User $user): bool
    {
        return true; // All users can view (filtered in controller)
    }

    /**
     * Determine if the user can view a specific shift swap request
     */
    public function view(User $user, ShiftSwapRequest $swapRequest): bool
    {
        // Managers can view all
        if ($user->hasAnyRole(['manager', 'admin', 'owner'])) {
            return true;
        }

        // Users can view if they're involved
        return $swapRequest->requester_id === $user->id
            || $swapRequest->target_user_id === $user->id;
    }

    /**
     * Determine if the user can create (execute) a shift swap
     * Only managers/admins/owners can execute swaps
     */
    public function create(User $user): bool
    {
        return $user->hasAnyRole(['manager', 'admin', 'owner']);
    }

    /**
     * Determine if the user can delete a shift swap record
     */
    public function delete(User $user, ShiftSwapRequest $swapRequest): bool
    {
        // Only admins and owners can delete swap records
        return $user->hasAnyRole(['admin', 'owner']);
    }
}
