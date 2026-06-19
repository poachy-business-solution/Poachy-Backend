<?php

namespace App\Http\Resources\Tenant\Budget;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class BudgetCollection extends ResourceCollection
{
    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection,
        ];
    }

    public function with(Request $request): array
    {
        $totalAllocated = $this->collection->sum('budget_amount');
        $totalSpent = $this->collection->sum('spent_amount');
        $totalRemaining = $this->collection->sum('remaining_amount');

        return [
            'meta' => [
                'total_count' => $this->collection->count(),
                'total_allocated' => $totalAllocated,
                'formatted_total_allocated' => 'KES ' . number_format($totalAllocated, 2),
                'total_spent' => $totalSpent,
                'formatted_total_spent' => 'KES ' . number_format($totalSpent, 2),
                'total_remaining' => $totalRemaining,
                'formatted_total_remaining' => 'KES ' . number_format($totalRemaining, 2),
                'on_track_count' => $this->collection->where('status', 'on_track')->count(),
                'warning_count' => $this->collection->where('status', 'warning')->count(),
                'over_budget_count' => $this->collection->where('status', 'over_budget')->count(),
            ],
        ];
    }
}
