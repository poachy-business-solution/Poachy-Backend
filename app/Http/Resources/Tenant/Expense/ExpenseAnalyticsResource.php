<?php

namespace App\Http\Resources\Tenant\Expense;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExpenseAnalyticsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'by_category' => $this->resource['by_category']->map(function ($item) {
                return [
                    'category_id' => $item->category_id,
                    'category_name' => $item->category->name ?? 'Unknown',
                    'category_code' => $item->category->code ?? null,
                    'expense_count' => (int) $item->expense_count,
                    'total_amount' => (float) $item->total_amount,
                    'formatted_amount' => 'KES ' . number_format($item->total_amount, 2),
                ];
            }),

            'by_payment_method' => $this->resource['by_payment_method']->map(function ($item) {
                return [
                    'payment_method' => $item->payment_method,
                    'payment_method_label' => $item->payment_method->label(),
                    'expense_count' => (int) $item->expense_count,
                    'total_amount' => (float) $item->total_amount,
                    'formatted_amount' => 'KES ' . number_format($item->total_amount, 2),
                ];
            }),

            'summary' => [
                'total_amount' => (float) $this->resource['total_amount'],
                'formatted_total_amount' => 'KES ' . number_format($this->resource['total_amount'], 2),
                'total_count' => (int) $this->resource['total_count'],
                'average_expense' => $this->resource['total_count'] > 0
                    ? (float) ($this->resource['total_amount'] / $this->resource['total_count'])
                    : 0,
                'formatted_average_expense' => $this->resource['total_count'] > 0
                    ? 'KES ' . number_format($this->resource['total_amount'] / $this->resource['total_count'], 2)
                    : 'KES 0.00',
            ],
        ];
    }
}
