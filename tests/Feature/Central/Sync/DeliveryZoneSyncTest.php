<?php

namespace Tests\Feature\Central\Sync;

use App\DataTransferObjects\Sync\DeliveryZoneSyncDTO;
use App\Jobs\Central\ProcessInboundDeliveryZoneSync;
use App\Models\SyncQueueInbound;
use App\Models\TenantDeliveryZone;
use App\Services\Central\Sync\TenantDeliveryZoneSyncService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DeliveryZoneSyncTest extends TestCase
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

        $this->tenantId = 'dz-sync-test-'.uniqid();
        DB::connection('central')->table('tenants')->insertOrIgnore([
            'id' => $this->tenantId, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::connection('central')->table('domains')->insert([
            'domain' => 'dz-sync-'.uniqid().'.poachy.test', 'tenant_id' => $this->tenantId,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        DB::connection('central')->statement('SET foreign_key_checks = 0');
        SyncQueueInbound::on('central')->where('tenant_id', $this->tenantId)->delete();
        TenantDeliveryZone::on('central')->where('tenant_id', $this->tenantId)->delete();
        DB::connection('central')->table('domains')->where('tenant_id', $this->tenantId)->delete();
        DB::connection('central')->table('tenants')->where('id', $this->tenantId)->delete();
        DB::connection('central')->statement('SET foreign_key_checks = 1');

        parent::tearDown();
    }

    private function makeApplyService(): TenantDeliveryZoneSyncService
    {
        return new TenantDeliveryZoneSyncService;
    }

    private function makeDto(array $overrides = []): DeliveryZoneSyncDTO
    {
        return DeliveryZoneSyncDTO::fromArray(array_merge([
            'tenant_id' => $this->tenantId,
            'zone_id' => 1,
            'zone_name' => 'Nairobi CBD',
            'zone_type' => 'city',
            'cities' => ['nairobi'],
            'standard_fee' => 200.0,
            'supported_methods' => ['standard'],
            'priority' => 100,
            'is_active' => true,
        ], $overrides));
    }

    // =========================================================================
    // TenantDeliveryZoneSyncService — create / update / delete + idempotency
    // =========================================================================

    public function test_create_creates_central_zone(): void
    {
        $zone = $this->makeApplyService()->createDeliveryZone($this->makeDto());

        $this->assertSame($this->tenantId, $zone->tenant_id);
        $this->assertSame(1, $zone->tenant_zone_id);
        $this->assertSame('synced', $zone->sync_status);
    }

    public function test_create_is_idempotent_updates_existing_on_retry(): void
    {
        $first = $this->makeApplyService()->createDeliveryZone($this->makeDto(['standard_fee' => 200]));

        $second = $this->makeApplyService()->createDeliveryZone($this->makeDto(['standard_fee' => 300]));

        $this->assertSame($first->id, $second->id);
        $this->assertEquals(300, $second->standard_fee);
        $this->assertSame(1, TenantDeliveryZone::on('central')->where('tenant_id', $this->tenantId)->count());
    }

    public function test_update_updates_existing_zone(): void
    {
        $this->makeApplyService()->createDeliveryZone($this->makeDto(['zone_name' => 'Original']));

        $updated = $this->makeApplyService()->updateDeliveryZone($this->makeDto(['zone_name' => 'Renamed']));

        $this->assertSame('Renamed', $updated->zone_name);
    }

    public function test_update_creates_when_missing_out_of_order_delivery(): void
    {
        $zone = $this->makeApplyService()->updateDeliveryZone($this->makeDto());

        $this->assertNotNull($zone->id);
        $this->assertSame($this->tenantId, $zone->tenant_id);
    }

    public function test_delete_removes_existing_zone(): void
    {
        $this->makeApplyService()->createDeliveryZone($this->makeDto());

        $this->makeApplyService()->deleteDeliveryZone($this->makeDto());

        $this->assertSame(0, TenantDeliveryZone::on('central')->where('tenant_id', $this->tenantId)->count());
    }

    public function test_delete_is_idempotent_when_already_absent(): void
    {
        // No prior create() call — zone never existed centrally.
        $this->makeApplyService()->deleteDeliveryZone($this->makeDto());

        $this->assertSame(0, TenantDeliveryZone::on('central')->where('tenant_id', $this->tenantId)->count());
    }

    // =========================================================================
    // ProcessInboundDeliveryZoneSync — inbound processing + ACK
    // =========================================================================

    private function createInboundSync(array $overrides = []): SyncQueueInbound
    {
        return SyncQueueInbound::on('central')->create(array_merge([
            'tenant_id' => $this->tenantId,
            'syncable_type' => 'DeliveryZone',
            'tenant_syncable_id' => 1,
            'action' => 'create',
            'payload' => [
                'tenant_id' => $this->tenantId, 'zone_id' => 1, 'zone_name' => 'Nairobi CBD',
                'zone_type' => 'city', 'cities' => ['nairobi'], 'standard_fee' => 200.0,
                'supported_methods' => ['standard'], 'priority' => 100, 'is_active' => true,
            ],
            'metadata' => ['sync_queue_id_from_tenant' => 601],
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

    public function test_inbound_job_creates_zone_and_acks_completed(): void
    {
        Http::fake(['*/delivery-zone-ack' => Http::response(['success' => true], 200)]);
        $sync = $this->createInboundSync();

        (new ProcessInboundDeliveryZoneSync($sync->id))->handle(app(TenantDeliveryZoneSyncService::class));

        $fresh = $sync->fresh();
        $this->assertSame('completed', $fresh->status);
        $zone = TenantDeliveryZone::on('central')->where('tenant_id', $this->tenantId)->first();
        $this->assertNotNull($zone);
        $this->assertEquals($zone->id, $fresh->central_record_id);

        Http::assertSent(function ($request) use ($zone) {
            return str_contains($request->url(), 'delivery-zone-ack')
                && $request['outbound_sync_queue_id'] === 601
                && $request['status'] === 'completed'
                && $request['central_zone_id'] === $zone->id;
        });
    }

    public function test_inbound_job_deletes_zone_and_acks_completed(): void
    {
        Http::fake(['*/delivery-zone-ack' => Http::response(['success' => true], 200)]);
        $this->makeApplyService()->createDeliveryZone($this->makeDto());
        $sync = $this->createInboundSync(['action' => 'delete']);

        (new ProcessInboundDeliveryZoneSync($sync->id))->handle(app(TenantDeliveryZoneSyncService::class));

        $this->assertSame('completed', $sync->fresh()->status);
        $this->assertSame(0, TenantDeliveryZone::on('central')->where('tenant_id', $this->tenantId)->count());
    }

    public function test_inbound_job_marks_failed_and_does_not_ack_on_transient_failure(): void
    {
        Http::fake();
        // 'activate' is a valid value in the action column's enum, just not one
        // the job's match() handles — genuinely exercises the default => throw
        // branch, unlike an arbitrary string the enum column would reject outright.
        $sync = $this->createInboundSync(['action' => 'activate']);

        try {
            (new ProcessInboundDeliveryZoneSync($sync->id))->handle(app(TenantDeliveryZoneSyncService::class));
            $this->fail('Expected exception was not thrown');
        } catch (\InvalidArgumentException $e) {
            // expected — unknown action
        }

        $fresh = $sync->fresh();
        $this->assertSame('pending', $fresh->status);
        $this->assertSame(1, $fresh->retry_count);
        Http::assertNothingSent();
    }

    public function test_inbound_job_acks_failed_after_max_retries(): void
    {
        Http::fake(['*/delivery-zone-ack' => Http::response(['success' => true], 200)]);
        $sync = $this->createInboundSync(['action' => 'activate', 'retry_count' => 3, 'max_retries' => 3]);

        try {
            (new ProcessInboundDeliveryZoneSync($sync->id))->handle(app(TenantDeliveryZoneSyncService::class));
            $this->fail('Expected exception was not thrown');
        } catch (\InvalidArgumentException $e) {
            // expected
        }

        $this->assertSame('failed', $sync->fresh()->status);
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'delivery-zone-ack') && $request['status'] === 'failed';
        });
    }

    public function test_inbound_job_skips_when_already_completed(): void
    {
        Http::fake();
        $sync = $this->createInboundSync(['status' => 'completed']);

        (new ProcessInboundDeliveryZoneSync($sync->id))->handle(app(TenantDeliveryZoneSyncService::class));

        Http::assertNothingSent();
    }

    public function test_inbound_job_marks_stale_without_processing_when_expired(): void
    {
        Http::fake();
        $sync = $this->createInboundSync(['expires_at' => now()->subHour()]);

        (new ProcessInboundDeliveryZoneSync($sync->id))->handle(app(TenantDeliveryZoneSyncService::class));

        $this->assertSame('stale', $sync->fresh()->status);
        Http::assertNothingSent();
    }
}
