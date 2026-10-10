<?php

namespace App\Http\Controllers\Api\Tenant\Reports;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Reports\NeedsYouRequest;
use App\Http\Requests\Tenant\Reports\ReportPeriodRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant\Store;
use App\Services\Tenant\Reports\NeedsYouService;
use App\Services\Tenant\Reports\StaffReportService;
use App\Services\Tenant\Reports\StockReportService;
use App\Services\Tenant\Shift\TillService;
use Illuminate\Http\JsonResponse;

/**
 * Owner reports beyond the overview: staff, stock and time of day.
 */
class ReportsController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/tenant/reports/staff",
     *     summary="How each person at the counter did",
     *     description="Per person: sales, haggling uplift above the list price, lines sold below the list price (floor) and their cost, giveaways at list value, refunds paid out, and till sessions with over/short (counted sessions with a known float only).",
     *     operationId="staffReport",
     *     tags={"Reports"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="from", in="query", required=true, @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="to", in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="store_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="user_id", in="query", @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=200, description="Staff report")
     * )
     */
    public function staff(ReportPeriodRequest $request, StaffReportService $staff): JsonResponse
    {
        return ApiResponse::success('Staff report', $staff->forPeriod(
            $request->fromDate(),
            $request->toDate(),
            $request->storeId(),
            $request->validated('user_id') !== null ? (int) $request->validated('user_id') : null,
        ));
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/reports/sales-by-hour",
     *     summary="Sales per hour of the shop's day",
     *     operationId="salesByHourReport",
     *     tags={"Reports"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="from", in="query", required=true, @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="to", in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="store_id", in="query", @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=200, description="24 hourly buckets + busiest hour")
     * )
     */
    public function salesByHour(ReportPeriodRequest $request, StockReportService $reports): JsonResponse
    {
        return ApiResponse::success('Sales by hour', $reports->salesByHour($request->fromDate(), $request->toDate(), $request->storeId()));
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/reports/stock",
     *     summary="Stock health at a branch",
     *     description="Each stocked product: on hand, reorder level, sold in the last 30 days, days of stock left, last sold, value at cost, and status out | low (at/under reorder level or under 7 days left) | dead (nothing sold for `days`, default 30) | ok. Summary counts and dead stock value. Uses the first day of from as 'as of' (default today).",
     *     operationId="stockHealthReport",
     *     tags={"Reports"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="store_id", in="query", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="from", in="query", required=true, @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="days", in="query", @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=200, description="Stock health")
     * )
     */
    public function stock(ReportPeriodRequest $request, StockReportService $reports, TillService $tills): JsonResponse
    {
        $storeId = $request->storeId() ?? (int) Store::query()->where('is_active', true)->orderByDesc('is_main_store')->orderBy('id')->value('id');

        return ApiResponse::success('Stock health', $reports->stockHealth(
            $storeId,
            (int) ($request->validated('days') ?? 30),
            $request->fromDate(),
        ));
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/reports/needs-you",
     *     summary="What the owner should look at today",
     *     description="Plain-words items, most urgent first: low_stock, till_short (counted tills short by at least the variance threshold, last two days), expenses_to_approve, shop_owes (the neighbour shop owing longest), customers_owe. Each: type, title, subtitle, count, amount, ids. Waiting-to-sync sales live on the device and are added by the app.",
     *     operationId="needsYouReport",
     *     tags={"Reports"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="store_id", in="query", description="Omit for all branches", @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=200, description="Needs-you items")
     * )
     */
    public function needsYou(NeedsYouRequest $request, NeedsYouService $needsYou): JsonResponse
    {
        $storeId = $request->validated('store_id');

        return ApiResponse::success('Needs you', ['items' => $needsYou->items($storeId !== null ? (int) $storeId : null)]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/reports/owed",
     *     summary="Money owed both ways",
     *     description="Owed to you: customers on credit and neighbour shops; you owe: neighbour shops and suppliers. Oldest first.",
     *     operationId="owedReport",
     *     tags={"Reports"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(response=200, description="Owed both ways")
     * )
     */
    public function owed(NeedsYouService $needsYou): JsonResponse
    {
        return ApiResponse::success('Owed both ways', $needsYou->owedBothWays());
    }
}
