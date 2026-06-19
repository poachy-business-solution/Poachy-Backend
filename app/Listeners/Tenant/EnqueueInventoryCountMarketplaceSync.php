<?php

namespace App\Listeners\Tenant;

use App\Events\Tenant\InventoryCountMarketplaceSyncRequested;
use App\Jobs\Tenant\ProcessOutboundInventoryCountSync;
use App\Models\Tenant\SyncQueueOutbound;
use Illuminate\Contracts\Queue\ShouldQueue;
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

            $idempotencyKey = $dto->generateIdempotencyKey($action);

            // Check if already queued or recently completed (idempotency).
            // We include 'completed' within the expiry window because the outbound job
            // can finish (mark status=completed) before a duplicate listener execution runs,
            // which would otherwise pass the in-flight check and hit a unique constraint.
            $existingSync = SyncQueueOutbound::where('idempotency_key', $idempotencyKey)
                ->where(function ($query) {
                    $query->whereIn('status', ['pending', 'queued', 'processing'])
                        ->orWhere(function ($q) {
                            $q->where('status', 'completed')
                                ->where('expires_at', '>', now());
                        });
                })
                ->first();

            if ($existingSync) {
                Log::info('InventoryCount sync skipped — already ' . $existingSync->status . ' for this payload', [
                    'tenant_id' => $dto->tenantId,
                    'product_id' => $dto->productId,
                    'variant_id' => $dto->variantId,
                    'idempotency_key' => $idempotencyKey,
                    'existing_sync_id' => $existingSync->id,
                    'existing_status' => $existingSync->status,
                ]);

                DB::commit();

                return;
            }

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
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
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
