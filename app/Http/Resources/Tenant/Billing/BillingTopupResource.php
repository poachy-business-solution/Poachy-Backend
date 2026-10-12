<?php

namespace App\Http\Resources\Tenant\Billing;

use App\Helpers\PhoneNumber;
use App\Models\BillingTopup;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BillingTopup
 */
class BillingTopupResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'amount' => (float) $this->amount,
            'phone' => PhoneNumber::mask($this->phone),
            'status' => $this->status,
            'mpesa_receipt' => $this->mpesa_receipt,
            'failure_reason' => $this->failure_reason,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
