<?php

namespace App\Http\Resources\Tenant\Sales;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *     schema="SaleRefundResource",
 *     type="object",
 *     title="Sale Refund Resource",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="refund_number", type="string", example="REF-01-202503-000001"),
 *     @OA\Property(property="refund_date", type="string", format="date", example="2025-03-09"),
 *     @OA\Property(property="original_sale", type="object", nullable=true,
 *         @OA\Property(property="id", type="integer", example=10),
 *         @OA\Property(property="sale_number", type="string", example="SALE-01-202503-000010"),
 *         @OA\Property(property="total_amount", type="number", format="float", example=500.00),
 *         @OA\Property(property="sale_date", type="string", format="date-time")
 *     ),
 *     @OA\Property(property="store", type="object",
 *         @OA\Property(property="id", type="integer", example=1),
 *         @OA\Property(property="name", type="string", example="Main Store"),
 *         @OA\Property(property="code", type="string", nullable=true, example="STR-01")
 *     ),
 *     @OA\Property(property="customer", type="object", nullable=true,
 *         @OA\Property(property="id", type="integer", example=7),
 *         @OA\Property(property="name", type="string", example="Jane Doe"),
 *         @OA\Property(property="phone", type="string", example="+254712345678")
 *     ),
 *     @OA\Property(property="items", type="array",
 *         @OA\Items(ref="#/components/schemas/SaleRefundItemResource")
 *     ),
 *     @OA\Property(property="summary", type="object",
 *         @OA\Property(property="refund_amount", type="number", format="float", example=250.00),
 *         @OA\Property(property="items_count", type="integer", example=1),
 *         @OA\Property(property="quantity_refunded", type="number", format="float", example=1.0)
 *     ),
 *     @OA\Property(property="status", type="string", example="completed"),
 *     @OA\Property(property="status_label", type="string", example="Completed"),
 *     @OA\Property(property="refund_method", type="string", example="cash"),
 *     @OA\Property(property="refund_method_label", type="string", example="Cash"),
 *     @OA\Property(property="reason", type="string", example="wrong_item"),
 *     @OA\Property(property="reason_label", type="string", example="Wrong Item Sold"),
 *     @OA\Property(property="processed_by", type="object", nullable=true,
 *         @OA\Property(property="id", type="integer", example=2),
 *         @OA\Property(property="name", type="string", example="Manager Name")
 *     ),
 *     @OA\Property(property="approved_by", type="object", nullable=true,
 *         @OA\Property(property="id", type="integer", example=2),
 *         @OA\Property(property="name", type="string", example="Manager Name")
 *     ),
 *     @OA\Property(property="exchange_sale", type="object", nullable=true,
 *         @OA\Property(property="id", type="integer", example=11),
 *         @OA\Property(property="sale_number", type="string", example="SALE-01-202503-000011"),
 *         @OA\Property(property="total_amount", type="number", format="float", example=300.00)
 *     ),
 *     @OA\Property(property="notes", type="string", nullable=true, example="Customer returned wrong item"),
 *     @OA\Property(property="approved_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="processed_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="created_at", type="string", format="date-time")
 * )
 */
class SaleRefundResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'refund_number' => $this->refund_number,
            'refund_date' => $this->refund_date?->toDateString(),

            'original_sale' => $this->when($this->originalSale, fn () => [
                'id' => $this->originalSale->id,
                'sale_number' => $this->originalSale->sale_number,
                'total_amount' => (float) $this->originalSale->total_amount,
                'sale_date' => $this->originalSale->sale_date?->toIso8601String(),
            ]),

            'store' => [
                'id' => $this->store->id,
                'name' => $this->store->name,
                'code' => $this->store->code,
            ],

            'customer' => $this->when($this->customer, fn () => [
                'id' => $this->customer->id,
                'name' => $this->customer->name,
                'phone' => $this->customer->phone,
            ]),

            'items' => SaleRefundItemResource::collection($this->whenLoaded('items')),

            'summary' => [
                'refund_amount' => (float) $this->refund_amount,
                'items_count' => $this->total_items_count,
                'quantity_refunded' => (float) $this->total_quantity_refunded,
            ],

            'status' => $this->status->value,
            'status_label' => $this->status->label(),

            'refund_method' => $this->refund_method->value,
            'refund_method_label' => $this->refund_method_label,

            'reason' => $this->reason->value,
            'reason_label' => $this->reason_label,

            'processed_by' => $this->when($this->processedBy, fn () => [
                'id' => $this->processedBy->id,
                'name' => $this->processedBy->name,
            ]),

            'approved_by' => $this->when($this->approvedBy, fn () => [
                'id' => $this->approvedBy->id,
                'name' => $this->approvedBy->name,
            ]),

            'exchange_sale' => $this->when($this->exchangeSale, fn () => [
                'id' => $this->exchangeSale->id,
                'sale_number' => $this->exchangeSale->sale_number,
                'total_amount' => (float) $this->exchangeSale->total_amount,
            ]),

            'notes' => $this->notes,
            'approved_at' => $this->approved_at?->toIso8601String(),
            'processed_at' => $this->processed_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
