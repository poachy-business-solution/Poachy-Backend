<?php

namespace App\DataTransferObjects\Sync;

use App\Models\Tenant\ProductBundleItem;

class BundleItemDTO
{
    public function __construct(
        public readonly int $productId,
        public readonly string $productName,
        public readonly string $productSku,
        public readonly ?int $variantId,
        public readonly ?string $variantName,
        public readonly ?string $variantSku,
        public readonly float $quantity,
        public readonly float $quantityInBaseUom,
        public readonly string $uomCode,
        public readonly string $uomName,
        public readonly float $itemPrice,
        public readonly float $totalPrice,
    ) {}

    /**
     * Create DTO from ProductBundleItem model
     */
    public static function fromBundleItem(ProductBundleItem $item): self
    {
        $item->load(['product', 'variant', 'uom']);

        return new self(
            productId: $item->product_id,
            productName: $item->product->name,
            productSku: $item->product->sku,
            variantId: $item->product_variant_id,
            variantName: $item->variant?->display_name,
            variantSku: $item->variant?->sku,
            quantity: (float) $item->quantity,
            quantityInBaseUom: (float) $item->quantity_in_base_uom,
            uomCode: $item->uom->code,
            uomName: $item->uom->name,
            itemPrice: (float) $item->item_price,
            totalPrice: (float) $item->total_price,
        );
    }

    /**
     * Convert DTO to array for queue payload
     */
    public function toArray(): array
    {
        return [
            'product_id' => $this->productId,
            'product_name' => $this->productName,
            'product_sku' => $this->productSku,
            'variant_id' => $this->variantId,
            'variant_name' => $this->variantName,
            'variant_sku' => $this->variantSku,
            'quantity' => $this->quantity,
            'quantity_in_base_uom' => $this->quantityInBaseUom,
            'uom_code' => $this->uomCode,
            'uom_name' => $this->uomName,
            'item_price' => $this->itemPrice,
            'total_price' => $this->totalPrice,
        ];
    }

    /**
     * Create DTO from array (for queue deserialization)
     */
    public static function fromArray(array $data): self
    {
        return new self(
            productId: $data['product_id'],
            productName: $data['product_name'],
            productSku: $data['product_sku'],
            variantId: $data['variant_id'] ?? null,
            variantName: $data['variant_name'] ?? null,
            variantSku: $data['variant_sku'] ?? null,
            quantity: (float) $data['quantity'],
            quantityInBaseUom: (float) $data['quantity_in_base_uom'],
            uomCode: $data['uom_code'],
            uomName: $data['uom_name'],
            itemPrice: (float) $data['item_price'],
            totalPrice: (float) $data['total_price'],
        );
    }
}
