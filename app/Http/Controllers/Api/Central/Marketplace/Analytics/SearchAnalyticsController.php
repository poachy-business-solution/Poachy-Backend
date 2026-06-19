<?php

namespace App\Http\Controllers\Api\Central\Marketplace\Analytics;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\Central\Marketplace\Analytics\SearchAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchAnalyticsController extends Controller
{
    public function __construct(
        private readonly SearchAnalyticsService $searchService
    ) {}

    /**
     * @OA\Get(
     *     path="/api/v1/central/reports/search/zero-results",
     *     summary="Get searches with zero results (catalog gaps)",
     *     description="Retrieves search queries that returned no results, indicating potential catalog gaps or missing products. Ordered by frequency to prioritize opportunities. Date range defaults to last 30 days if not specified. Requires admin authentication.",
     *     operationId="getZeroResultSearches",
     *     tags={"Central - Analytics - Search Queries"},
     *     security={{"sanctum": {}}},
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
     *         description="Catalog gap metrics retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Catalog gap metrics retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="search_query", type="string", example="waterproof leather sandals size 52"),
     *                     @OA\Property(property="count", type="integer", description="Number of times this query returned zero results", example=1)
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-02-22T15:23:42.094722Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="775e0eab-e88e-4cdc-8b23-8afb187e4ac0"),
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
     *     path="/api/v1/central/reports/search/popular",
     *     summary="Get popular search queries with performance metrics",
     *     description="Retrieves most frequently searched terms with conversion metrics including clicks, cart additions, and purchases. Date range defaults to last 30 days if not specified. Requires admin authentication.",
     *     operationId="getPopularSearches",
     *     tags={"Central - Analytics - Search Queries"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         description="Number of popular searches to return",
     *         required=false,
     *         @OA\Schema(type="integer", minimum=1, maximum=100, default=20, example=20)
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
     *         description="Popular searches retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Popular searches retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="search_query", type="string", example="laptop backpack"),
     *                     @OA\Property(property="search_count", type="integer", description="Number of times searched", example=1),
     *                     @OA\Property(property="avg_results", type="integer", description="Average number of results returned", example=12),
     *                     @OA\Property(property="clicks", type="string", description="Number of result clicks", example="0"),
     *                     @OA\Property(property="cart_adds", type="string", description="Number of products added to cart from search", example="0"),
     *                     @OA\Property(property="conversions", type="string", description="Number of purchases from search", example="0"),
     *                     @OA\Property(property="conversion_rate", type="number", format="float", description="Search to purchase conversion rate (%)", example=0)
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-02-22T15:31:32.528279Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="fdfac678-74cb-4fce-9604-73a1df7eea0a"),
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
     *     path="/api/v1/central/reports/search/metrics",
     *     summary="Get overall search conversion metrics",
     *     description="Retrieves aggregate search performance metrics including zero result rate, click-through rate, and conversion rate. Date range defaults to last 30 days if not specified. Requires admin authentication.",
     *     operationId="getSearchMetrics",
     *     tags={"Central - Analytics - Search Queries"},
     *     security={{"sanctum": {}}},
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
     *         description="Search conversion metrics retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Search conversion metrics retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="total_searches", type="integer", description="Total search queries", example=4),
     *                 @OA\Property(property="searches_with_results", type="string", description="Searches that returned results", example="3"),
     *                 @OA\Property(property="zero_result_rate", type="number", format="float", description="Percentage with zero results (%)", example=25),
     *                 @OA\Property(property="total_clicks", type="string", description="Total result clicks", example="0"),
     *                 @OA\Property(property="click_through_rate", type="number", format="float", description="Click-through rate (%)", example=0),
     *                 @OA\Property(property="total_cart_adds", type="string", description="Total cart additions from search", example="0"),
     *                 @OA\Property(property="total_conversions", type="string", description="Total purchases from search", example="0"),
     *                 @OA\Property(property="conversion_rate", type="number", format="float", description="Overall search conversion rate (%)", example=0)
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-02-22T15:46:40.816692Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="8a91ac0c-89c2-4788-914d-91bb3e2999e9"),
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

    public function zeroResults(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date'   => ['nullable', 'date', 'after_or_equal:start_date'],
            'limit'      => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        [$startDate, $endDate] = $this->resolveDateRange($request);

        $zeroResults = $this->searchService->getZeroResultSearches(
            $startDate,
            $endDate,
            $request->integer('limit', 50)
        );

        return ApiResponse::success('Catalog gap metrics retrieved successfully', $zeroResults);
    }

    public function popular(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date'   => ['nullable', 'date', 'after_or_equal:start_date'],
            'limit'      => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        [$startDate, $endDate] = $this->resolveDateRange($request);

        $popular = $this->searchService->getPopularSearches(
            $startDate,
            $endDate,
            $request->integer('limit', 50)
        );

        return ApiResponse::success('Popular searches retrieved successfully', $popular);
    }

    public function metrics(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date'   => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        [$startDate, $endDate] = $this->resolveDateRange($request);

        $metrics = $this->searchService->getSearchConversionMetrics(
            $startDate,
            $endDate
        );

        return ApiResponse::success('Search conversion metrics retrieved successfully', $metrics);
    }

    public function refinements(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => ['required', 'date'],
            'end_date'   => ['required', 'date', 'after_or_equal:start_date'],
            'limit'      => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $refinements = $this->searchService->getSearchRefinements(
            new \DateTime($request->start_date),
            new \DateTime($request->end_date),
            $request->integer('limit', 20)
        );

        return response()->json([
            'success' => true,
            'data'    => $refinements,
        ]);
    }

    /**
     * Resolve start/end dates from the request, defaulting to the last 30 days.
     *
     * @return array{\DateTime, \DateTime}
     */
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
