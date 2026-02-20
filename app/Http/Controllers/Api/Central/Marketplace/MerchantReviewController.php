<?php

namespace App\Http\Controllers\Api\Central\Marketplace;

use App\Helpers\CustomerHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Review\StoreMerchantReviewRequest;
use App\Http\Resources\Central\Marketplace\MerchantReviewResource;
use App\Http\Responses\ApiResponse;
use App\Services\Central\Marketplace\MerchantReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MerchantReviewController extends Controller
{
    public function __construct(
        private readonly MerchantReviewService $reviewService,
    ) {}

    /**
     * @OA\Get(
     *     path="/api/v1/central/marketplace/merchants/{tenant_id}/reviews",
     *     summary="List all approved merchant reviews",
     *     description="Retrieves a paginated list of all approved reviews for a specific merchant/tenant. No authentication required. Returns overall rating and breakdown of product quality, delivery, and service ratings.",
     *     operationId="listMerchantReviews",
     *     tags={"Central - Reviews - Merchants"},
     *     @OA\Parameter(
     *         name="tenant_id",
     *         in="path",
     *         description="Merchant/Tenant ID (UUID)",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed")
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
     *         description="Merchant reviews retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Merchant reviews retrieved successfully."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="data",
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
     *                         @OA\Property(property="helpful_count", type="integer", example=0),
     *                         @OA\Property(property="not_helpful_count", type="integer", example=0),
     *                         @OA\Property(
     *                             property="customer",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="name", type="string", example="Richard Hensley")
     *                         ),
     *                         @OA\Property(property="created_at", type="string", format="date-time", example="2026-02-19T20:28:46.000000Z"),
     *                         @OA\Property(property="updated_at", type="string", format="date-time", example="2026-02-19T20:44:25.000000Z")
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
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-02-19T20:47:49.675695Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="6adc071f-56d5-4b77-ba91-b56c05fc3d89"),
     *                 @OA\Property(property="tenant_id", type="string", nullable=true, example=null),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     )
     * )
     */
    public function index(string $tenantId, Request $request): JsonResponse
    {
        try {
            $reviews = $this->reviewService->getApprovedReviewsForTenant(
                $tenantId,
                $request->only(['sort_by', 'per_page'])
            );

            return ApiResponse::paginated(
                MerchantReviewResource::collection($reviews),
                'Merchant reviews retrieved successfully.'
            );
        } catch (\Exception $e) {
            return ApiResponse::serverError('Failed to retrieve reviews: ' . $e->getMessage());
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/central/marketplace/orders/{id}/merchant-review",
     *     summary="Submit a merchant review for an order",
     *     description="Submit a review for the merchant/tenant who fulfilled an order. Requires authentication. Reviews must be for completed orders. All ratings must be in 0.5 increments (e.g., 1.0, 1.5, 2.0, 2.5). Reviews are submitted with 'pending' status and require moderation approval.",
     *     operationId="createMerchantReview",
     *     tags={"Central - Reviews - Merchants"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Order ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=2)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Merchant review data",
     *         @OA\JsonContent(
     *             required={"overall_rating", "product_quality_rating", "delivery_rating", "review_text"},
     *             @OA\Property(
     *                 property="overall_rating",
     *                 type="number",
     *                 format="float",
     *                 description="Overall merchant rating in 0.5 increments (1.0 to 5.0)",
     *                 example=4.0,
     *                 minimum=1.0,
     *                 maximum=5.0
     *             ),
     *             @OA\Property(
     *                 property="product_quality_rating",
     *                 type="number",
     *                 format="float",
     *                 description="Product quality rating in 0.5 increments",
     *                 example=4.0,
     *                 minimum=1.0,
     *                 maximum=5.0
     *             ),
     *             @OA\Property(
     *                 property="delivery_rating",
     *                 type="number",
     *                 format="float",
     *                 description="Delivery rating in 0.5 increments",
     *                 example=3.5,
     *                 minimum=1.0,
     *                 maximum=5.0
     *             ),
     *             @OA\Property(
     *                 property="service_rating",
     *                 type="number",
     *                 format="float",
     *                 description="Customer service rating in 0.5 increments (optional)",
     *                 example=4.0,
     *                 minimum=1.0,
     *                 maximum=5.0,
     *                 nullable=true
     *             ),
     *             @OA\Property(
     *                 property="review_text",
     *                 type="string",
     *                 description="Review text content",
     *                 example="Great service and product quality. Delivery was on time.",
     *                 minLength=10,
     *                 maxLength=2000
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Merchant review submitted successfully and pending moderation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Your merchant review has been submitted and is pending moderation."
     *             ),
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
     *                 @OA\Property(property="status", type="string", example="pending"),
     *                 @OA\Property(property="helpful_count", type="integer", nullable=true, example=null),
     *                 @OA\Property(property="not_helpful_count", type="integer", nullable=true, example=null),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2026-02-19T20:28:46.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2026-02-19T20:28:46.000000Z")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-02-19T20:28:46.434472Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="1acb3f24-fff9-4932-848b-cb4ca1225f8c"),
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
     *         description="Validation error - invalid rating increment",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="service_rating",
     *                     type="array",
     *                     @OA\Items(
     *                         type="string",
     *                         example="The service rating must be in 0.5 increments (e.g., 1.0, 1.5, 2.0, 2.5)."
     *                     )
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-02-19T20:28:22.361237Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="60751429-6c8b-43da-9000-a011cc3e34d6"),
     *                 @OA\Property(property="tenant_id", type="string", nullable=true, example=null),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     )
     * )
     */
    public function store(StoreMerchantReviewRequest $request, int $orderId): JsonResponse
    {
        try {
            $customer = CustomerHelper::getAuthenticatedCustomerOrFail();

            $review = $this->reviewService->storeReview(
                $customer,
                $orderId,
                $request->validated()
            );

            return ApiResponse::created(
                'Your merchant review has been submitted and is pending moderation.',
                new MerchantReviewResource($review)
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
}
