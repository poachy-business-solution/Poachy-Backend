<?php

namespace App\Http\Resources\Tenant\Billing;

use App\Models\BillingLedgerEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One statement line.
 *
 * @mixin BillingLedgerEntry
 */
class BillingLedgerEntryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'amount' => (float) $this->amount,
            'balance_after' => (float) $this->balance_after,
            'description' => $this->description,
            'reference' => $this->reference,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
