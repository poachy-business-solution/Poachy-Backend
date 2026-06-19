<?php

namespace App\Http\Controllers\Api\Central\Marketplace;

use App\Helpers\BusinessHelper;
use App\Http\Controllers\Controller;
use App\Http\Resources\Central\Marketplace\TenantProfileResource;
use App\Http\Responses\ApiResponse;
use App\Models\TenantProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Tenant profile endpoints for marketplace.
 *
 * Returns aggregate metrics for tenants including ratings, orders, and products.
 */
class TenantProfileController extends Controller
{

    /**
     * @OA\Get(
     *     path="/api/v1/central/tenant-profiles",
     *     summary="List all tenant profiles with aggregated metrics",
     *     description="Retrieves a paginated list of all tenant profiles with aggregated business information, ratings, reviews counts, order statistics, product counts, and last calculation timestamps. Requires authentication. Useful for marketplace overview and tenant comparison.",
     *     operationId="listTenantProfiles",
     *     tags={"Central - Tenant Profiles"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number for pagination",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of results per page",
     *         required=false,
     *         @OA\Schema(type="integer", example=24)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Tenant profiles retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Tenant profiles retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="data",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                         @OA\Property(
     *                             property="business",
     *                             type="object",
     *                             @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                             @OA\Property(property="business_name", type="string", example="Tech Haven Electronics Solutions"),
     *                             @OA\Property(property="logo", type="string", example="business/logos/7cjtDAZssxGboFSLkiqEGqpG1f06dkzRQ9bz7JFI.jpg"),
     *                             @OA\Property(property="is_verified", type="boolean", example=true)
     *                         ),
     *                         @OA\Property(
     *                             property="ratings",
     *                             type="object",
     *                             description="Aggregated merchant ratings",
     *                             @OA\Property(property="overall", type="number", format="float", example=3.67),
     *                             @OA\Property(property="product_quality", type="number", format="float", example=4),
     *                             @OA\Property(property="delivery", type="number", format="float", example=3.5),
     *                             @OA\Property(property="service", type="number", format="float", example=4)
     *                         ),
     *                         @OA\Property(
     *                             property="reviews",
     *                             type="object",
     *                             description="Review counts by status",
     *                             @OA\Property(property="total", type="integer", example=3),
     *                             @OA\Property(property="approved", type="integer", example=3),
     *                             @OA\Property(property="pending", type="integer", example=0)
     *                         ),
     *                         @OA\Property(
     *                             property="orders",
     *                             type="object",
     *                             description="Order statistics and revenue",
     *                             @OA\Property(property="total", type="integer", example=0),
     *                             @OA\Property(property="completed", type="integer", example=0),
     *                             @OA\Property(property="revenue", type="number", format="float", example=0)
     *                         ),
     *                         @OA\Property(
     *                             property="products",
     *                             type="object",
     *                             description="Product counts",
     *                             @OA\Property(property="total", type="integer", example=5),
     *                             @OA\Property(property="active", type="integer", example=4)
     *                         ),
     *                         @OA\Property(
     *                             property="last_calculated",
     *                             type="object",
     *                             description="Timestamps of last metric calculations",
     *                             @OA\Property(property="ratings", type="string", format="date-time", example="2026-02-20T21:10:59.000000Z"),
     *                             @OA\Property(property="orders", type="string", format="date-time", nullable=true, example=null),
     *                             @OA\Property(property="products", type="string", format="date-time", example="2026-02-20T21:17:30.000000Z")
     *                         ),
     *                         @OA\Property(property="created_at", type="string", format="date-time", example="2026-02-20T21:10:59.000000Z"),
     *                         @OA\Property(property="updated_at", type="string", format="date-time", example="2026-02-20T21:17:30.000000Z")
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="links",
     *                     type="object",
     *                     @OA\Property(property="first", type="string", example="http://0.0.0.0/api/v1/central/tenant-profiles?page=1"),
     *                     @OA\Property(property="last", type="string", example="http://0.0.0.0/api/v1/central/tenant-profiles?page=1"),
     *                     @OA\Property(property="prev", type="string", nullable=true, example=null),
     *                     @OA\Property(property="next", type="string", nullable=true, example=null)
     *                 ),
     *                 @OA\Property(
     *                     property="meta",
     *                     type="object",
     *                     @OA\Property(property="current_page", type="integer", example=1),
     *                     @OA\Property(property="from", type="integer", example=1),
     *                     @OA\Property(property="last_page", type="integer", example=1),
     *                     @OA\Property(
     *                         property="links",
     *                         type="array",
     *                         @OA\Items(
     *                             type="object",
     *                             @OA\Property(property="url", type="string", nullable=true),
     *                             @OA\Property(property="label", type="string"),
     *                             @OA\Property(property="page", type="integer", nullable=true),
     *                             @OA\Property(property="active", type="boolean")
     *                         )
     *                     ),
     *                     @OA\Property(property="path", type="string", example="http://0.0.0.0/api/v1/central/tenant-profiles"),
     *                     @OA\Property(property="per_page", type="integer", example=24),
     *                     @OA\Property(property="to", type="integer", example=1),
     *                     @OA\Property(property="total", type="integer", example=1)
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-02-20T21:46:56.835698Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="0020f1dc-9d1d-4565-b938-b0ff40e4894e"),
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
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->input('per_page', 24), 100);

        $query = TenantProfile::on('central')
            ->orderBy('created_at', 'desc');

        // Filter by minimum rating if provided
        if ($request->filled('min_rating')) {
            $minRating = (float) $request->input('min_rating');
            $query->where('average_overall_rating', '>=', $minRating);
        }

        // Filter by minimum orders if provided
        if ($request->filled('min_orders')) {
            $minOrders = (int) $request->input('min_orders');
            $query->where('total_orders', '>=', $minOrders);
        }

        // Sort options
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');

        $allowedSorts = [
            'created_at',
            'average_overall_rating',
            'total_orders',
            'total_revenue',
            'approved_reviews',
            'active_marketplace_products',
        ];

        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortOrder === 'asc' ? 'asc' : 'desc');
        }

        $profiles = $query->paginate($perPage);

        // Warm cache for business details
        $tenantIds = $profiles->pluck('tenant_id')->toArray();
        BusinessHelper::warmCache($tenantIds);

        return ApiResponse::success(
            'Tenant profiles retrieved successfully',
            TenantProfileResource::collection($profiles)->response()->getData(true)
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/central/tenant-profiles/{tenant_id}",
     *     summary="Get a single tenant profile with aggregated metrics",
     *     description="Retrieves detailed profile information for a specific tenant including business details, aggregated ratings across product quality/delivery/service, review counts, order statistics with revenue, product counts, and metric calculation timestamps. Requires authentication.",
     *     operationId="getTenantProfile",
     *     tags={"Central - Tenant Profiles"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="tenant_id",
     *         in="path",
     *         description="Tenant/Merchant ID (UUID)",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Tenant profile retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Tenant profile retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(
     *                     property="business",
     *                     type="object",
     *                     description="Business information",
     *                     @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                     @OA\Property(property="business_name", type="string", example="Tech Haven Electronics Solutions"),
     *                     @OA\Property(property="logo", type="string", example="business/logos/7cjtDAZssxGboFSLkiqEGqpG1f06dkzRQ9bz7JFI.jpg"),
     *                     @OA\Property(property="is_verified", type="boolean", example=true)
     *                 ),
     *                 @OA\Property(
     *                     property="ratings",
     *                     type="object",
     *                     description="Aggregated merchant ratings from approved reviews",
     *                     @OA\Property(property="overall", type="number", format="float", example=3.67),
     *                     @OA\Property(property="product_quality", type="number", format="float", example=4),
     *                     @OA\Property(property="delivery", type="number", format="float", example=3.5),
     *                     @OA\Property(property="service", type="number", format="float", example=4)
     *                 ),
     *                 @OA\Property(
     *                     property="reviews",
     *                     type="object",
     *                     description="Review counts by status",
     *                     @OA\Property(property="total", type="integer", example=3),
     *                     @OA\Property(property="approved", type="integer", example=3),
     *                     @OA\Property(property="pending", type="integer", example=0)
     *                 ),
     *                 @OA\Property(
     *                     property="orders",
     *                     type="object",
     *                     description="Order statistics and total revenue",
     *                     @OA\Property(property="total", type="integer", example=0),
     *                     @OA\Property(property="completed", type="integer", example=0),
     *                     @OA\Property(property="revenue", type="number", format="float", example=0)
     *                 ),
     *                 @OA\Property(
     *                     property="products",
     *                     type="object",
     *                     description="Product inventory counts",
     *                     @OA\Property(property="total", type="integer", example=5),
     *                     @OA\Property(property="active", type="integer", example=4)
     *                 ),
     *                 @OA\Property(
     *                     property="last_calculated",
     *                     type="object",
     *                     description="Timestamps indicating when each metric was last calculated/updated",
     *                     @OA\Property(property="ratings", type="string", format="date-time", example="2026-02-20T21:10:59.000000Z"),
     *                     @OA\Property(property="orders", type="string", format="date-time", nullable=true, example=null),
     *                     @OA\Property(property="products", type="string", format="date-time", example="2026-02-20T21:17:30.000000Z")
     *                 ),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2026-02-20T21:10:59.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2026-02-20T21:17:30.000000Z")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-02-20T21:48:31.651038Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="05d96e43-0b0e-47ac-9692-d4b1ffe6a5c4"),
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
     *         description="Tenant profile not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Tenant profile not found."),
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
    public function show(string $tenantId): JsonResponse
    {
        $profile = TenantProfile::on('central')
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        return ApiResponse::success(
            'Tenant profile retrieved successfully',
            new TenantProfileResource($profile)
        );
    }
}
