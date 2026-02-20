<?php

namespace App\Listeners\Central;

use App\Events\Central\ProductReviewApproved;
use App\Jobs\Central\ProcessOutboundApprovedReviewSync;
use App\Models\SyncQueueOutbound;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EnqueueApprovedReviewSync implements ShouldQueue
{
    public $queue = 'sync-normal';

    public function handle(ProductReviewApproved $event): void
    {
        $review = $event->review;
        $tenantId = $review->product->tenant_id;

        $idempotencyKey = md5("approved_review_{$review->id}_tenant_{$tenantId}");

        // Check for duplicate
        $existing = SyncQueueOutbound::on('central')
            ->where('idempotency_key', $idempotencyKey)
            ->whereIn('status', ['pending', 'queued', 'processing'])
            ->first();

        if ($existing) {
            Log::info('Approved review sync already queued', [
                'review_id' => $review->id,
                'tenant_id' => $tenantId,
            ]);

            return;
        }

        DB::connection('central')->beginTransaction();
        try {
            $payload = [
                'review_id'             => $review->id,
                'tenant_id'             => $tenantId,
                'product_id'            => $review->product->tenant_product_id,
                'product_name'          => $review->product->name,
                'product_sku'           => $review->product->sku,
                'customer_name'         => $review->customer->user?->name ?? 'Customer',
                'rating'                => (float) $review->rating,
                'title'                 => $review->title,
                'review_text'           => $review->review_text,
                'review_images'         => $review->review_images ?? [],
                'is_verified_purchase'  => (bool) $review->is_verified_purchase,
                'reviewed_at'           => $review->created_at->toISOString(),
            ];

            $syncQueue = SyncQueueOutbound::on('central')->create([
                'tenant_id'       => $tenantId,
                'syncable_type'   => 'ApprovedReview',
                'syncable_id'     => $review->id,
                'action'          => 'create',
                'payload'         => $payload,
                'priority'        => 2,
                'scheduled_at'    => now(),
                'expires_at'      => now()->addHours(48),
                'status'          => 'pending',
                'retry_count'     => 0,
                'max_retries'     => 3,
                'idempotency_key' => $idempotencyKey,
                'payload_hash'    => hash('sha256', json_encode($payload)),
            ]);

            DB::connection('central')->commit();

            ProcessOutboundApprovedReviewSync::dispatch($syncQueue->id)
                ->onQueue('sync-normal');

            Log::info('Approved review sync enqueued', [
                'review_id'     => $review->id,
                'tenant_id'     => $tenantId,
                'sync_queue_id' => $syncQueue->id,
            ]);
        } catch (\Exception $e) {
            DB::connection('central')->rollBack();
            Log::error('Failed to enqueue approved review sync', [
                'review_id' => $review->id,
                'tenant_id' => $tenantId,
                'error'     => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
