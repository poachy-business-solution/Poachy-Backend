<?php

namespace App\Http\Resources\Tenant\Sales;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *     schema="SaleRefundItemResource",
 *     type="object",
 *     title="Sale Refund Item Resource",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="sale_item_id", type="integer", example=5),
 *     @OA\Property(property="product_id", type="integer", example=3),
 *     @OA\Property(property="product_name", type="string", nullable=true, example="Coca-Cola 500ml"),
 *     @OA\Property(property="original_quantity", type="number", format="float", example=2.0),
 *     @OA\Property(property="original_price", type="number", format="float", example=150.00),
 *     @OA\Property(property="quantity_refunded", type="number", format="float", example=1.0),
 *     @OA\Property(property="quantity_refunded_in_base_uom", type="number", format="float", example=1.0),
 *     @OA\Property(property="remaining_quantity", type="number", format="float", example=1.0),
 *     @OA\Property(property="refund_amount", type="number", format="float", example=150.00),
 *     @OA\Property(property="unit_refund_price", type="number", format="float", example=150.00),
 *     @OA\Property(property="is_full_refund", type="boolean", example=false)
 * )
 */
class SaleRefundItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sale_item_id' => $this->sale_item_id,
            'product_id' => $this->product_id,
            'product_name' => $this->product_name,
            'original_quantity' => (float) $this->original_quantity,
            'original_price' => (float) $this->original_price,
            'quantity_refunded' => (float) $this->quantity_refunded,
            'quantity_refunded_in_base_uom' => (float) $this->quantity_refunded_in_base_uom,
            'remaining_quantity' => (float) $this->remaining_quantity,
            'refund_amount' => (float) $this->refund_amount,
            'unit_refund_price' => (float) $this->unit_refund_price,
            'is_full_refund' => $this->is_full_refund,
        ];
    }
}
