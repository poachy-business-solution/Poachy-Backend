<?php

namespace App\Http\Controllers\Api\Central\Marketplace\Analytics;

use App\Http\Controllers\Controller;
use App\Services\Central\Marketplace\Analytics\CustomerJourneyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerJourneyController extends Controller
{
    public function __construct(
        private readonly CustomerJourneyService $journeyService
    ) {}

    /**
     * Get session journey reconstruction.
     *
     * GET /api/v1/central/marketplace/analytics/journey/{sessionUuid}
     */
    public function show(string $sessionUuid): JsonResponse
    {
        $journey = $this->journeyService->getSessionJourney($sessionUuid);

        return response()->json([
            'success' => true,
            'data'    => $journey,
        ]);
    }

    /**
     * Get common conversion paths.
     *
     * GET /api/v1/central/marketplace/analytics/journey/paths
     */
    public function paths(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => ['required', 'date'],
            'end_date'   => ['required', 'date', 'after_or_equal:start_date'],
            'limit'      => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $paths = $this->journeyService->getCommonConversionPaths(
            new \DateTime($request->start_date),
            new \DateTime($request->end_date),
            $request->integer('limit', 10)
        );

        return response()->json([
            'success' => true,
            'data'    => $paths,
        ]);
    }
}
