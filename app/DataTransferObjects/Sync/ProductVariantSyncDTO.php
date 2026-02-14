<?php

namespace App\DataTransferObjects\Sync;

use App\Models\Tenant\ProductVariant;
use Illuminate\Support\Facades\Auth;

class ProductVariantSyncDTO
{
    public function __construct(
        public readonly string $tenantId,
        public readonly int $productId,
        public readonly string $productUuid,
        public readonly string $productType, // always 'variant'
        public readonly int $variantId,
        public readonly string $variantName,
        public readonly string $sku,
        public readonly array $attributes,
        public readonly ?string $description,
        public readonly ?string $onlineDescription,
        public readonly ?float $variantPrice,
        public readonly float $onlinePrice,
        public readonly float $computedOnlinePrice,
        public readonly float $taxRate,
        public readonly UomDTO $baseUom,
        public readonly UomDTO $variantUom,
        public readonly float $uomQuantity,
        public readonly float $quantityInBaseUom,
        public readonly CategoryDTO $category,
        public readonly ?BrandDTO $brand,
        public readonly string $parentProductName,
        public readonly string $parentProductSku,
        public readonly ?string $primaryImage,
        public readonly array $secondaryImages,
        public readonly InventoryDTO $inventory,
        public readonly bool $isActive,
        public readonly bool $isFeatured,
        public readonly array $metadata,
    ) {}

    /**
     * Create DTO from Tenant ProductVariant model
     */
    public static function fromVariant(ProductVariant $variant, bool $skipValidation = false): self
    {
        if (!tenant()) {
            throw new \RuntimeException('Cannot create ProductVariantSyncDTO outside tenant context');
        }

        if (!$skipValidation && ($variant->online_price === null || $variant->online_price <= 0)) {
            throw new \InvalidArgumentException('Variant must have a valid online_price set');
        }

        // Load required relationships
        $variant->loadMissing([
            'product.category',
            'product.brand',
            'product.taxRate',
            'product.baseUom',
            'uom',
            'inventories',
        ]);

        $product = $variant->product;

        if (!$product) {
            throw new \InvalidArgumentException('Variant must belong to a product');
        }

        if (!$skipValidation && !$product->category) {
            throw new \InvalidArgumentException('Parent product must have a category assigned');
        }

        // Calculate variant-specific inventory
        $totalAvailable = $variant->inventories()->sum('quantity_available');
        $stockStatus = match (true) {
            $totalAvailable <= 0 => 'out_of_stock',
            $totalAvailable <= $variant->reorder_level => 'low_stock',
            default => 'in_stock',
        };

        return new self(
            tenantId: tenant()->id,
            productId: $product->id,
            productUuid: $product->uuid,
            productType: 'variant',
            variantId: $variant->id,
            variantName: $variant->variant_name,
            sku: $variant->sku,
            attributes: $variant->attributes ?? [],
            description: $product->description,
            onlineDescription: $product->online_description,
            variantPrice: $variant->variant_price ? (float) $variant->variant_price : null,
            onlinePrice: (float) ($variant->online_price ?? 0),
            computedOnlinePrice: (float) ($variant->computed_online_price ?? 0),
            taxRate: (float) ($product->taxRate?->rate ?? 0),
            baseUom: $product->baseUom ? UomDTO::fromModel($product->baseUom) : new UomDTO(code: 'N/A', name: 'N/A'),
            variantUom: $variant->uom ? UomDTO::fromModel($variant->uom) : new UomDTO(code: 'N/A', name: 'N/A'),
            uomQuantity: (float) $variant->uom_quantity,
            quantityInBaseUom: (float) $variant->quantity_in_base_uom,
            category: $product->category ? CategoryDTO::fromModel($product->category) : new CategoryDTO(id: 0, name: 'N/A', slug: 'n-a'),
            brand: $product->brand ? BrandDTO::fromModel($product->brand) : null,
            parentProductName: $product->name,
            parentProductSku: $product->sku,
            primaryImage: $product->primary_image,
            secondaryImages: $product->secondary_images ?? [],
            inventory: new InventoryDTO(
                availableQuantity: (float) $totalAvailable,
                stockStatus: $stockStatus,
            ),
            isActive: (bool) $variant->is_active,
            isFeatured: (bool) $product->is_featured,
            metadata: [
                'user_id' => Auth::id(),
                'ip_address' => request()->ip(),
                'timestamp' => now()->toISOString(),
                'source' => 'variant_observer',
            ],
        );
    }

    /**
     * Convert DTO to array for queue payload
     */
    public function toArray(): array
    {
        return [
            'tenant_id' => $this->tenantId,
            'product_id' => $this->productId,
            'product_uuid' => $this->productUuid,
            'product_type' => $this->productType,
            'variant_id' => $this->variantId,
            'variant_name' => $this->variantName,
            'sku' => $this->sku,
            'attributes' => $this->attributes,
            'description' => $this->description,
            'online_description' => $this->onlineDescription,
            'variant_price' => $this->variantPrice,
            'online_price' => $this->onlinePrice,
            'computed_online_price' => $this->computedOnlinePrice,
            'tax_rate' => $this->taxRate,
            'base_uom' => $this->baseUom->toArray(),
            'variant_uom' => $this->variantUom->toArray(),
            'uom_quantity' => $this->uomQuantity,
            'quantity_in_base_uom' => $this->quantityInBaseUom,
            'category' => $this->category->toArray(),
            'brand' => $this->brand?->toArray(),
            'parent_product_name' => $this->parentProductName,
            'parent_product_sku' => $this->parentProductSku,
            'primary_image' => $this->primaryImage,
            'secondary_images' => $this->secondaryImages,
            'inventory' => $this->inventory->toArray(),
            'is_active' => $this->isActive,
            'is_featured' => $this->isFeatured,
            'metadata' => $this->metadata,
        ];
    }

    /**
     * Create DTO from array (for queue deserialization)
     */
    public static function fromArray(array $data): self
    {
        return new self(
            tenantId: $data['tenant_id'],
            productId: $data['product_id'],
            productUuid: $data['product_uuid'],
            productType: $data['product_type'],
            variantId: $data['variant_id'],
            variantName: $data['variant_name'],
            sku: $data['sku'],
            attributes: $data['attributes'] ?? [],
            description: $data['description'] ?? null,
            onlineDescription: $data['online_description'] ?? null,
            variantPrice: isset($data['variant_price']) ? (float) $data['variant_price'] : null,
            onlinePrice: (float) $data['online_price'],
            computedOnlinePrice: (float) ($data['computed_online_price'] ?? $data['online_price']),
            taxRate: (float) $data['tax_rate'],
            baseUom: UomDTO::fromArray($data['base_uom']),
            variantUom: UomDTO::fromArray($data['variant_uom']),
            uomQuantity: (float) ($data['uom_quantity'] ?? 1),
            quantityInBaseUom: (float) ($data['quantity_in_base_uom'] ?? 1),
            category: CategoryDTO::fromArray($data['category']),
            brand: isset($data['brand']) ? BrandDTO::fromArray($data['brand']) : null,
            parentProductName: $data['parent_product_name'] ?? '',
            parentProductSku: $data['parent_product_sku'] ?? '',
            primaryImage: $data['primary_image'] ?? null,
            secondaryImages: $data['secondary_images'] ?? [],
            inventory: InventoryDTO::fromArray($data['inventory']),
            isActive: $data['is_active'] ?? true,
            isFeatured: $data['is_featured'] ?? false,
            metadata: $data['metadata'] ?? [],
        );
    }

    /**
     * Generate idempotency key for deduplication
     */
    public function generateIdempotencyKey(string $action = 'create'): string
    {
        $payload = json_encode($this->toArray());
        $payloadHash = hash('sha256', $payload);

        return md5(
            $this->tenantId .
                $this->productType .
                $this->variantId .
                $action .
                $payloadHash
        );
    }
}
