<?php

namespace App\Http\Resources\Central\Marketplace;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MerchantReviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'overall_rating' => (float) $this->overall_rating,
            'ratings'        => [
                'product_quality' => $this->product_quality_rating !== null ? (float) $this->product_quality_rating : null,
                'delivery'        => $this->delivery_rating !== null ? (float) $this->delivery_rating : null,
                'service'         => $this->service_rating !== null ? (float) $this->service_rating : null,
            ],
            'review_text'      => $this->review_text,
            'status'           => $this->status->value,
            'helpful_count'    => $this->helpful_count,
            'not_helpful_count' => $this->not_helpful_count,

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
