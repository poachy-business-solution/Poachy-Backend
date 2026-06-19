<?php

namespace App\Http\Resources\Tenant\Sales;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SaleCalculationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'line_items' => array_map(function ($item) {
                return [
                    'product_id' => $item['product_id'],
                    'variant_id' => $item['variant_id'],
                    'bundle_id' => $item['bundle_id'],
                    'product_name' => $item['product_name'],
                    'sku' => $item['sku'],
                    'uom_code' => $item['uom_code'],
                    'quantity' => (float) $item['quantity'],
                    'quantity_in_base_uom' => (float) $item['quantity_in_base_uom'],
                    'unit_price' => (float) $item['unit_price'],
                    'line_total_before_discount' => (float) $item['line_total_before_discount'],
                    'promotion_discount' => (float) $item['promotion_discount'],
                    'promotion_details' => $item['promotion_details'],
                    'line_total_after_discount' => (float) $item['line_total_after_discount'],
                ];
            }, $this->resource['line_items']),

            'summary' => [
                'base_subtotal' => (float) $this->resource['base_subtotal'],
                'promotion_discount' => (float) $this->resource['promotion_discount'],
                'subtotal_after_promotions' => (float) $this->resource['subtotal_after_promotions'],
                'coupon_discount' => (float) $this->resource['coupon_discount'],
                'subtotal_after_coupon' => (float) $this->resource['subtotal_after_coupon'],
                'tax_amount' => (float) $this->resource['tax_amount'],
                'total_amount' => (float) $this->resource['total_amount'],
                'loyalty_redemption_value' => (float) $this->resource['loyalty_redemption_value'],
                'amount_payable' => (float) $this->resource['amount_payable'],
                'loyalty_points_earned' => (float) $this->resource['loyalty_points_earned'],
            ],

            'stacking_info' => [
                'stacking_allowed' => $this->resource['stacking_info']['stacking_allowed'],
                'coupon_priority' => $this->resource['stacking_info']['coupon_priority'],
                'promotions_applied' => $this->resource['promotions_applied'],
                'coupon_applied' => $this->resource['coupon_applied'],
            ],

            'coupon_data' => $this->when(
                $this->resource['coupon_data'],
                function () {
                    return [
                        'id' => $this->resource['coupon_data']->id,
                        'code' => $this->resource['coupon_data']->code,
                        'description' => $this->resource['coupon_data']->description,
                    ];
                }
            ),
        ];
    }
}
