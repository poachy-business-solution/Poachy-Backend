<?php

namespace App\Listeners\Tenant;

use App\Events\Tenant\ProductMarketplaceSyncRequested;
use App\Jobs\Tenant\ProcessOutboundProductSync;
use App\Models\Tenant\SyncQueueOutbound;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EnqueueProductMarketplaceSync implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * The name of the queue the job should be sent to.
     */
    public $queue = 'sync-high';

    /**
     * Handle the event.
     */
    public function handle(ProductMarketplaceSyncRequested $event): void
    {
        try {
            DB::beginTransaction();

            $dto = $event->productDTO;
            $action = $event->action;
            $priority = $event->priority;

            // Generate idempotency key
            $idempotencyKey = $dto->generateIdempotencyKey($action);

            // Check if already queued
            $existingSync = SyncQueueOutbound::where('idempotency_key', $idempotencyKey)
                ->whereIn('status', ['pending', 'queued', 'processing'])
                ->first();

            if ($existingSync) {
                Log::info('Product sync already queued, skipping duplicate', [
                    'tenant_id' => tenant()->id,
                    'product_id' => $dto->productId,
                    'idempotency_key' => $idempotencyKey,
                    'existing_sync_id' => $existingSync->id,
                ]);

                DB::commit();
                return;
            }

            // Create sync queue record
            $syncQueue = SyncQueueOutbound::create([
                'tenant_id' => tenant()->id,
                'syncable_type' => 'Product',
                'syncable_id' => $dto->productId,
                'tenant_record_uuid' => $dto->productUuid,
                'action' => $action,
                'payload' => $dto->toArray(),
                'changes' => null, // For 'create' action, no changes to track
                'metadata' => $dto->metadata,
                'priority' => $priority,
                'scheduled_at' => now(),
                'expires_at' => now()->addHours(24), // Stale after 24 hours
                'status' => 'pending',
                'retry_count' => 0,
                'max_retries' => 3,
                'backoff_strategy' => 'exponential',
                'idempotency_key' => $idempotencyKey,
                'payload_hash' => hash('sha256', json_encode($dto->toArray())),
                'created_by' => Auth::id(),
            ]);

            DB::commit();

            Log::info('Product marketplace sync enqueued', [
                'tenant_id' => tenant()->id,
                'product_id' => $dto->productId,
                'product_name' => $dto->name,
                'sync_queue_id' => $syncQueue->id,
                'action' => $action,
                'priority' => $priority,
            ]);

            // Dispatch job to process sync
            ProcessOutboundProductSync::dispatch($syncQueue->id)
                ->onQueue('sync-high');
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to enqueue product marketplace sync', [
                'tenant_id' => tenant()->id,
                'product_id' => $event->productDTO->productId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Re-throw to mark job as failed
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(ProductMarketplaceSyncRequested $event, \Throwable $exception): void
    {
        Log::error('ProductMarketplaceSync listener failed', [
            'tenant_id' => tenant()->id,
            'product_id' => $event->productDTO->productId,
            'error' => $exception->getMessage(),
        ]);
    }
}
