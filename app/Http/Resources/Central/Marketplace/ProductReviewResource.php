<?php

namespace App\Http\Resources\Central\Marketplace;

use App\Helpers\BusinessHelper;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductReviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                    => $this->id,
            'rating'                => (float) $this->rating,
            'title'                 => $this->title,
            'review_text'           => $this->review_text,
            'review_images'         => $this->review_images ?? [],
            'is_verified_purchase'  => (bool) $this->is_verified_purchase,
            'status'                => $this->status->value,
            'helpful_count'         => $this->helpful_count,
            'not_helpful_count'     => $this->not_helpful_count,
            'merchant_name'         => BusinessHelper::getBusinessName($this->order->tenant_id),
            'merchant_response'     => $this->merchant_response,
            'merchant_responded_at' => $this->merchant_responded_at?->toISOString(),

            // Customer info — display name only, no PII
            'customer' => $this->when(
                $this->relationLoaded('customer') && $this->customer,
                fn () => [
                    'id'   => $this->customer->id,
                    'name' => $this->customer->user?->name ?? 'Customer',
                ]
            ),

            // Admin-only fields
            'rejection_reason' => $this->when(
                $request->user()?->hasRole('admin'),
                $this->rejection_reason
            ),
            'moderated_at' => $this->when(
                $request->user()?->hasRole('admin'),
                $this->moderated_at?->toISOString()
            ),

            // Flag information (admin only)
            'flags_count' => $this->when(
                $request->user()?->hasRole('admin') && isset($this->flags_count),
                $this->flags_count ?? 0
            ),
            'flags' => $this->when(
                $request->user()?->hasRole('admin') && $this->relationLoaded('flags'),
                fn () => $this->flags->map(fn ($flag) => [
                    'id'         => $flag->id,
                    'reason'     => $flag->reason,
                    'flagged_by' => [
                        'id'   => $flag->customer->id,
                        'name' => $flag->customer->user?->name ?? 'Customer',
                    ],
                    'flagged_at' => $flag->created_at->toISOString(),
                ])
            ),

            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
