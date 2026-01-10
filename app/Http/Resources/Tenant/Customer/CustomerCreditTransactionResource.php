<?php

namespace App\Http\Resources\Tenant\Customer;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerCreditTransactionResource extends JsonResource
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
            'amount' => (float) $this->amount,
            'absolute_amount' => (float) $this->absolute_amount,
            'balance_after' => (float) $this->balance_after,
            'reference_type' => $this->reference_type,
            'reference_id' => $this->reference_id,
            'reference' => $this->when(
                $this->relationLoaded('reference'),
                fn() => $this->formatReference()
            ),
            'payment_method' => $this->payment_method?->value,
            'payment_reference' => $this->payment_reference,
            'notes' => $this->notes,
            'created_by' => $this->created_by,
            'created_by_name' => $this->createdBy?->name,
            'is_debit' => $this->is_debit,
            'is_credit' => $this->is_credit,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
            'deleted_at' => $this->when($this->deleted_at, fn() => $this->deleted_at->toIso8601String()),
        ];
    }

    /**
     * Format the polymorphic reference based on type
     */
    private function formatReference(): ?array
    {
        if (!$this->reference) {
            return null;
        }

        // Handle different reference types
        $type = class_basename($this->reference_type);

        switch ($type) {
            case 'Sale':
                return [
                    'type' => 'sale',
                    'sale_number' => $this->reference->sale_number ?? null,
                    'total_amount' => (float) ($this->reference->total_amount ?? 0),
                    'sale_date' => $this->reference->sale_date?->format('Y-m-d'),
                    'payment_status' => $this->reference->payment_status?->value,
                ];

            case 'manual_payment':
            case 'manual_adjustment':
                return [
                    'type' => $type,
                    'processed_by' => $this->createdBy?->name ?? 'System',
                ];

            default:
                return [
                    'type' => $type,
                    'id' => $this->reference_id,
                ];
        }
    }
}
