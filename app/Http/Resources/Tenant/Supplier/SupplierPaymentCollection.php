<?php

namespace App\Http\Resources\Tenant\Supplier;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class SupplierPaymentCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection,
            'meta' => [
                'total_amount' => round($this->collection->sum('amount'), 2),
                'payment_count' => $this->collection->count(),
                'currency' => 'KES',
            ],
        ];
    }
}
