<?php

namespace App\Http\Controllers\Api\Tenant\Reports;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Reports\OwnerOverviewRequest;
use App\Http\Responses\ApiResponse;
use App\Services\Tenant\Reports\OwnerOverviewService;
use Illuminate\Http\JsonResponse;

class OwnerOverviewController extends Controller
{
    public function __construct(protected OwnerOverviewService $overviews) {}

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/reports/owner-overview",
     *     summary="Owner's numbers for a day or range",
     *     description="Sales, cost of goods, expenses (recurring bills spread per day), profit (VAT left out), money in by method, comparison (a day: same weekday last week + yesterday; a range: the period before), where money went, owed both ways, break-even daily sales, top sellers and — for all branches — a per-branch comparison. Days are shop-local (tenant timezone).",
     *     operationId="ownerOverview",
     *     tags={"Reports"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="from", in="query", required=true, @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="to", in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="store_id", in="query", description="Omit for all branches", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="top", in="query", @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=200, description="Overview"),
     *     @OA\Response(response=403, description="Owners and managers only")
     * )
     */
    public function show(OwnerOverviewRequest $request): JsonResponse
    {
        $from = $request->validated('from');

        return ApiResponse::success('Owner overview', $this->overviews->overview(
            $from,
            $request->validated('to') ?? $from,
            $request->validated('store_id') !== null ? (int) $request->validated('store_id') : null,
            (int) ($request->validated('top') ?? 5),
        ));
    }
}
