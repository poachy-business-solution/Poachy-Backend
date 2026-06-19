<?php

namespace App\Http\Resources\Tenant\Expense;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class ExpenseCollection extends ResourceCollection
{
    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection,
        ];
    }

    public function with(Request $request): array
    {
        $totalAmount = $this->collection->sum('amount');
        $approvedCount = $this->collection->where('approval_status', 'approved')->count();
        $pendingCount = $this->collection->where('approval_status', 'pending')->count();

        return [
            'meta' => [
                'total_count' => $this->collection->count(),
                'total_amount' => $totalAmount,
                'formatted_total_amount' => 'KES ' . number_format($totalAmount, 2),
                'approved_count' => $approvedCount,
                'pending_count' => $pendingCount,
                'rejected_count' => $this->collection->where('approval_status', 'rejected')->count(),
            ],
        ];
    }
}
