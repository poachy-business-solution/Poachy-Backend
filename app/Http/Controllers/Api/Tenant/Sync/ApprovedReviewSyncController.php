<?php

namespace App\Http\Controllers\Api\Tenant\Sync;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\InboundApprovedReviewSyncRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant\ProductReview;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class ApprovedReviewSyncController extends Controller
{
    public function store(InboundApprovedReviewSyncRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();

            // Directly create/update review (idempotent via unique central_review_id)
            $review = ProductReview::updateOrCreate(
                ['central_review_id' => $validated['review_id']],
                [
                    'product_id'            => $validated['product_id'],
                    'product_name'          => $validated['product_name'],
                    'product_sku'           => $validated['product_sku'] ?? null,
                    'customer_name'         => $validated['customer_name'],
                    'rating'                => $validated['rating'],
                    'title'                 => $validated['title'] ?? null,
                    'review_text'           => $validated['review_text'] ?? null,
                    'review_images'         => $validated['review_images'] ?? null,
                    'is_verified_purchase'  => $validated['is_verified_purchase'] ?? false,
                    'status'                => 'approved',
                    'reviewed_at'           => $validated['reviewed_at'],
                ]
            );

            Log::info('Approved review synced to tenant', [
                'tenant_id'         => tenant()->id,
                'central_review_id' => $validated['review_id'],
                'local_review_id'   => $review->id,
            ]);

            return ApiResponse::success('Review synced successfully', [
                'local_review_id' => $review->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to sync approved review', [
                'tenant_id' => tenant()->id,
                'error'     => $e->getMessage(),
            ]);

            return ApiResponse::serverError('Failed to sync review');
        }
    }
}
