<?php

namespace App\Http\Controllers\Api\Central\Marketplace;

use App\Helpers\CustomerHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Review\StoreProductReviewRequest;
use App\Http\Resources\Central\Marketplace\ProductReviewResource;
use App\Http\Responses\ApiResponse;
use App\Models\ProductReview;
use App\Services\Central\Marketplace\ProductReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductReviewController extends Controller
{
    public function __construct(
        private readonly ProductReviewService $reviewService,
    ) {}

    /**
     * @OA\Get(
     *     path="/api/v1/central/marketplace/products/{id}/reviews",
     *     summary="List all approved reviews for a product",
     *     description="Retrieves a paginated list of all approved reviews for a specific product. No authentication required. Returns review details including rating, text, images, customer information, and merchant responses.",
     *     operationId="listProductReviews",
     *     tags={"Central - Reviews - Products"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Product ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=2)
     *     ),
     *     @OA\Parameter(
     *         name="sort_by",
     *         in="query",
     *         description="Field to sort by",
     *         required=false,
     *         @OA\Schema(type="string", example="created_at")
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
     *         description="Product reviews retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Product reviews retrieved successfully."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="data",
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
     *                         @OA\Property(property="helpful_count", type="integer", example=0),
     *                         @OA\Property(property="not_helpful_count", type="integer", example=0),
     *                         @OA\Property(property="merchant_name", type="string", example="Tech Haven Electronics Solutions"),
     *                         @OA\Property(property="merchant_response", type="string", nullable=true, example=null),
     *                         @OA\Property(property="merchant_responded_at", type="string", format="date-time", nullable=true, example=null),
     *                         @OA\Property(
     *                             property="customer",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="name", type="string", example="Richard Hensley")
     *                         ),
     *                         @OA\Property(property="created_at", type="string", format="date-time", example="2026-02-19T19:26:05.000000Z"),
     *                         @OA\Property(property="updated_at", type="string", format="date-time", example="2026-02-19T20:10:49.000000Z")
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="pagination",
     *                     type="object",
     *                     @OA\Property(property="current_page", type="integer", example=1),
     *                     @OA\Property(property="last_page", type="integer", example=1),
     *                     @OA\Property(property="per_page", type="integer", example=15),
     *                     @OA\Property(property="total", type="integer", example=1),
     *                     @OA\Property(property="from", type="integer", example=1),
     *                     @OA\Property(property="to", type="integer", example=1)
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-02-19T20:11:16.996681Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="761b5f72-e8b8-4520-a066-1f126034dde5"),
     *                 @OA\Property(property="tenant_id", type="string", nullable=true, example=null),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     )
     * )
     */
    public function index(int $productId, Request $request): JsonResponse
    {
        try {
            $reviews = $this->reviewService->getApprovedReviewsForProduct(
                $productId,
                $request->only(['sort_by', 'per_page'])
            );

            return ApiResponse::paginated(
                ProductReviewResource::collection($reviews),
                'Product reviews retrieved successfully.'
            );
        } catch (\Exception $e) {
            return ApiResponse::serverError('Failed to retrieve reviews: ' . $e->getMessage());
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/central/marketplace/products/{id}/reviews",
     *     summary="Create a product review",
     *     description="Submit a new review for a product. Requires authentication. Reviews must be for completed orders and submitted within 30 days of order completion. Supports up to 5 review images. Reviews are submitted with 'pending' status and require moderation approval. Rating must be in 0.5 increments (e.g., 1.0, 1.5, 2.0). Rate limited to prevent spam.",
     *     operationId="createProductReview",
     *     tags={"Central - Reviews - Products"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Product ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=2)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"rating", "review_text"},
     *                 @OA\Property(
     *                     property="order_id",
     *                     type="integer",
     *                     description="Order ID (optional - to mark as verified purchase)",
     *                     example=1,
     *                     nullable=true
     *                 ),
     *                 @OA\Property(
     *                     property="rating",
     *                     type="number",
     *                     format="float",
     *                     description="Product rating in 0.5 increments (1.0 to 5.0)",
     *                     example=4.0,
     *                     minimum=1.0,
     *                     maximum=5.0
     *                 ),
     *                 @OA\Property(
     *                     property="title",
     *                     type="string",
     *                     description="Review title",
     *                     example="Excellent TV Quality",
     *                     maxLength=150,
     *                     nullable=true
     *                 ),
     *                 @OA\Property(
     *                     property="review_text",
     *                     type="string",
     *                     description="Review text content",
     *                     example="This is my second time ordering from Tech Haven and I'm impressed again. The TCL 55 4K UHD TV has amazing picture clarity, vibrant colors, and smooth performance. Delivery was timely and the packaging was secure.",
     *                     minLength=10,
     *                     maxLength=2000
     *                 ),
     *                 @OA\Property(
     *                     property="review_images[]",
     *                     type="array",
     *                     description="Review images (max 5 images, each max 5MB)",
     *                     @OA\Items(
     *                         type="string",
     *                         format="binary",
     *                         description="Image file - JPEG, PNG, JPG formats only"
     *                     ),
     *                     maxItems=5,
     *                     nullable=true
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Review submitted successfully and pending moderation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Your review has been submitted and is pending moderation."),
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
     *                 @OA\Property(property="status", type="string", example="pending"),
     *                 @OA\Property(property="helpful_count", type="integer", nullable=true, example=null),
     *                 @OA\Property(property="not_helpful_count", type="integer", nullable=true, example=null),
     *                 @OA\Property(property="merchant_name", type="string", example="Tech Haven Electronics Solutions"),
     *                 @OA\Property(property="merchant_response", type="string", nullable=true, example=null),
     *                 @OA\Property(property="merchant_responded_at", type="string", format="date-time", nullable=true, example=null),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2026-02-19T19:26:05.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2026-02-19T19:26:05.000000Z")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-02-19T19:26:05.519491Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="4c62168f-af79-429a-8a45-f7a06f840f11"),
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
     *         description="Validation error or business rule violation",
     *         @OA\JsonContent(
     *             oneOf={
     *                 @OA\Schema(
     *                     description="Order not completed",
     *                     @OA\Property(property="success", type="boolean", example=false),
     *                     @OA\Property(property="message", type="string", example="Reviews can only be submitted for completed orders."),
     *                     @OA\Property(
     *                         property="meta",
     *                         type="object",
     *                         @OA\Property(property="timestamp", type="string", format="date-time", example="2026-02-19T19:23:02.183586Z"),
     *                         @OA\Property(property="request_id", type="string", format="uuid", example="81854483-b3eb-47ee-a703-b1947dd2e1da"),
     *                         @OA\Property(property="tenant_id", type="string", nullable=true, example=null),
     *                         @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *                     )
     *                 ),
     *                 @OA\Schema(
     *                     description="Product not in order",
     *                     @OA\Property(property="success", type="boolean", example=false),
     *                     @OA\Property(property="message", type="string", example="This product was not part of the specified order."),
     *                     @OA\Property(
     *                         property="meta",
     *                         type="object",
     *                         @OA\Property(property="timestamp", type="string", format="date-time", example="2026-02-19T19:24:06.676695Z"),
     *                         @OA\Property(property="request_id", type="string", format="uuid", example="c67ece08-069a-42e6-ab3a-e23f24cd63a9"),
     *                         @OA\Property(property="tenant_id", type="string", nullable=true, example=null),
     *                         @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *                     )
     *                 ),
     *                 @OA\Schema(
     *                     description="Review window expired",
     *                     @OA\Property(property="success", type="boolean", example=false),
     *                     @OA\Property(property="message", type="string", example="The 30-day review window for this order has expired."),
     *                     @OA\Property(
     *                         property="meta",
     *                         type="object",
     *                         @OA\Property(property="timestamp", type="string", format="date-time", example="2026-02-19T19:25:12.959164Z"),
     *                         @OA\Property(property="request_id", type="string", format="uuid", example="d6d81abb-8b02-49a1-a460-8f96f81c0ce3"),
     *                         @OA\Property(property="tenant_id", type="string", nullable=true, example=null),
     *                         @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *                     )
     *                 ),
     *                 @OA\Schema(
     *                     description="Duplicate review",
     *                     @OA\Property(property="success", type="boolean", example=false),
     *                     @OA\Property(property="message", type="string", example="You have already submitted a review for this product on this order."),
     *                     @OA\Property(
     *                         property="meta",
     *                         type="object",
     *                         @OA\Property(property="timestamp", type="string", format="date-time", example="2026-02-19T20:56:14.655527Z"),
     *                         @OA\Property(property="request_id", type="string", format="uuid", example="b1dbe7bd-df71-45c1-b59a-b4390e2be379"),
     *                         @OA\Property(property="tenant_id", type="string", nullable=true, example=null),
     *                         @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *                     )
     *                 ),
     *                 @OA\Schema(
     *                     description="Standard validation errors",
     *                     @OA\Property(property="success", type="boolean", example=false),
     *                     @OA\Property(property="message", type="string", example="The given data was invalid."),
     *                     @OA\Property(
     *                         property="errors",
     *                         type="object",
     *                         description="Validation errors keyed by field name",
     *                         additionalProperties={
     *                             "type": "array",
     *                             "items": {"type": "string"}
     *                         }
     *                     ),
     *                     @OA\Property(
     *                         property="meta",
     *                         type="object",
     *                         @OA\Property(property="timestamp", type="string", format="date-time"),
     *                         @OA\Property(property="request_id", type="string", format="uuid"),
     *                         @OA\Property(property="tenant_id", type="string", nullable=true),
     *                         @OA\Property(property="tenant_name", type="string", nullable=true)
     *                     )
     *                 )
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=429,
     *         description="Rate limit exceeded - too many reviews submitted",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="You have submitted too many reviews. Please try again in 1591 seconds."
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-02-19T19:56:31.208266Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="604a1eaa-bbf8-430c-ab19-2fe2d922ee73"),
     *                 @OA\Property(property="tenant_id", type="string", nullable=true, example=null),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     )
     * )
     */
    public function store(StoreProductReviewRequest $request, int $productId): JsonResponse
    {
        try {
            $customer = CustomerHelper::getAuthenticatedCustomerOrFail();
            $imageFiles = $request->hasFile('review_images')
                ? $request->file('review_images')
                : [];

            $review = $this->reviewService->storeReview(
                $customer,
                $productId,
                $request->safe()->except('review_images'),
                $imageFiles
            );

            return ApiResponse::created(
                'Your review has been submitted and is pending moderation.',
                new ProductReviewResource($review)
            );
        } catch (\RuntimeException $e) {
            $code = $e->getCode();
            if ($code === 429) {
                return ApiResponse::rateLimited($e->getMessage());
            }

            return ApiResponse::error($e->getMessage(), null, $code ?: 422);
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), null, 422);
        } catch (\Exception $e) {
            return ApiResponse::serverError('Failed to submit review: ' . $e->getMessage());
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/central/marketplace/reviews/{id}",
     *     summary="Get approved review details",
     *     description="Retrieves the full details of a specific approved product review by ID. No authentication required. Returns review information including rating, text, images, customer details, and merchant response if available.",
     *     operationId="getReviewDetails",
     *     tags={"Central - Reviews - Products"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Review ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Review retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Review retrieved successfully."),
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
     *                 @OA\Property(property="merchant_name", type="string", example="Tech Haven Electronics Solutions"),
     *                 @OA\Property(property="merchant_response", type="string", nullable=true, example=null),
     *                 @OA\Property(property="merchant_responded_at", type="string", format="date-time", nullable=true, example=null),
     *                 @OA\Property(
     *                     property="customer",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Richard Hensley")
     *                 ),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2026-02-19T19:26:05.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2026-02-19T20:10:49.000000Z")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-02-19T20:12:20.574340Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="2ef3c73c-34f5-497b-9ed6-294da252c676"),
     *                 @OA\Property(property="tenant_id", type="string", nullable=true, example=null),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
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
     *     )
     * )
     */
    public function show(int $id): JsonResponse
    {
        try {
            $review = ProductReview::approved()
                ->with('customer.user:id,name')
                ->findOrFail($id);

            return ApiResponse::success(
                'Review retrieved successfully.',
                new ProductReviewResource($review)
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return ApiResponse::notFound('Review not found.');
        } catch (\Exception $e) {
            return ApiResponse::serverError('Failed to retrieve review: ' . $e->getMessage());
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/central/marketplace/reviews/{id}",
     *     summary="Delete a product review",
     *     description="Deletes a product review that belongs to the authenticated customer. Only the customer who created the review can delete it.",
     *     operationId="deleteProductReview",
     *     tags={"Central - Reviews - Products"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Review ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Review deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Review deleted successfully"),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-02-19T19:32:21.788623Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="7337223a-07fd-44fd-8789-1edea6f87d66"),
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
     *         description="Forbidden - review does not belong to the authenticated customer",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="You do not have permission to delete this review."),
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
     *     )
     * )
     */
    public function destroy(int $id): JsonResponse|\Illuminate\Http\Response
    {
        try {
            $customer = CustomerHelper::getAuthenticatedCustomerOrFail();

            $review = ProductReview::where('id', $id)
                ->where('customer_id', $customer->id)
                ->firstOrFail();

            $review->delete();

            return ApiResponse::success('Review deleted successfully');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return ApiResponse::notFound('Review not found or does not belong to your account.');
        } catch (\Exception $e) {
            return ApiResponse::serverError('Failed to delete review: ' . $e->getMessage());
        }
    }
}
