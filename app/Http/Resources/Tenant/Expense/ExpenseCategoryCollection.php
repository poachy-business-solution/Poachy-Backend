<?php

namespace App\Http\Resources\Tenant\Expense;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class ExpenseCategoryCollection extends ResourceCollection
{
    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection,
        ];
    }

    public function with(Request $request): array
    {
        return [
            'meta' => [
                'total_count' => $this->collection->count(),
                'active_count' => $this->collection->where('is_active', true)->count(),
                'inactive_count' => $this->collection->where('is_active', false)->count(),
            ],
        ];
    }
}
