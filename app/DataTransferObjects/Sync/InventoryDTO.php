<?php

namespace App\DataTransferObjects\Sync;

use App\Enums\Tenant\ProductStatus;
use App\Models\Tenant\Product;

class InventoryDTO
{
    public function __construct(
        public readonly float $availableQuantity,
        public readonly string $stockStatus, // 'in_stock', 'low_stock', 'out_of_stock'
    ) {}

    public static function fromProduct(Product $product): self
    {
        // Calculate total available quantity across all stores
        $totalAvailable = $product->inventories()
            ->whereNull('product_variant_id')
            ->sum('quantity_available');

        // Determine stock status
        $stockStatus = match (true) {
            $totalAvailable <= 0 => 'out_of_stock',
            $totalAvailable <= $product->reorder_level => 'low_stock',
            default => 'in_stock',
        };

        return new self(
            availableQuantity: (float) $totalAvailable,
            stockStatus: $stockStatus,
        );
    }

    public static function fromArray(array $data): self
    {
        return new self(
            availableQuantity: (float) $data['available_quantity'],
            stockStatus: $data['stock_status'],
        );
    }

    public function toArray(): array
    {
        return [
            'available_quantity' => $this->availableQuantity,
            'stock_status' => $this->stockStatus,
        ];
    }
}
