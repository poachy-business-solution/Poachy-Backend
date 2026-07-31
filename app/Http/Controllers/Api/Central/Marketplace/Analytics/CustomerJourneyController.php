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
     * @OA\Get(
     *     path="/api/v1/central/reports/journey/{sessionUuid}",
     *     summary="Get session journey reconstruction",
     *     description="Reconstructs the ordered sequence of tracked events (product views, cart actions, checkout, purchase, etc.) for a single browsing session. Requires admin authentication.",
     *     operationId="getCustomerSessionJourney",
     *     tags={"Central - Analytics - Customer Journey"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="sessionUuid",
     *         in="path",
     *         description="Session UUID to reconstruct the journey for",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Session journey retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="event_type", type="string", example="product_view"),
     *                     @OA\Property(property="event_timestamp", type="string", format="date-time", example="2026-06-15T10:20:00Z"),
     *                     @OA\Property(
     *                         property="product",
     *                         type="object",
     *                         nullable=true,
     *                         @OA\Property(property="id", type="integer", example=2),
     *                         @OA\Property(property="name", type="string", example="TCL 55 4K UHD Smart LED TV")
     *                     ),
     *                     @OA\Property(
     *                         property="tenant",
     *                         type="object",
     *                         nullable=true,
     *                         @OA\Property(property="id", type="string", format="uuid"),
     *                         @OA\Property(property="name", type="string", example="Acme Electronics")
     *                     ),
     *                     @OA\Property(property="event_properties", type="object", nullable=true),
     *                     @OA\Property(property="page_url", type="string", nullable=true, example="/products/tcl-55-4k")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(@OA\Property(property="message", type="string", example="Unauthenticated."))
     *     )
     * )
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
     * @OA\Get(
     *     path="/api/v1/central/reports/journey/paths",
     *     summary="Get common conversion paths",
     *     description="Groups tracked events by session to build event-type sequences (e.g. 'product_view → add_to_cart → purchase'), then returns the most frequent paths for the given date range. Requires admin authentication.",
     *     operationId="getCommonConversionPaths",
     *     tags={"Central - Analytics - Customer Journey"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="start_date",
     *         in="query",
     *         description="Start date for the analysis window",
     *         required=true,
     *         @OA\Schema(type="string", format="date", example="2026-06-01")
     *     ),
     *     @OA\Parameter(
     *         name="end_date",
     *         in="query",
     *         description="End date for the analysis window",
     *         required=true,
     *         @OA\Schema(type="string", format="date", example="2026-06-30")
     *     ),
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         description="Maximum number of paths to return (default 10)",
     *         required=false,
     *         @OA\Schema(type="integer", minimum=1, maximum=100, example=10)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Conversion paths retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="path", type="string", example="product_view → add_to_cart → purchase"),
     *                     @OA\Property(property="count", type="integer", example=18)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(@OA\Property(property="message", type="string", example="Unauthenticated."))
     *     )
     * )
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
