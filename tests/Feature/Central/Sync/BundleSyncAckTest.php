<?php

namespace Tests\Feature\Central\Sync;

use App\Jobs\Central\ProcessInboundBundleSync;
use App\Models\MarketplaceProduct;
use App\Models\SyncQueueInbound;
use App\Services\Central\Sync\MarketplaceBundleSyncService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BundleSyncAckTest extends TestCase
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

        $this->tenantId = 'bundle-sync-test-'.uniqid();
        DB::connection('central')->table('tenants')->insertOrIgnore([
            'id' => $this->tenantId, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::connection('central')->table('domains')->insert([
            'domain' => 'bundle-sync-'.uniqid().'.poachy.test', 'tenant_id' => $this->tenantId,
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
            'bundle_id' => 5,
            'bundle_uuid' => 'bundle-uuid-'.uniqid(),
            'product_type' => 'bundle',
            'bundle_name' => 'Starter Kit',
            'bundle_sku' => 'BUNDLE-'.uniqid(),
            'description' => 'A starter kit bundle',
            'online_description' => 'Online starter kit description',
            'bundle_price' => 500.00,
            'online_price' => 450.00,
            'calculated_individual_price' => 600.00,
            'discount_amount' => 100.00,
            'savings_percentage' => 16.67,
            'tax_rate' => 16.00,
            'base_uom' => ['code' => 'SET', 'name' => 'Set'],
            'primary_image' => null,
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
            ],
            'is_active' => true,
            'is_featured' => false,
            'metadata' => [],
        ], $overrides['payload'] ?? []);
        unset($overrides['payload']);

        return SyncQueueInbound::on('central')->create(array_merge([
            'tenant_id' => $this->tenantId,
            'syncable_type' => 'Bundle',
            'tenant_syncable_id' => 5,
            'action' => 'create',
            'payload' => $payload,
            'metadata' => ['sync_queue_id_from_tenant' => 703],
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

    public function test_inbound_job_creates_bundle_and_acks_completed(): void
    {
        Http::fake(['*/bundle-ack' => Http::response(['success' => true], 200)]);
        $sync = $this->createInboundSync();

        (new ProcessInboundBundleSync($sync->id))->handle(app(MarketplaceBundleSyncService::class));

        $fresh = $sync->fresh();
        $this->assertSame('completed', $fresh->status);
        $product = MarketplaceProduct::on('central')->where('tenant_id', $this->tenantId)->first();
        $this->assertNotNull($product);
        $this->assertEquals($product->id, $fresh->central_record_id);

        Http::assertSent(function ($request) use ($product) {
            return str_contains($request->url(), 'bundle-ack')
                && $request['outbound_sync_queue_id'] === 703
                && $request['status'] === 'completed'
                && $request['central_product_id'] === $product->id;
        });
    }

    public function test_inbound_job_acks_failed_after_max_retries(): void
    {
        Http::fake(['*/bundle-ack' => Http::response(['success' => true], 200)]);
        $sync = $this->createInboundSync(['action' => 'bulk_update', 'retry_count' => 3, 'max_retries' => 3]);

        try {
            (new ProcessInboundBundleSync($sync->id))->handle(app(MarketplaceBundleSyncService::class));
            $this->fail('Expected exception was not thrown');
        } catch (\InvalidArgumentException $e) {
            // expected
        }

        $this->assertSame('failed', $sync->fresh()->status);
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'bundle-ack') && $request['status'] === 'failed';
        });
    }

    public function test_inbound_job_does_not_ack_on_transient_failure(): void
    {
        Http::fake();
        $sync = $this->createInboundSync(['action' => 'bulk_update']);

        try {
            (new ProcessInboundBundleSync($sync->id))->handle(app(MarketplaceBundleSyncService::class));
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

        (new ProcessInboundBundleSync($sync->id))->handle(app(MarketplaceBundleSyncService::class));

        Http::assertNothingSent();
    }
}
