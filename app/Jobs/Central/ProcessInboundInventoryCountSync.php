<?php

namespace App\Jobs\Central;

use App\DataTransferObjects\Sync\InventoryCountSyncDTO;
use App\Models\SyncQueueInbound;
use App\Models\Tenant;
use App\Services\Central\Sync\MarketplaceInventoryCountSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProcessInboundInventoryCountSync implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 180;

    public int $tries = 3;

    public int $maxExceptions = 3;

    public $backoff = [60, 300, 900]; // 1min, 5min, 15min

    public function __construct(
        public int $syncQueueId
    ) {}

    public function handle(MarketplaceInventoryCountSyncService $syncService): void
    {
        $syncQueue = SyncQueueInbound::find($this->syncQueueId);

        if (! $syncQueue) {
            Log::error('SyncQueueInbound record not found', [
                'sync_queue_id' => $this->syncQueueId,
            ]);

            return;
        }

        if ($syncQueue->status === 'completed') {
            Log::info('InventoryCount inbound sync already completed, skipping', [
                'sync_queue_id' => $syncQueue->id,
            ]);

            return;
        }

        if ($syncQueue->isStale()) {
            $syncQueue->update(['status' => 'stale']);
            Log::warning('InventoryCount inbound sync is stale, marking as expired', [
                'sync_queue_id' => $syncQueue->id,
                'expires_at' => $syncQueue->expires_at,
            ]);

            return;
        }

        $workerId = getmypid();
        if (! $syncQueue->acquireLock($workerId)) {
            Log::info('Could not acquire lock, another worker processing', [
                'sync_queue_id' => $syncQueue->id,
            ]);

            return;
        }

        $centralProductId = null;
        $ackStatus = 'failed';
        $ackReason = null;

        try {
            $syncQueue->markAsProcessing();

            Log::info('Processing inbound inventory count sync', [
                'tenant_id' => $syncQueue->tenant_id,
                'sync_queue_id' => $syncQueue->id,
                'product_id' => $syncQueue->tenant_syncable_id,
                'action' => $syncQueue->action,
            ]);

            $dto = InventoryCountSyncDTO::fromArray($syncQueue->payload);

            $centralProductId = $syncService->updateInventoryCount($dto);

            $syncQueue->markAsCompleted($centralProductId, 'marketplace_products');
            $ackStatus = 'completed';

            Log::info('Inbound inventory count sync completed', [
                'tenant_id' => $syncQueue->tenant_id,
                'sync_queue_id' => $syncQueue->id,
                'product_id' => $dto->productId,
                'variant_id' => $dto->variantId,
            ]);
        } catch (\Exception $e) {
            $ackStatus = 'failed';
            $ackReason = $e->getMessage();

            Log::error('Inbound inventory count sync failed', [
                'tenant_id' => $syncQueue->tenant_id,
                'sync_queue_id' => $syncQueue->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $syncQueue->markAsFailed(
                errorMessage: $e->getMessage(),
                errorCode: 'SYNC_ERROR',
                errorDetails: [
                    'exception' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]
            );

            if ($syncQueue->canRetry()) {
                $syncQueue->incrementRetry();

                Log::info('InventoryCount sync will be retried', [
                    'sync_queue_id' => $syncQueue->id,
                    'retry_count' => $syncQueue->retry_count,
                    'next_retry_at' => $syncQueue->next_retry_at,
                ]);

                $syncQueue->releaseLock();

                ProcessInboundInventoryCountSync::dispatch($syncQueue->id)
                    ->delay($syncQueue->next_retry_at)
                    ->onQueue('sync-high');
            } else {
                Log::error('Max retries reached, InventoryCount sync failed permanently', [
                    'sync_queue_id' => $syncQueue->id,
                    'retry_count' => $syncQueue->retry_count,
                ]);
            }

            throw $e;
        } finally {
            if ($syncQueue->lock_token) {
                $syncQueue->releaseLock();
            }

            // Always ACK the tenant on final success or permanent failure — not on a
            // transient failure that's about to be retried.
            if ($ackStatus === 'completed' || ! $syncQueue->canRetry()) {
                $this->ackTenant($syncQueue, $ackStatus, $centralProductId, $ackReason);
            }
        }
    }

    /**
     * ACK the tenant once we have a final result (success or permanent failure).
     */
    private function ackTenant(
        SyncQueueInbound $syncQueue,
        string $status,
        ?int $centralProductId,
        ?string $reason,
    ): void {
        $tenantOutboundSyncId = $syncQueue->metadata['sync_queue_id_from_tenant'] ?? null;

        if (! $tenantOutboundSyncId) {
            Log::warning('No tenant outbound sync queue ID in metadata, skipping ACK', [
                'sync_queue_id' => $syncQueue->id,
            ]);

            return;
        }

        try {
            $tenant = Tenant::on('central')->find($syncQueue->tenant_id);

            if (! $tenant) {
                Log::warning('Tenant not found for inventory count ACK', [
                    'tenant_id' => $syncQueue->tenant_id,
                    'sync_queue_id' => $syncQueue->id,
                ]);

                return;
            }

            $domain = $tenant->domains()->first();

            if (! $domain) {
                Log::warning('No domain found for tenant inventory count ACK', [
                    'tenant_id' => $syncQueue->tenant_id,
                    'sync_queue_id' => $syncQueue->id,
                ]);

                return;
            }

            $scheme = app()->environment('local') ? 'http://' : 'https://';
            $tenantUrl = $scheme.$domain->domain;

            Http::withToken(config('services.tenant_api.token'))
                ->timeout(30)
                ->retry(2, 100)
                ->post($tenantUrl.'/api/v1/tenant/sync/inbound/inventory-count-ack', [
                    'outbound_sync_queue_id' => (int) $tenantOutboundSyncId,
                    'status' => $status,
                    'central_product_id' => $centralProductId,
                    'reason' => $reason,
                ]);

            Log::info('InventoryCount ACK sent to tenant', [
                'tenant_id' => $syncQueue->tenant_id,
                'sync_queue_id' => $syncQueue->id,
                'outbound_sync_queue_id' => $tenantOutboundSyncId,
                'status' => $status,
                'central_product_id' => $centralProductId,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send InventoryCount ACK to tenant', [
                'tenant_id' => $syncQueue->tenant_id,
                'sync_queue_id' => $syncQueue->id,
                'error' => $e->getMessage(),
            ]);
            // Don't rethrow — ACK failure should not fail the sync job
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessInboundInventoryCountSync job failed permanently', [
            'sync_queue_id' => $this->syncQueueId,
            'error' => $exception->getMessage(),
        ]);

        $syncQueue = SyncQueueInbound::find($this->syncQueueId);
        if ($syncQueue) {
            $syncQueue->markAsFailed(
                errorMessage: 'Job failed permanently: '.$exception->getMessage(),
                errorCode: 'JOB_FAILED',
                errorDetails: [
                    'exception' => get_class($exception),
                    'trace' => $exception->getTraceAsString(),
                ]
            );
        }
    }
}
