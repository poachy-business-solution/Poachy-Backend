<?php

namespace App\Services\Tenant;

use App\Models\BusinessDetail;
use App\Models\BusinessSubscription;
use Illuminate\Support\Facades\Cache;

class TenantAccessService
{
    /**
     * Check if tenant has valid access to the platform.
     * 
     * @return array ['allowed' => bool, 'reason' => string|null, 'details' => array]
     */
    public function checkTenantAccess(string $tenantId): array
    {
        // Cache the result for 24 hours
        return Cache::remember(
            "tenant_access:{$tenantId}",
            now()->addHours(24),
            fn() => $this->performAccessCheck($tenantId)
        );
    }

    /**
     * Perform the actual access check.
     */
    private function performAccessCheck(string $tenantId): array
    {
        // 1. Check if business details exist
        $businessDetail = BusinessDetail::on('central')
            ->where('tenant_id', $tenantId)
            ->first();

        if (!$businessDetail) {
            return [
                'allowed' => false,
                'reason' => 'business_details_missing',
                'message' => 'Business details not found. Please complete your business profile.',
                'details' => [
                    'action_required' => 'submit_business_details',
                    'endpoint' => '/api/v1/tenant/business-details',
                ],
            ];
        }

        // 2. Check if business is active
        if ($businessDetail->status !== 'active') {
            return [
                'allowed' => false,
                'reason' => 'business_not_active',
                'message' => $this->getBusinessStatusMessage($businessDetail->status),
                'details' => [
                    'current_status' => $businessDetail->status,
                    'action_required' => 'contact_support',
                ],
            ];
        }

        // 3. Check if business is onboarded
        if (is_null($businessDetail->onboarded_at)) {
            return [
                'allowed' => false,
                'reason' => 'business_not_onboarded',
                'message' => 'Business onboarding is incomplete. Please complete the onboarding process.',
                'details' => [
                    'action_required' => 'complete_onboarding',
                    'status' => $businessDetail->status,
                ],
            ];
        }

        // 4. Check subscription status
        $subscriptionCheck = $this->checkSubscriptionStatus($tenantId);

        if (!$subscriptionCheck['valid']) {
            return [
                'allowed' => false,
                'reason' => $subscriptionCheck['reason'],
                'message' => $subscriptionCheck['message'],
                'details' => $subscriptionCheck['details'],
            ];
        }

        // All checks passed
        return [
            'allowed' => true,
            'reason' => null,
            'message' => 'Access granted',
            'details' => [
                'business_name' => $businessDetail->business_name,
                'subscription' => $subscriptionCheck['subscription'],
            ],
        ];
    }

    /**
     * Check subscription status and validity.
     */
    private function checkSubscriptionStatus(string $tenantId): array
    {
        $subscription = BusinessSubscription::on('central')
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->orderBy('created_at', 'desc')
            ->first();

        // No active subscription found
        if (!$subscription) {
            return [
                'valid' => false,
                'reason' => 'no_active_subscription',
                'message' => 'No active subscription found. Please subscribe to a plan to continue.',
                'details' => [
                    'action_required' => 'subscribe',
                    'available_plans_endpoint' => '/api/v1/central/subscription-plans',
                ],
            ];
        }

        // Check if subscription is in trial
        if ($subscription->isInTrial()) {
            // Check if trial has expired
            if ($subscription->trial_ends_at && now()->gt($subscription->trial_ends_at)) {
                return [
                    'valid' => false,
                    'reason' => 'trial_expired',
                    'message' => 'Your free trial has expired. Please upgrade to a paid plan to continue.',
                    'details' => [
                        'action_required' => 'upgrade_subscription',
                        'trial_ended_at' => $subscription->trial_ends_at->toDateTimeString(),
                        'plan_name' => $subscription->plan->name ?? 'Unknown',
                    ],
                ];
            }

            // Trial is still valid
            return [
                'valid' => true,
                'subscription' => [
                    'type' => 'trial',
                    'plan' => $subscription->plan->name ?? 'Unknown',
                    'trial_ends_at' => $subscription->trial_ends_at?->toDateTimeString(),
                    'days_remaining' => $subscription->trial_ends_at ? now()->diffInDays($subscription->trial_ends_at, false) : null,
                ],
            ];
        }

        // Check if paid subscription has expired
        if ($subscription->end_date && now()->gt($subscription->end_date)) {
            return [
                'valid' => false,
                'reason' => 'subscription_expired',
                'message' => 'Your subscription has expired. Please renew to continue using the platform.',
                'details' => [
                    'action_required' => 'renew_subscription',
                    'expired_at' => $subscription->end_date->toDateTimeString(),
                    'plan_name' => $subscription->plan->name ?? 'Unknown',
                    'auto_renew' => $subscription->auto_renew,
                ],
            ];
        }

        // Subscription is valid
        return [
            'valid' => true,
            'subscription' => [
                'type' => 'active',
                'plan' => $subscription->plan->name ?? 'Unknown',
                'plan_slug' => $subscription->plan->slug ?? null,
                'start_date' => $subscription->start_date?->toDateString(),
                'end_date' => $subscription->end_date?->toDateString(),
                'days_remaining' => $subscription->end_date ? now()->diffInDays($subscription->end_date, false) : null,
                'auto_renew' => $subscription->auto_renew,
            ],
        ];
    }

    /**
     * Get user-friendly message for business status.
     */
    private function getBusinessStatusMessage(string $status): string
    {
        return match ($status) {
            'pending' => 'Your business is pending approval. Please wait for admin verification.',
            'inactive' => 'Your business account is inactive. Please contact support for assistance.',
            'suspended' => 'Your business account has been suspended. Please contact support immediately.',
            default => 'Your business account status does not allow access to the platform.',
        };
    }

    /**
     * Clear cached access check for a tenant.
     */
    public function clearTenantAccessCache(string $tenantId): void
    {
        Cache::forget("tenant_access:{$tenantId}");
    }

    /**
     * Get subscription info for a tenant (without access check).
     */
    public function getSubscriptionInfo(string $tenantId): ?array
    {
        $subscription = BusinessSubscription::on('central')
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->with('plan')
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$subscription) {
            return null;
        }

        return [
            'id' => $subscription->id,
            'plan' => [
                'id' => $subscription->plan->id,
                'name' => $subscription->plan->name,
                'slug' => $subscription->plan->slug,
                'price' => $subscription->plan->price,
                'features' => $subscription->plan->features,
            ],
            'status' => $subscription->status,
            'is_trial' => $subscription->is_trial,
            'trial_ends_at' => $subscription->trial_ends_at?->toDateTimeString(),
            'start_date' => $subscription->start_date?->toDateString(),
            'end_date' => $subscription->end_date?->toDateString(),
            'auto_renew' => $subscription->auto_renew,
        ];
    }
}
