<?php

namespace App\Jobs\Central;

use App\DataTransferObjects\Sync\BundleSyncDTO;
use App\Models\SyncQueueInbound;
use App\Services\Central\Sync\MarketplaceBundleSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessInboundBundleSync implements ShouldQueue
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
    public function handle(MarketplaceBundleSyncService $syncService): void
    {
        $syncQueue = SyncQueueInbound::find($this->syncQueueId);

        if (!$syncQueue) {
            Log::error('SyncQueueInbound record not found', [
                'sync_queue_id' => $this->syncQueueId,
            ]);
            return;
        }

        // Check if already completed
        if ($syncQueue->status === 'completed') {
            Log::info('Bundle sync already completed, skipping', [
                'sync_queue_id' => $syncQueue->id,
            ]);
            return;
        }

        // Check if stale
        if ($syncQueue->isStale()) {
            $syncQueue->update(['status' => 'stale']);
            Log::warning('Bundle sync is stale, marking as expired', [
                'sync_queue_id' => $syncQueue->id,
                'expires_at' => $syncQueue->expires_at,
            ]);
            return;
        }

        // Acquire lock
        $workerId = getmypid();
        if (!$syncQueue->acquireLock($workerId)) {
            Log::info('Could not acquire lock, another worker processing', [
                'sync_queue_id' => $syncQueue->id,
            ]);
            return;
        }

        try {
            // Mark as processing
            $syncQueue->markAsProcessing();

            Log::info('Processing inbound bundle sync', [
                'tenant_id' => $syncQueue->tenant_id,
                'sync_queue_id' => $syncQueue->id,
                'bundle_id' => $syncQueue->tenant_syncable_id,
                'action' => $syncQueue->action,
            ]);

            // Convert payload to DTO
            $dto = BundleSyncDTO::fromArray($syncQueue->payload);

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

            Log::info('Inbound bundle sync completed', [
                'tenant_id' => $syncQueue->tenant_id,
                'sync_queue_id' => $syncQueue->id,
                'marketplace_product_id' => $result['marketplace_product_id'],
                'action' => $syncQueue->action,
            ]);
        } catch (\Exception $e) {
            Log::error('Inbound bundle sync failed', [
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

                Log::info('Bundle sync will be retried', [
                    'sync_queue_id' => $syncQueue->id,
                    'retry_count' => $syncQueue->retry_count,
                    'next_retry_at' => $syncQueue->next_retry_at,
                ]);

                // Release lock for retry
                $syncQueue->releaseLock();

                // Dispatch retry job
                ProcessInboundBundleSync::dispatch($syncQueue->id)
                    ->delay($syncQueue->next_retry_at)
                    ->onQueue('sync-high');
            } else {
                Log::error('Max retries reached, bundle sync failed permanently', [
                    'sync_queue_id' => $syncQueue->id,
                    'retry_count' => $syncQueue->retry_count,
                ]);
            }

            throw $e;
        } finally {
            // Always release lock if still held
            if ($syncQueue->lock_token) {
                $syncQueue->releaseLock();
            }
        }
    }

    /**
     * Get error code from exception
     */
    protected function getErrorCode(\Throwable $e): string
    {
        if ($e instanceof \Illuminate\Validation\ValidationException) {
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
        Log::error('ProcessInboundBundleSync job failed permanently', [
            'sync_queue_id' => $this->syncQueueId,
            'error' => $exception->getMessage(),
        ]);

        $syncQueue = SyncQueueInbound::find($this->syncQueueId);
        if ($syncQueue) {
            $syncQueue->markAsFailed(
                errorMessage: 'Job failed permanently: ' . $exception->getMessage(),
                errorCode: 'JOB_FAILED',
                errorDetails: [
                    'exception' => get_class($exception),
                    'trace' => $exception->getTraceAsString(),
                ]
            );
        }
    }
}
