<?php

namespace App\Jobs\Central;

use App\DataTransferObjects\Sync\ProductVariantSyncDTO;
use App\Models\SyncQueueInbound;
use App\Models\Tenant;
use App\Services\Central\Sync\MarketplaceVariantSyncService;
use App\Services\Central\Sync\SyncFailureNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ProcessInboundVariantSync implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 180;

    public int $tries = 3;

    public int $maxExceptions = 3;

    public $backoff = [60, 300, 900]; // 1min, 5min, 15min

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $syncQueueId
    ) {}

    /**
     * Execute the job.
     */
    public function handle(MarketplaceVariantSyncService $syncService): void
    {
        $syncQueue = SyncQueueInbound::find($this->syncQueueId);

        if (! $syncQueue) {
            Log::error('SyncQueueInbound record not found', [
                'sync_queue_id' => $this->syncQueueId,
            ]);

            return;
        }

        // Check if already completed
        if ($syncQueue->status === 'completed') {
            Log::info('Variant sync already completed, skipping', [
                'sync_queue_id' => $syncQueue->id,
            ]);

            return;
        }

        // Check if stale
        if ($syncQueue->isStale()) {
            $syncQueue->update(['status' => 'stale']);
            Log::warning('Variant sync is stale, marking as expired', [
                'sync_queue_id' => $syncQueue->id,
                'expires_at' => $syncQueue->expires_at,
            ]);

            return;
        }

        // Acquire lock
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
            // Mark as processing
            $syncQueue->markAsProcessing();

            Log::info('Processing inbound variant sync', [
                'tenant_id' => $syncQueue->tenant_id,
                'sync_queue_id' => $syncQueue->id,
                'variant_id' => $syncQueue->tenant_syncable_id,
                'action' => $syncQueue->action,
            ]);

            // Convert payload to DTO
            $dto = ProductVariantSyncDTO::fromArray($syncQueue->payload);

            // Process based on action
            $result = match ($syncQueue->action) {
                'create' => $syncService->createMarketplaceProduct($dto, $syncQueue),
                'update' => $syncService->updateMarketplaceProduct($dto, $syncQueue),
                'delete' => $syncService->deleteMarketplaceProduct($dto, $syncQueue),
                'activate' => $syncService->activateMarketplaceProduct($dto, $syncQueue),
                'deactivate' => $syncService->deactivateMarketplaceProduct($dto, $syncQueue),
                default => throw new \InvalidArgumentException("Unknown action: {$syncQueue->action}"),
            };

            // Mark as completed
            $syncQueue->markAsCompleted(
                centralRecordId: $result['marketplace_product_id'],
                centralTable: 'marketplace_products'
            );

            $centralProductId = $result['marketplace_product_id'];
            $ackStatus = 'completed';

            Log::info('Inbound variant sync completed', [
                'tenant_id' => $syncQueue->tenant_id,
                'sync_queue_id' => $syncQueue->id,
                'marketplace_product_id' => $result['marketplace_product_id'],
                'action' => $syncQueue->action,
            ]);
        } catch (\Exception $e) {
            $ackStatus = 'failed';
            $ackReason = $e->getMessage();

            Log::error('Inbound variant sync failed', [
                'tenant_id' => $syncQueue->tenant_id,
                'sync_queue_id' => $syncQueue->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Mark as failed
            $syncQueue->markAsFailed(
                errorMessage: $e->getMessage(),
                errorCode: $this->getErrorCode($e),
                errorDetails: [
                    'exception' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]
            );

            // Check if can retry
            if ($syncQueue->canRetry()) {
                $syncQueue->incrementRetry();

                Log::info('Variant sync will be retried', [
                    'sync_queue_id' => $syncQueue->id,
                    'retry_count' => $syncQueue->retry_count,
                    'next_retry_at' => $syncQueue->next_retry_at,
                ]);

                // Release lock for retry
                $syncQueue->releaseLock();

                // Dispatch retry job
                ProcessInboundVariantSync::dispatch($syncQueue->id)
                    ->delay($syncQueue->next_retry_at)
                    ->onQueue('sync-high');
            } else {
                Log::error('Max retries reached, variant sync failed permanently', [
                    'sync_queue_id' => $syncQueue->id,
                    'retry_count' => $syncQueue->retry_count,
                ]);

                app(SyncFailureNotificationService::class)->notifyTenantOwners($syncQueue, 'Variant inbound sync');
            }

            throw $e;
        } finally {
            // Always release lock if still held
            if ($syncQueue->lock_token) {
                $syncQueue->releaseLock();
            }

            // Always ACK the tenant on final success or permanent failure
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
                Log::warning('Tenant not found for variant ACK', [
                    'tenant_id' => $syncQueue->tenant_id,
                    'sync_queue_id' => $syncQueue->id,
                ]);

                return;
            }

            $domain = $tenant->domains()->first();

            if (! $domain) {
                Log::warning('No domain found for tenant variant ACK', [
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
                ->post($tenantUrl.'/api/v1/tenant/sync/inbound/variant-ack', [
                    'outbound_sync_queue_id' => (int) $tenantOutboundSyncId,
                    'status' => $status,
                    'central_product_id' => $centralProductId,
                    'reason' => $reason,
                ]);

            Log::info('Variant ACK sent to tenant', [
                'tenant_id' => $syncQueue->tenant_id,
                'sync_queue_id' => $syncQueue->id,
                'outbound_sync_queue_id' => $tenantOutboundSyncId,
                'status' => $status,
                'central_product_id' => $centralProductId,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send variant ACK to tenant', [
                'tenant_id' => $syncQueue->tenant_id,
                'sync_queue_id' => $syncQueue->id,
                'error' => $e->getMessage(),
            ]);
            // Don't rethrow — ACK failure should not fail the sync job
        }
    }

    /**
     * Get error code from exception
     */
    protected function getErrorCode(\Throwable $e): string
    {
        if ($e instanceof ValidationException) {
            return 'VALIDATION_ERROR';
        }

        if (str_contains($e->getMessage(), 'mapping')) {
            return 'MAPPING_ERROR';
        }

        if (str_contains($e->getMessage(), 'duplicate')) {
            return 'DUPLICATE_ERROR';
        }

        return 'SYNC_ERROR';
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessInboundVariantSync job failed permanently', [
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
