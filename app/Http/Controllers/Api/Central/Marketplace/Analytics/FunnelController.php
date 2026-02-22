<?php

namespace App\Http\Controllers\Api\Central\Marketplace\Analytics;

use App\Http\Controllers\Controller;
use App\Services\Central\Marketplace\Analytics\FunnelAnalysisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FunnelController extends Controller
{
    public function __construct(
        private readonly FunnelAnalysisService $funnelService
    ) {}

    /**
     * Get conversion funnel metrics.
     *
     * GET /api/v1/central/marketplace/analytics/funnel
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => ['required', 'date'],
            'end_date'   => ['required', 'date', 'after_or_equal:start_date'],
        ]);

        $funnel = $this->funnelService->getConversionFunnel(
            new \DateTime($request->start_date),
            new \DateTime($request->end_date)
        );

        return response()->json([
            'success' => true,
            'data'    => $funnel,
        ]);
    }

    /**
     * Get abandonment rates by checkout step.
     *
     * GET /api/v1/central/marketplace/analytics/funnel/abandonment
     */
    public function abandonment(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => ['required', 'date'],
            'end_date'   => ['required', 'date', 'after_or_equal:start_date'],
        ]);

        $abandonment = $this->funnelService->getAbandonmentRates(
            new \DateTime($request->start_date),
            new \DateTime($request->end_date)
        );

        return response()->json([
            'success' => true,
            'data'    => $abandonment,
        ]);
    }

    /**
     * Get conversion rates by device type.
     *
     * GET /api/v1/central/marketplace/analytics/funnel/by-device
     */
    public function byDevice(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => ['required', 'date'],
            'end_date'   => ['required', 'date', 'after_or_equal:start_date'],
        ]);

        $devices = $this->funnelService->getConversionByDevice(
            new \DateTime($request->start_date),
            new \DateTime($request->end_date)
        );

        return response()->json([
            'success' => true,
            'data'    => $devices,
        ]);
    }

    /**
     * Get average time to purchase.
     *
     * GET /api/v1/central/marketplace/analytics/funnel/time-to-purchase
     */
    public function timeToPurchase(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => ['required', 'date'],
            'end_date'   => ['required', 'date', 'after_or_equal:start_date'],
        ]);

        $timings = $this->funnelService->getAverageTimeToPurchase(
            new \DateTime($request->start_date),
            new \DateTime($request->end_date)
        );

        return response()->json([
            'success' => true,
            'data'    => $timings,
        ]);
    }
}
