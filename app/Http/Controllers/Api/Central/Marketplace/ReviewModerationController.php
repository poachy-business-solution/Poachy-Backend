<?php

namespace App\Http\Controllers\Api\Central\Marketplace;

use App\Helpers\CustomerHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Review\FlagReviewRequest;
use App\Http\Requests\Central\Review\ModerateReviewRequest;
use App\Http\Resources\Central\Marketplace\MerchantReviewResource;
use App\Http\Resources\Central\Marketplace\ProductReviewResource;
use App\Http\Responses\ApiResponse;
use App\Models\MerchantReview;
use App\Models\ProductReview;
use App\Services\Central\Marketplace\ReviewModerationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewModerationController extends Controller
{
    public function __construct(
        private readonly ReviewModerationService $moderationService,
    ) {}

    /**
     * @OA\Get(
     *     path="/api/v1/central/marketplace/pending-reviews",
     *     summary="List pending and flagged reviews for moderation",
     *     description="Retrieves pending and flagged product and merchant reviews awaiting moderation.",
     *     operationId="listPendingReviews",
     *     tags={"Central - Admin - Moderate Reviews"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="type",
     *         in="query",
     *         description="Filter reviews by type",
     *         required=false,
     *         @OA\Schema(
     *             type="string",
     *             enum={"all", "product", "merchant"},
     *             default="all"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of results per page",
     *         required=false,
     *         @OA\Schema(type="integer", example=15)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Pending reviews retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Pending reviews retrieved successfully."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="product_reviews",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="rating", type="number", format="float", example=4),
     *                         @OA\Property(property="title", type="string", example="Excellent TV Quality"),
     *                         @OA\Property(
     *                             property="review_text",
     *                             type="string",
     *                             example="This is my second time ordering from Tech Haven and I'm impressed again. The TCL 55 4K UHD TV has amazing picture clarity, vibrant colors, and smooth performance. Delivery was timely and the packaging was secure."
     *                         ),
     *                         @OA\Property(
     *                             property="review_images",
     *                             type="array",
     *                             @OA\Items(type="string", example="reviews/products/2/hrWffhno7yGBVsOlFejV8KQiPzECDqTCXysOXCgP.jpg")
     *                         ),
     *                         @OA\Property(property="is_verified_purchase", type="boolean", example=true),
     *                         @OA\Property(property="status", type="string", example="pending"),
     *                         @OA\Property(property="helpful_count", type="integer", example=0),
     *                         @OA\Property(property="not_helpful_count", type="integer", example=0),
     *                         @OA\Property(property="merchant_response", type="string", nullable=true, example=null),
     *                         @OA\Property(property="merchant_responded_at", type="string", format="date-time", nullable=true, example=null),
     *                         @OA\Property(
     *                             property="customer",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="name", type="string", example="Richard Hensley")
     *                         ),
     *                         @OA\Property(property="rejection_reason", type="string", nullable=true, example=null),
     *                         @OA\Property(property="moderated_at", type="string", format="date-time", nullable=true, example=null),
     *                         @OA\Property(property="created_at", type="string", format="date-time", example="2026-02-19T19:26:05.000000Z"),
     *                         @OA\Property(property="updated_at", type="string", format="date-time", example="2026-02-19T19:32:21.000000Z")
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="merchant_reviews",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="overall_rating", type="number", format="float", example=4),
     *                         @OA\Property(
     *                             property="ratings",
     *                             type="object",
     *                             @OA\Property(property="product_quality", type="number", format="float", example=4),
     *                             @OA\Property(property="delivery", type="number", format="float", example=3.5),
     *                             @OA\Property(property="service", type="number", format="float", example=4)
     *                         ),
     *                         @OA\Property(
     *                             property="review_text",
     *                             type="string",
     *                             example="Great service and product quality. Delivery was on time."
     *                         ),
     *                         @OA\Property(property="status", type="string", example="pending"),
     *                         @OA\Property(property="helpful_count", type="integer", example=0),
     *                         @OA\Property(property="not_helpful_count", type="integer", example=0),
     *                         @OA\Property(
     *                             property="customer",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="name", type="string", example="Richard Hensley")
     *                         ),
     *                         @OA\Property(property="rejection_reason", type="string", nullable=true, example=null),
     *                         @OA\Property(property="moderated_at", type="string", format="date-time", nullable=true, example=null),
     *                         @OA\Property(property="created_at", type="string", format="date-time", example="2026-02-19T20:28:46.000000Z"),
     *                         @OA\Property(property="updated_at", type="string", format="date-time", example="2026-02-19T20:28:46.000000Z")
     *                     )
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-02-19T19:41:34.298273Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="d9af8a72-16c2-49b5-9110-ae4bbe1ef99b"),
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
     *         response=403,
     *         description="Forbidden - insufficient privileges",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="You do not have permission to access this resource."),
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
    public function pendingReviews(Request $request): JsonResponse
    {
        try {
            $type = $request->query('type', 'all');
            $filters = $request->only(['per_page']);

            $result = [];

            if ($type === 'all' || $type === 'product') {
                $result['product_reviews'] = ProductReviewResource::collection(
                    $this->moderationService->getPendingProductReviews($filters)
                );
            }

            if ($type === 'all' || $type === 'merchant') {
                $result['merchant_reviews'] = MerchantReviewResource::collection(
                    $this->moderationService->getPendingMerchantReviews($filters)
                );
            }

            return ApiResponse::success('Pending reviews retrieved successfully.', $result);
        } catch (\Exception $e) {
            return ApiResponse::serverError('Failed to retrieve pending reviews: ' . $e->getMessage());
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/central/marketplace/flagged-reviews",
     *     summary="List flagged reviews",
     *     description="Retrieves reviews that have been flagged by customers as inappropriate. Requires authentication and admin privileges. Supports filtering by type (all, product, merchant) and pagination. Returns review details along with flag information including who flagged it and when.",
     *     operationId="listFlaggedReviews",
     *     tags={"Central - Reviews - Flag"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="type",
     *         in="query",
     *         description="Filter reviews by type",
     *         required=false,
     *         @OA\Schema(
     *             type="string",
     *             enum={"all", "product", "merchant"},
     *             default="all"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of results per page",
     *         required=false,
     *         @OA\Schema(type="integer", example=15)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Flagged reviews retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Flagged reviews retrieved successfully."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="product_reviews",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="rating", type="number", format="float", example=4),
     *                         @OA\Property(property="title", type="string", example="Excellent TV Quality"),
     *                         @OA\Property(
     *                             property="review_text",
     *                             type="string",
     *                             example="This is my second time ordering from Tech Haven and I'm impressed again. The TCL 55 4K UHD TV has amazing picture clarity, vibrant colors, and smooth performance. Delivery was timely and the packaging was secure."
     *                         ),
     *                         @OA\Property(
     *                             property="review_images",
     *                             type="array",
     *                             @OA\Items(type="string", example="reviews/products/2/hrWffhno7yGBVsOlFejV8KQiPzECDqTCXysOXCgP.jpg")
     *                         ),
     *                         @OA\Property(property="is_verified_purchase", type="boolean", example=true),
     *                         @OA\Property(property="status", type="string", example="approved"),
     *                         @OA\Property(property="helpful_count", type="integer", example=1),
     *                         @OA\Property(property="not_helpful_count", type="integer", example=0),
     *                         @OA\Property(property="merchant_response", type="string", nullable=true, example=null),
     *                         @OA\Property(property="merchant_responded_at", type="string", format="date-time", nullable=true, example=null),
     *                         @OA\Property(
     *                             property="customer",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="name", type="string", example="Richard Hensley")
     *                         ),
     *                         @OA\Property(property="rejection_reason", type="string", nullable=true, example=null),
     *                         @OA\Property(property="moderated_at", type="string", format="date-time", example="2026-02-19T20:10:49.000000Z"),
     *                         @OA\Property(property="flags_count", type="integer", example=1),
     *                         @OA\Property(
     *                             property="flags",
     *                             type="array",
     *                             @OA\Items(
     *                                 type="object",
     *                                 @OA\Property(property="id", type="integer", example=1),
     *                                 @OA\Property(property="reason", type="string", example="Inapproriate language used"),
     *                                 @OA\Property(
     *                                     property="flagged_by",
     *                                     type="object",
     *                                     @OA\Property(property="id", type="integer", example=2),
     *                                     @OA\Property(property="name", type="string", example="Anna Paige")
     *                                 ),
     *                                 @OA\Property(property="flagged_at", type="string", format="date-time", example="2026-02-20T07:43:28.000000Z")
     *                             )
     *                         ),
     *                         @OA\Property(property="created_at", type="string", format="date-time", example="2026-02-19T19:26:05.000000Z"),
     *                         @OA\Property(property="updated_at", type="string", format="date-time", example="2026-02-19T21:13:18.000000Z")
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="merchant_reviews",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="overall_rating", type="number", format="float", example=4),
     *                         @OA\Property(
     *                             property="ratings",
     *                             type="object",
     *                             @OA\Property(property="product_quality", type="number", format="float", example=4),
     *                             @OA\Property(property="delivery", type="number", format="float", example=3.5),
     *                             @OA\Property(property="service", type="number", format="float", example=4)
     *                         ),
     *                         @OA\Property(
     *                             property="review_text",
     *                             type="string",
     *                             example="Great service and product quality. Delivery was on time."
     *                         ),
     *                         @OA\Property(property="status", type="string", example="approved"),
     *                         @OA\Property(property="helpful_count", type="integer", example=1),
     *                         @OA\Property(property="not_helpful_count", type="integer", example=0),
     *                         @OA\Property(
     *                             property="customer",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="name", type="string", example="Richard Hensley")
     *                         ),
     *                         @OA\Property(property="rejection_reason", type="string", nullable=true, example=null),
     *                         @OA\Property(property="moderated_at", type="string", format="date-time", example="2026-02-19T20:44:25.000000Z"),
     *                         @OA\Property(property="flags_count", type="integer", example=1),
     *                         @OA\Property(
     *                             property="flags",
     *                             type="array",
     *                             @OA\Items(
     *                                 type="object",
     *                                 @OA\Property(property="id", type="integer", example=2),
     *                                 @OA\Property(property="reason", type="string", example="Inapproriate language used"),
     *                                 @OA\Property(
     *                                     property="flagged_by",
     *                                     type="object",
     *                                     @OA\Property(property="id", type="integer", example=2),
     *                                     @OA\Property(property="name", type="string", example="Anna Paige")
     *                                 ),
     *                                 @OA\Property(property="flagged_at", type="string", format="date-time", example="2026-02-20T07:51:41.000000Z")
     *                             )
     *                         ),
     *                         @OA\Property(property="created_at", type="string", format="date-time", example="2026-02-19T20:28:46.000000Z"),
     *                         @OA\Property(property="updated_at", type="string", format="date-time", example="2026-02-20T07:54:24.000000Z")
     *                     )
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-02-20T10:51:36.943949Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="2e7525e2-03e8-4d72-b1a1-25c304f556f4"),
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
     *         response=403,
     *         description="Forbidden - insufficient privileges",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="You do not have permission to access this resource."),
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
    public function flaggedReviews(Request $request): JsonResponse
    {
        try {
            $type = $request->query('type', 'all');
            $filters = $request->only(['per_page']);

            $result = [];

            if ($type === 'all' || $type === 'product') {
                $result['product_reviews'] = ProductReviewResource::collection(
                    $this->moderationService->getFlaggedProductReviews($filters)
                );
            }

            if ($type === 'all' || $type === 'merchant') {
                $result['merchant_reviews'] = MerchantReviewResource::collection(
                    $this->moderationService->getFlaggedMerchantReviews($filters)
                );
            }

            return ApiResponse::success('Flagged reviews retrieved successfully.', $result);
        } catch (\Exception $e) {
            return ApiResponse::serverError('Failed to retrieve flagged reviews: ' . $e->getMessage());
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/central/marketplace/product-reviews/{id}/moderate",
     *     summary="Moderate a product review (approve/reject/flag)",
     *     description="Moderates a product review by approving, rejecting, flagging or unflagging it.",
     *     operationId="moderateProductReview",
     *     tags={"Central - Admin - Moderate Reviews"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Product Review ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Moderation action and optional rejection reason",
     *         @OA\JsonContent(
     *             required={"action"},
     *             @OA\Property(
     *                 property="action",
     *                 type="string",
     *                 description="Moderation action to perform",
     *                 enum={"approve", "reject", "flag", "dismiss_flags"},
     *                 example="approve"
     *             ),
     *             @OA\Property(
     *                 property="rejection_reason",
     *                 type="string",
     *                 description="Reason for rejection (required when action is 'reject')",
     *                 example="Review contains inappropriate language",
     *                 maxLength=500,
     *                 nullable=true
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Review moderated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Review approved successfully."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="rating", type="number", format="float", example=4),
     *                 @OA\Property(property="title", type="string", example="Excellent TV Quality"),
     *                 @OA\Property(
     *                     property="review_text",
     *                     type="string",
     *                     example="This is my second time ordering from Tech Haven and I'm impressed again. The TCL 55 4K UHD TV has amazing picture clarity, vibrant colors, and smooth performance. Delivery was timely and the packaging was secure."
     *                 ),
     *                 @OA\Property(
     *                     property="review_images",
     *                     type="array",
     *                     @OA\Items(type="string", example="reviews/products/2/hrWffhno7yGBVsOlFejV8KQiPzECDqTCXysOXCgP.jpg")
     *                 ),
     *                 @OA\Property(property="is_verified_purchase", type="boolean", example=true),
     *                 @OA\Property(property="status", type="string", example="approved"),
     *                 @OA\Property(property="helpful_count", type="integer", example=0),
     *                 @OA\Property(property="not_helpful_count", type="integer", example=0),
     *                 @OA\Property(property="merchant_response", type="string", nullable=true, example=null),
     *                 @OA\Property(property="merchant_responded_at", type="string", format="date-time", nullable=true, example=null),
     *                 @OA\Property(property="rejection_reason", type="string", nullable=true, example=null),
     *                 @OA\Property(property="moderated_at", type="string", format="date-time", example="2026-02-19T20:10:49.000000Z"),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2026-02-19T19:26:05.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2026-02-19T20:10:49.000000Z")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-02-19T20:10:49.849214Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="7051706c-2acb-48f3-81cc-63ac60365d6a"),
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
     *         response=403,
     *         description="Forbidden - insufficient privileges",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="You do not have permission to moderate reviews."),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_id", type="string", nullable=true),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Review not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Review not found."),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_id", type="string", nullable=true),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error - missing rejection reason",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 description="Validation errors keyed by field name",
     *                 additionalProperties={
     *                     "type": "array",
     *                     "items": {"type": "string"}
     *                 }
     *             ),
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
    public function moderateProductReview(ModerateReviewRequest $request, int $id): JsonResponse
    {
        try {
            $review = ProductReview::findOrFail($id);
            $moderatorId = auth('central')->id();

            $validated = $request->validated();
            $review = $this->moderationService->moderateProductReview(
                $review,
                $validated['action'],
                $moderatorId,
                $validated['rejection_reason'] ?? null
            );

            return ApiResponse::success(
                "Review {$validated['action']}d successfully.",
                new ProductReviewResource($review)
            );
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), null, 422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return ApiResponse::notFound('Review not found.');
        } catch (\Exception $e) {
            return ApiResponse::serverError('Failed to moderate review: ' . $e->getMessage());
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/central/marketplace/merchant-reviews/{id}/moderate",
     *     summary="Moderate a merchant review (approve/reject/flag)",
     *     description="Moderates a merchant review by approving, rejecting, flagging or unflagging it.",
     *     operationId="moderateMerchantReview",
     *     tags={"Central - Admin - Moderate Reviews"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Merchant Review ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Moderation action and optional rejection reason",
     *         @OA\JsonContent(
     *             required={"action"},
     *             @OA\Property(
     *                 property="action",
     *                 type="string",
     *                 description="Moderation action to perform",
     *                 enum={"approve", "reject", "flag", "dismiss_flags"},
     *                 example="approve"
     *             ),
     *             @OA\Property(
     *                 property="rejection_reason",
     *                 type="string",
     *                 description="Reason for rejection (required when action is 'reject')",
     *                 example="Review contains inappropriate language",
     *                 maxLength=500,
     *                 nullable=true
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Review moderated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Review approved successfully."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="overall_rating", type="number", format="float", example=4),
     *                 @OA\Property(
     *                     property="ratings",
     *                     type="object",
     *                     @OA\Property(property="product_quality", type="number", format="float", example=4),
     *                     @OA\Property(property="delivery", type="number", format="float", example=3.5),
     *                     @OA\Property(property="service", type="number", format="float", example=4)
     *                 ),
     *                 @OA\Property(
     *                     property="review_text",
     *                     type="string",
     *                     example="Great service and product quality. Delivery was on time."
     *                 ),
     *                 @OA\Property(property="status", type="string", example="approved"),
     *                 @OA\Property(property="helpful_count", type="integer", example=0),
     *                 @OA\Property(property="not_helpful_count", type="integer", example=0),
     *                 @OA\Property(property="rejection_reason", type="string", nullable=true, example=null),
     *                 @OA\Property(property="moderated_at", type="string", format="date-time", example="2026-02-19T20:44:25.000000Z"),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2026-02-19T20:28:46.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2026-02-19T20:44:25.000000Z")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-02-19T20:44:25.590383Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="46fdb53a-5c20-4ae0-bd45-95dec55b287b"),
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
     *         response=403,
     *         description="Forbidden - insufficient privileges",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="You do not have permission to moderate reviews."),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_id", type="string", nullable=true),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Review not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Review not found."),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_id", type="string", nullable=true),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error - missing rejection reason",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 description="Validation errors keyed by field name",
     *                 additionalProperties={
     *                     "type": "array",
     *                     "items": {"type": "string"}
     *                 }
     *             ),
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
    public function moderateMerchantReview(ModerateReviewRequest $request, int $id): JsonResponse
    {
        try {
            $review = MerchantReview::findOrFail($id);
            $moderatorId = auth('central')->id();

            $validated = $request->validated();
            $review = $this->moderationService->moderateMerchantReview(
                $review,
                $validated['action'],
                $moderatorId,
                $validated['rejection_reason'] ?? null
            );

            return ApiResponse::success(
                "Review {$validated['action']}d successfully.",
                new MerchantReviewResource($review)
            );
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), null, 422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return ApiResponse::notFound('Review not found.');
        } catch (\Exception $e) {
            return ApiResponse::serverError('Failed to moderate review: ' . $e->getMessage());
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/central/marketplace/product-reviews/{id}/flag",
     *     summary="Flag a product review as inappropriate",
     *     description="Allows authenticated customers to flag a product review as inappropriate. Customers cannot flag their own reviews. The flag is recorded with a reason and sent to the moderation queue for admin review.",
     *     operationId="flagProductReview",
     *     tags={"Central - Reviews - Flag"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Product Review ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Flag reason",
     *         @OA\JsonContent(
     *             required={"reason"},
     *             @OA\Property(
     *                 property="reason",
     *                 type="string",
     *                 description="Reason for flagging the review",
     *                 example="Inapproriate language used",
     *                 minLength=10,
     *                 maxLength=500
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Review flagged successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Review flagged. Our team will review it shortly."),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-02-20T07:43:28.936835Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="964db9c4-ee6c-4cde-8229-48c5632333e9"),
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
     *         description="Cannot flag own review or validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="You cannot flag your own review."),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-02-20T07:30:47.442740Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="3a9558e5-c17a-4490-94fe-07f793610a02"),
     *                 @OA\Property(property="tenant_id", type="string", nullable=true, example=null),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     )
     * )
     */
    public function flagProductReview(FlagReviewRequest $request, int $id): JsonResponse
    {
        try {
            $customer = CustomerHelper::getAuthenticatedCustomerOrFail();
            $review = ProductReview::approved()->findOrFail($id);

            $this->moderationService->flagProductReview($review, $customer, $request->validated('reason'));

            return ApiResponse::success('Review flagged. Our team will review it shortly.');
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), null, 422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return ApiResponse::notFound('Review not found.');
        } catch (\Exception $e) {
            return ApiResponse::serverError('Failed to flag review: ' . $e->getMessage());
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/central/marketplace/merchant-reviews/{id}/flag",
     *     summary="Flag a merchant review as inappropriate",
     *     description="Allows authenticated customers to flag a merchant review as inappropriate. Customers cannot flag their own reviews. The flag is recorded with a reason and sent to the moderation queue for admin review.",
     *     operationId="flagMerchantReview",
     *     tags={"Central - Reviews - Flag"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Merchant Review ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Flag reason",
     *         @OA\JsonContent(
     *             required={"reason"},
     *             @OA\Property(
     *                 property="reason",
     *                 type="string",
     *                 description="Reason for flagging the review",
     *                 example="Inapproriate language used",
     *                 minLength=10,
     *                 maxLength=500
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Review flagged successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Review flagged. Our team will review it shortly."),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-02-20T07:51:41.602149Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="95d64589-a495-46fa-8b2d-725b1634187e"),
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
     *         description="Cannot flag own review or validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="You cannot flag your own review."),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-02-20T07:30:47.442740Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="3a9558e5-c17a-4490-94fe-07f793610a02"),
     *                 @OA\Property(property="tenant_id", type="string", nullable=true, example=null),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     )
     * )
     */
    public function flagMerchantReview(FlagReviewRequest $request, int $id): JsonResponse
    {
        try {
            $customer = CustomerHelper::getAuthenticatedCustomerOrFail();
            $review = MerchantReview::approved()->findOrFail($id);

            $this->moderationService->flagMerchantReview($review, $customer, $request->validated('reason'));

            return ApiResponse::success('Review flagged. Our team will review it shortly.');
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), null, 422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return ApiResponse::notFound('Review not found.');
        } catch (\Exception $e) {
            return ApiResponse::serverError('Failed to flag review: ' . $e->getMessage());
        }
    }
}
