<?php

namespace App\Jobs\Central\Subscription;

use App\Mail\Central\Subscription\SubscriptionReminderMail;
use App\Models\BusinessSubscription;
use App\Services\Central\Notification\SystemNotificationService;
use App\Services\Central\Subscription\SubscriptionExpiryService;
use App\Services\Central\Subscription\SubscriptionPaymentService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendSubscriptionReminderJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $subscriptionId,
        public int $reminderTier,
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
            Log::warning('Subscription not found for reminder', ['subscription_id' => $this->subscriptionId]);

            return;
        }

        $sentColumn = $this->reminderTier === 7 ? 'reminder_7day_sent' : 'reminder_1day_sent';

        if ($subscription->{$sentColumn}) {
            Log::info('Subscription reminder already sent', [
                'subscription_id' => $subscription->id,
                'tier' => $this->reminderTier,
            ]);

            return;
        }

        $businessDetail = $subscription->businessDetail;

        if (! $businessDetail) {
            Log::warning('No business detail found for subscription reminder', ['subscription_id' => $subscription->id]);

            return;
        }

        $instructions = $paymentService->getPaybillInstructions($subscription->tenant_id);
        $owner = $expiryService->resolveOwnerEmail($subscription->tenant_id);

        if ($owner) {
            Mail::to($owner['email'])->send(new SubscriptionReminderMail(
                businessName: $businessDetail->business_name,
                planName: $subscription->plan->name,
                amountDue: (float) $subscription->plan->price,
                expiryDate: $subscription->end_date->toFormattedDateString(),
                daysRemaining: $this->reminderTier,
                paybillShortcode: $instructions['shortcode'],
                paybillAccountNumber: $instructions['account_number'],
            ));
        } else {
            Log::warning('No owner email found — skipping reminder email, in-app notice only', [
                'subscription_id' => $subscription->id,
            ]);
        }

        $notifications->notifyTenant(
            businessDetailId: $businessDetail->id,
            type: 'subscription_expiring',
            title: $this->reminderTier <= 1
                ? 'Final notice: subscription expires tomorrow'
                : "Your subscription expires in {$this->reminderTier} days",
            message: "Your {$subscription->plan->name} plan expires on {$subscription->end_date->toFormattedDateString()}. Pay Paybill {$instructions['shortcode']}, account {$instructions['account_number']} to renew.",
            data: ['subscription_id' => $subscription->id, 'days_remaining' => $this->reminderTier],
            actionUrl: '/api/v1/tenant/subscription/pay/paybill',
            actionLabel: 'Renew now',
        );

        $subscription->update([
            $sentColumn => true,
            "{$sentColumn}_at" => now(),
        ]);
    }
}
