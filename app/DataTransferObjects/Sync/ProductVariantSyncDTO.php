<?php

namespace App\DataTransferObjects\Sync;

use App\Models\Tenant\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class ProductSyncDTO
{
    public function __construct(
        public readonly string $tenantId,
        public readonly int $productId,
        public readonly string $productUuid,
        public readonly string $productType, // 'product', 'variant', 'bundle'
        public readonly ?int $variantId,
        public readonly ?int $bundleId,
        public readonly string $name,
        public readonly string $slug,
        public readonly string $sku,
        public readonly ?string $description,
        public readonly ?string $onlineDescription,
        public readonly float $onlinePrice,
        public readonly float $taxRate,
        public readonly UomDTO $baseUom,
        public readonly CategoryDTO $category,
        public readonly ?BrandDTO $brand,
        public readonly ?string $primaryImage,
        public readonly array $secondaryImages,
        public readonly InventoryDTO $inventory,
        public readonly bool $isActive,
        public readonly bool $isFeatured,
        public readonly array $metadata,
    ) {}

    /**
     * Create DTO from Tenant Product model
     */
    public static function fromProduct(Product $product): self
    {
        if (!tenant()) {
            throw new \RuntimeException('Cannot create ProductSyncDTO outside tenant context');
        }

        if (!$product->is_available_online) {
            throw new \InvalidArgumentException('Product must be available online to sync');
        }

        if (!$product->online_price) {
            throw new \InvalidArgumentException('Product must have online_price set');
        }

        // Load required relationships
        $product->load(['category', 'brand', 'baseUom', 'taxRate', 'inventories']);

        return new self(
            tenantId: tenant()->id,
            productId: $product->id,
            productUuid: $product->uuid,
            productType: 'product',
            variantId: null,
            bundleId: null,
            name: $product->name,
            slug: $product->slug,
            sku: $product->sku,
            description: $product->description,
            onlineDescription: $product->online_description,
            onlinePrice: (float) $product->online_price,
            taxRate: (float) $product->taxRate->rate,
            baseUom: UomDTO::fromModel($product->baseUom),
            category: CategoryDTO::fromModel($product->category),
            brand: $product->brand ? BrandDTO::fromModel($product->brand) : null,
            primaryImage: $product->primary_image,
            secondaryImages: $product->secondary_images ?? [],
            inventory: InventoryDTO::fromProduct($product),
            isActive: (bool) $product->is_active,
            isFeatured: (bool) $product->is_featured,
            metadata: [
                'user_id' => Auth::id(),
                'ip_address' => request()->ip(),
                'timestamp' => now()->toISOString(),
                'source' => 'product_observer',
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
            'bundle_id' => $this->bundleId,
            'name' => $this->name,
            'slug' => $this->slug,
            'sku' => $this->sku,
            'description' => $this->description,
            'online_description' => $this->onlineDescription,
            'online_price' => $this->onlinePrice,
            'tax_rate' => $this->taxRate,
            'base_uom' => $this->baseUom->toArray(),
            'category' => $this->category->toArray(),
            'brand' => $this->brand?->toArray(),
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
            variantId: $data['variant_id'] ?? null,
            bundleId: $data['bundle_id'] ?? null,
            name: $data['name'],
            slug: $data['slug'],
            sku: $data['sku'],
            description: $data['description'] ?? null,
            onlineDescription: $data['online_description'] ?? null,
            onlinePrice: (float) $data['online_price'],
            taxRate: (float) $data['tax_rate'],
            baseUom: UomDTO::fromArray($data['base_uom']),
            category: CategoryDTO::fromArray($data['category']),
            brand: isset($data['brand']) ? BrandDTO::fromArray($data['brand']) : null,
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
                $this->productId .
                $action .
                $payloadHash
        );
    }
}
