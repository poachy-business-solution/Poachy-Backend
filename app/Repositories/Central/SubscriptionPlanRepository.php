<?php

namespace App\Repositories\Central;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Eloquent\Collection;

class SubscriptionPlanRepository
{
    /**
     * Get subscription plans with filtering and sorting.
     *
     * @param array $filters
     * @return Collection
     */
    public function getPlans(array $filters = []): Collection
    {
        $query = SubscriptionPlan::query();

        // Filter by active status
        if (isset($filters['is_active'])) {
            $query->where('is_active', (bool) $filters['is_active']);
        }

        // Filter by featured status
        if (isset($filters['is_featured'])) {
            $query->where('is_featured', (bool) $filters['is_featured']);
        }

        // Filter by price range
        if (isset($filters['min_price'])) {
            $query->where('price', '>=', $filters['min_price']);
        }

        if (isset($filters['max_price'])) {
            $query->where('price', '<=', $filters['max_price']);
        }

        // Sorting
        $sortBy = $filters['sort_by'] ?? 'price';
        $sortOrder = $filters['sort_order'] ?? 'asc';
        $query->orderBy($sortBy, $sortOrder);

        // Default secondary sort by name
        if ($sortBy !== 'name') {
            $query->orderBy('name', 'asc');
        }

        return $query->get();
    }

    /**
     * Find a subscription plan by ID.
     *
     * @param int $planId
     * @return SubscriptionPlan|null
     */
    public function findById(int $planId): ?SubscriptionPlan
    {
        return SubscriptionPlan::find($planId);
    }

    /**
     * Find a subscription plan by slug.
     *
     * @param string $slug
     * @return SubscriptionPlan|null
     */
    public function findBySlug(string $slug): ?SubscriptionPlan
    {
        return SubscriptionPlan::where('slug', $slug)->first();
    }

    /**
     * Get only active subscription plans.
     *
     * @return Collection
     */
    public function getActivePlans(): Collection
    {
        return SubscriptionPlan::active()
            ->orderBy('price', 'asc')
            ->get();
    }

    /**
     * Get featured subscription plans.
     *
     * @return Collection
     */
    public function getFeaturedPlans(): Collection
    {
        return SubscriptionPlan::featured()
            ->active()
            ->orderBy('price', 'asc')
            ->get();
    }

    /**
     * Get free plans.
     *
     * @return Collection
     */
    public function getFreePlans(): Collection
    {
        return SubscriptionPlan::where('price', 0)
            ->active()
            ->get();
    }

    /**
     * Get paid plans.
     *
     * @return Collection
     */
    public function getPaidPlans(): Collection
    {
        return SubscriptionPlan::where('price', '>', 0)
            ->active()
            ->orderBy('price', 'asc')
            ->get();
    }

    /**
     * Count active subscriptions for a plan.
     *
     * @param int $planId
     * @return int
     */
    public function countActiveSubscriptions(int $planId): int
    {
        $plan = $this->findById($planId);

        return $plan ? $plan->activeSubscriptions()->count() : 0;
    }
}
