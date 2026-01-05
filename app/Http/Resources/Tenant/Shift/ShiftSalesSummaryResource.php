<?php

namespace App\Http\Resources\Tenant\Shift;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShiftSalesSummaryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'shift_assignment_id' => $this->shift_assignment_id,

            // Transaction Metrics
            'total_transactions' => $this->total_transactions,
            'total_sales_amount' => $this->total_sales_amount,
            'net_sales' => $this->net_sales,
            'average_transaction_value' => $this->average_transaction_value,

            // Payment Methods
            'total_cash_sales' => $this->total_cash_sales,
            'total_card_sales' => $this->total_card_sales,
            'total_mpesa_sales' => $this->total_mpesa_sales,
            'total_credit_sales' => $this->total_credit_sales,
            'payment_method_breakdown' => $this->getPaymentMethodBreakdown(),

            // Refunds
            'total_refunds' => $this->total_refunds,
            'total_refund_amount' => $this->total_refund_amount,
            'refund_rate' => $this->refund_rate,

            // Discounts
            'total_discounts_given' => $this->total_discounts_given,
            'discount_rate' => $this->discount_rate,

            // Customer Metrics
            'unique_customers' => $this->unique_customers,

            // Cash Analysis
            'expected_cash' => $this->expected_cash,
            'cash_variance_vs_expected' => $this->cash_variance_vs_expected,

            // Performance
            'sales_per_hour' => $this->getSalesPerHour(),

            // Metadata
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
