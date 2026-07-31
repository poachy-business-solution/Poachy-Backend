<?php

namespace Tests\Feature\Tenant\Sync;

use App\DataTransferObjects\Sync\DeliveryZoneSyncDTO;
use App\Events\Tenant\DeliveryZoneMarketplaceSyncRequested;
use App\Jobs\Tenant\ProcessOutboundDeliveryZoneSync;
use App\Listeners\Tenant\EnqueueDeliveryZoneMarketplaceSync;
use App\Models\Tenant\SyncQueueOutbound;
use App\Models\Tenant\TenantDeliveryZone;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Stancl\Tenancy\Contracts\Tenant as TenantContract;
use Tests\TestCase;

class DeliveryZoneSyncTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'tenant');
        Config::set('database.connections.tenant.database', 'poachy_test');
        Config::set('services.central_api.url', 'https://central.test');
        Config::set('services.central_api.token', 'central-test-token');
        DB::purge('tenant');
        DB::connection('tenant')->statement('SET foreign_key_checks = 0');

        $this->dropTestTables();
        $this->createMinimalSchema();

        $fakeTenant = new \stdClass;
        $fakeTenant->id = 'test-tenant';
        app()->instance(TenantContract::class, $fakeTenant);
    }

    protected function tearDown(): void
    {
        $this->dropTestTables();
        DB::connection('tenant')->statement('SET foreign_key_checks = 1');
        parent::tearDown();
    }

    private function makeListener(): EnqueueDeliveryZoneMarketplaceSync
    {
        return new EnqueueDeliveryZoneMarketplaceSync;
    }

    private function createZone(array $overrides = []): TenantDeliveryZone
    {
        return TenantDeliveryZone::create(array_merge([
            'zone_name' => 'Nairobi CBD',
            'zone_type' => 'city',
            'cities' => ['nairobi'],
            'standard_fee' => 200,
            'is_active' => true,
        ], $overrides));
    }

    // =========================================================================
    // DeliveryZoneSyncDTO::fromModel()
    // =========================================================================

    public function test_dto_throws_when_zone_not_persisted(): void
    {
        $zone = new TenantDeliveryZone(['zone_name' => 'Unsaved Zone', 'zone_type' => 'city']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('must be persisted');

        DeliveryZoneSyncDTO::fromModel($zone);
    }

    public function test_dto_throws_when_zone_name_empty(): void
    {
        $zone = $this->createZone();
        $zone->zone_name = '';

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('zone_name');

        DeliveryZoneSyncDTO::fromModel($zone);
    }

    public function test_dto_builds_from_zone_correctly(): void
    {
        $zone = $this->createZone(['express_fee' => 300, 'priority' => 5]);

        $dto = DeliveryZoneSyncDTO::fromModel($zone);

        $this->assertSame('test-tenant', $dto->tenantId);
        $this->assertSame($zone->id, $dto->zoneId);
        $this->assertSame(300.0, $dto->expressFee);
        $this->assertSame(5, $dto->priority);
    }

    // =========================================================================
    // EnqueueDeliveryZoneMarketplaceSync listener
    // =========================================================================

    public function test_listener_creates_sync_queue_row_and_dispatches_job(): void
    {
        Queue::fake();
        $zone = $this->createZone();
        $event = new DeliveryZoneMarketplaceSyncRequested($zone, 'create', 3);

        $this->makeListener()->handle($event);

        $this->assertDatabaseHas('sync_queue_outbound', [
            'tenant_id' => 'test-tenant',
            'syncable_type' => 'DeliveryZone',
            'syncable_id' => $zone->id,
            'status' => 'pending',
        ], 'tenant');
        Queue::assertPushed(ProcessOutboundDeliveryZoneSync::class);
    }

    public function test_listener_skips_when_identical_sync_already_pending(): void
    {
        Queue::fake();
        $zone = $this->createZone();
        $event = new DeliveryZoneMarketplaceSyncRequested($zone, 'create', 3);

        $this->makeListener()->handle($event);
        $this->makeListener()->handle($event);

        $this->assertSame(1, SyncQueueOutbound::where('syncable_id', $zone->id)->count());
    }

    /**
     * Confirmed real behavior, not fixed here: unlike inventory count's listener,
     * this dedup query has no "completed but expired" branch at all — it only
     * checks pending/queued/processing. Once a sync completes, a later identical
     * payload skips the status check (existingSync is null) and falls straight
     * into SyncQueueOutbound::create(), which collides with the completed row's
     * still-unique idempotency_key. The catch(UniqueConstraintViolationException)
     * swallows it silently, so — same as inventory count — an identical payload
     * can never be re-synced once its first attempt completes.
     */
    public function test_listener_silently_no_ops_when_identical_payload_recreated_after_completion(): void
    {
        Queue::fake();
        $zone = $this->createZone();
        $event = new DeliveryZoneMarketplaceSyncRequested($zone, 'create', 3);
        $this->makeListener()->handle($event);
        SyncQueueOutbound::where('syncable_id', $zone->id)->update(['status' => 'completed']);

        $this->makeListener()->handle($event);

        $this->assertSame(1, SyncQueueOutbound::where('syncable_id', $zone->id)->count());
    }

    public function test_listener_creates_new_when_payload_differs(): void
    {
        Queue::fake();
        $zone = $this->createZone(['standard_fee' => 200]);
        $this->makeListener()->handle(new DeliveryZoneMarketplaceSyncRequested($zone, 'create', 3));

        $zone->update(['standard_fee' => 999]);
        $this->makeListener()->handle(new DeliveryZoneMarketplaceSyncRequested($zone->fresh(), 'update', 3));

        $this->assertSame(2, SyncQueueOutbound::where('syncable_id', $zone->id)->count());
    }

    // =========================================================================
    // ProcessOutboundDeliveryZoneSync
    // =========================================================================

    private function createOutboundSync(array $overrides = []): SyncQueueOutbound
    {
        return SyncQueueOutbound::create(array_merge([
            'tenant_id' => 'test-tenant',
            'syncable_type' => 'DeliveryZone',
            'syncable_id' => 1,
            'action' => 'create',
            'payload' => ['tenant_id' => 'test-tenant', 'zone_id' => 1, 'zone_name' => 'Nairobi CBD', 'zone_type' => 'city', 'standard_fee' => 200.0],
            'priority' => 3,
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

    public function test_outbound_job_marks_completed_on_success(): void
    {
        Http::fake(['*/api/v1/central/sync/inbound/delivery-zone' => Http::response(['success' => true, 'data' => ['sync_id' => 99]], 200)]);
        $sync = $this->createOutboundSync();

        (new ProcessOutboundDeliveryZoneSync($sync->id))->handle();

        $this->assertSame('completed', $sync->fresh()->status);
    }

    public function test_outbound_job_reschedules_retry_on_http_error(): void
    {
        Queue::fake();
        Http::fake(['*/api/v1/central/sync/inbound/delivery-zone' => Http::response(['success' => false], 500)]);
        $sync = $this->createOutboundSync();

        try {
            (new ProcessOutboundDeliveryZoneSync($sync->id))->handle();
            $this->fail('Expected exception was not thrown');
        } catch (\Illuminate\Http\Client\RequestException $e) {
            // Http::retry() defaults to $throw=true — same as inventory count's job.
        }

        $fresh = $sync->fresh();
        $this->assertSame('pending', $fresh->status);
        $this->assertSame(1, $fresh->retry_count);
        Queue::assertPushed(ProcessOutboundDeliveryZoneSync::class);
    }

    public function test_outbound_job_marks_stale_without_http_call_when_expired(): void
    {
        Http::fake();
        $sync = $this->createOutboundSync(['expires_at' => now()->subHour()]);

        (new ProcessOutboundDeliveryZoneSync($sync->id))->handle();

        $this->assertSame('stale', $sync->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_outbound_job_skips_when_already_completed(): void
    {
        Http::fake();
        $sync = $this->createOutboundSync(['status' => 'completed']);

        (new ProcessOutboundDeliveryZoneSync($sync->id))->handle();

        Http::assertNothingSent();
    }

    // =========================================================================
    // Schema helpers
    // =========================================================================

    private function dropTestTables(): void
    {
        foreach (['sync_queue_outbound', 'tenant_delivery_zones'] as $table) {
            Schema::connection('tenant')->dropIfExists($table);
        }
    }

    private function createMinimalSchema(): void
    {
        $conn = 'tenant';

        Schema::connection($conn)->create('tenant_delivery_zones', function (Blueprint $table) {
            $table->id();
            $table->string('zone_name');
            $table->enum('zone_type', ['city', 'county', 'postal_code', 'radius'])->default('city');
            $table->json('cities')->nullable();
            $table->json('counties')->nullable();
            $table->json('postal_codes')->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->unsignedInteger('radius_km')->nullable();
            $table->decimal('standard_fee', 10, 2)->default(0);
            $table->decimal('express_fee', 10, 2)->nullable();
            $table->decimal('scheduled_fee', 10, 2)->nullable();
            $table->decimal('free_delivery_threshold', 10, 2)->nullable();
            $table->string('standard_delivery_time')->nullable();
            $table->string('express_delivery_time')->nullable();
            $table->string('scheduled_delivery_time')->nullable();
            $table->json('supported_methods')->nullable();
            $table->unsignedInteger('priority')->default(100);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::connection($conn)->create('sync_queue_outbound', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id', 100)->index();
            $table->string('syncable_type', 100);
            $table->unsignedBigInteger('syncable_id');
            $table->string('action', 30)->default('create');
            $table->json('payload')->nullable();
            $table->json('changes')->nullable();
            $table->json('metadata')->nullable();
            $table->unsignedTinyInteger('priority')->default(5);
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->string('status', 30)->default('pending');
            $table->string('lock_token', 100)->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->unsignedInteger('locked_by_worker_id')->nullable();
            $table->unsignedTinyInteger('retry_count')->default(0);
            $table->unsignedTinyInteger('max_retries')->default(3);
            $table->timestamp('next_retry_at')->nullable();
            $table->string('backoff_strategy', 30)->default('exponential');
            $table->text('error_message')->nullable();
            $table->string('error_code', 50)->nullable();
            $table->json('error_details')->nullable();
            $table->json('sync_response')->nullable();
            $table->string('central_record_id')->nullable();
            $table->string('central_table', 100)->nullable();
            $table->timestamp('processing_started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->string('idempotency_key', 100)->unique()->nullable();
            $table->string('payload_hash', 64)->nullable();
            $table->timestamps();
        });
    }
}
