<?php

namespace App\Listeners\Tenant\Concerns;

use App\Models\Tenant\Store;
use App\Models\Tenant\User;
use Illuminate\Support\Collection;

trait ResolvesNotificationRecipients
{
    /**
     * Store manager (if any) plus everyone with the 'owner' role.
     * Handles a null $storeId (e.g. company-wide budgets) by returning owners only.
     */
    protected function getManagerAndOwners(?int $storeId): Collection
    {
        $users = collect();

        if ($storeId) {
            $store = Store::find($storeId);

            if ($store && $store->manager_id) {
                $manager = User::find($store->manager_id);

                if ($manager) {
                    $users->push($manager);
                }
            }
        }

        $owners = User::role('owner')->get();
        $users = $users->merge($owners);

        return $users->unique('id');
    }

    /**
     * A single user by id (e.g. the original requester/submitter), as a one-item
     * collection so callers can iterate it the same way as getManagerAndOwners().
     */
    protected function getSingleUser(?int $userId): Collection
    {
        if (! $userId) {
            return collect();
        }

        $user = User::find($userId);

        return $user ? collect([$user]) : collect();
    }
}
