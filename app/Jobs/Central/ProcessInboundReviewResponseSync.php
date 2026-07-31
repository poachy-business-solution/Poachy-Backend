<?php

namespace App\Jobs\Central;

use App\Models\ProductReview;
use App\Models\SyncQueueInbound;
use App\Models\Tenant;
use App\Services\Central\Marketplace\ProductReviewService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProcessInboundReviewResponseSync implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $timeout = 120;
    public int $tries = 3;
    public $backoff = [60, 300, 900];

    public function __construct(public int $syncQueueId) {}

    public function handle(ProductReviewService $reviewService): void
    {
        $syncQueue = SyncQueueInbound::find($this->syncQueueId);

        if (! $syncQueue || $syncQueue->status === 'completed') {
            return;
        }

        $workerId = getmypid();
        if (! $syncQueue->acquireLock($workerId)) {
            return;
        }

        $ackStatus = 'failed';
        $ackReason = null;

        try {
            $syncQueue->markAsProcessing();

            $payload = $syncQueue->payload;
            $review = ProductReview::findOrFail($payload['review_id']);

            // Verify ownership
            if ($review->product->tenant_id !== $payload['tenant_id']) {
                throw new \InvalidArgumentException('Tenant does not own this product/review');
            }

            // Add or update response
            if ($review->merchant_response) {
                $review = $reviewService->updateMerchantResponse($review, $payload['tenant_id'], $payload['response_text']);
            } else {
                $review = $reviewService->addMerchantResponse($review, $payload['tenant_id'], $payload['response_text']);
            }

            $syncQueue->markAsCompleted($review->id, 'product_reviews');
            $ackStatus = 'completed';

            Log::info('Inbound review response sync completed', [
                'sync_queue_id' => $syncQueue->id,
                'review_id'     => $review->id,
            ]);
        } catch (\Exception $e) {
            $ackReason = $e->getMessage();

            $syncQueue->markAsFailed($e->getMessage());

            if ($syncQueue->canRetry()) {
                $syncQueue->incrementRetry();
                ProcessInboundReviewResponseSync::dispatch($syncQueue->id)
                    ->delay($syncQueue->next_retry_at)
                    ->onQueue('sync-high');
            }

            throw $e;
        } finally {
            $syncQueue->releaseLock();

            // Always ACK the tenant on final success or permanent failure — not on a
            // transient failure that's about to be retried.
            if ($ackStatus === 'completed' || ! $syncQueue->canRetry()) {
                $this->ackTenant($syncQueue, $ackStatus, $ackReason);
            }
        }
    }

    /**
     * ACK the tenant once we have a final result (success or permanent failure).
     */
    private function ackTenant(SyncQueueInbound $syncQueue, string $status, ?string $reason): void
    {
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
                Log::warning('Tenant not found for review response ACK', [
                    'tenant_id'     => $syncQueue->tenant_id,
                    'sync_queue_id' => $syncQueue->id,
                ]);

                return;
            }

            $domain = $tenant->domains()->first();

            if (! $domain) {
                Log::warning('No domain found for tenant review response ACK', [
                    'tenant_id'     => $syncQueue->tenant_id,
                    'sync_queue_id' => $syncQueue->id,
                ]);

                return;
            }

            $scheme = app()->environment('local') ? 'http://' : 'https://';
            $tenantUrl = $scheme . $domain->domain;

            Http::withToken(config('services.tenant_api.token'))
                ->timeout(30)
                ->retry(2, 100)
                ->post($tenantUrl . '/api/v1/tenant/sync/inbound/review-response-ack', [
                    'outbound_sync_queue_id' => (int) $tenantOutboundSyncId,
                    'status'                 => $status,
                    'reason'                 => $reason,
                ]);

            Log::info('Review response ACK sent to tenant', [
                'tenant_id'              => $syncQueue->tenant_id,
                'sync_queue_id'          => $syncQueue->id,
                'outbound_sync_queue_id' => $tenantOutboundSyncId,
                'status'                 => $status,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send review response ACK to tenant', [
                'tenant_id'     => $syncQueue->tenant_id,
                'sync_queue_id' => $syncQueue->id,
                'error'         => $e->getMessage(),
            ]);
            // Don't rethrow — ACK failure should not fail the sync job
        }
    }
}
