<?php

namespace App\Jobs\Central;

use App\Models\SyncQueueOutbound;
use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProcessOutboundApprovedReviewSync implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $timeout = 120;
    public int $tries = 3;
    public $backoff = [60, 300, 900];

    public function __construct(public int $syncQueueId) {}

    public function handle(): void
    {
        $syncQueue = SyncQueueOutbound::on('central')->find($this->syncQueueId);

        if (! $syncQueue || $syncQueue->status === 'completed') {
            return;
        }

        $workerId = getmypid();
        if (! $syncQueue->acquireLock($workerId)) {
            return;
        }

        try {
            $syncQueue->markAsProcessing();

            $tenant = Tenant::on('central')->find($syncQueue->tenant_id);

            if (! $tenant) {
                throw new \RuntimeException("Tenant not found: {$syncQueue->tenant_id}");
            }

            // Get tenant's first domain
            $domain = $tenant->domains()->first();

            if (! $domain) {
                throw new \RuntimeException("No domain found for tenant: {$syncQueue->tenant_id}");
            }

            $scheme = app()->environment('local') ? 'http://' : 'https://';

            $tenantUrl = $scheme . $domain->domain;

            $response = Http::timeout(60)
                ->retry(2, 100)
                ->withHeaders([
                    'X-Central-Sync'     => 'true',
                    'X-Sync-Queue-ID'    => $syncQueue->id,
                    'X-Idempotency-Key'  => $syncQueue->idempotency_key,
                ])
                ->withToken(config('services.tenant_api.token'))
                ->post(
                    $tenantUrl . '/api/v1/tenant/sync/inbound/approved-review',
                    $syncQueue->payload
                );

            if (! $response->successful()) {
                throw new \RuntimeException("Tenant API request failed: {$response->status()} - {$response->body()}");
            }

            $syncQueue->markAsCompleted($response->json('data.local_review_id'));

            Log::info('Outbound approved review sync completed', [
                'sync_queue_id' => $syncQueue->id,
                'tenant_id'     => $syncQueue->tenant_id,
            ]);
        } catch (\Exception $e) {
            $syncQueue->markAsFailed($e->getMessage());

            if ($syncQueue->canRetry()) {
                $syncQueue->incrementRetry();
                ProcessOutboundApprovedReviewSync::dispatch($syncQueue->id)
                    ->delay($syncQueue->next_retry_at)
                    ->onQueue('sync-normal');
            }

            throw $e;
        } finally {
            $syncQueue->releaseLock();
        }
    }
}
