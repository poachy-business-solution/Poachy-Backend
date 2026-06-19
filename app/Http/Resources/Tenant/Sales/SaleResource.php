<?php

namespace App\Http\Resources\Tenant\Sales;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SaleResource extends JsonResource
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
            'sale_number' => $this->sale_number,
            'sale_date' => $this->sale_date->toIso8601String(),

            'store' => [
                'id' => $this->store->id,
                'name' => $this->store->name,
                'code' => $this->store->code,
            ],

            'customer' => $this->when(
                $this->customer,
                function () {
                    return [
                        'id' => $this->customer->id,
                        'customer_number' => $this->customer->customer_number,
                        'name' => $this->customer->name,
                        'phone' => $this->customer->phone,
                    ];
                }
            ),

            'items' => SaleItemResource::collection($this->whenLoaded('items')),
            'payments' => SalePaymentResource::collection($this->whenLoaded('payments')),

            'summary' => [
                'subtotal' => (float) $this->subtotal,
                'tax_amount' => (float) $this->tax_amount,
                'discount_amount' => (float) $this->discount_amount,
                'total_amount' => (float) $this->total_amount,
                'amount_paid' => (float) $this->amount_paid,
                'amount_due' => (float) $this->amount_due,
                'change' => $this->getChangeAmount(),
            ],

            'shift' => $this->when(
                $this->shiftAssignment,
                function () {
                    return [
                        'id' => $this->shiftAssignment->id,
                        'shift_date' => $this->shiftAssignment->shift_date->toDateString(),
                        'user' => [
                            'id' => $this->shiftAssignment->user->id,
                            'name' => $this->shiftAssignment->user->name,
                        ],
                    ];
                }
            ),

            'payment_status' => $this->payment_status->value,
            'payment_status_label' => $this->payment_status->label(),
            'payment_method' => $this->payment_method->value,
            'payment_method_label' => $this->payment_method->label(),
            'payment_reference' => $this->payment_reference,

            'loyalty' => [
                'points_earned' => (float) $this->loyalty_points_earned,
                'points_redeemed' => (float) $this->loyalty_points_redeemed,
            ],

            'coupon' => $this->when(
                $this->coupon,
                function () {
                    return [
                        'id' => $this->coupon->id,
                        'code' => $this->coupon->code,
                        'description' => $this->coupon->description,
                    ];
                }
            ),

            'served_by' => [
                'id' => $this->servedBy->id,
                'name' => $this->servedBy->name,
            ],

            'notes' => $this->notes,
            'can_refund' => $this->canBeRefunded(),
            'is_walk_in' => $this->isWalkIn(),

            'profit_margin' => $this->when(
                $request->input('include_profit', false),
                function () {
                    return $this->getProfitMargin();
                }
            ),

            // 'receipt_url' => route('sales.receipt', ['sale' => $this->id]),

            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
