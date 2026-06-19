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

class ProcessOutboundDeliveryZoneSync implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;
    public int $tries = 3;
    public int $maxExceptions = 3;
    public array $backoff = [60, 300, 900];

    public function __construct(
        public int $syncQueueId
    ) {}

    public function handle(): void
    {
        $syncQueue = SyncQueueOutbound::find($this->syncQueueId);

        if (! $syncQueue) {
            Log::error('SyncQueueOutbound record not found', [
                'sync_queue_id' => $this->syncQueueId,
            ]);

            return;
        }

        if ($syncQueue->status === 'completed') {
            Log::info('Delivery zone sync already completed, skipping', [
                'sync_queue_id' => $syncQueue->id,
            ]);

            return;
        }

        if ($syncQueue->isStale()) {
            $syncQueue->update(['status' => 'stale']);
            Log::warning('Delivery zone sync is stale, marking as expired', [
                'sync_queue_id' => $syncQueue->id,
                'expires_at'    => $syncQueue->expires_at,
            ]);

            return;
        }

        $workerId = getmypid();
        if (! $syncQueue->acquireLock($workerId)) {
            Log::info('Could not acquire lock for delivery zone sync, another worker processing', [
                'sync_queue_id' => $syncQueue->id,
            ]);

            return;
        }

        try {
            $syncQueue->markAsProcessing();

            Log::info('Processing outbound delivery zone sync', [
                'tenant_id'     => $syncQueue->tenant_id,
                'sync_queue_id' => $syncQueue->id,
                'zone_id'       => $syncQueue->syncable_id,
                'action'        => $syncQueue->action,
            ]);

            $response = $this->sendToCentralApi($syncQueue);

            // Mark completed with central's sync_queue_inbound_id for reference.
            // Record remains as 'completed' until the central ACK updates central_record_id.
            $syncQueue->markAsCompleted($response);

            Log::info('Outbound delivery zone sync delivered to central', [
                'tenant_id'      => $syncQueue->tenant_id,
                'sync_queue_id'  => $syncQueue->id,
                'central_sync_id' => $response['sync_id'] ?? null,
            ]);
        } catch (\Exception $e) {
            Log::error('Outbound delivery zone sync failed', [
                'tenant_id'     => $syncQueue->tenant_id,
                'sync_queue_id' => $syncQueue->id,
                'error'         => $e->getMessage(),
                'trace'         => $e->getTraceAsString(),
            ]);

            $syncQueue->markAsFailed(
                errorMessage: $e->getMessage(),
                errorCode: $this->getErrorCode($e),
                errorDetails: [
                    'exception' => get_class($e),
                    'file'      => $e->getFile(),
                    'line'      => $e->getLine(),
                ]
            );

            if ($syncQueue->canRetry()) {
                $syncQueue->incrementRetry();

                Log::info('Delivery zone sync will be retried', [
                    'sync_queue_id' => $syncQueue->id,
                    'retry_count'   => $syncQueue->retry_count,
                    'next_retry_at' => $syncQueue->next_retry_at,
                ]);

                $syncQueue->releaseLock();

                ProcessOutboundDeliveryZoneSync::dispatch($syncQueue->id)
                    ->delay($syncQueue->next_retry_at)
                    ->onQueue('sync-high');
            } else {
                Log::error('Max retries reached for delivery zone sync, failed permanently', [
                    'sync_queue_id' => $syncQueue->id,
                    'retry_count'   => $syncQueue->retry_count,
                ]);
            }

            throw $e;
        } finally {
            if ($syncQueue->lock_token) {
                $syncQueue->releaseLock();
            }
        }
    }

    protected function sendToCentralApi(SyncQueueOutbound $syncQueue): array
    {
        $centralApiUrl = config('services.central_api.url') . '/api/v1/central/sync/inbound/delivery-zone';
        $apiToken = config('services.central_api.token');

        Log::debug('Sending delivery zone sync to central API', [
            'url'           => $centralApiUrl,
            'sync_queue_id' => $syncQueue->id,
        ]);

        $response = Http::timeout(60)
            ->retry(2, 100)
            ->withHeaders([
                'Accept'            => 'application/json',
                'X-Tenant-ID'       => $syncQueue->tenant_id,
                'X-Sync-Queue-ID'   => $syncQueue->id,
                'X-Idempotency-Key' => $syncQueue->idempotency_key,
            ])
            ->withToken($apiToken)
            ->post($centralApiUrl, [
                'tenant_id'               => $syncQueue->tenant_id,
                'action'                  => $syncQueue->action,
                'priority'                => $syncQueue->priority,
                'payload'                 => $syncQueue->payload,
                'metadata'                => $syncQueue->metadata,
                'idempotency_key'         => $syncQueue->idempotency_key,
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException(
                "Central API request failed: {$response->status()} - {$response->body()}"
            );
        }

        $responseData = $response->json();

        if (! isset($responseData['success']) || ! $responseData['success']) {
            throw new \RuntimeException(
                'Central API returned error: ' . ($responseData['message'] ?? 'Unknown error')
            );
        }

        return $responseData['data'] ?? [];
    }

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

    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessOutboundDeliveryZoneSync job failed permanently', [
            'sync_queue_id' => $this->syncQueueId,
            'error'         => $exception->getMessage(),
        ]);

        $syncQueue = SyncQueueOutbound::find($this->syncQueueId);
        if ($syncQueue) {
            $syncQueue->markAsFailed(
                errorMessage: 'Job failed permanently: ' . $exception->getMessage(),
                errorCode: 'JOB_FAILED',
                errorDetails: [
                    'exception' => get_class($exception),
                    'trace'     => $exception->getTraceAsString(),
                ]
            );
        }
    }
}
