<?php

namespace App\Http\Controllers\Api\Central\Sync;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Sync\InboundProductSyncRequest;
use App\Http\Responses\ApiResponse;
use App\Jobs\Central\ProcessInboundProductSync;
use App\Models\SyncQueueInbound;
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
}
