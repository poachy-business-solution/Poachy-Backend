<?php

namespace App\Http\Resources\Tenant\Budget;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BudgetPerformanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $percentageSpent = $this->resource['total_allocated'] > 0
            ? round(($this->resource['total_spent'] / $this->resource['total_allocated']) * 100, 2)
            : 0;

        return [
            'summary' => [
                'total_budgets' => $this->resource['total_budgets'],
                'total_allocated' => (float) $this->resource['total_allocated'],
                'formatted_total_allocated' => 'KES ' . number_format($this->resource['total_allocated'], 2),
                'total_spent' => (float) $this->resource['total_spent'],
                'formatted_total_spent' => 'KES ' . number_format($this->resource['total_spent'], 2),
                'total_remaining' => (float) $this->resource['total_remaining'],
                'formatted_total_remaining' => 'KES ' . number_format($this->resource['total_remaining'], 2),
                'percentage_spent' => $percentageSpent,
            ],
            'status_breakdown' => [
                'on_track' => $this->resource['on_track_count'],
                'warning' => $this->resource['warning_count'],
                'over_budget' => $this->resource['over_budget_count'],
            ],
        ];
    }
}
