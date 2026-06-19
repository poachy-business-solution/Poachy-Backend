<?php

namespace App\Http\Resources\Tenant\Customer;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\Tenant\User;

class LoyaltyTransactionResource extends JsonResource
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
            'customer_id' => $this->customer_id,
            'customer_name' => $this->customer?->name,
            'customer_phone' => $this->customer?->phone,
            'customer_email' => $this->customer?->email,
            'transaction_type' => $this->transaction_type->value,
            'points' => (float) $this->points,
            'balance_after' => (float) $this->balance_after,
            'reference_type' => $this->reference_type,
            'reference_id' => $this->reference_id,
            'reference' => $this->formatReference(),
            'description' => $this->description,
            'expires_at' => $this->expires_at?->format('Y-m-d'),
            'is_expired' => $this->is_expired,
            'days_until_expiry' => $this->calculateDaysUntilExpiry(),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }

    /**
     * Format the polymorphic reference based on type
     */
    private function formatReference(): ?array
    {
        // Handle manual awards (null reference_type with reference_id pointing to user)
        if ($this->reference_type === null && $this->reference_id) {
            $user = User::find($this->reference_id);
            return [
                'type' => 'Manual Award',
                'awarded_by' => $user?->name ?? 'Unknown User',
                'awarded_by_id' => $this->reference_id,
            ];
        }

        // If no reference at all
        if (!$this->reference_type || !$this->reference) {
            return null;
        }

        // Handle different reference types
        $type = class_basename($this->reference_type);

        switch ($type) {
            case 'Sale':
                return [
                    'type' => 'Sale',
                    'sale_number' => $this->reference->sale_number ?? null,
                    'total_amount' => (float) ($this->reference->total_amount ?? 0),
                    'sale_date' => $this->reference->sale_date?->format('Y-m-d'),
                ];

            default:
                return [
                    'type' => $type,
                    'id' => $this->reference_id,
                ];
        }
    }

    /**
     * Calculate days until expiry
     */
    private function calculateDaysUntilExpiry(): ?int
    {
        if (!$this->expires_at) {
            return null;
        }

        if ($this->expires_at->isPast()) {
            return 0;
        }

        return now()->diffInDays($this->expires_at);
    }
}
