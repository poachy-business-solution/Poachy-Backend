<?php

namespace App\Jobs\Tenant;

use App\Models\Tenant\SyncQueueOutbound;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProcessOutboundReviewResponseSync implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $timeout = 120;
    public int $tries = 3;
    public $backoff = [60, 300, 900];

    public function __construct(public int $syncQueueId) {}

    public function handle(): void
    {
        $syncQueue = SyncQueueOutbound::find($this->syncQueueId);

        if (! $syncQueue || $syncQueue->status === 'completed') {
            return;
        }

        $workerId = getmypid();
        if (! $syncQueue->acquireLock($workerId)) {
            return;
        }

        try {
            $syncQueue->markAsProcessing();

            $response = Http::timeout(60)
                ->retry(2, 100)
                ->withHeaders([
                    'X-Tenant-ID'        => $syncQueue->tenant_id,
                    'X-Sync-Queue-ID'    => $syncQueue->id,
                    'X-Idempotency-Key'  => $syncQueue->idempotency_key,
                ])
                ->withToken(config('services.central_api.token'))
                ->post(
                    config('services.central_api.url') . '/api/v1/central/sync/inbound/product-review-response',
                    $syncQueue->payload
                );

            if (! $response->successful()) {
                throw new \RuntimeException("Central API request failed: {$response->status()} - {$response->body()}");
            }

            $syncQueue->markAsCompleted($response->json('data') ?? []);

            Log::info('Outbound review response sync completed', [
                'sync_queue_id' => $syncQueue->id,
            ]);
        } catch (\Exception $e) {
            $syncQueue->markAsFailed($e->getMessage());

            if ($syncQueue->canRetry()) {
                $syncQueue->incrementRetry();
                ProcessOutboundReviewResponseSync::dispatch($syncQueue->id)
                    ->delay($syncQueue->next_retry_at)
                    ->onQueue('sync-high');
            }

            throw $e;
        } finally {
            $syncQueue->releaseLock();
        }
    }
}
