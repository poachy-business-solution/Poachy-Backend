<?php

namespace App\Http\Controllers\Api\Central\Marketplace\Analytics;

use App\Http\Controllers\Controller;
use App\Services\Central\Marketplace\Analytics\ProductAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductAnalyticsController extends Controller
{
    public function __construct(
        private readonly ProductAnalyticsService $productService
    ) {}

    /**
     * Get product performance metrics.
     *
     * GET /api/v1/central/marketplace/analytics/products/{productId}
     */
    public function show(int $productId, Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => ['required', 'date'],
            'end_date'   => ['required', 'date', 'after_or_equal:start_date'],
        ]);

        $performance = $this->productService->getProductPerformance(
            $productId,
            new \DateTime($request->start_date),
            new \DateTime($request->end_date)
        );

        return response()->json([
            'success' => true,
            'data'    => $performance,
        ]);
    }

    /**
     * Get top performing products.
     *
     * GET /api/v1/central/marketplace/analytics/products/top
     */
    public function top(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => ['required', 'date'],
            'end_date'   => ['required', 'date', 'after_or_equal:start_date'],
            'limit'      => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $topProducts = $this->productService->getTopProducts(
            new \DateTime($request->start_date),
            new \DateTime($request->end_date),
            $request->integer('limit', 10)
        );

        return response()->json([
            'success' => true,
            'data'    => $topProducts,
        ]);
    }

    /**
     * Get referrer source breakdown for a product.
     *
     * GET /api/v1/central/marketplace/analytics/products/{productId}/referrers
     */
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
}
