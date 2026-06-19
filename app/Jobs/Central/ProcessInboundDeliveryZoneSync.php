<?php

namespace App\Jobs\Central;

use App\DataTransferObjects\Sync\DeliveryZoneSyncDTO;
use App\Models\SyncQueueInbound;
use App\Models\Tenant;
use App\Services\Central\Sync\TenantDeliveryZoneSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProcessInboundDeliveryZoneSync implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 180;
    public int $tries = 3;
    public int $maxExceptions = 3;
    public array $backoff = [60, 300, 900];

    public function __construct(
        public int $syncQueueId
    ) {}

    public function handle(TenantDeliveryZoneSyncService $syncService): void
    {
        $syncQueue = SyncQueueInbound::find($this->syncQueueId);

        if (! $syncQueue) {
            Log::error('SyncQueueInbound record not found for delivery zone sync', [
                'sync_queue_id' => $this->syncQueueId,
            ]);

            return;
        }

        if ($syncQueue->status === 'completed') {
            Log::info('Delivery zone sync already completed, skipping', [
                'sync_queue_id' => $syncQueue->id,
            ]);

            return;
        }

        if ($syncQueue->isStale()) {
            $syncQueue->update(['status' => 'stale']);
            Log::warning('Delivery zone sync is stale, marking as expired', [
                'sync_queue_id' => $syncQueue->id,
                'expires_at'    => $syncQueue->expires_at,
            ]);

            return;
        }

        $workerId = getmypid();
        if (! $syncQueue->acquireLock($workerId)) {
            Log::info('Could not acquire lock for delivery zone inbound sync, another worker processing', [
                'sync_queue_id' => $syncQueue->id,
            ]);

            return;
        }

        $centralZoneId = null;
        $ackStatus = 'failed';
        $ackReason = null;

        try {
            $syncQueue->markAsProcessing();

            Log::info('Processing inbound delivery zone sync', [
                'tenant_id'     => $syncQueue->tenant_id,
                'sync_queue_id' => $syncQueue->id,
                'zone_id'       => $syncQueue->tenant_syncable_id,
                'action'        => $syncQueue->action,
            ]);

            $syncQueue->markAsValidating();

            $dto = DeliveryZoneSyncDTO::fromArray($syncQueue->payload);

            $syncQueue->markAsSyncing();

            $result = match ($syncQueue->action) {
                'create' => $this->handleCreate($syncService, $dto, $syncQueue),
                'update' => $this->handleUpdate($syncService, $dto, $syncQueue),
                'delete' => $this->handleDelete($syncService, $dto, $syncQueue),
                default  => throw new \InvalidArgumentException("Unknown action: {$syncQueue->action}"),
            };

            $centralZoneId = $result;
            $ackStatus = 'completed';

            Log::info('Inbound delivery zone sync completed', [
                'tenant_id'       => $syncQueue->tenant_id,
                'sync_queue_id'   => $syncQueue->id,
                'central_zone_id' => $centralZoneId,
                'action'          => $syncQueue->action,
            ]);
        } catch (\Exception $e) {
            $ackStatus = 'failed';
            $ackReason = $e->getMessage();

            Log::error('Inbound delivery zone sync failed', [
                'tenant_id'     => $syncQueue->tenant_id,
                'sync_queue_id' => $syncQueue->id,
                'error'         => $e->getMessage(),
                'trace'         => $e->getTraceAsString(),
            ]);

            $syncQueue->markAsFailed(
                errorMessage: $e->getMessage(),
                errorCode: $this->getErrorCode($e),
                errorDetails: [
                    'exception' => get_class($e),
                    'file'      => $e->getFile(),
                    'line'      => $e->getLine(),
                ]
            );

            if ($syncQueue->canRetry()) {
                $syncQueue->incrementRetry();

                Log::info('Delivery zone sync will be retried', [
                    'sync_queue_id' => $syncQueue->id,
                    'retry_count'   => $syncQueue->retry_count,
                    'next_retry_at' => $syncQueue->next_retry_at,
                ]);

                $syncQueue->releaseLock();

                ProcessInboundDeliveryZoneSync::dispatch($syncQueue->id)
                    ->delay($syncQueue->next_retry_at)
                    ->onQueue('sync-high');
            } else {
                Log::error('Max retries reached for delivery zone inbound sync, failed permanently', [
                    'sync_queue_id' => $syncQueue->id,
                    'retry_count'   => $syncQueue->retry_count,
                ]);
            }

            throw $e;
        } finally {
            if ($syncQueue->lock_token) {
                $syncQueue->releaseLock();
            }

            // Always ACK the tenant on final success or permanent failure
            if ($ackStatus === 'completed' || ! $syncQueue->canRetry()) {
                $this->ackTenant($syncQueue, $ackStatus, $centralZoneId, $ackReason);
            }
        }
    }

    private function handleCreate(
        TenantDeliveryZoneSyncService $syncService,
        DeliveryZoneSyncDTO $dto,
        SyncQueueInbound $syncQueue,
    ): ?int {
        $zone = $syncService->createDeliveryZone($dto);

        $syncQueue->markAsCompleted(
            centralRecordId: $zone->id,
            centralTable: 'tenant_delivery_zones'
        );

        return $zone->id;
    }

    private function handleUpdate(
        TenantDeliveryZoneSyncService $syncService,
        DeliveryZoneSyncDTO $dto,
        SyncQueueInbound $syncQueue,
    ): ?int {
        $zone = $syncService->updateDeliveryZone($dto);

        $syncQueue->markAsCompleted();

        return $zone->id;
    }

    private function handleDelete(
        TenantDeliveryZoneSyncService $syncService,
        DeliveryZoneSyncDTO $dto,
        SyncQueueInbound $syncQueue,
    ): ?int {
        $syncService->deleteDeliveryZone($dto);

        $syncQueue->markAsCompleted();

        return null;
    }

    /**
     * ACK the tenant once we have a final result (success or permanent failure).
     */
    private function ackTenant(
        SyncQueueInbound $syncQueue,
        string $status,
        ?int $centralZoneId,
        ?string $reason,
    ): void {
        $tenantOutboundSyncId = $syncQueue->metadata['sync_queue_id_from_tenant'] ?? null;

        if (! $tenantOutboundSyncId) {
            Log::warning('No tenant outbound sync queue ID in metadata, skipping ACK', [
                'sync_queue_id' => $syncQueue->id,
            ]);

            return;
        }

        try {
            $tenant = Tenant::on('central')->find($syncQueue->tenant_id);

            if (! $tenant) {
                Log::warning('Tenant not found for delivery zone ACK', [
                    'tenant_id'     => $syncQueue->tenant_id,
                    'sync_queue_id' => $syncQueue->id,
                ]);

                return;
            }

            $domain = $tenant->domains()->first();

            if (! $domain) {
                Log::warning('No domain found for tenant delivery zone ACK', [
                    'tenant_id'     => $syncQueue->tenant_id,
                    'sync_queue_id' => $syncQueue->id,
                ]);

                return;
            }

            $scheme = app()->environment('local') ? 'http://' : 'https://';
            $tenantUrl = $scheme . $domain->domain;

            Http::withToken(config('services.tenant_api.token'))
                ->timeout(30)
                ->retry(2, 100)
                ->post($tenantUrl . '/api/v1/tenant/sync/inbound/delivery-zone-ack', [
                    'outbound_sync_queue_id' => (int) $tenantOutboundSyncId,
                    'status'                 => $status,
                    'central_zone_id'        => $centralZoneId,
                    'reason'                 => $reason,
                ]);

            Log::info('Delivery zone ACK sent to tenant', [
                'tenant_id'               => $syncQueue->tenant_id,
                'sync_queue_id'           => $syncQueue->id,
                'outbound_sync_queue_id'  => $tenantOutboundSyncId,
                'status'                  => $status,
                'central_zone_id'         => $centralZoneId,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send delivery zone ACK to tenant', [
                'tenant_id'     => $syncQueue->tenant_id,
                'sync_queue_id' => $syncQueue->id,
                'error'         => $e->getMessage(),
            ]);
            // Don't rethrow — ACK failure should not fail the sync job
        }
    }

    protected function getErrorCode(\Throwable $e): string
    {
        if ($e instanceof \Illuminate\Validation\ValidationException) {
            return 'VALIDATION_ERROR';
        }

        if ($e instanceof \InvalidArgumentException) {
            return 'VALIDATION_ERROR';
        }

        return 'SYNC_ERROR';
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessInboundDeliveryZoneSync job failed permanently', [
            'sync_queue_id' => $this->syncQueueId,
            'error'         => $exception->getMessage(),
        ]);

        $syncQueue = SyncQueueInbound::find($this->syncQueueId);
        if ($syncQueue) {
            $syncQueue->markAsFailed(
                errorMessage: 'Job failed permanently: ' . $exception->getMessage(),
                errorCode: 'JOB_FAILED',
                errorDetails: [
                    'exception' => get_class($exception),
                    'trace'     => $exception->getTraceAsString(),
                ]
            );
        }
    }
}
