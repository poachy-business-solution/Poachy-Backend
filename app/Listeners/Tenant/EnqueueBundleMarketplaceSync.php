<?php

namespace App\Listeners\Tenant;

use App\Events\Tenant\BundleMarketplaceSyncRequested;
use App\Jobs\Tenant\ProcessOutboundBundleSync;
use App\Models\Tenant\SyncQueueOutbound;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EnqueueBundleMarketplaceSync implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * The name of the queue the job should be sent to.
     */
    public $queue = 'sync-high';

    /**
     * Handle the event.
     */
    public function handle(BundleMarketplaceSyncRequested $event): void
    {
        try {
            DB::beginTransaction();

            $dto = $event->bundleDTO;
            $action = $event->action;
            $priority = $event->priority;

            // Generate idempotency key
            $idempotencyKey = $dto->generateIdempotencyKey($action);

            // Check if already queued
            $existingSync = SyncQueueOutbound::where('idempotency_key', $idempotencyKey)
                ->whereIn('status', ['pending', 'queued', 'processing'])
                ->first();

            if ($existingSync) {
                Log::info('Bundle sync already queued, skipping duplicate', [
                    'tenant_id' => tenant()->id,
                    'bundle_id' => $dto->bundleId,
                    'idempotency_key' => $idempotencyKey,
                    'existing_sync_id' => $existingSync->id,
                ]);

                DB::commit();
                return;
            }

            // Create sync queue record
            $syncQueue = SyncQueueOutbound::create([
                'tenant_id' => tenant()->id,
                'syncable_type' => 'ProductBundle',
                'syncable_id' => $dto->bundleId,
                'tenant_record_uuid' => $dto->bundleUuid,
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

            Log::info('Bundle marketplace sync enqueued', [
                'tenant_id' => tenant()->id,
                'bundle_id' => $dto->bundleId,
                'bundle_name' => $dto->bundleName,
                'sync_queue_id' => $syncQueue->id,
                'action' => $action,
                'priority' => $priority,
            ]);

            // Dispatch job to process sync
            ProcessOutboundBundleSync::dispatch($syncQueue->id)
                ->onQueue('sync-high');
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to enqueue bundle marketplace sync', [
                'tenant_id' => tenant()->id,
                'bundle_id' => $event->bundleDTO->bundleId,
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
    public function failed(BundleMarketplaceSyncRequested $event, \Throwable $exception): void
    {
        Log::error('BundleMarketplaceSync listener failed', [
            'tenant_id' => tenant()->id,
            'bundle_id' => $event->bundleDTO->bundleId,
            'error' => $exception->getMessage(),
        ]);
    }
}
