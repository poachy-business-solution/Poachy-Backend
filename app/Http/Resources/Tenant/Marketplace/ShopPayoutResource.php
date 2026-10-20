<?php

namespace App\Http\Resources\Tenant\Marketplace;

use App\Models\MarketplacePayout;
use App\Models\MarketplacePayoutLine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A payout as the shop sees it; with `lines` loaded it is the payout's statement.
 *
 * @mixin MarketplacePayout
 */
class ShopPayoutResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'amount' => (float) $this->amount,
            'status' => $this->status,
            'phone' => $this->phone ? substr($this->phone, 0, 4).'•••'.substr($this->phone, -3) : null,
            'mpesa_receipt' => $this->mpesa_receipt,
            'failure_reason' => in_array($this->status, ['failed', 'failed_final', 'needs_check'], true) ? $this->failure_reason : null,
            'lines_count' => $this->lines_count,
            'paid_at' => $this->paid_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'lines' => $this->whenLoaded('lines', fn () => $this->lines->map(fn (MarketplacePayoutLine $line) => [
                'type' => $line->type,
                'description' => $line->description,
                'gross' => (float) $line->gross,
                'processing_fee' => (float) $line->processing_fee,
                'commission_rate' => (float) $line->commission_rate,
                'commission_amount' => (float) $line->commission_amount,
                'net' => (float) $line->net,
                'source' => $line->source,
                'payment_method' => $line->payment_method,
                'handed_over_at' => $line->handed_over_at?->toIso8601String(),
            ])->values()),
        ];
    }
}
