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
     * @OA\Get(
     *     path="/api/v1/central/reports/abandoned-carts/stats",
     *     summary="Get cart abandonment statistics",
     *     description="Retrieves aggregate cart abandonment counts and rates for the given date range, including how many recovery emails/SMS have already been sent. Requires admin authentication.",
     *     operationId="getAbandonedCartStats",
     *     tags={"Central - Analytics - Abandoned Cart"},
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
     *     @OA\Response(
     *         response=200,
     *         description="Abandonment statistics retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="total_carts", type="integer", example=42),
     *                 @OA\Property(property="abandoned_carts", type="integer", example=17),
     *                 @OA\Property(property="converted_carts", type="integer", example=25),
     *                 @OA\Property(property="abandonment_rate", type="number", format="float", description="Abandoned/total (%)", example=40.48),
     *                 @OA\Property(property="recovery_emails_sent", type="integer", example=9),
     *                 @OA\Property(property="recovery_sms_sent", type="integer", example=5)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(@OA\Property(property="message", type="string", example="Unauthenticated."))
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(@OA\Property(property="message", type="string", example="The start date field is required."))
     *     )
     * )
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
     * @OA\Get(
     *     path="/api/v1/central/reports/abandoned-carts/email-eligible",
     *     summary="Get abandoned carts eligible for email recovery",
     *     description="Retrieves abandoned carts that have not yet received a recovery email, belong to a customer who accepts marketing, and (if provided) were abandoned since the given timestamp. Requires admin authentication.",
     *     operationId="getEmailEligibleAbandonedCarts",
     *     tags={"Central - Analytics - Abandoned Cart"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="since",
     *         in="query",
     *         description="Only include carts abandoned on or after this date (defaults to no lower bound)",
     *         required=false,
     *         @OA\Schema(type="string", format="date", example="2026-06-01")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Eligible carts retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="cart_id", type="integer", example=42),
     *                     @OA\Property(property="customer_id", type="integer", example=7),
     *                     @OA\Property(property="email", type="string", format="email", example="jane@example.com"),
     *                     @OA\Property(property="item_count", type="integer", example=3),
     *                     @OA\Property(property="subtotal", type="number", format="float", example=45000),
     *                     @OA\Property(property="abandoned_at", type="string", format="date-time", example="2026-06-15T10:22:00Z")
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
     * @OA\Get(
     *     path="/api/v1/central/reports/abandoned-carts/sms-eligible",
     *     summary="Get abandoned carts eligible for SMS recovery",
     *     description="Retrieves abandoned carts that have not yet received a recovery SMS, belong to a customer with a verified phone who accepts SMS, and (if provided) were abandoned since the given timestamp. Requires admin authentication.",
     *     operationId="getSmsEligibleAbandonedCarts",
     *     tags={"Central - Analytics - Abandoned Cart"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="since",
     *         in="query",
     *         description="Only include carts abandoned on or after this date (defaults to no lower bound)",
     *         required=false,
     *         @OA\Schema(type="string", format="date", example="2026-06-01")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Eligible carts retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="cart_id", type="integer", example=42),
     *                     @OA\Property(property="customer_id", type="integer", example=7),
     *                     @OA\Property(property="phone", type="string", example="+254712345678"),
     *                     @OA\Property(property="item_count", type="integer", example=3),
     *                     @OA\Property(property="subtotal", type="number", format="float", example=45000),
     *                     @OA\Property(property="abandoned_at", type="string", format="date-time", example="2026-06-15T10:22:00Z")
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
