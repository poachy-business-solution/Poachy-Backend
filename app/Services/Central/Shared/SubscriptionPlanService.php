<?php

namespace App\Services\Central\Shared;

use App\Models\SubscriptionPlan;
use App\Repositories\Central\SubscriptionPlanRepository;
use Illuminate\Database\Eloquent\Collection;

class SubscriptionPlanService
{
    public function __construct(
        private readonly SubscriptionPlanRepository $subscriptionPlanRepository
    ) {}

    /**
     * Get a list of subscription plans with optional filtering and sorting.
     */
    public function listPlans(array $filters = []): Collection
    {
        return $this->subscriptionPlanRepository->getPlans($filters);
    }

    /**
     * Get a specific subscription plan by ID.
     *
     * @return SubscriptionPlan|null
     */
    public function getPlanById(int $planId)
    {
        return $this->subscriptionPlanRepository->findById($planId);
    }

    /**
     * Get a specific subscription plan by slug.
     *
     * @return SubscriptionPlan|null
     */
    public function getPlanBySlug(string $slug)
    {
        return $this->subscriptionPlanRepository->findBySlug($slug);
    }

    /**
     * Get only active subscription plans.
     */
    public function getActivePlans(): Collection
    {
        return $this->subscriptionPlanRepository->getActivePlans();
    }

    /**
     * Get featured subscription plans.
     */
    public function getFeaturedPlans(): Collection
    {
        return $this->subscriptionPlanRepository->getFeaturedPlans();
    }

    /**
     * Check if a plan is free.
     */
    public function isPlanFree(int $planId): bool
    {
        $plan = $this->getPlanById($planId);

        return $plan ? $plan->isFree() : false;
    }

    /**
     * Get plan feature value.
     *
     * @param  mixed  $default
     * @return mixed
     */
    public function getPlanFeature(int $planId, string $featureKey, $default = null)
    {
        $plan = $this->getPlanById($planId);

        return $plan ? $plan->getFeature($featureKey, $default) : $default;
    }
}
