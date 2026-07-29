<?php

namespace App\Jobs\Central\Subscription;

use App\Mail\Central\Subscription\SubscriptionExpiredMail;
use App\Models\BusinessSubscription;
use App\Services\Central\Notification\SystemNotificationService;
use App\Services\Central\Subscription\SubscriptionExpiryService;
use App\Services\Central\Subscription\SubscriptionPaymentService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendSubscriptionExpiredNoticeJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $subscriptionId,
    ) {
        $this->onQueue('sync-low');
    }

    public function handle(
        SubscriptionExpiryService $expiryService,
        SubscriptionPaymentService $paymentService,
        SystemNotificationService $notifications,
    ): void {
        $subscription = BusinessSubscription::on('central')
            ->with(['plan', 'businessDetail'])
            ->find($this->subscriptionId);

        if (! $subscription) {
            Log::warning('Subscription not found for expired notice', ['subscription_id' => $this->subscriptionId]);

            return;
        }

        if ($subscription->expired_notified) {
            Log::info('Subscription expired notice already sent', ['subscription_id' => $subscription->id]);

            return;
        }

        $businessDetail = $subscription->businessDetail;

        if (! $businessDetail) {
            Log::warning('No business detail found for subscription expired notice', ['subscription_id' => $subscription->id]);

            return;
        }

        $instructions = $paymentService->getPaybillInstructions($subscription->tenant_id);
        $owner = $expiryService->resolveOwnerEmail($subscription->tenant_id);

        if ($owner) {
            Mail::to($owner['email'])->send(new SubscriptionExpiredMail(
                businessName: $businessDetail->business_name,
                planName: $subscription->plan->name,
                expiredDate: $subscription->end_date->toFormattedDateString(),
                paybillShortcode: $instructions['shortcode'],
                paybillAccountNumber: $instructions['account_number'],
                planPrice: (float) $subscription->plan->price,
            ));
        } else {
            Log::warning('No owner email found — skipping expired notice email, in-app notice only', [
                'subscription_id' => $subscription->id,
            ]);
        }

        $notifications->notifyTenant(
            businessDetailId: $businessDetail->id,
            type: 'subscription_expired',
            title: 'Your subscription has expired',
            message: "Your {$subscription->plan->name} plan expired on {$subscription->end_date->toFormattedDateString()}. Pay Paybill {$instructions['shortcode']}, account {$instructions['account_number']} to restore access.",
            data: ['subscription_id' => $subscription->id],
            actionUrl: '/api/v1/tenant/subscription/pay/paybill',
            actionLabel: 'Renew now',
        );

        $subscription->update([
            'expired_notified' => true,
            'expired_notified_at' => now(),
        ]);
    }
}
