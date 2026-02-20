<?php

namespace App\Listeners\Tenant;

use App\Events\Tenant\MerchantReviewResponseCreated;
use App\Jobs\Tenant\ProcessOutboundReviewResponseSync;
use App\Models\Tenant\SyncQueueOutbound;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EnqueueMerchantReviewResponseSync implements ShouldQueue
{
    public $queue = 'sync-high';

    public function handle(MerchantReviewResponseCreated $event): void
    {
        $dto = $event->responseDTO;
        $idempotencyKey = $dto->generateIdempotencyKey($event->action);

        // Check for duplicate
        $existing = SyncQueueOutbound::where('idempotency_key', $idempotencyKey)
            ->whereIn('status', ['pending', 'queued', 'processing'])
            ->first();

        if ($existing) {
            Log::info('Review response sync already queued', [
                'tenant_id'          => tenant()->id,
                'central_review_id'  => $dto->centralReviewId,
            ]);

            return;
        }

        DB::beginTransaction();
        try {
            $syncQueue = SyncQueueOutbound::create([
                'tenant_id'       => tenant()->id,
                'syncable_type'   => 'ReviewResponse',
                'syncable_id'     => $dto->centralReviewId,
                'action'          => $event->action,
                'payload'         => $dto->toArray(),
                'priority'        => $event->priority,
                'scheduled_at'    => now(),
                'expires_at'      => now()->addHours(24),
                'status'          => 'pending',
                'retry_count'     => 0,
                'max_retries'     => 3,
                'idempotency_key' => $idempotencyKey,
                'payload_hash'    => hash('sha256', json_encode($dto->toArray())),
            ]);

            DB::commit();

            ProcessOutboundReviewResponseSync::dispatch($syncQueue->id)
                ->onQueue('sync-high');

            Log::info('Review response sync enqueued', [
                'tenant_id'      => tenant()->id,
                'sync_queue_id'  => $syncQueue->id,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to enqueue review response sync', [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
