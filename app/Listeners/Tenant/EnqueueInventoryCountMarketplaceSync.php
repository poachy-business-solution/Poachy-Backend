<?php

namespace App\Listeners\Tenant;

use App\Events\Tenant\InventoryCountMarketplaceSyncRequested;
use App\Jobs\Tenant\ProcessOutboundInventoryCountSync;
use App\Models\Tenant\SyncQueueOutbound;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EnqueueInventoryCountMarketplaceSync implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * The name of the queue the job should be sent to.
     */
    public $queue = 'sync-high';

    /**
     * Handle the event.
     */
    public function handle(InventoryCountMarketplaceSyncRequested $event): void
    {
        try {
            DB::beginTransaction();

            $dto = $event->inventoryDTO;
            $action = $event->action;
            $priority = $event->priority;

            $stateKey = $dto->generateIdempotencyKey($action);

            // Idempotency against the LATEST sync of this product/variant only. The state key is a
            // content hash, so "available 1" after a hold and "available 1" after the release look the
            // same — but if a different count went out in between, central is stale and must hear the
            // repeat. The row key carries the previous entry's id so a repeat after an intervening
            // state is a new unique key, while two concurrent identical events still collide (race safe).
            $latest = SyncQueueOutbound::where('tenant_id', $dto->tenantId)
                ->where('syncable_type', 'InventoryCount')
                ->where('syncable_id', $dto->productId)
                ->where('payload->variant_id', $dto->variantId)
                ->orderByDesc('id')
                ->first();

            $latestIsSameState = $latest !== null
                && str_starts_with($latest->idempotency_key, $stateKey)
                && (in_array($latest->status, ['pending', 'queued', 'processing'], true)
                    || ($latest->status === 'completed' && $latest->expires_at !== null && $latest->expires_at->isFuture()));

            if ($latestIsSameState) {
                Log::info('InventoryCount sync skipped — already '.$latest->status.' for this payload', [
                    'tenant_id' => $dto->tenantId,
                    'product_id' => $dto->productId,
                    'variant_id' => $dto->variantId,
                    'idempotency_key' => $latest->idempotency_key,
                    'existing_sync_id' => $latest->id,
                    'existing_status' => $latest->status,
                ]);

                DB::commit();

                return;
            }

            $idempotencyKey = $stateKey.':'.($latest?->id ?? 0);

            $syncQueue = SyncQueueOutbound::create([
                'tenant_id' => $dto->tenantId,
                'syncable_type' => 'InventoryCount',
                'syncable_id' => $dto->productId,
                'action' => $action,
                'payload' => $dto->toArray(),
                'changes' => null,
                'metadata' => null,
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

            Log::info('InventoryCount marketplace sync enqueued', [
                'tenant_id' => $dto->tenantId,
                'product_id' => $dto->productId,
                'variant_id' => $dto->variantId,
                'sync_queue_id' => $syncQueue->id,
                'action' => $action,
                'priority' => $priority,
            ]);

            ProcessOutboundInventoryCountSync::dispatch($syncQueue->id)
                ->onQueue('sync-high');
        } catch (UniqueConstraintViolationException $e) {
            // A duplicate execution slipped past the idempotency check before either
            // committed (true race condition). The other execution already owns this key.
            DB::rollBack();

            Log::info('InventoryCount sync skipped — idempotency key already taken (race condition)', [
                'tenant_id' => $event->inventoryDTO->tenantId,
                'product_id' => $event->inventoryDTO->productId,
                'variant_id' => $event->inventoryDTO->variantId,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to enqueue InventoryCount marketplace sync', [
                'tenant_id' => $event->inventoryDTO->tenantId,
                'product_id' => $event->inventoryDTO->productId,
                'variant_id' => $event->inventoryDTO->variantId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(InventoryCountMarketplaceSyncRequested $event, \Throwable $exception): void
    {
        Log::error('EnqueueInventoryCountMarketplaceSync listener failed', [
            'tenant_id' => $event->inventoryDTO->tenantId,
            'product_id' => $event->inventoryDTO->productId,
            'variant_id' => $event->inventoryDTO->variantId,
            'error' => $exception->getMessage(),
        ]);
    }
}
