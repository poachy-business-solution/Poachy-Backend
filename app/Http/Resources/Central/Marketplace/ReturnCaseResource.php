<?php

namespace App\Http\Resources\Central\Marketplace;

use App\Models\MarketplaceRefund;
use App\Models\MarketplaceReturnCase;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A return case for the buyer (web) and the shop (app): same shape, evidence as counts (the shop
 * opens the buyer's photos through its own endpoint).
 *
 * @mixin MarketplaceReturnCase
 */
class ReturnCaseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $refund = $this->relationLoaded('refunds') ? $this->refunds->sortByDesc('id')->first() : null;

        return [
            'reference' => $this->reference,
            'order_number' => $this->order?->order_number,
            'shop' => $this->order?->merchant_name,
            'status' => $this->status,
            'items' => $this->items,
            'perishable' => (bool) $this->perishable,
            'bring_back_by' => $this->status === 'requested'
                ? $this->created_at?->copy()->{$this->perishable ? 'addHours' : 'addDays'}($this->perishable ? (int) config('returns.perishable_bring_back_hours', 24) : (int) config('returns.bring_back_days', 7))->toIso8601String()
                : null,
            'reason_code' => $this->reason_code,
            'description' => $this->description,
            'shop_at_fault' => $this->shop_at_fault,
            'requested_amount' => (float) $this->requested_amount,
            'refund_amount' => $this->refund_amount === null ? null : (float) $this->refund_amount,
            'shop_decision' => $this->shop_decision,
            'shop_reason' => $this->shop_reason,
            'admin_decision' => $this->admin_decision,
            'admin_note' => $this->admin_note,
            'buyer_photos' => count($this->buyer_evidence ?? []),
            'shop_photos' => count($this->shop_evidence ?? []),
            'dispute_reason' => $this->dispute_reason,
            'received_at' => $this->received_at?->toIso8601String(),
            'inspection_due_at' => $this->inspection_due_at?->toIso8601String(),
            'dispute_until' => $this->dispute_until?->toIso8601String(),
            'can_dispute' => $this->status === 'rejected' && $this->dispute_until?->isFuture(),
            'can_cancel' => $this->status === 'requested',
            'refund' => $refund instanceof MarketplaceRefund ? [
                'amount' => (float) $refund->amount, 'status' => $refund->status, 'method' => $refund->method,
                'completed_at' => $refund->completed_at?->toIso8601String(),
            ] : null,
            'created_at' => $this->created_at?->toIso8601String(),
            'closed_at' => $this->closed_at?->toIso8601String(),
        ];
    }
}
