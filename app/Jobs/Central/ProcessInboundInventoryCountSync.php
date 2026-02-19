<?php

namespace App\Jobs\Central;

use App\DataTransferObjects\Sync\InventoryCountSyncDTO;
use App\Models\SyncQueueInbound;
use App\Services\Central\Sync\MarketplaceInventoryCountSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
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

        if (!$syncQueue) {
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
        if (!$syncQueue->acquireLock($workerId)) {
            Log::info('Could not acquire lock, another worker processing', [
                'sync_queue_id' => $syncQueue->id,
            ]);

            return;
        }

        try {
            $syncQueue->markAsProcessing();

            Log::info('Processing inbound inventory count sync', [
                'tenant_id' => $syncQueue->tenant_id,
                'sync_queue_id' => $syncQueue->id,
                'product_id' => $syncQueue->tenant_syncable_id,
                'action' => $syncQueue->action,
            ]);

            $dto = InventoryCountSyncDTO::fromArray($syncQueue->payload);

            $syncService->updateInventoryCount($dto);

            $syncQueue->markAsCompleted();

            Log::info('Inbound inventory count sync completed', [
                'tenant_id' => $syncQueue->tenant_id,
                'sync_queue_id' => $syncQueue->id,
                'product_id' => $dto->productId,
                'variant_id' => $dto->variantId,
            ]);
        } catch (\Exception $e) {
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

            throw $e;
        } finally {
            if ($syncQueue->lock_token) {
                $syncQueue->releaseLock();
            }
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
