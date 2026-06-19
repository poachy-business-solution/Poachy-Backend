<?php

namespace App\Jobs\Tenant;

use App\Models\Tenant\SyncQueueOutbound;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProcessOutboundProductSync implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;
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
    public function handle(): void
    {
        $syncQueue = SyncQueueOutbound::find($this->syncQueueId);

        if (!$syncQueue) {
            Log::error('SyncQueueOutbound record not found', [
                'sync_queue_id' => $this->syncQueueId,
            ]);
            return;
        }

        // Check if already completed
        if ($syncQueue->status === 'completed') {
            Log::info('Sync already completed, skipping', [
                'sync_queue_id' => $syncQueue->id,
            ]);
            return;
        }

        // Check if stale
        if ($syncQueue->isStale()) {
            $syncQueue->update(['status' => 'stale']);
            Log::warning('Sync is stale, marking as expired', [
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

            Log::info('Processing outbound product sync', [
                'tenant_id' => $syncQueue->tenant_id,
                'sync_queue_id' => $syncQueue->id,
                'product_id' => $syncQueue->syncable_id,
                'action' => $syncQueue->action,
            ]);

            // Send to central API
            $response = $this->sendToCentralAPI($syncQueue);

            // Mark as completed
            $syncQueue->markAsCompleted($response);

            Log::info('Outbound product sync completed', [
                'tenant_id' => $syncQueue->tenant_id,
                'sync_queue_id' => $syncQueue->id,
                'central_sync_id' => $response['sync_id'] ?? null,
            ]);
        } catch (\Exception $e) {
            Log::error('Outbound product sync failed', [
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

                Log::info('Sync will be retried', [
                    'sync_queue_id' => $syncQueue->id,
                    'retry_count' => $syncQueue->retry_count,
                    'next_retry_at' => $syncQueue->next_retry_at,
                ]);

                // Release lock for retry
                $syncQueue->releaseLock();

                // Dispatch retry job
                ProcessOutboundProductSync::dispatch($syncQueue->id)
                    ->delay($syncQueue->next_retry_at)
                    ->onQueue('sync-high');
            } else {
                Log::error('Max retries reached, sync failed permanently', [
                    'sync_queue_id' => $syncQueue->id,
                    'retry_count' => $syncQueue->retry_count,
                ]);

                // TODO: Notify merchant of failed sync
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
     * Send sync data to central API
     */
    protected function sendToCentralAPI(SyncQueueOutbound $syncQueue): array
    {
        $centralApiUrl = config('services.central_api.url') . '/api/v1/central/sync/inbound/product';
        $apiToken = config('services.central_api.token');

        Log::debug('Sending sync to central API', [
            'url' => $centralApiUrl,
            'sync_queue_id' => $syncQueue->id,
        ]);

        $response = Http::timeout(60)
            ->retry(2, 100) // Retry twice with 100ms delay
            ->withHeaders([
                'Accept' => 'application/json',
                'X-Tenant-ID' => $syncQueue->tenant_id,
                'X-Sync-Queue-ID' => $syncQueue->id,
                'X-Idempotency-Key' => $syncQueue->idempotency_key,
            ])
            ->withToken($apiToken)
            ->post($centralApiUrl, [
                'tenant_id' => $syncQueue->tenant_id,
                'action' => $syncQueue->action,
                'priority' => $syncQueue->priority,
                'payload' => $syncQueue->payload,
                'metadata' => $syncQueue->metadata,
                'idempotency_key' => $syncQueue->idempotency_key,
            ]);

        if (!$response->successful()) {
            throw new \RuntimeException(
                "Central API request failed: {$response->status()} - {$response->body()}"
            );
        }

        $responseData = $response->json();

        if (!isset($responseData['success']) || !$responseData['success']) {
            throw new \RuntimeException(
                "Central API returned error: " . ($responseData['message'] ?? 'Unknown error')
            );
        }

        return $responseData['data'] ?? [];
    }

    /**
     * Get error code from exception
     */
    protected function getErrorCode(\Throwable $e): string
    {
        if ($e instanceof \Illuminate\Http\Client\ConnectionException) {
            return 'NETWORK_ERROR';
        }

        if ($e instanceof \Illuminate\Http\Client\RequestException) {
            return 'API_ERROR';
        }

        if (str_contains($e->getMessage(), 'timeout')) {
            return 'TIMEOUT';
        }

        return 'UNKNOWN_ERROR';
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessOutboundProductSync job failed permanently', [
            'sync_queue_id' => $this->syncQueueId,
            'error' => $exception->getMessage(),
        ]);

        $syncQueue = SyncQueueOutbound::find($this->syncQueueId);
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
