<?php

namespace App\Http\Controllers\Api\Central\Marketplace\Analytics;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\Central\Marketplace\Analytics\FunnelAnalysisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FunnelController extends Controller
{
    public function __construct(
        private readonly FunnelAnalysisService $funnelService
    ) {}

    /**
     * @OA\Get(
     *     path="/api/v1/central/reports/funnel",
     *     summary="Get conversion funnel metrics",
     *     description="Retrieves comprehensive conversion funnel statistics showing progression from product views through to order confirmation. Includes conversion rates at each stage. Date range defaults to last 30 days if not specified. Requires admin authentication.",
     *     operationId="getConversionFunnel",
     *     tags={"Central - Analytics - Funnel"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="start_date",
     *         in="query",
     *         description="Start date for analysis (defaults to 30 days ago)",
     *         required=false,
     *         @OA\Schema(type="string", format="date", example="2026-01-23")
     *     ),
     *     @OA\Parameter(
     *         name="end_date",
     *         in="query",
     *         description="End date for analysis (defaults to today)",
     *         required=false,
     *         @OA\Schema(type="string", format="date", example="2026-02-22")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Conversion funnel retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Conversion funnel retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="product_views", type="integer", description="Total product views", example=1),
     *                 @OA\Property(property="carts_created", type="integer", description="Number of carts created", example=19),
     *                 @OA\Property(property="checkouts_initiated", type="integer", description="Number of checkouts started", example=2),
     *                 @OA\Property(property="payments_initiated", type="integer", description="Number of payments initiated", example=13),
     *                 @OA\Property(property="payments_completed", type="integer", description="Number of payments completed", example=9),
     *                 @OA\Property(property="orders_confirmed", type="integer", description="Number of orders confirmed", example=6),
     *                 @OA\Property(property="view_to_cart_rate", type="number", format="float", description="Conversion rate from view to cart (%)", example=1900),
     *                 @OA\Property(property="cart_to_checkout_rate", type="number", format="float", description="Conversion rate from cart to checkout (%)", example=10.53),
     *                 @OA\Property(property="checkout_to_payment_rate", type="number", format="float", description="Conversion rate from checkout to payment (%)", example=650),
     *                 @OA\Property(property="payment_success_rate", type="number", format="float", description="Payment completion rate (%)", example=69.23),
     *                 @OA\Property(property="overall_conversion_rate", type="number", format="float", description="Overall view to order conversion (%)", example=600)
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-02-22T13:22:55.517636Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="89f2d7b8-c8c9-4326-ab14-1f017b8d9a74"),
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
     *         response=403,
     *         description="Forbidden - insufficient privileges",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="You do not have permission to access this resource."),
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
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date'   => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        [$startDate, $endDate] = $this->resolveDateRange($request);

        $funnel = $this->funnelService->getConversionFunnel($startDate, $endDate);

        return ApiResponse::success('Conversion funnel retrieved successfully', $funnel);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/central/reports/funnel/abandonment",
     *     summary="Get cart abandonment metrics",
     *     description="Retrieves abandonment rates and breakdown by checkout stage. Shows where customers drop off in the checkout process. Date range defaults to last 30 days if not specified. Requires admin authentication.",
     *     operationId="getAbandonmentRates",
     *     tags={"Central - Analytics - Funnel"},
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
     *         description="Abandonment rates retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Abandonment rates retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="total_sessions", type="integer", description="Total checkout sessions", example=2),
     *                 @OA\Property(property="abandoned_sessions", type="string", description="Number of abandoned sessions", example="0"),
     *                 @OA\Property(property="abandonment_rate", type="number", format="float", description="Overall abandonment rate (%)", example=0),
     *                 @OA\Property(property="abandoned_at_cart", type="string", description="Abandoned at cart stage", example="0"),
     *                 @OA\Property(property="abandoned_at_shipping", type="string", description="Abandoned at shipping stage", example="0"),
     *                 @OA\Property(property="abandoned_at_payment", type="string", description="Abandoned at payment stage", example="0"),
     *                 @OA\Property(property="abandoned_at_review", type="string", description="Abandoned at review stage", example="0")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-02-22T13:24:01.244308Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="8fc09793-cd27-4f5a-a464-54d1ed8a584c"),
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
    public function abandonment(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date'   => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        [$startDate, $endDate] = $this->resolveDateRange($request);

        $abandonment = $this->funnelService->getAbandonmentRates($startDate, $endDate);

        return ApiResponse::success('Abandonment rates retrieved successfully', $abandonment);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/central/reports/funnel/by-device",
     *     summary="Get conversion rates by device type",
     *     description="Retrieves conversion rates broken down by device type (mobile, tablet, desktop, unknown). Shows cart creation and conversion statistics per device. Date range defaults to last 30 days if not specified. Requires admin authentication.",
     *     operationId="getDeviceConversionRates",
     *     tags={"Central - Analytics - Funnel"},
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
     *         description="Device conversion rates retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Device conversion rates retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="device_type", type="string", example="unknown"),
     *                     @OA\Property(property="total_carts", type="integer", description="Number of carts created on this device", example=19),
     *                     @OA\Property(property="converted_carts", type="string", description="Number of converted carts", example="13"),
     *                     @OA\Property(property="conversion_rate", type="number", format="float", description="Conversion rate (%)", example=68.42)
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-02-22T13:26:13.763886Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="e166150f-109c-4d61-965d-e0314b143ced"),
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
    public function byDevice(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date'   => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        [$startDate, $endDate] = $this->resolveDateRange($request);

        $devices = $this->funnelService->getConversionByDevice($startDate, $endDate);

        return ApiResponse::success('Device conversion rates retrieved successfully', $devices);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/central/reports/funnel/time-to-purchase",
     *     summary="Get time to purchase metrics",
     *     description="Retrieves timing statistics from cart creation to purchase completion. Shows average, minimum, and maximum purchase times. Date range defaults to last 30 days if not specified. Requires admin authentication.",
     *     operationId="getTimeToPurchase",
     *     tags={"Central - Analytics - Funnel"},
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
     *         description="Time to purchase metrics retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Time to purchase metrics retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="average_seconds", type="integer", description="Average time in seconds", example=4423),
     *                 @OA\Property(property="average_minutes", type="number", format="float", description="Average time in minutes", example=73.71),
     *                 @OA\Property(property="min_seconds", type="integer", description="Minimum time in seconds", example=24),
     *                 @OA\Property(property="max_seconds", type="integer", description="Maximum time in seconds", example=50130)
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-02-22T13:26:44.301835Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="49d48908-6c7e-4890-8163-01535a945a9a"),
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
    public function timeToPurchase(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date'   => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        [$startDate, $endDate] = $this->resolveDateRange($request);

        $timings = $this->funnelService->getAverageTimeToPurchase($startDate, $endDate);

        return ApiResponse::success('Time to purchase metrics retrieved successfully', $timings);
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
