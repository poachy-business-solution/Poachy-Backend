<?php

namespace App\DataTransferObjects\Sync;

use App\Models\Tenant\ProductBundle;
use Illuminate\Support\Facades\Auth;

class BundleSyncDTO
{
    public function __construct(
        public readonly string $tenantId,
        public readonly int $bundleId,
        public readonly string $bundleUuid,
        public readonly string $productType,
        public readonly string $bundleName,
        public readonly string $bundleSku,
        public readonly ?string $description,
        public readonly ?string $onlineDescription,
        public readonly float $bundlePrice,
        public readonly float $onlinePrice,
        public readonly ?float $calculatedIndividualPrice,
        public readonly ?float $discountAmount,
        public readonly ?float $savingsPercentage,
        public readonly float $taxRate,
        public readonly UomDTO $baseUom,
        public readonly ?string $primaryImage,
        public readonly array $secondaryImages,
        public readonly array $items,
        public readonly bool $isActive,
        public readonly bool $isAvailableOnline,
        public readonly array $metadata,
    ) {}

    /**
     * Create DTO from Tenant ProductBundle model
     */
    public static function fromBundle(ProductBundle $bundle, bool $skipValidation = false): self
    {
        if (!tenant()) {
            throw new \RuntimeException('Cannot create BundleSyncDTO outside tenant context');
        }

        if (!$skipValidation) {
            if (!$bundle->is_available_online) {
                throw new \InvalidArgumentException('Bundle must be available online to sync');
            }

            if (!$bundle->online_price || $bundle->online_price <= 0) {
                throw new \InvalidArgumentException('Bundle must have online_price set');
            }
        }

        // Load required relationships
        $bundle->load(['items.product', 'items.variant', 'items.uom', 'baseUom', 'taxRate']);

        // Build items array from BundleItemDTOs
        $items = $bundle->items->map(function ($item) {
            return BundleItemDTO::fromBundleItem($item);
        })->all();

        // Primary image from accessor, secondary from remaining images
        $images = $bundle->images ?? [];
        $primaryImage = $bundle->primary_image;
        $secondaryImages = array_slice($images, 1);

        return new self(
            tenantId: tenant()->id,
            bundleId: $bundle->id,
            bundleUuid: $bundle->uuid,
            productType: 'bundle',
            bundleName: $bundle->bundle_name,
            bundleSku: $bundle->bundle_sku,
            description: $bundle->description,
            onlineDescription: $bundle->online_description,
            bundlePrice: (float) $bundle->bundle_price,
            onlinePrice: (float) ($bundle->online_price ?? 0),
            calculatedIndividualPrice: $bundle->calculated_individual_price ? (float) $bundle->calculated_individual_price : null,
            discountAmount: $bundle->discount_amount ? (float) $bundle->discount_amount : null,
            savingsPercentage: $bundle->savings_percentage,
            taxRate: (float) ($bundle->taxRate?->rate ?? 0),
            baseUom: $bundle->baseUom ? UomDTO::fromModel($bundle->baseUom) : new UomDTO(code: 'N/A', name: 'N/A'),
            primaryImage: $primaryImage,
            secondaryImages: $secondaryImages,
            items: $items,
            isActive: (bool) $bundle->is_active,
            isAvailableOnline: (bool) $bundle->is_available_online,
            metadata: [
                'user_id' => Auth::id(),
                'ip_address' => request()->ip(),
                'timestamp' => now()->toISOString(),
                'source' => 'bundle_observer',
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
            'bundle_id' => $this->bundleId,
            'bundle_uuid' => $this->bundleUuid,
            'product_type' => $this->productType,
            'bundle_name' => $this->bundleName,
            'bundle_sku' => $this->bundleSku,
            'description' => $this->description,
            'online_description' => $this->onlineDescription,
            'bundle_price' => $this->bundlePrice,
            'online_price' => $this->onlinePrice,
            'calculated_individual_price' => $this->calculatedIndividualPrice,
            'discount_amount' => $this->discountAmount,
            'savings_percentage' => $this->savingsPercentage,
            'tax_rate' => $this->taxRate,
            'base_uom' => $this->baseUom->toArray(),
            'primary_image' => $this->primaryImage,
            'secondary_images' => $this->secondaryImages,
            'items' => array_map(fn (BundleItemDTO $item) => $item->toArray(), $this->items),
            'is_active' => $this->isActive,
            'is_available_online' => $this->isAvailableOnline,
            'metadata' => $this->metadata,
        ];
    }

    /**
     * Create DTO from array (for queue deserialization)
     */
    public static function fromArray(array $data): self
    {
        $items = array_map(
            fn (array $item) => BundleItemDTO::fromArray($item),
            $data['items'] ?? []
        );

        return new self(
            tenantId: $data['tenant_id'],
            bundleId: $data['bundle_id'],
            bundleUuid: $data['bundle_uuid'],
            productType: $data['product_type'],
            bundleName: $data['bundle_name'],
            bundleSku: $data['bundle_sku'],
            description: $data['description'] ?? null,
            onlineDescription: $data['online_description'] ?? null,
            bundlePrice: (float) $data['bundle_price'],
            onlinePrice: (float) $data['online_price'],
            calculatedIndividualPrice: isset($data['calculated_individual_price']) ? (float) $data['calculated_individual_price'] : null,
            discountAmount: isset($data['discount_amount']) ? (float) $data['discount_amount'] : null,
            savingsPercentage: isset($data['savings_percentage']) ? (float) $data['savings_percentage'] : null,
            taxRate: (float) $data['tax_rate'],
            baseUom: UomDTO::fromArray($data['base_uom']),
            primaryImage: $data['primary_image'] ?? null,
            secondaryImages: $data['secondary_images'] ?? [],
            items: $items,
            isActive: $data['is_active'] ?? true,
            isAvailableOnline: $data['is_available_online'] ?? false,
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
                $this->bundleId .
                $action .
                $payloadHash
        );
    }
}
