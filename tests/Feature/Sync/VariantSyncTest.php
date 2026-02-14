<?php

namespace Tests\Feature\Sync;

use App\DataTransferObjects\Sync\BrandDTO;
use App\DataTransferObjects\Sync\CategoryDTO;
use App\DataTransferObjects\Sync\InventoryDTO;
use App\DataTransferObjects\Sync\ProductVariantSyncDTO;
use App\DataTransferObjects\Sync\UomDTO;
use App\Models\Tenant\Product;
use App\Models\Tenant\ProductVariant;
use App\Services\Tenant\Sync\VariantSyncService;
use Tests\TestCase;

class VariantSyncTest extends TestCase
{
    /**
     * Get a valid variant DTO data array for testing.
     *
     * @return array<string, mixed>
     */
    private function getValidVariantDTOData(): array
    {
        return [
            'tenant_id' => 'test-tenant-123',
            'product_id' => 1,
            'product_uuid' => 'prod-uuid-123',
            'product_type' => 'variant',
            'variant_id' => 10,
            'variant_name' => 'Large Red',
            'sku' => 'PROD-LG-RED',
            'attributes' => ['size' => 'Large', 'color' => 'Red'],
            'description' => 'Test description',
            'online_description' => 'Online test description',
            'variant_price' => 100.00,
            'online_price' => 120.00,
            'computed_online_price' => 120.00,
            'tax_rate' => 16.00,
            'base_uom' => ['code' => 'PCS', 'name' => 'Pieces'],
            'variant_uom' => ['code' => 'PK', 'name' => 'Pack'],
            'uom_quantity' => 6.0,
            'quantity_in_base_uom' => 6.0,
            'category' => ['id' => 1, 'name' => 'Electronics', 'slug' => 'electronics'],
            'brand' => ['id' => 1, 'name' => 'TestBrand', 'slug' => 'testbrand'],
            'parent_product_name' => 'T-Shirt',
            'parent_product_sku' => 'TSHIRT-001',
            'primary_image' => 'https://example.com/image.jpg',
            'secondary_images' => ['https://example.com/image2.jpg'],
            'inventory' => ['available_quantity' => 50.0, 'stock_status' => 'in_stock'],
            'is_active' => true,
            'is_featured' => false,
            'metadata' => ['source' => 'test'],
        ];
    }

    public function testVariantSyncDTOFromArrayCreatesValidDTO(): void
    {
        $data = $this->getValidVariantDTOData();

        $dto = ProductVariantSyncDTO::fromArray($data);

        $this->assertInstanceOf(ProductVariantSyncDTO::class, $dto);
        $this->assertSame('test-tenant-123', $dto->tenantId);
        $this->assertSame(1, $dto->productId);
        $this->assertSame('prod-uuid-123', $dto->productUuid);
        $this->assertSame('variant', $dto->productType);
        $this->assertSame(10, $dto->variantId);
        $this->assertSame('Large Red', $dto->variantName);
        $this->assertSame('PROD-LG-RED', $dto->sku);
        $this->assertSame(['size' => 'Large', 'color' => 'Red'], $dto->attributes);
        $this->assertSame('Test description', $dto->description);
        $this->assertSame('Online test description', $dto->onlineDescription);
        $this->assertSame(100.00, $dto->variantPrice);
        $this->assertSame(120.00, $dto->onlinePrice);
        $this->assertSame(120.00, $dto->computedOnlinePrice);
        $this->assertSame(16.00, $dto->taxRate);
        $this->assertInstanceOf(UomDTO::class, $dto->baseUom);
        $this->assertSame('PCS', $dto->baseUom->code);
        $this->assertSame('Pieces', $dto->baseUom->name);
        $this->assertInstanceOf(UomDTO::class, $dto->variantUom);
        $this->assertSame('PK', $dto->variantUom->code);
        $this->assertSame('Pack', $dto->variantUom->name);
        $this->assertSame(6.0, $dto->uomQuantity);
        $this->assertSame(6.0, $dto->quantityInBaseUom);
        $this->assertInstanceOf(CategoryDTO::class, $dto->category);
        $this->assertSame(1, $dto->category->id);
        $this->assertSame('Electronics', $dto->category->name);
        $this->assertSame('electronics', $dto->category->slug);
        $this->assertInstanceOf(BrandDTO::class, $dto->brand);
        $this->assertSame(1, $dto->brand->id);
        $this->assertSame('TestBrand', $dto->brand->name);
        $this->assertSame('testbrand', $dto->brand->slug);
        $this->assertSame('T-Shirt', $dto->parentProductName);
        $this->assertSame('TSHIRT-001', $dto->parentProductSku);
        $this->assertSame('https://example.com/image.jpg', $dto->primaryImage);
        $this->assertSame(['https://example.com/image2.jpg'], $dto->secondaryImages);
        $this->assertInstanceOf(InventoryDTO::class, $dto->inventory);
        $this->assertSame(50.0, $dto->inventory->availableQuantity);
        $this->assertSame('in_stock', $dto->inventory->stockStatus);
        $this->assertTrue($dto->isActive);
        $this->assertFalse($dto->isFeatured);
        $this->assertSame(['source' => 'test'], $dto->metadata);
    }

    public function testVariantSyncDTOToArrayAndFromArrayRoundTrip(): void
    {
        $data = $this->getValidVariantDTOData();

        $dto = ProductVariantSyncDTO::fromArray($data);
        $arrayOutput = $dto->toArray();
        $restoredDto = ProductVariantSyncDTO::fromArray($arrayOutput);

        $this->assertSame($dto->tenantId, $restoredDto->tenantId);
        $this->assertSame($dto->productId, $restoredDto->productId);
        $this->assertSame($dto->productUuid, $restoredDto->productUuid);
        $this->assertSame($dto->productType, $restoredDto->productType);
        $this->assertSame($dto->variantId, $restoredDto->variantId);
        $this->assertSame($dto->variantName, $restoredDto->variantName);
        $this->assertSame($dto->sku, $restoredDto->sku);
        $this->assertSame($dto->attributes, $restoredDto->attributes);
        $this->assertSame($dto->description, $restoredDto->description);
        $this->assertSame($dto->onlineDescription, $restoredDto->onlineDescription);
        $this->assertSame($dto->variantPrice, $restoredDto->variantPrice);
        $this->assertSame($dto->onlinePrice, $restoredDto->onlinePrice);
        $this->assertSame($dto->computedOnlinePrice, $restoredDto->computedOnlinePrice);
        $this->assertSame($dto->taxRate, $restoredDto->taxRate);
        $this->assertSame($dto->baseUom->code, $restoredDto->baseUom->code);
        $this->assertSame($dto->baseUom->name, $restoredDto->baseUom->name);
        $this->assertSame($dto->variantUom->code, $restoredDto->variantUom->code);
        $this->assertSame($dto->variantUom->name, $restoredDto->variantUom->name);
        $this->assertSame($dto->uomQuantity, $restoredDto->uomQuantity);
        $this->assertSame($dto->quantityInBaseUom, $restoredDto->quantityInBaseUom);
        $this->assertSame($dto->category->id, $restoredDto->category->id);
        $this->assertSame($dto->category->name, $restoredDto->category->name);
        $this->assertSame($dto->brand->id, $restoredDto->brand->id);
        $this->assertSame($dto->brand->name, $restoredDto->brand->name);
        $this->assertSame($dto->parentProductName, $restoredDto->parentProductName);
        $this->assertSame($dto->parentProductSku, $restoredDto->parentProductSku);
        $this->assertSame($dto->primaryImage, $restoredDto->primaryImage);
        $this->assertSame($dto->secondaryImages, $restoredDto->secondaryImages);
        $this->assertSame($dto->inventory->availableQuantity, $restoredDto->inventory->availableQuantity);
        $this->assertSame($dto->inventory->stockStatus, $restoredDto->inventory->stockStatus);
        $this->assertSame($dto->isActive, $restoredDto->isActive);
        $this->assertSame($dto->isFeatured, $restoredDto->isFeatured);
        $this->assertSame($dto->metadata, $restoredDto->metadata);

        // Verify the arrays are identical
        $this->assertSame($dto->toArray(), $restoredDto->toArray());
    }

    public function testVariantSyncDTOGenerateIdempotencyKeyIsConsistent(): void
    {
        $data = $this->getValidVariantDTOData();

        $dto1 = ProductVariantSyncDTO::fromArray($data);
        $dto2 = ProductVariantSyncDTO::fromArray($data);

        $key1 = $dto1->generateIdempotencyKey('create');
        $key2 = $dto2->generateIdempotencyKey('create');

        $this->assertSame($key1, $key2);
        $this->assertNotEmpty($key1);
        $this->assertIsString($key1);
    }

    public function testVariantSyncDTOGenerateIdempotencyKeyDiffersForDifferentActions(): void
    {
        $data = $this->getValidVariantDTOData();

        $dto = ProductVariantSyncDTO::fromArray($data);

        $createKey = $dto->generateIdempotencyKey('create');
        $updateKey = $dto->generateIdempotencyKey('update');
        $deleteKey = $dto->generateIdempotencyKey('delete');

        $this->assertNotSame($createKey, $updateKey);
        $this->assertNotSame($createKey, $deleteKey);
        $this->assertNotSame($updateKey, $deleteKey);
    }

    public function testVariantSyncServiceIsEligibleForSyncReturnsTrueWhenAllConditionsMet(): void
    {
        $product = new Product();
        $product->forceFill([
            'id' => 1,
            'is_available_online' => true,
            'category_id' => 1,
            'tax_rate_id' => 1,
            'base_uom_id' => 1,
        ]);

        $variant = new ProductVariant();
        $variant->forceFill([
            'id' => 10,
            'product_id' => 1,
            'is_active' => true,
            'online_price' => 120.00,
        ]);
        $variant->setRelation('product', $product);

        $service = new VariantSyncService();
        $this->assertTrue($service->isEligibleForSync($variant));
    }

    public function testVariantSyncServiceIsEligibleForSyncReturnsFalseWhenVariantInactive(): void
    {
        $product = new Product();
        $product->forceFill([
            'id' => 1,
            'is_available_online' => true,
            'category_id' => 1,
            'tax_rate_id' => 1,
            'base_uom_id' => 1,
        ]);

        $variant = new ProductVariant();
        $variant->forceFill([
            'id' => 10,
            'product_id' => 1,
            'is_active' => false,
            'online_price' => 120.00,
        ]);
        $variant->setRelation('product', $product);

        $service = new VariantSyncService();
        $this->assertFalse($service->isEligibleForSync($variant));
    }

    public function testVariantSyncServiceIsEligibleForSyncReturnsFalseWhenNoOnlinePrice(): void
    {
        $product = new Product();
        $product->forceFill([
            'id' => 1,
            'is_available_online' => true,
            'category_id' => 1,
            'tax_rate_id' => 1,
            'base_uom_id' => 1,
        ]);

        $variant = new ProductVariant();
        $variant->forceFill([
            'id' => 10,
            'product_id' => 1,
            'is_active' => true,
            'online_price' => null,
        ]);
        $variant->setRelation('product', $product);

        $service = new VariantSyncService();
        $this->assertFalse($service->isEligibleForSync($variant));
    }

    public function testVariantSyncServiceIsEligibleForSyncReturnsFalseWhenParentNotOnline(): void
    {
        $product = new Product();
        $product->forceFill([
            'id' => 1,
            'is_available_online' => false,
            'category_id' => 1,
            'tax_rate_id' => 1,
            'base_uom_id' => 1,
        ]);

        $variant = new ProductVariant();
        $variant->forceFill([
            'id' => 10,
            'product_id' => 1,
            'is_active' => true,
            'online_price' => 120.00,
        ]);
        $variant->setRelation('product', $product);

        $service = new VariantSyncService();
        $this->assertFalse($service->isEligibleForSync($variant));
    }

    public function testVariantSyncServiceGetSyncValidationErrorsReturnsAllErrors(): void
    {
        $product = new Product();
        $product->forceFill([
            'id' => 1,
            'is_available_online' => false,
            'category_id' => null,
            'tax_rate_id' => null,
            'base_uom_id' => null,
        ]);

        $variant = new ProductVariant();
        $variant->forceFill([
            'id' => 10,
            'product_id' => 1,
            'is_active' => false,
            'online_price' => null,
        ]);
        $variant->setRelation('product', $product);

        $service = new VariantSyncService();
        $errors = $service->getSyncValidationErrors($variant);

        $this->assertNotEmpty($errors);
        $this->assertContains('Variant must be active', $errors);
        $this->assertContains('Variant must have a valid online price', $errors);
        $this->assertContains('Parent product must be available online', $errors);
        $this->assertContains('Parent product must be assigned to a category', $errors);
        $this->assertContains('Parent product must have a tax rate assigned', $errors);
        $this->assertContains('Parent product must have a base unit of measure', $errors);
        $this->assertCount(6, $errors);
    }

    public function testVariantSyncServiceGetSyncValidationErrorsReturnsEmptyWhenEligible(): void
    {
        $product = new Product();
        $product->forceFill([
            'id' => 1,
            'is_available_online' => true,
            'category_id' => 1,
            'tax_rate_id' => 1,
            'base_uom_id' => 1,
        ]);

        $variant = new ProductVariant();
        $variant->forceFill([
            'id' => 10,
            'product_id' => 1,
            'is_active' => true,
            'online_price' => 120.00,
        ]);
        $variant->setRelation('product', $product);

        $service = new VariantSyncService();
        $errors = $service->getSyncValidationErrors($variant);

        $this->assertEmpty($errors);
        $this->assertIsArray($errors);
    }
}
