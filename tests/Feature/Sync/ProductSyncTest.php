<?php

namespace Tests\Feature\Sync;

use App\DataTransferObjects\Sync\BrandDTO;
use App\DataTransferObjects\Sync\CategoryDTO;
use App\DataTransferObjects\Sync\InventoryDTO;
use App\DataTransferObjects\Sync\ProductSyncDTO;
use App\DataTransferObjects\Sync\UomDTO;
use App\Models\Tenant\Product;
use App\Services\Tenant\Sync\ProductSyncService;
use Tests\TestCase;

class ProductSyncTest extends TestCase
{
    /**
     * Get a valid product DTO data array for testing.
     *
     * @return array<string, mixed>
     */
    private function getValidProductDTOData(): array
    {
        return [
            'tenant_id' => 'test-tenant-123',
            'product_id' => 1,
            'product_uuid' => 'prod-uuid-123',
            'product_type' => 'product',
            'variant_id' => null,
            'bundle_id' => null,
            'name' => 'Test Product',
            'slug' => 'test-product',
            'sku' => 'PROD-001',
            'description' => 'Test description',
            'online_description' => 'Online test description',
            'online_price' => 120.00,
            'tax_rate' => 16.00,
            'base_uom' => ['code' => 'PCS', 'name' => 'Pieces'],
            'category' => ['id' => 1, 'name' => 'Electronics', 'slug' => 'electronics'],
            'brand' => ['id' => 1, 'name' => 'TestBrand', 'slug' => 'testbrand'],
            'primary_image' => 'https://example.com/image.jpg',
            'secondary_images' => ['https://example.com/image2.jpg'],
            'inventory' => ['available_quantity' => 50.0, 'stock_status' => 'in_stock'],
            'is_active' => true,
            'is_featured' => false,
            'metadata' => ['source' => 'test'],
        ];
    }

    public function test_product_sync_dto_from_array_creates_valid_dto(): void
    {
        $data = $this->getValidProductDTOData();

        $dto = ProductSyncDTO::fromArray($data);

        $this->assertInstanceOf(ProductSyncDTO::class, $dto);
        $this->assertSame('test-tenant-123', $dto->tenantId);
        $this->assertSame(1, $dto->productId);
        $this->assertSame('prod-uuid-123', $dto->productUuid);
        $this->assertSame('product', $dto->productType);
        $this->assertNull($dto->variantId);
        $this->assertNull($dto->bundleId);
        $this->assertSame('Test Product', $dto->name);
        $this->assertSame('test-product', $dto->slug);
        $this->assertSame('PROD-001', $dto->sku);
        $this->assertSame('Test description', $dto->description);
        $this->assertSame('Online test description', $dto->onlineDescription);
        $this->assertSame(120.00, $dto->onlinePrice);
        $this->assertSame(16.00, $dto->taxRate);
        $this->assertInstanceOf(UomDTO::class, $dto->baseUom);
        $this->assertSame('PCS', $dto->baseUom->code);
        $this->assertInstanceOf(CategoryDTO::class, $dto->category);
        $this->assertSame(1, $dto->category->id);
        $this->assertSame('Electronics', $dto->category->name);
        $this->assertInstanceOf(BrandDTO::class, $dto->brand);
        $this->assertSame('TestBrand', $dto->brand->name);
        $this->assertSame('https://example.com/image.jpg', $dto->primaryImage);
        $this->assertSame(['https://example.com/image2.jpg'], $dto->secondaryImages);
        $this->assertInstanceOf(InventoryDTO::class, $dto->inventory);
        $this->assertSame(50.0, $dto->inventory->availableQuantity);
        $this->assertTrue($dto->isActive);
        $this->assertFalse($dto->isFeatured);
        $this->assertSame(['source' => 'test'], $dto->metadata);
    }

    public function test_product_sync_dto_to_array_and_from_array_round_trip(): void
    {
        $data = $this->getValidProductDTOData();

        $dto = ProductSyncDTO::fromArray($data);
        $arrayOutput = $dto->toArray();
        $restoredDto = ProductSyncDTO::fromArray($arrayOutput);

        $this->assertSame($dto->tenantId, $restoredDto->tenantId);
        $this->assertSame($dto->productId, $restoredDto->productId);
        $this->assertSame($dto->productUuid, $restoredDto->productUuid);
        $this->assertSame($dto->productType, $restoredDto->productType);
        $this->assertSame($dto->name, $restoredDto->name);
        $this->assertSame($dto->slug, $restoredDto->slug);
        $this->assertSame($dto->sku, $restoredDto->sku);
        $this->assertSame($dto->onlinePrice, $restoredDto->onlinePrice);
        $this->assertSame($dto->taxRate, $restoredDto->taxRate);
        $this->assertSame($dto->baseUom->code, $restoredDto->baseUom->code);
        $this->assertSame($dto->category->id, $restoredDto->category->id);
        $this->assertSame($dto->brand->id, $restoredDto->brand->id);
        $this->assertSame($dto->isActive, $restoredDto->isActive);
        $this->assertSame($dto->isFeatured, $restoredDto->isFeatured);

        $this->assertSame($dto->toArray(), $restoredDto->toArray());
    }

    public function test_product_sync_dto_generate_idempotency_key_is_consistent(): void
    {
        $data = $this->getValidProductDTOData();

        $dto1 = ProductSyncDTO::fromArray($data);
        $dto2 = ProductSyncDTO::fromArray($data);

        $key1 = $dto1->generateIdempotencyKey('create');
        $key2 = $dto2->generateIdempotencyKey('create');

        $this->assertSame($key1, $key2);
        $this->assertNotEmpty($key1);
    }

    public function test_product_sync_dto_generate_idempotency_key_differs_for_different_actions(): void
    {
        $data = $this->getValidProductDTOData();
        $dto = ProductSyncDTO::fromArray($data);

        $createKey = $dto->generateIdempotencyKey('create');
        $updateKey = $dto->generateIdempotencyKey('update');
        $deleteKey = $dto->generateIdempotencyKey('delete');

        $this->assertNotSame($createKey, $updateKey);
        $this->assertNotSame($createKey, $deleteKey);
        $this->assertNotSame($updateKey, $deleteKey);
    }

    public function test_product_sync_service_is_eligible_for_sync_returns_true_when_all_conditions_met(): void
    {
        $product = new Product;
        $product->forceFill([
            'id' => 1,
            'is_available_online' => true,
            'online_price' => 120.00,
            'category_id' => 1,
            'base_uom_id' => 1,
            'tax_rate_id' => 1,
            'is_active' => true,
        ]);

        $service = new ProductSyncService;
        $this->assertTrue($service->isEligibleForSync($product));
    }

    public function test_product_sync_service_is_eligible_for_sync_returns_false_when_not_online(): void
    {
        $product = new Product;
        $product->forceFill([
            'id' => 1,
            'is_available_online' => false,
            'online_price' => 120.00,
            'category_id' => 1,
            'base_uom_id' => 1,
            'tax_rate_id' => 1,
            'is_active' => true,
        ]);

        $service = new ProductSyncService;
        $this->assertFalse($service->isEligibleForSync($product));
    }

    public function test_product_sync_service_is_eligible_for_sync_returns_false_when_no_online_price(): void
    {
        $product = new Product;
        $product->forceFill([
            'id' => 1,
            'is_available_online' => true,
            'online_price' => null,
            'category_id' => 1,
            'base_uom_id' => 1,
            'tax_rate_id' => 1,
            'is_active' => true,
        ]);

        $service = new ProductSyncService;
        $this->assertFalse($service->isEligibleForSync($product));
    }

    public function test_product_sync_service_get_sync_validation_errors_returns_all_errors(): void
    {
        $product = new Product;
        $product->forceFill([
            'id' => 1,
            'is_available_online' => false,
            'online_price' => null,
            'category_id' => null,
            'base_uom_id' => null,
            'tax_rate_id' => null,
            'is_active' => false,
        ]);

        $service = new ProductSyncService;
        $errors = $service->getSyncValidationErrors($product);

        $this->assertContains('Product must be marked as available online', $errors);
        $this->assertContains('Product must have a valid online price', $errors);
        $this->assertContains('Product must be assigned to a category', $errors);
        $this->assertContains('Product must have a base unit of measure', $errors);
        $this->assertContains('Product must have a tax rate assigned', $errors);
        $this->assertContains('Product must be active', $errors);
        $this->assertCount(6, $errors);
    }

    public function test_product_sync_service_get_sync_validation_errors_returns_empty_when_eligible(): void
    {
        $product = new Product;
        $product->forceFill([
            'id' => 1,
            'is_available_online' => true,
            'online_price' => 120.00,
            'category_id' => 1,
            'base_uom_id' => 1,
            'tax_rate_id' => 1,
            'is_active' => true,
        ]);

        $service = new ProductSyncService;
        $errors = $service->getSyncValidationErrors($product);

        $this->assertEmpty($errors);
        $this->assertIsArray($errors);
    }
}
