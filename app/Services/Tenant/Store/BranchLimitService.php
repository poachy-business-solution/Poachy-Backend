<?php

namespace App\Services\Tenant\Store;

use App\Models\Tenant;
use App\Models\Tenant\Store;
use Illuminate\Validation\ValidationException;

/**
 * Enforces the subscription plan's `max_locations` (until now pricing-page
 * copy only): a business can't have more active branches than its plan
 * allows. No active subscription, or "unlimited", means no limit.
 */
class BranchLimitService
{
    /**
     * The plan's branch limit, or null for no limit.
     */
    public function maxBranches(): ?int
    {
        $tenant = tenant();

        if (! $tenant instanceof Tenant) {
            return null;
        }

        $limit = $tenant->activeSubscription?->plan?->features['max_locations'] ?? null;

        return is_numeric($limit) ? (int) $limit : null;
    }

    /**
     * @throws ValidationException when one more active branch would pass the plan's limit
     */
    public function ensureCanAddActiveBranch(?Store $except = null): void
    {
        $limit = $this->maxBranches();

        if ($limit === null) {
            return;
        }

        $active = Store::query()
            ->where('is_active', true)
            ->when($except !== null, fn ($query) => $query->whereKeyNot($except->getKey()))
            ->count();

        if ($active >= $limit) {
            throw ValidationException::withMessages([
                'store' => "Your plan allows {$limit} ".($limit === 1 ? 'branch' : 'branches').'. Upgrade your plan to add another.',
            ]);
        }
    }
}
