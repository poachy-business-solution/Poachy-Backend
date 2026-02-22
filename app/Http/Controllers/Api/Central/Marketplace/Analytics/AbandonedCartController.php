<?php

namespace App\Http\Controllers\Api\Central\Marketplace\Analytics;

use App\Http\Controllers\Controller;
use App\Services\Central\Marketplace\Analytics\AbandonedCartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AbandonedCartController extends Controller
{
    public function __construct(
        private readonly AbandonedCartService $cartService
    ) {}

    /**
     * Get cart abandonment statistics.
     *
     * GET /api/v1/central/marketplace/analytics/abandoned-carts/stats
     */
    public function stats(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => ['required', 'date'],
            'end_date'   => ['required', 'date', 'after_or_equal:start_date'],
        ]);

        $stats = $this->cartService->getAbandonmentStats(
            new \DateTime($request->start_date),
            new \DateTime($request->end_date)
        );

        return response()->json([
            'success' => true,
            'data'    => $stats,
        ]);
    }

    /**
     * Get abandoned carts eligible for email recovery.
     *
     * GET /api/v1/central/marketplace/analytics/abandoned-carts/email-eligible
     */
    public function emailEligible(Request $request): JsonResponse
    {
        $request->validate([
            'since' => ['nullable', 'date'],
        ]);

        $since = $request->has('since') ? new \DateTime($request->since) : null;
        $eligible = $this->cartService->getEmailEligibleCarts($since);

        return response()->json([
            'success' => true,
            'data'    => $eligible,
        ]);
    }

    /**
     * Get abandoned carts eligible for SMS recovery.
     *
     * GET /api/v1/central/marketplace/analytics/abandoned-carts/sms-eligible
     */
    public function smsEligible(Request $request): JsonResponse
    {
        $request->validate([
            'since' => ['nullable', 'date'],
        ]);

        $since = $request->has('since') ? new \DateTime($request->since) : null;
        $eligible = $this->cartService->getSMSEligibleCarts($since);

        return response()->json([
            'success' => true,
            'data'    => $eligible,
        ]);
    }
}
