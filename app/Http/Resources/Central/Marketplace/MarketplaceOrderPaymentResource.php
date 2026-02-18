<?php

namespace App\Http\Resources\Central\Marketplace;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MarketplaceOrderPaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                    => $this->id,
            'payment_method'        => $this->payment_method->value,
            'payment_provider'      => $this->payment_provider,
            'amount'                => (float) $this->amount,
            'payment_status'        => $this->payment_status->value,
            'transaction_reference' => $this->transaction_reference,
            'provider_reference'    => $this->provider_reference,
            'is_refunded'           => (bool) $this->is_refunded,
            'refunded_amount'       => $this->when($this->is_refunded, (float) $this->refunded_amount),
            'initiated_at'          => $this->initiated_at?->toIso8601String(),
            'completed_at'          => $this->completed_at?->toIso8601String(),
            'failed_at'             => $this->failed_at?->toIso8601String(),
            'failure_reason'        => $this->failure_reason,
        ];
    }
}
