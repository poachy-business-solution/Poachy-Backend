<?php

namespace App\Jobs\Central;

use App\Models\ProductReview;
use App\Models\SyncQueueInbound;
use App\Services\Central\Marketplace\ProductReviewService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
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

            Log::info('Inbound review response sync completed', [
                'sync_queue_id' => $syncQueue->id,
                'review_id'     => $review->id,
            ]);
        } catch (\Exception $e) {
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
        }
    }
}
