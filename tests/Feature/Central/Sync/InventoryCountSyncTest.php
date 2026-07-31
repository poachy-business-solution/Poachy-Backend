<?php

namespace Tests\Feature\Central\Sync;

use App\DataTransferObjects\Sync\InventoryCountSyncDTO;
use App\Jobs\Central\ProcessInboundInventoryCountSync;
use App\Models\MarketplaceProduct;
use App\Models\SyncQueueInbound;
use App\Services\Central\Sync\MarketplaceInventoryCountSyncService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class InventoryCountSyncTest extends TestCase
{
    private string $tenantId;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('tenancy.database.central_connection', 'central');
        Config::set('database.connections.central.host', env('CENTRAL_DB_HOST', '127.0.0.1'));
        Config::set('database.connections.central.port', env('CENTRAL_DB_PORT', '3306'));
        Config::set('database.connections.central.database', env('CENTRAL_DB_DATABASE', 'poachy'));
        Config::set('database.connections.central.username', env('CENTRAL_DB_USERNAME', 'root'));
        Config::set('database.connections.central.password', env('CENTRAL_DB_PASSWORD', ''));
        Config::set('services.tenant_api.token', 'tenant-test-token');
        DB::purge('central');
        DB::setDefaultConnection('central');
        DB::connection('central')->statement('SET foreign_key_checks = 0');

        $this->tenantId = 'inv-count-sync-test-'.uniqid();
        DB::connection('central')->table('tenants')->insertOrIgnore([
            'id' => $this->tenantId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::connection('central')->table('domains')->insert([
            'domain' => 'inv-count-sync-'.uniqid().'.poachy.test',
            'tenant_id' => $this->tenantId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        DB::connection('central')->statement('SET foreign_key_checks = 0');
        SyncQueueInbound::on('central')->where('tenant_id', $this->tenantId)->delete();
        MarketplaceProduct::on('central')->where('tenant_id', $this->tenantId)->forceDelete();
        DB::connection('central')->table('domains')->where('tenant_id', $this->tenantId)->delete();
        DB::connection('central')->table('tenants')->where('id', $this->tenantId)->delete();
        DB::connection('central')->statement('SET foreign_key_checks = 1');

        parent::tearDown();
    }

    private function makeApplyService(): MarketplaceInventoryCountSyncService
    {
        return new MarketplaceInventoryCountSyncService;
    }

    private function createMarketplaceProduct(array $overrides = []): MarketplaceProduct
    {
        return MarketplaceProduct::on('central')->create(array_merge([
            'tenant_id' => $this->tenantId,
            'tenant_product_id' => 1,
            'name' => 'Synced Product',
            'slug' => 'synced-product-'.uniqid(),
            'sku' => 'SKU-'.uniqid(),
            'online_price' => 500,
            'base_uom_code' => 'pcs',
            'base_uom_name' => 'Piece',
            'tax_rate' => 0,
            'available_quantity' => 0,
            'stock_status' => 'out_of_stock',
            'is_active' => true,
        ], $overrides));
    }

    private function makeDto(array $overrides = []): InventoryCountSyncDTO
    {
        return InventoryCountSyncDTO::fromArray(array_merge([
            'tenant_id' => $this->tenantId,
            'product_id' => 1,
            'variant_id' => null,
            'entity_type' => 'product',
            'available_quantity' => 42.0,
            'quantity_on_hand' => 42.0,
            'stock_status' => 'in_stock',
        ], $overrides));
    }

    // =========================================================================
    // MarketplaceInventoryCountSyncService::updateInventoryCount()
    // =========================================================================

    public function test_apply_service_updates_matching_product(): void
    {
        $product = $this->createMarketplaceProduct();

        $resultId = $this->makeApplyService()->updateInventoryCount($this->makeDto());

        $this->assertSame($product->id, $resultId);
        $fresh = $product->fresh();
        $this->assertEquals(42.0, $fresh->available_quantity);
        $this->assertSame('in_stock', $fresh->stock_status);
        $this->assertNotNull($fresh->last_synced_at);
    }

    public function test_apply_service_updates_matching_variant_not_base_product(): void
    {
        $baseProduct = $this->createMarketplaceProduct(['tenant_variant_id' => null]);
        $variantProduct = $this->createMarketplaceProduct(['tenant_variant_id' => 5, 'sku' => 'SKU-VARIANT-'.uniqid()]);

        $this->makeApplyService()->updateInventoryCount($this->makeDto(['entity_type' => 'variant', 'variant_id' => 5, 'available_quantity' => 7]));

        $this->assertEquals(7, $variantProduct->fresh()->available_quantity);
        $this->assertEquals(0, $baseProduct->fresh()->available_quantity, 'the base (non-variant) product row must be untouched');
    }

    public function test_apply_service_returns_null_when_no_matching_marketplace_product(): void
    {
        $result = $this->makeApplyService()->updateInventoryCount($this->makeDto(['product_id' => 999999]));

        $this->assertNull($result);
    }

    public function test_apply_service_scoped_to_tenant(): void
    {
        $otherTenantId = 'inv-count-sync-other-'.uniqid();
        DB::connection('central')->table('tenants')->insertOrIgnore(['id' => $otherTenantId, 'created_at' => now(), 'updated_at' => now()]);
        $otherProduct = MarketplaceProduct::on('central')->create([
            'tenant_id' => $otherTenantId, 'tenant_product_id' => 1, 'name' => 'Other Tenant Product',
            'slug' => 'other-tenant-product-'.uniqid(), 'sku' => 'SKU-'.uniqid(), 'online_price' => 500,
            'base_uom_code' => 'pcs', 'base_uom_name' => 'Piece', 'tax_rate' => 0,
            'available_quantity' => 0, 'stock_status' => 'out_of_stock', 'is_active' => true,
        ]);

        $result = $this->makeApplyService()->updateInventoryCount($this->makeDto());

        $this->assertNull($result);
        $this->assertEquals(0, $otherProduct->fresh()->available_quantity);

        $otherProduct->forceDelete();
        DB::connection('central')->table('tenants')->where('id', $otherTenantId)->delete();
    }

    // =========================================================================
    // ProcessInboundInventoryCountSync — inbound processing + ACK
    // =========================================================================

    private function createInboundSync(array $overrides = []): SyncQueueInbound
    {
        return SyncQueueInbound::on('central')->create(array_merge([
            'tenant_id' => $this->tenantId,
            'syncable_type' => 'InventoryCount',
            'tenant_syncable_id' => 1,
            'action' => 'update',
            'payload' => [
                'tenant_id' => $this->tenantId, 'product_id' => 1, 'variant_id' => null,
                'entity_type' => 'product', 'available_quantity' => 15.0, 'quantity_on_hand' => 15.0,
                'stock_status' => 'in_stock',
            ],
            'metadata' => ['sync_queue_id_from_tenant' => 501],
            'priority' => 3,
            'received_at' => now(),
            'scheduled_at' => now(),
            'expires_at' => now()->addHours(24),
            'status' => 'pending',
            'retry_count' => 0,
            'max_retries' => 3,
            'backoff_strategy' => 'exponential',
            'idempotency_key' => 'idem-'.uniqid(),
            'payload_hash' => 'hash-'.uniqid(),
        ], $overrides));
    }

    public function test_inbound_job_applies_update_and_acks_completed(): void
    {
        Http::fake(['*/inventory-count-ack' => Http::response(['success' => true], 200)]);
        $product = $this->createMarketplaceProduct();
        $sync = $this->createInboundSync();

        (new ProcessInboundInventoryCountSync($sync->id))->handle(app(MarketplaceInventoryCountSyncService::class));

        $fresh = $sync->fresh();
        $this->assertSame('completed', $fresh->status);
        $this->assertSame($product->id, (int) $fresh->central_record_id);
        $this->assertEquals(15.0, $product->fresh()->available_quantity);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'inventory-count-ack')
                && $request['outbound_sync_queue_id'] === 501
                && $request['status'] === 'completed';
        });
    }

    public function test_inbound_job_completes_and_acks_even_when_product_not_yet_synced(): void
    {
        Http::fake(['*/inventory-count-ack' => Http::response(['success' => true], 200)]);
        $sync = $this->createInboundSync();

        (new ProcessInboundInventoryCountSync($sync->id))->handle(app(MarketplaceInventoryCountSyncService::class));

        $fresh = $sync->fresh();
        $this->assertSame('completed', $fresh->status);
        $this->assertNull($fresh->central_record_id);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'inventory-count-ack')
                && $request['status'] === 'completed'
                && $request['central_product_id'] === null;
        });
    }

    public function test_inbound_job_marks_failed_and_does_not_ack_on_transient_failure(): void
    {
        Http::fake();
        $sync = $this->createInboundSync(['payload' => ['tenant_id' => $this->tenantId]]); // missing required DTO fields

        try {
            (new ProcessInboundInventoryCountSync($sync->id))->handle(app(MarketplaceInventoryCountSyncService::class));
            $this->fail('Expected exception was not thrown');
        } catch (\Throwable $e) {
            // expected — malformed payload
        }

        $fresh = $sync->fresh();
        $this->assertSame('pending', $fresh->status, 'still retryable, so incrementRetry() flips it back to pending');
        $this->assertSame(1, $fresh->retry_count);
        Http::assertNothingSent();
    }

    public function test_inbound_job_acks_failed_after_max_retries(): void
    {
        Http::fake(['*/inventory-count-ack' => Http::response(['success' => true], 200)]);
        $sync = $this->createInboundSync([
            'payload' => ['tenant_id' => $this->tenantId],
            'retry_count' => 3,
            'max_retries' => 3,
        ]);

        try {
            (new ProcessInboundInventoryCountSync($sync->id))->handle(app(MarketplaceInventoryCountSyncService::class));
            $this->fail('Expected exception was not thrown');
        } catch (\Throwable $e) {
            // expected
        }

        $this->assertSame('failed', $sync->fresh()->status);
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'inventory-count-ack') && $request['status'] === 'failed';
        });
    }

    public function test_inbound_job_skips_when_already_completed(): void
    {
        Http::fake();
        $sync = $this->createInboundSync(['status' => 'completed']);

        (new ProcessInboundInventoryCountSync($sync->id))->handle(app(MarketplaceInventoryCountSyncService::class));

        Http::assertNothingSent();
    }

    public function test_inbound_job_marks_stale_without_processing_when_expired(): void
    {
        Http::fake();
        $sync = $this->createInboundSync(['expires_at' => now()->subHour()]);

        (new ProcessInboundInventoryCountSync($sync->id))->handle(app(MarketplaceInventoryCountSyncService::class));

        $this->assertSame('stale', $sync->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_inbound_job_skips_ack_when_no_tenant_outbound_id_in_metadata(): void
    {
        Http::fake(['*/inventory-count-ack' => Http::response(['success' => true], 200)]);
        $this->createMarketplaceProduct();
        $sync = $this->createInboundSync(['metadata' => []]);

        (new ProcessInboundInventoryCountSync($sync->id))->handle(app(MarketplaceInventoryCountSyncService::class));

        $this->assertSame('completed', $sync->fresh()->status);
        Http::assertNothingSent();
    }
}
