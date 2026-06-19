<?php

namespace App\Http\Controllers\Api\Tenant\Reviews;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreReviewResponseRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant\ProductReview;
use App\Services\Tenant\ReviewResponseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function __construct(private readonly ReviewResponseService $reviewResponseService) {}

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/reviews",
     *     summary="List product reviews for tenant",
     *     description="Retrieves paginated list of product reviews synced from the central marketplace to the tenant database. Supports filtering by product_id. Returns tenant-specific review details including merchant response status.",
     *     operationId="tenantListReviews",
     *     tags={"Tenant - Product Reviews"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="product_id",
     *         in="query",
     *         description="Filter reviews by product ID",
     *         required=false,
     *         @OA\Schema(type="integer", example=4)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of results per page (max 50)",
     *         required=false,
     *         @OA\Schema(type="integer", example=20, maximum=50)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Reviews retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Reviews retrieved successfully."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(
     *                     property="data",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="central_review_id", type="integer", example=1),
     *                         @OA\Property(property="product_id", type="integer", example=4),
     *                         @OA\Property(property="product_name", type="string", example="TCL 55 4K UHD Smart LED TV"),
     *                         @OA\Property(property="product_sku", type="string", example="ELEC-DELL-56QT"),
     *                         @OA\Property(property="customer_name", type="string", example="Richard Hensley"),
     *                         @OA\Property(property="rating", type="string", example="4.0"),
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
     *                         @OA\Property(property="merchant_response", type="string", nullable=true, example=null),
     *                         @OA\Property(property="merchant_responded_at", type="string", format="date-time", nullable=true, example=null),
     *                         @OA\Property(property="status", type="string", example="approved"),
     *                         @OA\Property(property="reviewed_at", type="string", format="date-time", example="2026-02-19T16:26:05.000000Z"),
     *                         @OA\Property(property="created_at", type="string", format="date-time", example="2026-02-20T14:09:31.000000Z"),
     *                         @OA\Property(property="updated_at", type="string", format="date-time", example="2026-02-20T14:09:31.000000Z"),
     *                         @OA\Property(property="deleted_at", type="string", format="date-time", nullable=true, example=null),
     *                         @OA\Property(
     *                             property="product",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=4),
     *                             @OA\Property(property="name", type="string", example="TCL 55 4K UHD Smart LED TV"),
     *                             @OA\Property(property="sku", type="string", example="ELEC-DELL-56QT")
     *                         )
     *                     )
     *                 ),
     *                 @OA\Property(property="first_page_url", type="string", example="http://techhaven.localhost/api/v1/tenant/reviews?page=1"),
     *                 @OA\Property(property="from", type="integer", example=1),
     *                 @OA\Property(property="last_page", type="integer", example=1),
     *                 @OA\Property(property="last_page_url", type="string", example="http://techhaven.localhost/api/v1/tenant/reviews?page=1"),
     *                 @OA\Property(
     *                     property="links",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="url", type="string", nullable=true),
     *                         @OA\Property(property="label", type="string"),
     *                         @OA\Property(property="page", type="integer", nullable=true),
     *                         @OA\Property(property="active", type="boolean")
     *                     )
     *                 ),
     *                 @OA\Property(property="next_page_url", type="string", nullable=true, example=null),
     *                 @OA\Property(property="path", type="string", example="http://techhaven.localhost/api/v1/tenant/reviews"),
     *                 @OA\Property(property="per_page", type="integer", example=20),
     *                 @OA\Property(property="prev_page_url", type="string", nullable=true, example=null),
     *                 @OA\Property(property="to", type="integer", example=1),
     *                 @OA\Property(property="total", type="integer", example=1)
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-02-20T14:19:25.288515Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="93f5e482-6e8e-4188-9b75-4c2b2b92b443"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
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
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', 20), 50);
        $productId = $request->query('product_id');

        $query = ProductReview::with('product:id,name,sku')->orderBy('reviewed_at', 'desc');

        if ($productId) {
            $query->where('product_id', $productId);
        }

        return ApiResponse::success('Reviews retrieved successfully.', $query->paginate($perPage));
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/reviews/{id}",
     *     summary="Get single product review for tenant",
     *     description="Retrieves detailed information for a specific product review in the tenant database. Returns review content, product details, and merchant response status.",
     *     operationId="tenantGetReview",
     *     tags={"Tenant - Product Reviews"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Tenant Review ID",
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
     *                 @OA\Property(property="central_review_id", type="integer", example=1),
     *                 @OA\Property(property="product_id", type="integer", example=4),
     *                 @OA\Property(property="product_name", type="string", example="TCL 55 4K UHD Smart LED TV"),
     *                 @OA\Property(property="product_sku", type="string", example="ELEC-DELL-56QT"),
     *                 @OA\Property(property="customer_name", type="string", example="Richard Hensley"),
     *                 @OA\Property(property="rating", type="string", example="4.0"),
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
     *                 @OA\Property(property="merchant_response", type="string", nullable=true, example=null),
     *                 @OA\Property(property="merchant_responded_at", type="string", format="date-time", nullable=true, example=null),
     *                 @OA\Property(property="status", type="string", example="approved"),
     *                 @OA\Property(property="reviewed_at", type="string", format="date-time", example="2026-02-19T16:26:05.000000Z"),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2026-02-20T14:09:31.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2026-02-20T14:09:31.000000Z"),
     *                 @OA\Property(property="deleted_at", type="string", format="date-time", nullable=true, example=null),
     *                 @OA\Property(
     *                     property="product",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=4),
     *                     @OA\Property(property="name", type="string", example="TCL 55 4K UHD Smart LED TV"),
     *                     @OA\Property(property="sku", type="string", example="ELEC-DELL-56QT")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-02-20T14:24:07.506878Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="ed9bb42b-acf0-4a03-a024-6c9e3ad1deac"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
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
     *         description="Review not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Review not found."),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true)
     *             )
     *         )
     *     )
     * )
     */
    public function show(int $id): JsonResponse
    {
        $review = ProductReview::with('product:id,name,sku')->findOrFail($id);

        return ApiResponse::success('Review retrieved successfully.', $review);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/reviews/{id}/respond",
     *     summary="Submit a merchant response to a review",
     *     description="Allows tenant/merchant to respond to a product review. The response is validated for excessive capital letters and URLs. Response syncs back to the central marketplace. Requires tenant authentication.",
     *     operationId="respondToReview",
     *     tags={"Tenant - Product Reviews"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Tenant Review ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Merchant response text",
     *         @OA\JsonContent(
     *             required={"response_text"},
     *             @OA\Property(
     *                 property="response_text",
     *                 type="string",
     *                 description="Merchant's response to the review (no URLs or excessive capitals allowed)",
     *                 example="Great you loved the product!",
     *                 minLength=10,
     *                 maxLength=500
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Response submitted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Response submitted and will sync to marketplace."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="central_review_id", type="integer", example=1),
     *                 @OA\Property(property="product_id", type="integer", example=4),
     *                 @OA\Property(property="product_name", type="string", example="TCL 55 4K UHD Smart LED TV"),
     *                 @OA\Property(property="product_sku", type="string", example="ELEC-DELL-56QT"),
     *                 @OA\Property(property="customer_name", type="string", example="Richard Hensley"),
     *                 @OA\Property(property="rating", type="string", example="4.0"),
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
     *                 @OA\Property(property="merchant_response", type="string", example="Great you loved the product!"),
     *                 @OA\Property(property="merchant_responded_at", type="string", format="date-time", example="2026-02-20T14:26:46.000000Z"),
     *                 @OA\Property(property="status", type="string", example="approved"),
     *                 @OA\Property(property="reviewed_at", type="string", format="date-time", example="2026-02-19T16:26:05.000000Z"),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2026-02-20T14:09:31.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2026-02-20T14:26:46.000000Z"),
     *                 @OA\Property(property="deleted_at", type="string", format="date-time", nullable=true, example=null)
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-02-20T14:26:46.339633Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="728bd5b3-3b84-4d8a-92bd-f41196a6de82"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
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
     *         description="Validation error - excessive capitals or contains URL",
     *         @OA\JsonContent(
     *             oneOf={
     *                 @OA\Schema(
     *                     @OA\Property(property="success", type="boolean", example=false),
     *                     @OA\Property(property="message", type="string", example="Response contains excessive capital letters."),
     *                     @OA\Property(
     *                         property="meta",
     *                         type="object",
     *                         @OA\Property(property="timestamp", type="string", format="date-time", example="2026-02-20T14:36:48.873567Z"),
     *                         @OA\Property(property="request_id", type="string", format="uuid", example="404ba35e-1e31-47be-b986-44c0b87ccf20"),
     *                         @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                         @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *                     )
     *                 ),
     *                 @OA\Schema(
     *                     @OA\Property(property="success", type="boolean", example=false),
     *                     @OA\Property(property="message", type="string", example="Response may not contain URLs."),
     *                     @OA\Property(
     *                         property="meta",
     *                         type="object",
     *                         @OA\Property(property="timestamp", type="string", format="date-time", example="2026-02-20T14:39:39.950882Z"),
     *                         @OA\Property(property="request_id", type="string", format="uuid", example="ab9b0015-6f6f-4e1e-938c-b71c26a52ea1"),
     *                         @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                         @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *                     )
     *                 )
     *             }
     *         )
     *     )
     * )
     */
    public function respond(StoreReviewResponseRequest $request, int $id): JsonResponse
    {
        try {
            $review = ProductReview::findOrFail($id);
            $updated = $this->reviewResponseService->createResponse($review, $request->validated('response_text'));

            return ApiResponse::created('Response submitted and will sync to marketplace.', $updated);
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), null, 422);
        }
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/tenant/reviews/{id}/respond",
     *     summary="Update merchant response to a review",
     *     description="Allows tenant/merchant to update their response to a product review. The updated response is validated for excessive capital letters and URLs. Changes sync back to the central marketplace. Requires tenant authentication.",
     *     operationId="updateReviewResponse",
     *     tags={"Tenant - Product Reviews"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Tenant Review ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Updated merchant response text",
     *         @OA\JsonContent(
     *             required={"response_text"},
     *             @OA\Property(
     *                 property="response_text",
     *                 type="string",
     *                 description="Updated merchant's response (no URLs or excessive capitals allowed)",
     *                 example="I loved the product and the service was excellent. I would definitely recommend it to others.",
     *                 minLength=10,
     *                 maxLength=500
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Response updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Response updated and will sync to marketplace."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="central_review_id", type="integer", example=1),
     *                 @OA\Property(property="product_id", type="integer", example=4),
     *                 @OA\Property(property="product_name", type="string", example="TCL 55 4K UHD Smart LED TV"),
     *                 @OA\Property(property="product_sku", type="string", example="ELEC-DELL-56QT"),
     *                 @OA\Property(property="customer_name", type="string", example="Richard Hensley"),
     *                 @OA\Property(property="rating", type="string", example="4.0"),
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
     *                 @OA\Property(
     *                     property="merchant_response",
     *                     type="string",
     *                     example="I loved the product and the service was excellent. I would definitely recommend it to others."
     *                 ),
     *                 @OA\Property(property="merchant_responded_at", type="string", format="date-time", example="2026-02-20T14:26:46.000000Z"),
     *                 @OA\Property(property="status", type="string", example="approved"),
     *                 @OA\Property(property="reviewed_at", type="string", format="date-time", example="2026-02-19T16:26:05.000000Z"),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2026-02-20T14:09:31.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2026-02-20T14:41:56.000000Z"),
     *                 @OA\Property(property="deleted_at", type="string", format="date-time", nullable=true, example=null)
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-02-20T14:41:56.871795Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="8c12da7a-88fe-4486-957f-a78792384647"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
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
     *         description="Validation error - excessive capitals or contains URL",
     *         @OA\JsonContent(
     *             oneOf={
     *                 @OA\Schema(
     *                     @OA\Property(property="success", type="boolean", example=false),
     *                     @OA\Property(property="message", type="string", example="Response contains excessive capital letters."),
     *                     @OA\Property(
     *                         property="meta",
     *                         type="object",
     *                         @OA\Property(property="timestamp", type="string", format="date-time", example="2026-02-20T14:36:48.873567Z"),
     *                         @OA\Property(property="request_id", type="string", format="uuid", example="404ba35e-1e31-47be-b986-44c0b87ccf20"),
     *                         @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                         @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *                     )
     *                 ),
     *                 @OA\Schema(
     *                     @OA\Property(property="success", type="boolean", example=false),
     *                     @OA\Property(property="message", type="string", example="Response may not contain URLs."),
     *                     @OA\Property(
     *                         property="meta",
     *                         type="object",
     *                         @OA\Property(property="timestamp", type="string", format="date-time", example="2026-02-20T14:39:39.950882Z"),
     *                         @OA\Property(property="request_id", type="string", format="uuid", example="ab9b0015-6f6f-4e1e-938c-b71c26a52ea1"),
     *                         @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                         @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *                     )
     *                 )
     *             }
     *         )
     *     )
     * )
     */
    public function updateResponse(StoreReviewResponseRequest $request, int $id): JsonResponse
    {
        try {
            $review = ProductReview::findOrFail($id);
            $updated = $this->reviewResponseService->updateResponse($review, $request->validated('response_text'));

            return ApiResponse::success('Response updated and will sync to marketplace.', $updated);
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), null, 422);
        }
    }
}
