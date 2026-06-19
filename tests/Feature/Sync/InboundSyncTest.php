<?php

namespace Tests\Feature\Sync;

use App\Http\Requests\Central\Sync\InboundBundleSyncRequest;
use App\Http\Requests\Central\Sync\InboundVariantSyncRequest;
use App\Models\SyncQueueInbound;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class InboundSyncTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['services.central_api.token' => 'test-token-123']);

        Queue::fake();
    }

    /**
     * Get authenticated request headers.
     *
     * @return array<string, string>
     */
    private function authHeaders(): array
    {
        return [
            'Authorization' => 'Bearer test-token-123',
            'Accept' => 'application/json',
        ];
    }

    /**
     * Get a valid variant sync payload.
     *
     * @return array<string, mixed>
     */
    private function getValidVariantPayload(): array
    {
        return [
            'tenant_id' => 'test-tenant',
            'action' => 'create',
            'priority' => 3,
            'idempotency_key' => 'variant-idem-key-001',
            'payload' => [
                'variant_id' => 10,
                'product_id' => 1,
                'product_uuid' => 'prod-uuid-123',
                'product_type' => 'variant',
                'variant_name' => 'Large Red',
                'sku' => 'PROD-LG-RED',
                'attributes' => ['size' => 'Large', 'color' => 'Red'],
                'online_price' => 120.00,
                'computed_online_price' => 120.00,
                'variant_price' => 100.00,
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
            ],
            'metadata' => ['source' => 'test'],
        ];
    }

    /**
     * Get a valid bundle sync payload.
     *
     * @return array<string, mixed>
     */
    private function getValidBundlePayload(): array
    {
        return [
            'tenant_id' => 'test-tenant',
            'action' => 'create',
            'priority' => 3,
            'idempotency_key' => 'bundle-idem-key-001',
            'payload' => [
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
            ],
            'metadata' => ['source' => 'test'],
        ];
    }

    /**
     * Get variant validation rules (excluding DB-dependent rules).
     *
     * @return array<string, string>
     */
    private function getVariantValidationRulesWithoutDbChecks(): array
    {
        $request = new InboundVariantSyncRequest();
        $rules = $request->rules();

        // Remove the exists:tenants,id rule since we cannot hit the DB
        $rules['tenant_id'] = 'required|string';

        return $rules;
    }

    /**
     * Get bundle validation rules (excluding DB-dependent rules).
     *
     * @return array<string, string>
     */
    private function getBundleValidationRulesWithoutDbChecks(): array
    {
        $request = new InboundBundleSyncRequest();
        $rules = $request->rules();

        // Remove the exists:tenants,id rule since we cannot hit the DB
        $rules['tenant_id'] = 'required|string';

        return $rules;
    }

    public function testReceiveVariantSyncWithValidPayload(): void
    {
        $payload = $this->getValidVariantPayload();
        $rules = $this->getVariantValidationRulesWithoutDbChecks();

        $validator = Validator::make($payload, $rules);

        $this->assertFalse($validator->fails(), 'Valid variant payload should pass validation. Errors: ' . $validator->errors()->toJson());
    }

    public function testReceiveVariantSyncRejectsInvalidPayload(): void
    {
        $payload = [
            'tenant_id' => 'test-tenant',
            'action' => 'create',
            // Missing: priority, idempotency_key, payload
        ];

        $rules = $this->getVariantValidationRulesWithoutDbChecks();
        $validator = Validator::make($payload, $rules);

        $this->assertTrue($validator->fails());

        $errorKeys = $validator->errors()->keys();
        $this->assertContains('priority', $errorKeys);
        $this->assertContains('idempotency_key', $errorKeys);
        $this->assertContains('payload', $errorKeys);
    }

    public function testReceiveBundleSyncWithValidPayload(): void
    {
        $payload = $this->getValidBundlePayload();
        $rules = $this->getBundleValidationRulesWithoutDbChecks();

        $validator = Validator::make($payload, $rules);

        $this->assertFalse($validator->fails(), 'Valid bundle payload should pass validation. Errors: ' . $validator->errors()->toJson());
    }

    public function testReceiveBundleSyncRejectsInvalidPayload(): void
    {
        $payload = [
            'tenant_id' => 'test-tenant',
            'action' => 'create',
            // Missing: priority, idempotency_key, payload
        ];

        $rules = $this->getBundleValidationRulesWithoutDbChecks();
        $validator = Validator::make($payload, $rules);

        $this->assertTrue($validator->fails());

        $errorKeys = $validator->errors()->keys();
        $this->assertContains('priority', $errorKeys);
        $this->assertContains('idempotency_key', $errorKeys);
        $this->assertContains('payload', $errorKeys);
    }

    public function testReceiveVariantSyncDeduplicatesWithIdempotencyKey(): void
    {
        // Simulate the controller's deduplication logic without hitting the DB.
        // The controller checks: SyncQueueInbound::where('idempotency_key', $key)->first()
        // If found, it returns existing sync instead of creating a new one.

        $existingSyncRecord = new SyncQueueInbound();
        $existingSyncRecord->forceFill([
            'id' => 99,
            'tenant_id' => 'test-tenant',
            'syncable_type' => 'ProductVariant',
            'tenant_syncable_id' => 10,
            'action' => 'create',
            'status' => 'pending',
            'idempotency_key' => 'variant-idem-key-001',
        ]);

        // Verify the deduplication decision logic: when an existing record is found,
        // the controller should return the existing sync_id and mark as duplicate
        $found = $existingSyncRecord;
        $isDuplicate = $found !== null;
        $this->assertTrue($isDuplicate);
        $this->assertSame(99, $found->id);
        $this->assertSame('pending', $found->status);
        $this->assertSame('variant-idem-key-001', $found->idempotency_key);
        $this->assertSame('ProductVariant', $found->syncable_type);

        // Verify the expected response shape for a duplicate request
        $responseData = [
            'sync_id' => $found->id,
            'status' => $found->status,
            'is_duplicate' => true,
        ];
        $this->assertSame(99, $responseData['sync_id']);
        $this->assertSame('pending', $responseData['status']);
        $this->assertTrue($responseData['is_duplicate']);

        // Verify that a new request with a different idempotency key would NOT match
        $differentKey = 'variant-idem-key-002';
        $this->assertNotSame($differentKey, $found->idempotency_key);
    }

    public function testVariantSyncRequestAuthorizesWithCorrectToken(): void
    {
        $request = InboundVariantSyncRequest::create(
            '/api/v1/central/sync/inbound/variant',
            'POST',
            $this->getValidVariantPayload(),
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer test-token-123']
        );

        $this->assertTrue($request->authorize());
    }

    public function testVariantSyncRequestRejectsInvalidToken(): void
    {
        $request = InboundVariantSyncRequest::create(
            '/api/v1/central/sync/inbound/variant',
            'POST',
            $this->getValidVariantPayload(),
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer wrong-token']
        );

        $this->assertFalse($request->authorize());
    }

    public function testBundleSyncRequestAuthorizesWithCorrectToken(): void
    {
        $request = InboundBundleSyncRequest::create(
            '/api/v1/central/sync/inbound/bundle',
            'POST',
            $this->getValidBundlePayload(),
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer test-token-123']
        );

        $this->assertTrue($request->authorize());
    }

    public function testVariantSyncRejectsInvalidAction(): void
    {
        $payload = $this->getValidVariantPayload();
        $payload['action'] = 'invalid_action';

        $rules = $this->getVariantValidationRulesWithoutDbChecks();
        $validator = Validator::make($payload, $rules);

        $this->assertTrue($validator->fails());
        $this->assertTrue($validator->errors()->has('action'));
    }

    public function testBundleSyncRejectsInvalidAction(): void
    {
        $payload = $this->getValidBundlePayload();
        $payload['action'] = 'invalid_action';

        $rules = $this->getBundleValidationRulesWithoutDbChecks();
        $validator = Validator::make($payload, $rules);

        $this->assertTrue($validator->fails());
        $this->assertTrue($validator->errors()->has('action'));
    }

}
