<?php

namespace App\Services\Central\Subscription;

use App\Models\BusinessSubscription;
use App\Models\Tenant;
use App\Models\Tenant\User as TenantUser;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class SubscriptionExpiryService
{
    /**
     * Active subscriptions whose end_date has already passed (excludes lifetime/free plans with a null end_date).
     */
    public function getActiveExpiredSubscriptionIds(): Collection
    {
        return BusinessSubscription::on('central')
            ->where('status', 'active')
            ->whereNotNull('end_date')
            ->where('end_date', '<', now()->toDateString())
            ->pluck('id');
    }

    /**
     * Active subscriptions expiring within the next 7 days that haven't had the 7-day reminder sent.
     */
    public function getSubscriptionIdsDueFor7DayReminder(): Collection
    {
        return BusinessSubscription::on('central')
            ->where('status', 'active')
            ->where('reminder_7day_sent', false)
            ->whereNotNull('end_date')
            ->whereBetween('end_date', [now()->toDateString(), now()->addDays(7)->toDateString()])
            ->pluck('id');
    }

    /**
     * Active subscriptions expiring within the next day that haven't had the final reminder sent.
     */
    public function getSubscriptionIdsDueFor1DayReminder(): Collection
    {
        return BusinessSubscription::on('central')
            ->where('status', 'active')
            ->where('reminder_1day_sent', false)
            ->whereNotNull('end_date')
            ->whereBetween('end_date', [now()->toDateString(), now()->addDays(1)->toDateString()])
            ->pluck('id');
    }

    /**
     * Expired subscriptions that haven't had the expiry notice sent yet.
     */
    public function getExpiredSubscriptionIdsNeedingNotification(): Collection
    {
        return BusinessSubscription::on('central')
            ->where('status', 'expired')
            ->where('expired_notified', false)
            ->pluck('id');
    }

    /**
     * Resolve the tenant's owner (name + email) by switching into the tenant's own database.
     * Mirrors BusinessDetailsService::sendApprovalEmail()'s tenancy-switch pattern.
     *
     * @return array{name: string, email: string}|null
     */
    public function resolveOwnerEmail(string $tenantId): ?array
    {
        $tenant = Tenant::on('central')->find($tenantId);

        if (! $tenant) {
            Log::warning('Tenant not found while resolving subscription notification owner', [
                'tenant_id' => $tenantId,
            ]);

            return null;
        }

        try {
            tenancy()->initialize($tenant);

            $owner = TenantUser::whereHas('roles', function ($query) {
                $query->where('name', 'owner');
            })->first();
        } catch (\Exception $e) {
            Log::error('Failed to resolve subscription notification owner', [
                'tenant_id' => $tenantId,
                'error' => $e->getMessage(),
            ]);

            return null;
        } finally {
            tenancy()->end();
        }

        if (! $owner) {
            Log::warning('Owner user not found for tenant while resolving subscription notification recipient', [
                'tenant_id' => $tenantId,
            ]);

            return null;
        }

        return [
            'name' => $owner->name,
            'email' => $owner->email,
        ];
    }
}
