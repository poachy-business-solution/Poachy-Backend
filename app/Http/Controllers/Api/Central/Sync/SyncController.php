<?php

namespace App\Http\Controllers\Api\Central\Sync;

use App\Enums\Central\OrderFulfillmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Sync\InboundBundleSyncRequest;
use App\Http\Requests\Central\Sync\InboundInventoryCountSyncRequest;
use App\Http\Requests\Central\Sync\InboundOrderConfirmationRequest;
use App\Http\Requests\Central\Sync\InboundOrderStatusUpdateRequest;
use App\Http\Requests\Central\Sync\InboundOutboundSyncAckRequest;
use App\Http\Requests\Central\Sync\InboundProductSyncRequest;
use App\Http\Requests\Central\Sync\InboundVariantSyncRequest;
use App\Http\Responses\ApiResponse;
use App\Jobs\Central\ProcessInboundBundleSync;
use App\Jobs\Central\ProcessInboundInventoryCountSync;
use App\Jobs\Central\ProcessInboundProductSync;
use App\Jobs\Central\ProcessInboundVariantSync;
use App\Jobs\Central\ProcessTenantOrderConfirmation;
use App\Models\MarketplaceOrder;
use App\Models\SyncQueueInbound;
use App\Models\SyncQueueOutbound;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncController extends Controller
{
    /**
     * Receive product sync request from tenant
     * 
     * @group Central Sync API
     * @authenticated
     */
    public function receiveProductSync(InboundProductSyncRequest $request)
    {
        try {
            DB::connection('central')->beginTransaction();

            $tenantId = $request->input('tenant_id');
            $action = $request->input('action');
            $priority = $request->input('priority');
            $payload = $request->input('payload');
            $metadata = $request->input('metadata', []);
            $idempotencyKey = $request->input('idempotency_key');

            // Check for duplicate using idempotency key
            $existingSync = SyncQueueInbound::where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existingSync) {
                Log::info('Duplicate sync request received, returning existing sync', [
                    'tenant_id' => $tenantId,
                    'idempotency_key' => $idempotencyKey,
                    'existing_sync_id' => $existingSync->id,
                    'status' => $existingSync->status,
                ]);

                DB::connection('central')->commit();

                return ApiResponse::success(
                    message: 'Sync request already received',
                    data: [
                        'sync_id' => $existingSync->id,
                        'status' => $existingSync->status,
                        'is_duplicate' => true,
                    ],
                    status: 200
                );
            }

            // Create inbound sync queue record
            $syncQueue = SyncQueueInbound::create([
                'tenant_id' => $tenantId,
                'syncable_type' => 'Product',
                'tenant_syncable_id' => $payload['product_id'],
                'tenant_record_uuid' => $payload['product_uuid'],
                'action' => $action,
                'payload' => $payload,
                'changes' => null,
                'metadata' => array_merge($metadata, [
                    'received_from_ip' => $request->ip(),
                    'received_at_server' => now()->toISOString(),
                    'sync_queue_id_from_tenant' => $request->header('X-Sync-Queue-ID'),
                ]),
                'priority' => $priority,
                'received_at' => now(),
                'scheduled_at' => now(),
                'expires_at' => now()->addHours(24),
                'status' => 'pending',
                'retry_count' => 0,
                'max_retries' => 3,
                'backoff_strategy' => 'exponential',
                'idempotency_key' => $idempotencyKey,
                'payload_hash' => hash('sha256', json_encode($payload)),
            ]);

            DB::connection('central')->commit();

            Log::info('Product sync request received and queued', [
                'tenant_id' => $tenantId,
                'sync_queue_id' => $syncQueue->id,
                'product_id' => $payload['product_id'],
                'product_name' => $payload['name'],
                'action' => $action,
            ]);

            // Dispatch job to process sync
            ProcessInboundProductSync::dispatch($syncQueue->id)
                ->onQueue('sync-high');

            return ApiResponse::success(
                message: 'Product sync request received and queued for processing',
                data: [
                    'sync_id' => $syncQueue->id,
                    'status' => $syncQueue->status,
                    'estimated_processing_time' => '1-2 minutes',
                ],
                status: 202 // Accepted
            );
        } catch (\Exception $e) {
            DB::connection('central')->rollBack();

            Log::error('Failed to receive product sync request', [
                'tenant_id' => $request->input('tenant_id'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiResponse::serverError(
                message: 'Failed to process sync request',
                errors: config('app.debug') ? ['error' => $e->getMessage()] : null
            );
        }
    }

    /**
     * Receive variant sync request from tenant
     *
     * @group Central Sync API
     * @authenticated
     */
    public function receiveVariantSync(InboundVariantSyncRequest $request)
    {
        try {
            DB::connection('central')->beginTransaction();

            $tenantId = $request->input('tenant_id');
            $action = $request->input('action');
            $priority = $request->input('priority');
            $payload = $request->input('payload');
            $metadata = $request->input('metadata', []);
            $idempotencyKey = $request->input('idempotency_key');

            // Check for duplicate using idempotency key
            $existingSync = SyncQueueInbound::where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existingSync) {
                Log::info('Duplicate variant sync request received, returning existing sync', [
                    'tenant_id' => $tenantId,
                    'idempotency_key' => $idempotencyKey,
                    'existing_sync_id' => $existingSync->id,
                    'status' => $existingSync->status,
                ]);

                DB::connection('central')->commit();

                return ApiResponse::success(
                    message: 'Variant sync request already received',
                    data: [
                        'sync_id' => $existingSync->id,
                        'status' => $existingSync->status,
                        'is_duplicate' => true,
                    ],
                    status: 200
                );
            }

            // Create inbound sync queue record
            $syncQueue = SyncQueueInbound::create([
                'tenant_id' => $tenantId,
                'syncable_type' => 'ProductVariant',
                'tenant_syncable_id' => $payload['variant_id'],
                'tenant_record_uuid' => $payload['product_uuid'],
                'action' => $action,
                'payload' => $payload,
                'changes' => null,
                'metadata' => array_merge($metadata, [
                    'received_from_ip' => $request->ip(),
                    'received_at_server' => now()->toISOString(),
                    'sync_queue_id_from_tenant' => $request->header('X-Sync-Queue-ID'),
                ]),
                'priority' => $priority,
                'received_at' => now(),
                'scheduled_at' => now(),
                'expires_at' => now()->addHours(24),
                'status' => 'pending',
                'retry_count' => 0,
                'max_retries' => 3,
                'backoff_strategy' => 'exponential',
                'idempotency_key' => $idempotencyKey,
                'payload_hash' => hash('sha256', json_encode($payload)),
            ]);

            DB::connection('central')->commit();

            Log::info('Variant sync request received and queued', [
                'tenant_id' => $tenantId,
                'sync_queue_id' => $syncQueue->id,
                'variant_id' => $payload['variant_id'],
                'variant_name' => $payload['variant_name'],
                'action' => $action,
            ]);

            // Dispatch job to process sync
            ProcessInboundVariantSync::dispatch($syncQueue->id)
                ->onQueue('sync-high');

            return ApiResponse::success(
                message: 'Variant sync request received and queued for processing',
                data: [
                    'sync_id' => $syncQueue->id,
                    'status' => $syncQueue->status,
                    'estimated_processing_time' => '1-2 minutes',
                ],
                status: 202 // Accepted
            );
        } catch (\Exception $e) {
            DB::connection('central')->rollBack();

            Log::error('Failed to receive variant sync request', [
                'tenant_id' => $request->input('tenant_id'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiResponse::serverError(
                message: 'Failed to process variant sync request',
                errors: config('app.debug') ? ['error' => $e->getMessage()] : null
            );
        }
    }

    /**
     * Receive bundle sync request from tenant
     *
     * @group Central Sync API
     * @authenticated
     */
    public function receiveBundleSync(InboundBundleSyncRequest $request)
    {
        try {
            DB::connection('central')->beginTransaction();

            $tenantId = $request->input('tenant_id');
            $action = $request->input('action');
            $priority = $request->input('priority');
            $payload = $request->input('payload');
            $metadata = $request->input('metadata', []);
            $idempotencyKey = $request->input('idempotency_key');

            // Check for duplicate using idempotency key
            $existingSync = SyncQueueInbound::where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existingSync) {
                Log::info('Duplicate bundle sync request received, returning existing sync', [
                    'tenant_id' => $tenantId,
                    'idempotency_key' => $idempotencyKey,
                    'existing_sync_id' => $existingSync->id,
                    'status' => $existingSync->status,
                ]);

                DB::connection('central')->commit();

                return ApiResponse::success(
                    message: 'Bundle sync request already received',
                    data: [
                        'sync_id' => $existingSync->id,
                        'status' => $existingSync->status,
                        'is_duplicate' => true,
                    ],
                    status: 200
                );
            }

            // Create inbound sync queue record
            $syncQueue = SyncQueueInbound::create([
                'tenant_id' => $tenantId,
                'syncable_type' => 'ProductBundle',
                'tenant_syncable_id' => $payload['bundle_id'],
                'tenant_record_uuid' => $payload['bundle_uuid'],
                'action' => $action,
                'payload' => $payload,
                'changes' => null,
                'metadata' => array_merge($metadata, [
                    'received_from_ip' => $request->ip(),
                    'received_at_server' => now()->toISOString(),
                    'sync_queue_id_from_tenant' => $request->header('X-Sync-Queue-ID'),
                ]),
                'priority' => $priority,
                'received_at' => now(),
                'scheduled_at' => now(),
                'expires_at' => now()->addHours(24),
                'status' => 'pending',
                'retry_count' => 0,
                'max_retries' => 3,
                'backoff_strategy' => 'exponential',
                'idempotency_key' => $idempotencyKey,
                'payload_hash' => hash('sha256', json_encode($payload)),
            ]);

            DB::connection('central')->commit();

            Log::info('Bundle sync request received and queued', [
                'tenant_id' => $tenantId,
                'sync_queue_id' => $syncQueue->id,
                'bundle_id' => $payload['bundle_id'],
                'bundle_name' => $payload['bundle_name'],
                'action' => $action,
            ]);

            // Dispatch job to process sync
            ProcessInboundBundleSync::dispatch($syncQueue->id)
                ->onQueue('sync-high');

            return ApiResponse::success(
                message: 'Bundle sync request received and queued for processing',
                data: [
                    'sync_id' => $syncQueue->id,
                    'status' => $syncQueue->status,
                    'estimated_processing_time' => '1-2 minutes',
                ],
                status: 202 // Accepted
            );
        } catch (\Exception $e) {
            DB::connection('central')->rollBack();

            Log::error('Failed to receive bundle sync request', [
                'tenant_id' => $request->input('tenant_id'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiResponse::serverError(
                message: 'Failed to process bundle sync request',
                errors: config('app.debug') ? ['error' => $e->getMessage()] : null
            );
        }
    }

    /**
     * Receive inventory count sync request from tenant
     *
     * @group Central Sync API
     * @authenticated
     */
    public function receiveInventoryCountSync(InboundInventoryCountSyncRequest $request): JsonResponse
    {
        try {
            DB::connection('central')->beginTransaction();

            $tenantId = $request->input('tenant_id');
            $action = $request->input('action');
            $priority = $request->input('priority');
            $payload = $request->input('payload');
            $idempotencyKey = $request->input('idempotency_key');

            // Check for duplicate using idempotency key
            $existingSync = SyncQueueInbound::where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existingSync) {
                Log::info('Duplicate inventory count sync request received, returning existing sync', [
                    'tenant_id' => $tenantId,
                    'idempotency_key' => $idempotencyKey,
                    'existing_sync_id' => $existingSync->id,
                    'status' => $existingSync->status,
                ]);

                DB::connection('central')->commit();

                return ApiResponse::success(
                    message: 'Inventory count sync request already received',
                    data: [
                        'sync_id' => $existingSync->id,
                        'status' => $existingSync->status,
                        'is_duplicate' => true,
                    ],
                    status: 200
                );
            }

            $syncQueue = SyncQueueInbound::create([
                'tenant_id' => $tenantId,
                'syncable_type' => 'InventoryCount',
                'tenant_syncable_id' => $payload['product_id'],
                'action' => $action,
                'payload' => $payload,
                'changes' => null,
                'metadata' => [
                    'received_from_ip' => $request->ip(),
                    'received_at_server' => now()->toISOString(),
                    'sync_queue_id_from_tenant' => $request->header('X-Sync-Queue-ID'),
                ],
                'priority' => $priority,
                'received_at' => now(),
                'scheduled_at' => now(),
                'expires_at' => now()->addHours(24),
                'status' => 'pending',
                'retry_count' => 0,
                'max_retries' => 3,
                'backoff_strategy' => 'exponential',
                'idempotency_key' => $idempotencyKey,
                'payload_hash' => hash('sha256', json_encode($payload)),
            ]);

            DB::connection('central')->commit();

            Log::info('Inventory count sync request received and queued', [
                'tenant_id' => $tenantId,
                'sync_queue_id' => $syncQueue->id,
                'product_id' => $payload['product_id'],
                'variant_id' => $payload['variant_id'] ?? null,
                'entity_type' => $payload['entity_type'],
                'action' => $action,
            ]);

            ProcessInboundInventoryCountSync::dispatch($syncQueue->id)
                ->onQueue('sync-high');

            return ApiResponse::success(
                message: 'Inventory count sync request received and queued for processing',
                data: [
                    'sync_id' => $syncQueue->id,
                    'status' => $syncQueue->status,
                    'estimated_processing_time' => '1-2 minutes',
                ],
                status: 202
            );
        } catch (\Exception $e) {
            DB::connection('central')->rollBack();

            Log::error('Failed to receive inventory count sync request', [
                'tenant_id' => $request->input('tenant_id'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiResponse::serverError(
                message: 'Failed to process inventory count sync request',
                errors: config('app.debug') ? ['error' => $e->getMessage()] : null
            );
        }
    }

    /**
     * Get sync status
     *
     * @group Central Sync API
     * @authenticated
     */
    public function getSyncStatus(string $syncId)
    {
        $syncQueue = SyncQueueInbound::find($syncId);

        if (!$syncQueue) {
            return ApiResponse::notFound('Sync record not found');
        }

        return ApiResponse::success(
            message: 'Sync status retrieved',
            data: [
                'sync_id' => $syncQueue->id,
                'tenant_id' => $syncQueue->tenant_id,
                'status' => $syncQueue->status,
                'action' => $syncQueue->action,
                'priority' => $syncQueue->priority,
                'retry_count' => $syncQueue->retry_count,
                'created_at' => $syncQueue->created_at,
                'processing_started_at' => $syncQueue->processing_started_at,
                'completed_at' => $syncQueue->completed_at,
                'failed_at' => $syncQueue->failed_at,
                'error_message' => $syncQueue->error_message,
                'central_record_id' => $syncQueue->central_record_id,
            ]
        );
    }

    /**
     * Receive order confirmation/failure from tenant.
     */
    public function receiveOrderConfirmation(InboundOrderConfirmationRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $orderId = $validated['order_id'];

        $order = MarketplaceOrder::on('central')->find($orderId);

        if (! $order) {
            return ApiResponse::notFound('Order not found');
        }

        if ($order->tenant_id !== $validated['tenant_id']) {
            return ApiResponse::error('Tenant ID mismatch', null, 403);
        }

        ProcessTenantOrderConfirmation::dispatch(
            $orderId,
            $validated['status'],
            $validated['reason'] ?? null,
            $validated['tenant_response'] ?? [],
        )->onQueue('sync-high');

        if (! empty($validated['outbound_sync_id'])) {
            $this->closeOutboundSyncEntry(
                outboundSyncId: $validated['outbound_sync_id'],
                tenantId:       $validated['tenant_id'],
                status:         $validated['status'] === 'confirmed' ? 'completed' : 'failed',
                reason:         $validated['reason'] ?? null,
            );
        }

        Log::info('Order confirmation received from tenant', [
            'order_id'         => $orderId,
            'tenant_id'        => $validated['tenant_id'],
            'status'           => $validated['status'],
            'outbound_sync_id' => $validated['outbound_sync_id'] ?? null,
        ]);

        return ApiResponse::success(
            message: 'Order confirmation received',
            data: ['order_id' => $orderId, 'status' => $validated['status']],
            status: 202,
        );
    }

    /**
     * Receive order fulfillment status update from tenant.
     */
    public function receiveOrderStatusUpdate(InboundOrderStatusUpdateRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $orderId = $validated['order_id'];

        $order = MarketplaceOrder::on('central')
            ->with('items')
            ->find($orderId);

        if (! $order) {
            return ApiResponse::notFound('Order not found');
        }

        if ($order->tenant_id !== $validated['tenant_id']) {
            return ApiResponse::error('Tenant ID mismatch', null, 403);
        }

        $fulfillmentStatus = OrderFulfillmentStatus::from($validated['fulfillment_status']);

        $order->items()->update(['fulfillment_status' => $fulfillmentStatus]);

        if ($validated['notes'] ?? null) {
            $order->update(['merchant_notes' => $validated['notes']]);
        }

        Log::info('Order fulfillment status updated by tenant', [
            'order_id'           => $orderId,
            'tenant_id'          => $validated['tenant_id'],
            'fulfillment_status' => $fulfillmentStatus->value,
        ]);

        return ApiResponse::success(
            message: 'Order status updated',
            data: ['order_id' => $orderId, 'fulfillment_status' => $fulfillmentStatus->value],
        );
    }

    /**
     * Receive generic outbound sync acknowledgment from tenant (payment and cancellation flows).
     */
    public function acknowledgeOutboundSync(InboundOutboundSyncAckRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $this->closeOutboundSyncEntry(
            outboundSyncId: $validated['outbound_sync_id'],
            tenantId:       $validated['tenant_id'],
            status:         $validated['status'],
            reason:         $validated['reason'] ?? null,
            tenantRecordId: $validated['tenant_record_id'] ?? null,
            tenantTable:    $validated['tenant_table'] ?? null,
        );

        Log::info('Outbound sync ack received from tenant', [
            'outbound_sync_id' => $validated['outbound_sync_id'],
            'tenant_id'        => $validated['tenant_id'],
            'status'           => $validated['status'],
        ]);

        return ApiResponse::success(
            message: 'Outbound sync acknowledgment received',
            data: ['outbound_sync_id' => $validated['outbound_sync_id'], 'status' => $validated['status']],
            status: 202,
        );
    }

    private function closeOutboundSyncEntry(
        int $outboundSyncId,
        string $tenantId,
        string $status,
        ?string $reason = null,
        ?int $tenantRecordId = null,
        ?string $tenantTable = null,
    ): void {
        $entry = SyncQueueOutbound::on('central')->find($outboundSyncId);

        if (! $entry) {
            Log::warning('closeOutboundSyncEntry: entry not found', ['outbound_sync_id' => $outboundSyncId]);

            return;
        }

        if ($entry->tenant_id !== $tenantId) {
            Log::warning('closeOutboundSyncEntry: tenant_id mismatch', [
                'outbound_sync_id' => $outboundSyncId,
                'expected'         => $entry->tenant_id,
                'provided'         => $tenantId,
            ]);

            return;
        }

        // Only close entries that are currently in delivered status — prevents double-marking on retries
        if ($entry->status !== 'delivered') {
            return;
        }

        if ($status === 'completed') {
            $entry->markAsCompleted($tenantRecordId, $tenantTable);
        } else {
            $entry->markAsFailed($reason ?? 'Tenant reported failure');
        }
    }
}
