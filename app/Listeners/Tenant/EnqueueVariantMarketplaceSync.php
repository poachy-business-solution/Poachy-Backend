<?php

namespace App\Listeners\Tenant;

use App\Events\Tenant\VariantMarketplaceSyncRequested;
use App\Jobs\Tenant\ProcessOutboundVariantSync;
use App\Models\Tenant\SyncQueueOutbound;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EnqueueVariantMarketplaceSync implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * The name of the queue the job should be sent to.
     */
    public $queue = 'sync-high';

    /**
     * Handle the event.
     */
    public function handle(VariantMarketplaceSyncRequested $event): void
    {
        try {
            DB::beginTransaction();

            $dto = $event->variantDTO;
            $action = $event->action;
            $priority = $event->priority;

            // Generate idempotency key
            $idempotencyKey = $dto->generateIdempotencyKey($action);

            // Check if already queued
            $existingSync = SyncQueueOutbound::where('idempotency_key', $idempotencyKey)
                ->whereIn('status', ['pending', 'queued', 'processing'])
                ->first();

            if ($existingSync) {
                Log::info('Variant sync already queued, skipping duplicate', [
                    'tenant_id' => tenant()->id,
                    'variant_id' => $dto->variantId,
                    'idempotency_key' => $idempotencyKey,
                    'existing_sync_id' => $existingSync->id,
                ]);

                DB::commit();
                return;
            }

            // Create sync queue record
            $syncQueue = SyncQueueOutbound::create([
                'tenant_id' => tenant()->id,
                'syncable_type' => 'ProductVariant',
                'syncable_id' => $dto->variantId,
                'tenant_record_uuid' => $dto->productUuid,
                'action' => $action,
                'payload' => $dto->toArray(),
                'changes' => null,
                'metadata' => $dto->metadata,
                'priority' => $priority,
                'scheduled_at' => now(),
                'expires_at' => now()->addHours(24),
                'status' => 'pending',
                'retry_count' => 0,
                'max_retries' => 3,
                'backoff_strategy' => 'exponential',
                'idempotency_key' => $idempotencyKey,
                'payload_hash' => hash('sha256', json_encode($dto->toArray())),
                'created_by' => Auth::id(),
            ]);

            DB::commit();

            Log::info('Variant marketplace sync enqueued', [
                'tenant_id' => tenant()->id,
                'variant_id' => $dto->variantId,
                'variant_name' => $dto->variantName,
                'sync_queue_id' => $syncQueue->id,
                'action' => $action,
                'priority' => $priority,
            ]);

            // Dispatch job to process sync
            ProcessOutboundVariantSync::dispatch($syncQueue->id)
                ->onQueue('sync-high');
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to enqueue variant marketplace sync', [
                'tenant_id' => tenant()->id,
                'variant_id' => $event->variantDTO->variantId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(VariantMarketplaceSyncRequested $event, \Throwable $exception): void
    {
        Log::error('VariantMarketplaceSync listener failed', [
            'tenant_id' => tenant()->id,
            'variant_id' => $event->variantDTO->variantId,
            'error' => $exception->getMessage(),
        ]);
    }
}
