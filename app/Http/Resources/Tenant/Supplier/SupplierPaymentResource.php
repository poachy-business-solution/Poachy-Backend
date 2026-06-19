<?php

namespace App\Http\Resources\Tenant\Supplier;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupplierPaymentResource extends JsonResource
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
            'payment_number' => $this->payment_number,

            // Supplier info
            'supplier' => [
                'id' => $this->supplier_id,
                'name' => $this->supplier->name,
                'current_outstanding' => round($this->supplier->outstanding_balance, 2),
                'credit_limit' => round($this->supplier->credit_limit, 2),
            ],

            // PO info (if linked)
            'purchase_order' => $this->when($this->purchase_order_id, function () {
                return [
                    'id' => $this->purchase_order_id,
                    'po_number' => $this->purchaseOrder->po_number,
                    'total_amount' => round($this->purchaseOrder->total_amount, 2),
                    'amount_paid' => round($this->purchaseOrder->amount_paid, 2),
                    'outstanding' => round(
                        $this->purchaseOrder->total_amount - $this->purchaseOrder->amount_paid,
                        2
                    ),
                    'payment_status' => $this->purchaseOrder->payment_status->value,
                    'payment_status_label' => $this->purchaseOrder->payment_status->label(),
                ];
            }),

            // Payment details
            'payment_date' => $this->payment_date->format('Y-m-d'),
            'amount' => round($this->amount, 2),
            'payment_method' => $this->payment_method->value,
            'payment_method_label' => $this->payment_method->label(),
            'reference_number' => $this->reference_number,
            'notes' => $this->notes,

            // Receipt
            'has_receipt' => $this->has_receipt,
            'receipt_url' => $this->when($this->has_receipt, $this->receipt_url),

            // Audit
            'created_by' => [
                'id' => $this->created_by,
                'name' => $this->createdBy->name,
            ],

            // Timestamps
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
