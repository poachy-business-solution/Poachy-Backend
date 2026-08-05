<?php

namespace Tests\Feature\Central\Sync;

use App\Jobs\Central\ProcessInboundProductSync;
use App\Models\MarketplaceProduct;
use App\Models\SyncQueueInbound;
use App\Services\Central\Sync\MarketplaceSyncService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProductSyncAckTest extends TestCase
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

        $this->tenantId = 'prod-sync-test-'.uniqid();
        DB::connection('central')->table('tenants')->insertOrIgnore([
            'id' => $this->tenantId, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::connection('central')->table('domains')->insert([
            'domain' => 'prod-sync-'.uniqid().'.poachy.test', 'tenant_id' => $this->tenantId,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        DB::connection('central')->statement('SET foreign_key_checks = 0');
        SyncQueueInbound::on('central')->where('tenant_id', $this->tenantId)->delete();
        MarketplaceProduct::on('central')->where('tenant_id', $this->tenantId)->delete();
        DB::connection('central')->table('domains')->where('tenant_id', $this->tenantId)->delete();
        DB::connection('central')->table('tenants')->where('id', $this->tenantId)->delete();
        DB::connection('central')->statement('SET foreign_key_checks = 1');

        parent::tearDown();
    }

    private function createInboundSync(array $overrides = []): SyncQueueInbound
    {
        $payload = array_merge([
            'tenant_id' => $this->tenantId,
            'product_id' => 1,
            'product_uuid' => 'prod-uuid-'.uniqid(),
            'product_type' => 'product',
            'variant_id' => null,
            'bundle_id' => null,
            'name' => 'Test Product',
            'slug' => 'test-product-'.uniqid(),
            'sku' => 'PROD-'.uniqid(),
            'description' => 'Test description',
            'online_description' => 'Online test description',
            'online_price' => 120.00,
            'tax_rate' => 16.00,
            'base_uom' => ['code' => 'PCS', 'name' => 'Pieces'],
            'category' => ['id' => 1, 'name' => 'Electronics', 'slug' => 'electronics'],
            'brand' => null,
            'primary_image' => null,
            'secondary_images' => [],
            'inventory' => ['available_quantity' => 50.0, 'stock_status' => 'in_stock'],
            'is_active' => true,
            'is_featured' => false,
            'metadata' => [],
        ], $overrides['payload'] ?? []);
        unset($overrides['payload']);

        return SyncQueueInbound::on('central')->create(array_merge([
            'tenant_id' => $this->tenantId,
            'syncable_type' => 'Product',
            'tenant_syncable_id' => 1,
            'action' => 'create',
            'payload' => $payload,
            'metadata' => ['sync_queue_id_from_tenant' => 701],
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

    public function test_inbound_job_creates_product_and_acks_completed(): void
    {
        Http::fake(['*/product-ack' => Http::response(['success' => true], 200)]);
        $sync = $this->createInboundSync();

        (new ProcessInboundProductSync($sync->id))->handle(app(MarketplaceSyncService::class));

        $fresh = $sync->fresh();
        $this->assertSame('completed', $fresh->status);
        $product = MarketplaceProduct::on('central')->where('tenant_id', $this->tenantId)->first();
        $this->assertNotNull($product);
        $this->assertEquals($product->id, $fresh->central_record_id);

        Http::assertSent(function ($request) use ($product) {
            return str_contains($request->url(), 'product-ack')
                && $request['outbound_sync_queue_id'] === 701
                && $request['status'] === 'completed'
                && $request['central_product_id'] === $product->id;
        });
    }

    public function test_inbound_job_acks_failed_after_max_retries(): void
    {
        Http::fake(['*/product-ack' => Http::response(['success' => true], 200)]);
        // 'bulk_update' is a valid value in the action column's enum, just not
        // one the job's match() handles — genuinely exercises the default =>
        // throw branch, unlike an arbitrary string the enum column would reject.
        $sync = $this->createInboundSync(['action' => 'bulk_update', 'retry_count' => 3, 'max_retries' => 3]);

        try {
            (new ProcessInboundProductSync($sync->id))->handle(app(MarketplaceSyncService::class));
            $this->fail('Expected exception was not thrown');
        } catch (\InvalidArgumentException $e) {
            // expected
        }

        $this->assertSame('failed', $sync->fresh()->status);
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'product-ack') && $request['status'] === 'failed';
        });
    }

    public function test_inbound_job_does_not_ack_on_transient_failure(): void
    {
        Http::fake();
        $sync = $this->createInboundSync(['action' => 'bulk_update']);

        try {
            (new ProcessInboundProductSync($sync->id))->handle(app(MarketplaceSyncService::class));
            $this->fail('Expected exception was not thrown');
        } catch (\InvalidArgumentException $e) {
            // expected — unknown action, retryable
        }

        $fresh = $sync->fresh();
        $this->assertSame('pending', $fresh->status);
        $this->assertSame(1, $fresh->retry_count);
        Http::assertNothingSent();
    }

    public function test_inbound_job_skips_when_already_completed(): void
    {
        Http::fake();
        $sync = $this->createInboundSync(['status' => 'completed']);

        (new ProcessInboundProductSync($sync->id))->handle(app(MarketplaceSyncService::class));

        Http::assertNothingSent();
    }
}
