<?php

namespace App\Http\Controllers\Api\Central\Marketplace\Analytics;

use App\Http\Controllers\Controller;
use App\Services\Central\Marketplace\Analytics\SearchAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchAnalyticsController extends Controller
{
    public function __construct(
        private readonly SearchAnalyticsService $searchService
    ) {}

    /**
     * Get zero-result searches (catalog gaps).
     *
     * GET /api/v1/central/marketplace/analytics/search/zero-results
     */
    public function zeroResults(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => ['required', 'date'],
            'end_date'   => ['required', 'date', 'after_or_equal:start_date'],
            'limit'      => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $zeroResults = $this->searchService->getZeroResultSearches(
            new \DateTime($request->start_date),
            new \DateTime($request->end_date),
            $request->integer('limit', 50)
        );

        return response()->json([
            'success' => true,
            'data'    => $zeroResults,
        ]);
    }

    /**
     * Get popular searches.
     *
     * GET /api/v1/central/marketplace/analytics/search/popular
     */
    public function popular(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => ['required', 'date'],
            'end_date'   => ['required', 'date', 'after_or_equal:start_date'],
            'limit'      => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $popular = $this->searchService->getPopularSearches(
            new \DateTime($request->start_date),
            new \DateTime($request->end_date),
            $request->integer('limit', 50)
        );

        return response()->json([
            'success' => true,
            'data'    => $popular,
        ]);
    }

    /**
     * Get search conversion metrics.
     *
     * GET /api/v1/central/marketplace/analytics/search/metrics
     */
    public function metrics(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => ['required', 'date'],
            'end_date'   => ['required', 'date', 'after_or_equal:start_date'],
        ]);

        $metrics = $this->searchService->getSearchConversionMetrics(
            new \DateTime($request->start_date),
            new \DateTime($request->end_date)
        );

        return response()->json([
            'success' => true,
            'data'    => $metrics,
        ]);
    }

    /**
     * Get search refinement patterns.
     *
     * GET /api/v1/central/marketplace/analytics/search/refinements
     */
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
}
