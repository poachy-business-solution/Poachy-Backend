<?php

namespace App\Http\Controllers\Api\Central\Marketplace;

use App\Enums\Central\ReviewVoteType;
use App\Helpers\CustomerHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Review\StoreReviewVoteRequest;
use App\Http\Responses\ApiResponse;
use App\Services\Central\Marketplace\ReviewVoteService;
use Illuminate\Http\JsonResponse;

class ReviewVoteController extends Controller
{
    public function __construct(
        private readonly ReviewVoteService $voteService,
    ) {}

    /**
     * @OA\Post(
     *     path="/api/v1/central/marketplace/reviews/{reviewType}/{id}/vote",
     *     summary="Cast a helpful/not-helpful vote on a review",
     *     description="Allows authenticated customers to vote on product or merchant reviews as helpful or not helpful. Customers cannot vote on their own reviews. The vote is recorded and updates the review's helpful_count or not_helpful_count. If the customer changes their vote, the previous vote is updated.",
     *     operationId="voteOnReview",
     *     tags={"Central - Reviews - Votes"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="reviewType",
     *         in="path",
     *         description="Type of review (product or merchant)",
     *         required=true,
     *         @OA\Schema(
     *             type="string",
     *             enum={"product", "merchant"},
     *             example="product"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Review ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Vote type",
     *         @OA\JsonContent(
     *             required={"vote_type"},
     *             @OA\Property(
     *                 property="vote_type",
     *                 type="string",
     *                 description="Type of vote to cast",
     *                 enum={"helpful", "not_helpful"},
     *                 example="helpful"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Vote recorded successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Vote recorded successfully."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="vote_type", type="string", example="helpful")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-02-19T21:13:18.229730Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="85d9aa99-a955-45d2-985f-e65376ea3655"),
     *                 @OA\Property(property="tenant_id", type="string", nullable=true, example=null),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Cannot vote on own review or validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="You cannot vote on your own review."),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-02-19T21:12:45.942639Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="ea143e56-a07f-4ccf-8374-815bbb0553c0"),
     *                 @OA\Property(property="tenant_id", type="string", nullable=true, example=null),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     )
     * )
     */
    public function store(StoreReviewVoteRequest $request, string $reviewType, int $id): JsonResponse
    {
        try {
            $customer = CustomerHelper::getAuthenticatedCustomerOrFail();
            $voteType = ReviewVoteType::from($request->validated('vote_type'));

            $vote = $this->voteService->vote($customer, $voteType, $reviewType, $id);

            return ApiResponse::success('Vote recorded successfully.', [
                'vote_type' => $vote->vote_type->value,
            ]);
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), null, 422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return ApiResponse::notFound('Review not found.');
        } catch (\Exception $e) {
            return ApiResponse::serverError('Failed to record vote: ' . $e->getMessage());
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/central/marketplace/reviews/{reviewType}/{id}/vote",
     *     summary="Remove vote from a review",
     *     description="Removes the authenticated customer's vote from a product or merchant review. The vote is deleted and the review's helpful_count or not_helpful_count is decremented accordingly.",
     *     operationId="deleteVoteOnReview",
     *     tags={"Central - Reviews - Votes"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="reviewType",
     *         in="path",
     *         description="Type of review (product or merchant)",
     *         required=true,
     *         @OA\Schema(
     *             type="string",
     *             enum={"product", "merchant"},
     *             example="merchant"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Review ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Vote deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Vote deleted successfully"),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-02-19T21:18:40.236223Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="16eae462-3fbe-4ee1-99e5-5ec67d00eda8"),
     *                 @OA\Property(property="tenant_id", type="string", nullable=true, example=null),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Vote not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Vote not found."),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_id", type="string", nullable=true),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true)
     *             )
     *         )
     *     )
     * )
    */
    public function destroy(string $reviewType, int $id): JsonResponse
    {
        try {
            $customer = CustomerHelper::getAuthenticatedCustomerOrFail();

            $this->voteService->removeVote($customer, $reviewType, $id);

            return ApiResponse::success('Vote deleted successfully');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return ApiResponse::notFound('Review not found.');
        } catch (\Exception $e) {
            return ApiResponse::serverError('Failed to remove vote: ' . $e->getMessage());
        }
    }
}
