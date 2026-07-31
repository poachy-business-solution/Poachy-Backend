<?php

namespace App\Http\Controllers\Api\Tenant\Sync;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Sync\DeliveryZoneSyncAckRequest;
use App\Http\Requests\Tenant\Sync\InventoryCountSyncAckRequest;
use App\Http\Requests\Tenant\Sync\ReviewResponseSyncAckRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant\ProductReview;
use App\Models\Tenant\SyncQueueOutbound;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class TenantSyncAckController extends Controller
{
    /**
     * Receive delivery zone sync ACK from central.
     * Updates the tenant's outbound sync queue record with the final processing result.
     */
    public function receiveDeliveryZoneAck(DeliveryZoneSyncAckRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $syncQueue = SyncQueueOutbound::find($validated['outbound_sync_queue_id']);

        if (! $syncQueue) {
            Log::warning('Delivery zone ACK received but outbound sync queue record not found', [
                'outbound_sync_queue_id' => $validated['outbound_sync_queue_id'],
                'tenant_id'              => tenant()->id,
            ]);

            return ApiResponse::notFound('Sync queue record not found');
        }

        if ($validated['status'] === 'completed') {
            $syncQueue->update([
                'central_record_id' => $validated['central_zone_id'],
                'central_table'     => 'tenant_delivery_zones',
                'sync_response'     => array_merge($syncQueue->sync_response ?? [], [
                    'ack_status'      => 'completed',
                    'central_zone_id' => $validated['central_zone_id'],
                    'acked_at'        => now()->toISOString(),
                ]),
            ]);

            Log::info('Delivery zone sync ACK received — central processing completed', [
                'tenant_id'              => tenant()->id,
                'outbound_sync_queue_id' => $syncQueue->id,
                'central_zone_id'        => $validated['central_zone_id'],
            ]);
        } else {
            $syncQueue->markAsFailed(
                errorMessage: $validated['reason'] ?? 'Central processing failed',
                errorCode: 'CENTRAL_PROCESSING_FAILED',
                errorDetails: [
                    'ack_status' => 'failed',
                    'reason'     => $validated['reason'],
                    'acked_at'   => now()->toISOString(),
                ]
            );

            Log::warning('Delivery zone sync ACK received — central processing failed', [
                'tenant_id'              => tenant()->id,
                'outbound_sync_queue_id' => $syncQueue->id,
                'reason'                 => $validated['reason'],
            ]);
        }

        return ApiResponse::success('Delivery zone sync acknowledgment received', [
            'outbound_sync_queue_id' => $syncQueue->id,
            'status'                 => $validated['status'],
        ]);
    }

    /**
     * Receive inventory count sync ACK from central.
     * Updates the tenant's outbound sync queue record with the final processing result.
     */
    public function receiveInventoryCountAck(InventoryCountSyncAckRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $syncQueue = SyncQueueOutbound::find($validated['outbound_sync_queue_id']);

        if (! $syncQueue) {
            Log::warning('Inventory count ACK received but outbound sync queue record not found', [
                'outbound_sync_queue_id' => $validated['outbound_sync_queue_id'],
                'tenant_id'              => tenant()->id,
            ]);

            return ApiResponse::notFound('Sync queue record not found');
        }

        if ($validated['status'] === 'completed') {
            $syncQueue->update([
                'central_record_id' => $validated['central_product_id'] ?? null,
                'central_table'     => 'marketplace_products',
                'sync_response'     => array_merge($syncQueue->sync_response ?? [], [
                    'ack_status'          => 'completed',
                    'central_product_id'  => $validated['central_product_id'] ?? null,
                    'acked_at'            => now()->toISOString(),
                ]),
            ]);

            Log::info('Inventory count sync ACK received — central processing completed', [
                'tenant_id'              => tenant()->id,
                'outbound_sync_queue_id' => $syncQueue->id,
                'central_product_id'     => $validated['central_product_id'] ?? null,
            ]);
        } else {
            $syncQueue->markAsFailed(
                errorMessage: $validated['reason'] ?? 'Central processing failed',
                errorCode: 'CENTRAL_PROCESSING_FAILED',
                errorDetails: [
                    'ack_status' => 'failed',
                    'reason'     => $validated['reason'],
                    'acked_at'   => now()->toISOString(),
                ]
            );

            Log::warning('Inventory count sync ACK received — central processing failed', [
                'tenant_id'              => tenant()->id,
                'outbound_sync_queue_id' => $syncQueue->id,
                'reason'                 => $validated['reason'],
            ]);
        }

        return ApiResponse::success('Inventory count sync acknowledgment received', [
            'outbound_sync_queue_id' => $syncQueue->id,
            'status'                 => $validated['status'],
        ]);
    }

    /**
     * Receive review response sync ACK from central.
     * Updates the tenant's outbound sync queue record, and — unlike the other ACK
     * handlers — also updates the local ProductReview.response_sync_status, since
     * that field is what the merchant-facing UI reads to show sync state.
     */
    public function receiveReviewResponseAck(ReviewResponseSyncAckRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $syncQueue = SyncQueueOutbound::find($validated['outbound_sync_queue_id']);

        if (! $syncQueue) {
            Log::warning('Review response ACK received but outbound sync queue record not found', [
                'outbound_sync_queue_id' => $validated['outbound_sync_queue_id'],
                'tenant_id'              => tenant()->id,
            ]);

            return ApiResponse::notFound('Sync queue record not found');
        }

        $localReviewId = $syncQueue->payload['metadata']['local_review_id'] ?? null;
        $review = $localReviewId ? ProductReview::find($localReviewId) : null;

        if ($validated['status'] === 'completed') {
            $syncQueue->update([
                'central_record_id' => $syncQueue->syncable_id,
                'central_table'     => 'product_reviews',
                'sync_response'     => array_merge($syncQueue->sync_response ?? [], [
                    'ack_status' => 'completed',
                    'acked_at'   => now()->toISOString(),
                ]),
            ]);

            $review?->update(['response_sync_status' => 'synced']);

            Log::info('Review response sync ACK received — central processing completed', [
                'tenant_id'              => tenant()->id,
                'outbound_sync_queue_id' => $syncQueue->id,
                'local_review_id'        => $localReviewId,
            ]);
        } else {
            $syncQueue->markAsFailed(
                errorMessage: $validated['reason'] ?? 'Central processing failed',
                errorCode: 'CENTRAL_PROCESSING_FAILED',
                errorDetails: [
                    'ack_status' => 'failed',
                    'reason'     => $validated['reason'],
                    'acked_at'   => now()->toISOString(),
                ]
            );

            $review?->update(['response_sync_status' => 'failed']);

            Log::warning('Review response sync ACK received — central processing failed', [
                'tenant_id'              => tenant()->id,
                'outbound_sync_queue_id' => $syncQueue->id,
                'reason'                 => $validated['reason'],
            ]);
        }

        return ApiResponse::success('Review response sync acknowledgment received', [
            'outbound_sync_queue_id' => $syncQueue->id,
            'status'                 => $validated['status'],
        ]);
    }
}
