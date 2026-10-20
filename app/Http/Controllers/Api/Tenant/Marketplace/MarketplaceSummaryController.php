<?php

namespace App\Http\Controllers\Api\Tenant\Marketplace;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Marketplace\MarketplaceSummaryRequest;
use App\Http\Responses\ApiResponse;
use App\Services\Tenant\Marketplace\MarketplaceSummaryService;
use Illuminate\Http\JsonResponse;

class MarketplaceSummaryController extends Controller
{
    public function __construct(private readonly MarketplaceSummaryService $summary) {}

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/marketplace/summary",
     *     summary="Marketplace tab: online orders, sales, reservations, earnings and alerts",
     *     description="Owners and managers (view-marketplace-sales). Counts of online orders by step (to prepare, preparing, ready, overdue = not handed over 24 h after payment, delivered in 7 days), paid online sales today / 7 / 30 days, open reservations, earnings from the payout ledger for the last 30 days (sold = handed over, fees, commission, clawbacks, net, paid out) plus held / available balances and the Needs-you alerts (online_overdue, online_orders, reservations_arriving).",
     *     operationId="tenantMarketplaceSummary",
     *     tags={"Tenant - Marketplace"},
     *     security={{"sanctum": {}}},
     *
     *     @OA\Parameter(name="store_id", in="query", required=false, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=200, description="Summary", @OA\JsonContent(
     *
     *         @OA\Property(property="success", type="boolean", example=true),
     *         @OA\Property(property="data", type="object",
     *             @OA\Property(property="orders", type="object",
     *                 @OA\Property(property="to_prepare", type="integer"), @OA\Property(property="preparing", type="integer"),
     *                 @OA\Property(property="ready", type="integer"), @OA\Property(property="overdue", type="integer"),
     *                 @OA\Property(property="delivered_7d", type="integer")),
     *             @OA\Property(property="sales", type="object"),
     *             @OA\Property(property="reservations", type="object", @OA\Property(property="open", type="integer"), @OA\Property(property="expiring_today", type="integer")),
     *             @OA\Property(property="earnings", type="object",
     *                 @OA\Property(property="gross_30d", type="number"), @OA\Property(property="fees_30d", type="number"),
     *                 @OA\Property(property="clawbacks_30d", type="number"), @OA\Property(property="net_30d", type="number"),
     *                 @OA\Property(property="paid_out_30d", type="number"), @OA\Property(property="commission_30d", type="number"),
     *                 @OA\Property(property="held", type="number"), @OA\Property(property="available", type="number"), @OA\Property(property="payouts_enabled", type="boolean")),
     *             @OA\Property(property="needs_you", type="array", @OA\Items(type="object",
     *                 @OA\Property(property="type", type="string", example="online_orders"), @OA\Property(property="title", type="string"),
     *                 @OA\Property(property="subtitle", type="string"), @OA\Property(property="count", type="integer")))
     *         )
     *     )),
     *
     *     @OA\Response(response=401, description="Signed out"),
     *     @OA\Response(response=403, description="Role without view-marketplace-sales (e.g. cashier)")
     * )
     */
    public function show(MarketplaceSummaryRequest $request): JsonResponse
    {
        $storeId = $request->validated('store_id');

        return ApiResponse::success('Marketplace summary', $this->summary->summary($storeId !== null ? (int) $storeId : null));
    }
}
