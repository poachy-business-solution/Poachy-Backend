<?php

namespace App\Http\Resources\Tenant\Store;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class StoreProductCollection extends ResourceCollection
{
    /**
     * The resource that this resource collects.
     *
     * @var string
     */
    public $collects = StoreProductResource::class;

    /**
     * Transform the resource collection into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection,
            'summary' => [
                'total_products' => $this->collection->count(),
                'available_products' => $this->collection->where('is_available', true)->count(),
                'unavailable_products' => $this->collection->where('is_available', false)->count(),
                'low_stock_products' => $this->collection->filter(fn($item) => $item->is_low_stock)->count(),
                'out_of_stock_products' => $this->collection->filter(fn($item) => $item->is_out_of_stock)->count(),
                'price_overrides' => $this->collection->where('is_price_overridden', true)->count(),
            ],
        ];
    }
}
