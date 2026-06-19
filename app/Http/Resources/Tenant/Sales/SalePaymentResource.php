<?php

namespace App\Http\Resources\Tenant\Sales;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalePaymentResource extends JsonResource
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
            'amount' => (float) $this->amount,
            'payment_method' => $this->payment_method->value,
            'payment_method_label' => $this->payment_method->label(),
            'reference_number' => $this->reference_number,
            'payment_date' => $this->payment_date->toIso8601String(),
            'received_by' => [
                'id' => $this->receivedBy->id,
                'name' => $this->receivedBy->name,
            ],
            'notes' => $this->notes,
            'is_electronic' => $this->isElectronic(),
        ];
    }
}
