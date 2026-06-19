<?php

namespace App\Http\Controllers\Api\Central\Marketplace\Analytics;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\Central\Marketplace\Analytics\ProductAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductAnalyticsController extends Controller
{
    public function __construct(
        private readonly ProductAnalyticsService $productService
    ) {}


    /**
     * @OA\Get(
     *     path="/api/v1/central/reports/products/top",
     *     summary="Get top performing products",
     *     description="Retrieves list of top performing products ranked by views, conversions, and conversion rates. Date range defaults to last 30 days if not specified. Requires admin authentication.",
     *     operationId="getTopProducts",
     *     tags={"Central - Analytics - Product Performance"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         description="Number of top products to return",
     *         required=false,
     *         @OA\Schema(type="integer", default=10, minimum=1, example=10)
     *     ),
     *     @OA\Parameter(
     *         name="start_date",
     *         in="query",
     *         description="Start date for analysis (defaults to 30 days ago)",
     *         required=false,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="end_date",
     *         in="query",
     *         description="End date for analysis (defaults to today)",
     *         required=false,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Top performing products retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Top performing products retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="product_id", type="integer", example=2),
     *                     @OA\Property(property="total_views", type="integer", description="Total product views", example=1),
     *                     @OA\Property(property="conversions", type="string", description="Number of purchases", example="0"),
     *                     @OA\Property(property="conversion_rate", type="number", format="float", description="Conversion rate (%)", example=0)
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-02-22T14:15:56.252067Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="122baa65-f15a-48bf-bd56-2563e06aa44e"),
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
     *
     * @OA\Get(
     *     path="/api/v1/central/reports/products/{id}",
     *     summary="Get single product performance metrics",
     *     description="Retrieves detailed performance metrics for a specific product including views, engagement metrics, and conversion rates. Date range defaults to last 30 days if not specified. Requires admin authentication.",
     *     operationId="getProductPerformance",
     *     tags={"Central - Analytics - Product Performance"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Product ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=2)
     *     ),
     *     @OA\Parameter(
     *         name="start_date",
     *         in="query",
     *         description="Start date for analysis (defaults to 30 days ago)",
     *         required=false,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="end_date",
     *         in="query",
     *         description="End date for analysis (defaults to today)",
     *         required=false,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Product performance metrics retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Product performance metrics retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="total_views", type="integer", description="Total product views", example=1),
     *                 @OA\Property(property="added_to_cart", type="boolean", description="Whether product was added to cart", example=false),
     *                 @OA\Property(property="added_to_wishlist", type="boolean", description="Whether product was added to wishlist", example=false),
     *                 @OA\Property(property="view_to_cart_rate", type="number", format="float", description="View to cart conversion rate (%)", example=0),
     *                 @OA\Property(property="view_to_wishlist_rate", type="number", format="float", description="View to wishlist rate (%)", example=0),
     *                 @OA\Property(property="avg_time_spent_seconds", type="integer", description="Average time spent viewing product", example=320),
     *                 @OA\Property(property="scrolled_to_description", type="boolean", description="Whether users scrolled to description", example=true),
     *                 @OA\Property(property="scrolled_to_reviews", type="boolean", description="Whether users scrolled to reviews", example=true),
     *                 @OA\Property(property="clicked_images", type="boolean", description="Whether users clicked product images", example=true),
     *                 @OA\Property(property="engagement_rate", type="number", format="float", description="Overall engagement rate (%)", example=100)
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-02-22T14:16:37.207318Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="1a3b129e-52da-49a4-9add-fc981009c38d"),
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
     *         description="Product not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Product not found."),
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

    public function show(int $productId, Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date'   => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        [$startDate, $endDate] = $this->resolveDateRange($request);

        $performance = $this->productService->getProductPerformance(
            $productId,
            $startDate,
            $endDate
        );

        return ApiResponse::success('Product performance metrics retrieved successfully', $performance);
    }

    public function top(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date'   => ['nullable', 'date', 'after_or_equal:start_date'],
            'limit'      => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        [$startDate, $endDate] = $this->resolveDateRange($request);

        $topProducts = $this->productService->getTopProducts(
            $startDate,
            $endDate,
            $request->integer('limit', 10)
        );

        return ApiResponse::success('Top performing products retrieved successfully', $topProducts);
    }

    public function referrers(int $productId, Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => ['required', 'date'],
            'end_date'   => ['required', 'date', 'after_or_equal:start_date'],
        ]);

        $referrers = $this->productService->getReferrerSourceBreakdown(
            $productId,
            new \DateTime($request->start_date),
            new \DateTime($request->end_date)
        );

        return response()->json([
            'success' => true,
            'data'    => $referrers,
        ]);
    }

    private function resolveDateRange(Request $request): array
    {
        $startDate = $request->start_date
            ? new \DateTime($request->start_date)
            : (new \DateTime())->modify('-30 days');

        $endDate = $request->end_date
            ? new \DateTime($request->end_date)
            : new \DateTime();

        return [$startDate, $endDate];
    }
}
