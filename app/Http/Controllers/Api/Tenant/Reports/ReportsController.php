<?php

namespace App\Http\Controllers\Api\Tenant\Reports;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Reports\ReportPeriodRequest;
use App\Http\Responses\ApiResponse;
use App\Services\Tenant\Reports\StaffReportService;
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
}
