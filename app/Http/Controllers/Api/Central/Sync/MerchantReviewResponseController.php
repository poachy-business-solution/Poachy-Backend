<?php

namespace App\Http\Controllers\Api\Central\Sync;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Sync\InboundReviewResponseSyncRequest;
use App\Http\Responses\ApiResponse;
use App\Jobs\Central\ProcessInboundReviewResponseSync;
use App\Models\SyncQueueInbound;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MerchantReviewResponseController extends Controller
{
    public function store(InboundReviewResponseSyncRequest $request): JsonResponse
    {
        try {
            DB::connection('central')->beginTransaction();

            $validated = $request->validated();
            $idempotencyKey = md5(
                $validated['tenant_id'] . 'review_response' . $validated['review_id'] . hash('sha256', $validated['response_text'])
            );

            // Check for duplicate
            $existing = SyncQueueInbound::where('idempotency_key', $idempotencyKey)->first();

            if ($existing) {
                DB::connection('central')->commit();

                return ApiResponse::success('Review response sync already received', [
                    'sync_id'      => $existing->id,
                    'status'       => $existing->status,
                    'is_duplicate' => true,
                ]);
            }

            $syncQueue = SyncQueueInbound::create([
                'tenant_id'          => $validated['tenant_id'],
                'syncable_type'      => 'ReviewResponse',
                'tenant_syncable_id' => $validated['review_id'],
                'action'             => 'create',
                'payload'            => $validated,
                'priority'           => 2,
                'received_at'        => now(),
                'scheduled_at'       => now(),
                'expires_at'         => now()->addHours(24),
                'status'             => 'pending',
                'retry_count'        => 0,
                'max_retries'        => 3,
                'idempotency_key'    => $idempotencyKey,
                'payload_hash'       => hash('sha256', json_encode($validated)),
            ]);

            DB::connection('central')->commit();

            ProcessInboundReviewResponseSync::dispatch($syncQueue->id)->onQueue('sync-high');

            return ApiResponse::success('Review response queued for processing', [
                'sync_id' => $syncQueue->id,
                'status'  => 'pending',
            ], 202);
        } catch (\Exception $e) {
            DB::connection('central')->rollBack();
            Log::error('Failed to receive review response sync', ['error' => $e->getMessage()]);

            return ApiResponse::serverError('Failed to process review response sync');
        }
    }
}
