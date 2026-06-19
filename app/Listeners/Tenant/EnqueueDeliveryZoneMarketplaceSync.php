<?php

namespace App\Listeners\Tenant;

use App\Events\Tenant\DeliveryZoneMarketplaceSyncRequested;
use App\Jobs\Tenant\ProcessOutboundDeliveryZoneSync;
use App\Models\Tenant\SyncQueueOutbound;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EnqueueDeliveryZoneMarketplaceSync implements ShouldQueue
{
    use InteractsWithQueue;

    public $queue = 'sync-high';

    public bool $afterCommit = true;

    public function handle(DeliveryZoneMarketplaceSyncRequested $event): void
    {
        try {
            DB::beginTransaction();

            $dto = $event->zoneDTO;
            $action = $event->action;
            $priority = $event->priority;

            $idempotencyKey = $dto->generateIdempotencyKey($action);

            // Skip if already queued
            $existingSync = SyncQueueOutbound::where('idempotency_key', $idempotencyKey)
                ->whereIn('status', ['pending', 'queued', 'processing'])
                ->first();

            if ($existingSync) {
                Log::info('Delivery zone sync already queued, skipping duplicate', [
                    'tenant_id'         => tenant()->id,
                    'zone_id'           => $dto->zoneId,
                    'idempotency_key'   => $idempotencyKey,
                    'existing_sync_id'  => $existingSync->id,
                ]);

                DB::commit();

                return;
            }

            $syncQueue = SyncQueueOutbound::create([
                'tenant_id'          => tenant()->id,
                'syncable_type'      => 'DeliveryZone',
                'syncable_id'        => $dto->zoneId,
                'action'             => $action,
                'payload'            => $dto->toArray(),
                'changes'            => null,
                'metadata'           => [
                    'timestamp' => now()->toISOString(),
                    'source'    => 'delivery_zone_observer',
                ],
                'priority'           => $priority,
                'scheduled_at'       => now(),
                'expires_at'         => now()->addHours(24),
                'status'             => 'pending',
                'retry_count'        => 0,
                'max_retries'        => 3,
                'backoff_strategy'   => 'exponential',
                'idempotency_key'    => $idempotencyKey,
                'payload_hash'       => hash('sha256', json_encode($dto->toArray())),
                'created_by'         => Auth::id(),
            ]);

            DB::commit();

            Log::info('Delivery zone marketplace sync enqueued', [
                'tenant_id'    => tenant()->id,
                'zone_id'      => $dto->zoneId,
                'zone_name'    => $dto->zoneName,
                'sync_queue_id' => $syncQueue->id,
                'action'       => $action,
                'priority'     => $priority,
            ]);

            ProcessOutboundDeliveryZoneSync::dispatch($syncQueue->id)
                ->onQueue('sync-high');
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            DB::rollBack();

            Log::info('Delivery zone sync already queued by concurrent process, skipping', [
                'tenant_id' => tenant()->id,
                'zone_id'   => $event->zoneDTO->zoneId,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to enqueue delivery zone marketplace sync', [
                'tenant_id' => tenant()->id,
                'zone_id'   => $event->zoneDTO->zoneId,
                'error'     => $e->getMessage(),
                'trace'     => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    public function failed(DeliveryZoneMarketplaceSyncRequested $event, \Throwable $exception): void
    {
        Log::error('EnqueueDeliveryZoneMarketplaceSync listener failed', [
            'tenant_id' => tenant()->id,
            'zone_id'   => $event->zoneDTO->zoneId,
            'error'     => $exception->getMessage(),
        ]);
    }
}
