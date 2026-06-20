<?php

namespace App\Http\Resources\Central\Subscription;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionPaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                       => $this->id,
            'tenant_id'                => $this->tenant_id,
            'plan_id'                  => $this->subscription_plan_id,
            'plan_name'                => $this->subscriptionPlan?->name,
            'payment_type'             => $this->payment_type, // 'stk' or 'c2b'
            'customer_phone'           => $this->customer_phone,
            'amount'                   => (float) $this->amount,
            'payment_status'           => $this->payment_status->value,
            'transaction_reference'    => $this->transaction_reference,
            'provider_reference'       => $this->provider_reference,
            'business_subscription_id' => $this->business_subscription_id,
            'initiated_at'             => $this->initiated_at?->toIso8601String(),
            'completed_at'             => $this->completed_at?->toIso8601String(),
            'failed_at'                => $this->failed_at?->toIso8601String(),
            'failure_reason'           => $this->failure_reason,
        ];
    }
}
