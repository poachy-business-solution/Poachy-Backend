<?php

namespace App\DataTransferObjects\Sync;

use App\Models\Tenant\Inventory;

class InventoryCountSyncDTO
{
    public function __construct(
        public readonly string $tenantId,
        public readonly int $productId,
        public readonly ?int $variantId,
        public readonly string $entityType, // 'product' or 'variant'
        public readonly float $availableQuantity,
        public readonly float $quantityOnHand,
        public readonly string $stockStatus, // 'in_stock', 'low_stock', 'out_of_stock'
    ) {}

    /**
     * Create DTO from Inventory model — aggregates across all stores for this product/variant pair.
     */
    public static function fromInventory(Inventory $inventory): self
    {
        if (!tenant()) {
            throw new \RuntimeException('Cannot create InventoryCountSyncDTO outside tenant context');
        }

        $inventory->loadMissing('product');

        if (!$inventory->product) {
            throw new \InvalidArgumentException(
                "Inventory record #{$inventory->id} has no associated product and cannot be synced."
            );
        }

        $totalAvailable = Inventory::where('product_id', $inventory->product_id)
            ->where('product_variant_id', $inventory->product_variant_id)
            ->sum('quantity_available');

        $totalOnHand = Inventory::where('product_id', $inventory->product_id)
            ->where('product_variant_id', $inventory->product_variant_id)
            ->sum('quantity_on_hand');

        $reorderLevel = $inventory->product->reorder_level ?? 0;

        $stockStatus = match (true) {
            $totalAvailable <= 0 => 'out_of_stock',
            $totalAvailable <= $reorderLevel => 'low_stock',
            default => 'in_stock',
        };

        return new self(
            tenantId: tenant()->id,
            productId: $inventory->product_id,
            variantId: $inventory->product_variant_id,
            entityType: is_null($inventory->product_variant_id) ? 'product' : 'variant',
            availableQuantity: (float) $totalAvailable,
            quantityOnHand: (float) $totalOnHand,
            stockStatus: $stockStatus,
        );
    }

    /**
     * Create DTO from array (for deserialization in central inbound jobs).
     */
    public static function fromArray(array $data): self
    {
        return new self(
            tenantId: $data['tenant_id'],
            productId: $data['product_id'],
            variantId: $data['variant_id'] ?? null,
            entityType: $data['entity_type'],
            availableQuantity: (float) $data['available_quantity'],
            quantityOnHand: (float) $data['quantity_on_hand'],
            stockStatus: $data['stock_status'],
        );
    }

    public function toArray(): array
    {
        return [
            'tenant_id' => $this->tenantId,
            'product_id' => $this->productId,
            'variant_id' => $this->variantId,
            'entity_type' => $this->entityType,
            'available_quantity' => $this->availableQuantity,
            'quantity_on_hand' => $this->quantityOnHand,
            'stock_status' => $this->stockStatus,
        ];
    }

    /**
     * Generate idempotency key for deduplication.
     */
    public function generateIdempotencyKey(string $action = 'update'): string
    {
        $payloadHash = hash('sha256', json_encode($this->toArray()));

        return md5(
            $this->tenantId .
                'InventoryCount' .
                $this->productId .
                ($this->variantId ?? '') .
                $action .
                $payloadHash
        );
    }
}
