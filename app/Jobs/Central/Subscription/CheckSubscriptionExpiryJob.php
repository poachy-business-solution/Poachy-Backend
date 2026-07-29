<?php

namespace App\Jobs\Central\Subscription;

use App\Models\BusinessSubscription;
use App\Services\Central\Subscription\SubscriptionExpiryService;
use App\Services\Tenant\TenantAccessService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CheckSubscriptionExpiryJob implements ShouldQueue
{
    use Queueable;

    public function __construct()
    {
        $this->onQueue('sync-low');
    }

    public function handle(SubscriptionExpiryService $expiryService, TenantAccessService $tenantAccess): void
    {
        foreach ($expiryService->getActiveExpiredSubscriptionIds() as $id) {
            $subscription = BusinessSubscription::on('central')->find($id);

            if (! $subscription) {
                continue;
            }

            $subscription->update(['status' => 'expired']);
            $tenantAccess->clearTenantAccessCache($subscription->tenant_id);
        }

        foreach ($expiryService->getSubscriptionIdsDueFor7DayReminder() as $id) {
            SendSubscriptionReminderJob::dispatch($id, 7);
        }

        foreach ($expiryService->getSubscriptionIdsDueFor1DayReminder() as $id) {
            SendSubscriptionReminderJob::dispatch($id, 1);
        }

        foreach ($expiryService->getExpiredSubscriptionIdsNeedingNotification() as $id) {
            SendSubscriptionExpiredNoticeJob::dispatch($id);
        }
    }
}
