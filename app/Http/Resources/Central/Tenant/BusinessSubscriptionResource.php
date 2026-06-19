<?php

namespace App\Http\Resources\Central\Tenant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BusinessSubscriptionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            // Subscription Details
            'subscription' => [
                'plan_id' => $this->subscription_plan_id,
                'plan_name' => $this->plan?->name,
                'plan_slug' => $this->plan?->slug,
            ],

            // Period
            'period' => [
                'start_date' => $this->start_date?->toDateString(),
                'end_date' => $this->end_date?->toDateString(),
                'duration_days' => $this->start_date && $this->end_date
                    ? $this->start_date->diffInDays($this->end_date)
                    : null,
            ],

            // Payment Information
            'payment' => [
                'amount_paid' => (float) $this->amount_paid,
                'currency' => $this->currency,
                'payment_method' => $this->payment_method,
                'payment_reference' => $this->payment_reference,
                'payment_date' => $this->payment_date?->toISOString(),
            ],

            // Status
            'status' => [
                'current_status' => $this->status,
                'is_active' => $this->isActive(),
                'is_expired' => $this->isExpired(),
                'auto_renew' => $this->auto_renew,
            ],

            // Trial Information
            'trial' => [
                'is_trial' => $this->is_trial,
                'trial_ends_at' => $this->trial_ends_at?->toDateString(),
                'is_in_trial' => $this->isInTrial(),
                'trial_days_remaining' => $this->trial_ends_at && $this->isInTrial()
                    ? (int) now()->diffInDays($this->trial_ends_at)
                    : null,
            ],

            // Cancellation
            'cancellation' => [
                'cancelled_at' => $this->cancelled_at?->toISOString(),
                'cancellation_reason' => $this->cancellation_reason,
                'is_cancelled' => $this->status === 'cancelled',
            ],

            // Metadata
            'metadata' => [
                'created_at' => $this->created_at?->toISOString(),
                'updated_at' => $this->updated_at?->toISOString(),
            ],
        ];
    }
}
