<?php

namespace Tests\Feature\Central\Sync;

use App\Jobs\Central\ProcessOutboundApprovedReviewSync;
use App\Models\SyncQueueOutbound;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ApprovedReviewSyncTest extends TestCase
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
        DB::purge('central');

        $this->tenantId = 'approved-review-test-'.uniqid();
        DB::connection('central')->table('tenants')->insertOrIgnore([
            'id' => $this->tenantId, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        SyncQueueOutbound::on('central')->where('tenant_id', $this->tenantId)->delete();
        DB::connection('central')->table('tenants')->where('id', $this->tenantId)->delete();

        parent::tearDown();
    }

    private function createOutboundSync(array $overrides = []): SyncQueueOutbound
    {
        return SyncQueueOutbound::on('central')->create(array_merge([
            'tenant_id' => $this->tenantId,
            'syncable_type' => 'ApprovedReview',
            'syncable_id' => 1,
            'action' => 'create',
            'payload' => [],
            'priority' => 2,
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

    public function test_failed_marks_sync_queue_failed_with_job_failed_message(): void
    {
        $sync = $this->createOutboundSync();

        (new ProcessOutboundApprovedReviewSync($sync->id))->failed(new \RuntimeException('queue worker gave up'));

        $fresh = $sync->fresh();
        $this->assertSame('failed', $fresh->status);
        $this->assertStringContainsString('queue worker gave up', $fresh->error_message);
    }
}
