<?php

namespace Tests\Feature\Sync;

use App\DataTransferObjects\Sync\BundleItemDTO;
use App\DataTransferObjects\Sync\BundleSyncDTO;
use App\DataTransferObjects\Sync\UomDTO;
use App\Models\Tenant\ProductBundle;
use App\Services\Tenant\Sync\BundleSyncService;
use Mockery;
use Tests\TestCase;

class BundleSyncTest extends TestCase
{
    /**
     * Get a valid bundle DTO data array for testing.
     *
     * @return array<string, mixed>
     */
    private function getValidBundleDTOData(): array
    {
        return [
            'tenant_id' => 'test-tenant-123',
            'bundle_id' => 5,
            'bundle_uuid' => 'bundle-uuid-456',
            'product_type' => 'bundle',
            'bundle_name' => 'Starter Kit',
            'bundle_sku' => 'BUNDLE-SK-001',
            'description' => 'A starter kit bundle',
            'online_description' => 'Online starter kit description',
            'bundle_price' => 500.00,
            'online_price' => 450.00,
            'calculated_individual_price' => 600.00,
            'discount_amount' => 100.00,
            'savings_percentage' => 16.67,
            'tax_rate' => 16.00,
            'base_uom' => ['code' => 'SET', 'name' => 'Set'],
            'primary_image' => 'https://example.com/bundle.jpg',
            'secondary_images' => [],
            'items' => [
                [
                    'product_id' => 1,
                    'product_name' => 'Widget A',
                    'product_sku' => 'WA-001',
                    'variant_id' => null,
                    'variant_name' => null,
                    'variant_sku' => null,
                    'quantity' => 2.0,
                    'quantity_in_base_uom' => 2.0,
                    'uom_code' => 'PCS',
                    'uom_name' => 'Pieces',
                    'item_price' => 150.00,
                    'total_price' => 300.00,
                ],
                [
                    'product_id' => 2,
                    'product_name' => 'Widget B',
                    'product_sku' => 'WB-001',
                    'variant_id' => 3,
                    'variant_name' => 'Blue Large',
                    'variant_sku' => 'WB-BL-001',
                    'quantity' => 1.0,
                    'quantity_in_base_uom' => 1.0,
                    'uom_code' => 'PCS',
                    'uom_name' => 'Pieces',
                    'item_price' => 300.00,
                    'total_price' => 300.00,
                ],
            ],
            'is_active' => true,
            'is_available_online' => true,
            'metadata' => ['source' => 'test'],
        ];
    }

    public function testBundleSyncDTOFromArrayCreatesValidDTO(): void
    {
        $data = $this->getValidBundleDTOData();

        $dto = BundleSyncDTO::fromArray($data);

        $this->assertInstanceOf(BundleSyncDTO::class, $dto);
        $this->assertSame('test-tenant-123', $dto->tenantId);
        $this->assertSame(5, $dto->bundleId);
        $this->assertSame('bundle-uuid-456', $dto->bundleUuid);
        $this->assertSame('bundle', $dto->productType);
        $this->assertSame('Starter Kit', $dto->bundleName);
        $this->assertSame('BUNDLE-SK-001', $dto->bundleSku);
        $this->assertSame('A starter kit bundle', $dto->description);
        $this->assertSame('Online starter kit description', $dto->onlineDescription);
        $this->assertSame(500.00, $dto->bundlePrice);
        $this->assertSame(450.00, $dto->onlinePrice);
        $this->assertSame(600.00, $dto->calculatedIndividualPrice);
        $this->assertSame(100.00, $dto->discountAmount);
        $this->assertSame(16.67, $dto->savingsPercentage);
        $this->assertSame(16.00, $dto->taxRate);
        $this->assertInstanceOf(UomDTO::class, $dto->baseUom);
        $this->assertSame('SET', $dto->baseUom->code);
        $this->assertSame('Set', $dto->baseUom->name);
        $this->assertSame('https://example.com/bundle.jpg', $dto->primaryImage);
        $this->assertSame([], $dto->secondaryImages);
        $this->assertCount(2, $dto->items);
        $this->assertInstanceOf(BundleItemDTO::class, $dto->items[0]);
        $this->assertInstanceOf(BundleItemDTO::class, $dto->items[1]);
        $this->assertTrue($dto->isActive);
        $this->assertTrue($dto->isAvailableOnline);
        $this->assertSame(['source' => 'test'], $dto->metadata);
    }

    public function testBundleSyncDTOToArrayAndFromArrayRoundTrip(): void
    {
        $data = $this->getValidBundleDTOData();

        $dto = BundleSyncDTO::fromArray($data);
        $arrayOutput = $dto->toArray();
        $restoredDto = BundleSyncDTO::fromArray($arrayOutput);

        $this->assertSame($dto->tenantId, $restoredDto->tenantId);
        $this->assertSame($dto->bundleId, $restoredDto->bundleId);
        $this->assertSame($dto->bundleUuid, $restoredDto->bundleUuid);
        $this->assertSame($dto->productType, $restoredDto->productType);
        $this->assertSame($dto->bundleName, $restoredDto->bundleName);
        $this->assertSame($dto->bundleSku, $restoredDto->bundleSku);
        $this->assertSame($dto->description, $restoredDto->description);
        $this->assertSame($dto->onlineDescription, $restoredDto->onlineDescription);
        $this->assertSame($dto->bundlePrice, $restoredDto->bundlePrice);
        $this->assertSame($dto->onlinePrice, $restoredDto->onlinePrice);
        $this->assertSame($dto->calculatedIndividualPrice, $restoredDto->calculatedIndividualPrice);
        $this->assertSame($dto->discountAmount, $restoredDto->discountAmount);
        $this->assertSame($dto->savingsPercentage, $restoredDto->savingsPercentage);
        $this->assertSame($dto->taxRate, $restoredDto->taxRate);
        $this->assertSame($dto->baseUom->code, $restoredDto->baseUom->code);
        $this->assertSame($dto->baseUom->name, $restoredDto->baseUom->name);
        $this->assertSame($dto->primaryImage, $restoredDto->primaryImage);
        $this->assertSame($dto->secondaryImages, $restoredDto->secondaryImages);
        $this->assertSame($dto->isActive, $restoredDto->isActive);
        $this->assertSame($dto->isAvailableOnline, $restoredDto->isAvailableOnline);
        $this->assertSame($dto->metadata, $restoredDto->metadata);

        // Verify the arrays are identical
        $this->assertSame($dto->toArray(), $restoredDto->toArray());
    }

    public function testBundleSyncDTOGenerateIdempotencyKeyIsConsistent(): void
    {
        $data = $this->getValidBundleDTOData();

        $dto1 = BundleSyncDTO::fromArray($data);
        $dto2 = BundleSyncDTO::fromArray($data);

        $key1 = $dto1->generateIdempotencyKey('create');
        $key2 = $dto2->generateIdempotencyKey('create');

        $this->assertSame($key1, $key2);
        $this->assertNotEmpty($key1);
        $this->assertIsString($key1);
    }

    public function testBundleSyncDTOItemsArePreservedInRoundTrip(): void
    {
        $data = $this->getValidBundleDTOData();

        $dto = BundleSyncDTO::fromArray($data);
        $arrayOutput = $dto->toArray();
        $restoredDto = BundleSyncDTO::fromArray($arrayOutput);

        $this->assertCount(2, $restoredDto->items);

        // Verify first item (no variant)
        $firstItem = $restoredDto->items[0];
        $this->assertSame(1, $firstItem->productId);
        $this->assertSame('Widget A', $firstItem->productName);
        $this->assertSame('WA-001', $firstItem->productSku);
        $this->assertNull($firstItem->variantId);
        $this->assertNull($firstItem->variantName);
        $this->assertNull($firstItem->variantSku);
        $this->assertSame(2.0, $firstItem->quantity);
        $this->assertSame(2.0, $firstItem->quantityInBaseUom);
        $this->assertSame('PCS', $firstItem->uomCode);
        $this->assertSame('Pieces', $firstItem->uomName);
        $this->assertSame(150.00, $firstItem->itemPrice);
        $this->assertSame(300.00, $firstItem->totalPrice);

        // Verify second item (with variant)
        $secondItem = $restoredDto->items[1];
        $this->assertSame(2, $secondItem->productId);
        $this->assertSame('Widget B', $secondItem->productName);
        $this->assertSame('WB-001', $secondItem->productSku);
        $this->assertSame(3, $secondItem->variantId);
        $this->assertSame('Blue Large', $secondItem->variantName);
        $this->assertSame('WB-BL-001', $secondItem->variantSku);
        $this->assertSame(1.0, $secondItem->quantity);
        $this->assertSame(1.0, $secondItem->quantityInBaseUom);
        $this->assertSame('PCS', $secondItem->uomCode);
        $this->assertSame('Pieces', $secondItem->uomName);
        $this->assertSame(300.00, $secondItem->itemPrice);
        $this->assertSame(300.00, $secondItem->totalPrice);
    }

    public function testBundleSyncServiceIsEligibleForSyncReturnsTrueWhenAllConditionsMet(): void
    {
        $bundle = Mockery::mock(ProductBundle::class)->makePartial();
        $bundle->shouldReceive('getAttribute')->with('is_available_online')->andReturn(true);
        $bundle->shouldReceive('getAttribute')->with('online_price')->andReturn(450.00);
        $bundle->shouldReceive('getAttribute')->with('is_active')->andReturn(true);
        $bundle->shouldReceive('getAttribute')->with('base_uom_id')->andReturn(1);
        $bundle->shouldReceive('getAttribute')->with('tax_rate_id')->andReturn(1);
        $bundle->shouldReceive('hasMinimumItems')->andReturn(true);
        $bundle->shouldReceive('allItemsActive')->andReturn(true);

        $service = new BundleSyncService();
        $this->assertTrue($service->isEligibleForSync($bundle));
    }

    public function testBundleSyncServiceIsEligibleForSyncReturnsFalseWhenNotOnline(): void
    {
        $bundle = Mockery::mock(ProductBundle::class)->makePartial();
        $bundle->shouldReceive('getAttribute')->with('is_available_online')->andReturn(false);
        $bundle->shouldReceive('getAttribute')->with('online_price')->andReturn(450.00);
        $bundle->shouldReceive('getAttribute')->with('is_active')->andReturn(true);
        $bundle->shouldReceive('getAttribute')->with('base_uom_id')->andReturn(1);
        $bundle->shouldReceive('getAttribute')->with('tax_rate_id')->andReturn(1);
        $bundle->shouldReceive('hasMinimumItems')->andReturn(true);
        $bundle->shouldReceive('allItemsActive')->andReturn(true);

        $service = new BundleSyncService();
        $this->assertFalse($service->isEligibleForSync($bundle));
    }

    public function testBundleSyncServiceIsEligibleForSyncReturnsFalseWhenNoOnlinePrice(): void
    {
        $bundle = Mockery::mock(ProductBundle::class)->makePartial();
        $bundle->shouldReceive('getAttribute')->with('is_available_online')->andReturn(true);
        $bundle->shouldReceive('getAttribute')->with('online_price')->andReturn(null);
        $bundle->shouldReceive('getAttribute')->with('is_active')->andReturn(true);
        $bundle->shouldReceive('getAttribute')->with('base_uom_id')->andReturn(1);
        $bundle->shouldReceive('getAttribute')->with('tax_rate_id')->andReturn(1);
        $bundle->shouldReceive('hasMinimumItems')->andReturn(true);
        $bundle->shouldReceive('allItemsActive')->andReturn(true);

        $service = new BundleSyncService();
        $this->assertFalse($service->isEligibleForSync($bundle));
    }

    public function testBundleSyncServiceGetSyncValidationErrorsReturnsAllErrors(): void
    {
        $bundle = Mockery::mock(ProductBundle::class)->makePartial();
        $bundle->shouldReceive('getAttribute')->with('is_available_online')->andReturn(false);
        $bundle->shouldReceive('getAttribute')->with('online_price')->andReturn(null);
        $bundle->shouldReceive('getAttribute')->with('is_active')->andReturn(false);
        $bundle->shouldReceive('getAttribute')->with('base_uom_id')->andReturn(null);
        $bundle->shouldReceive('getAttribute')->with('tax_rate_id')->andReturn(null);
        $bundle->shouldReceive('hasMinimumItems')->andReturn(false);
        $bundle->shouldReceive('allItemsActive')->andReturn(false);

        $service = new BundleSyncService();
        $errors = $service->getSyncValidationErrors($bundle);

        $this->assertNotEmpty($errors);
        $this->assertContains('Bundle must be marked as available online', $errors);
        $this->assertContains('Bundle must have a valid online price', $errors);
        $this->assertContains('Bundle must be active', $errors);
        $this->assertContains('Bundle must have a base unit of measure', $errors);
        $this->assertContains('Bundle must have a tax rate assigned', $errors);
        $this->assertContains('Bundle must have at least 2 items', $errors);
        $this->assertContains('All component products must be active', $errors);
        $this->assertCount(7, $errors);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
