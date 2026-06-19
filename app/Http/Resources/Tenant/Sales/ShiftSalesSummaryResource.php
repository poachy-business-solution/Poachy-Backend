<?php

namespace App\Http\Resources\Tenant\Sales;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShiftSalesSummaryResource extends JsonResource
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
            'shift_assignment_id' => $this->shift_assignment_id,

            'transactions' => [
                'total_count' => $this->total_transactions,
                'unique_customers' => $this->unique_customers,
                'average_value' => (float) $this->average_transaction_value,
            ],

            'sales' => [
                'total_amount' => (float) $this->total_sales_amount,
                'net_sales' => (float) $this->net_sales,
                'total_discounts' => (float) $this->total_discounts_given,
            ],

            'payment_methods' => [
                'cash' => (float) $this->total_cash_sales,
                'card' => (float) $this->total_card_sales,
                'mpesa' => (float) $this->total_mpesa_sales,
                'credit' => (float) $this->total_credit_sales,
            ],

            'payment_breakdown_percentage' => $this->getPaymentMethodBreakdown(),

            'refunds' => [
                'count' => $this->total_refunds,
                'amount' => (float) $this->total_refund_amount,
                'rate_percentage' => $this->refund_rate,
            ],

            'cash_tracking' => [
                'expected_cash' => (float) $this->expected_cash,
                'variance' => $this->cash_variance_vs_expected,
            ],

            'performance' => [
                'sales_per_hour' => $this->getSalesPerHour(),
                'discount_rate_percentage' => $this->discount_rate,
            ],

            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
