<?php

namespace App\Listeners\Tenant;

use App\Events\Tenant\MarketplaceSaleFulfillmentSyncRequested;
use App\Jobs\Tenant\ProcessOutboundMarketplaceFulfillmentSync;
use App\Models\Tenant\SyncQueueOutbound;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EnqueueMarketplaceFulfillmentSync implements ShouldQueue
{
    use InteractsWithQueue;

    public $queue = 'sync-high';

    public bool $afterCommit = true;

    public function handle(MarketplaceSaleFulfillmentSyncRequested $event): void
    {
        try {
            DB::beginTransaction();

            $dto = $event->fulfillmentDTO;
            $action = $event->action;
            $priority = $event->priority;

            $idempotencyKey = $dto->generateIdempotencyKey($action);

            // Skip if already queued
            $existingSync = SyncQueueOutbound::where('idempotency_key', $idempotencyKey)
                ->whereIn('status', ['pending', 'queued', 'processing'])
                ->first();

            if ($existingSync) {
                Log::info('Marketplace fulfillment sync already queued, skipping duplicate', [
                    'tenant_id' => tenant()->id,
                    'sale_id' => $dto->saleId,
                    'idempotency_key' => $idempotencyKey,
                    'existing_sync_id' => $existingSync->id,
                ]);

                DB::commit();

                return;
            }

            $syncQueue = SyncQueueOutbound::create([
                'tenant_id' => tenant()->id,
                'syncable_type' => 'MarketplaceFulfillment',
                'syncable_id' => $dto->saleId,
                'action' => $action,
                'payload' => $dto->toArray(),
                'changes' => null,
                'metadata' => [
                    'timestamp' => now()->toISOString(),
                    'source' => 'marketplace_sale_service',
                ],
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

            Log::info('Marketplace fulfillment sync enqueued', [
                'tenant_id' => tenant()->id,
                'sale_id' => $dto->saleId,
                'fulfillment_status' => $dto->fulfillmentStatus,
                'sync_queue_id' => $syncQueue->id,
                'action' => $action,
                'priority' => $priority,
            ]);

            ProcessOutboundMarketplaceFulfillmentSync::dispatch($syncQueue->id)
                ->onQueue('sync-high');
        } catch (UniqueConstraintViolationException $e) {
            DB::rollBack();

            Log::info('Marketplace fulfillment sync already queued by concurrent process, skipping', [
                'tenant_id' => tenant()->id,
                'sale_id' => $event->fulfillmentDTO->saleId,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to enqueue marketplace fulfillment sync', [
                'tenant_id' => tenant()->id,
                'sale_id' => $event->fulfillmentDTO->saleId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    public function failed(MarketplaceSaleFulfillmentSyncRequested $event, \Throwable $exception): void
    {
        Log::error('EnqueueMarketplaceFulfillmentSync listener failed', [
            'tenant_id' => tenant()->id,
            'sale_id' => $event->fulfillmentDTO->saleId,
            'error' => $exception->getMessage(),
        ]);
    }
}
