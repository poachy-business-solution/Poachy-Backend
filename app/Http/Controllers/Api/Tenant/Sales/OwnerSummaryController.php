<?php

namespace App\Http\Controllers\Api\Tenant\Sales;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Sales\OwnerSummaryRequest;
use App\Http\Responses\ApiResponse;
use App\Services\Tenant\Sales\OwnerSummaryService;
use Illuminate\Http\JsonResponse;

class OwnerSummaryController extends Controller
{
    public function __construct(protected OwnerSummaryService $summaryService) {}

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/reports/owner-summary",
     *     summary="The owner's day: takings net of refunds, money in by method, top sellers",
     *     description="Computed live from sales, payments and refunds. Cash is net of change given. Pass the device's UTC offset so 'the day' is the shop's local day.",
     *     operationId="ownerSummary",
     *     tags={"Daily Sales Reports"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="date", in="query", required=true, @OA\Schema(type="string", format="date", example="2026-10-08")),
     *     @OA\Parameter(name="utc_offset_minutes", in="query", required=false, @OA\Schema(type="integer", example=180)),
     *     @OA\Parameter(name="store_id", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="top", in="query", required=false, @OA\Schema(type="integer", example=5)),
     *
     *     @OA\Response(response=200, description="Summary for the day"),
     *     @OA\Response(response=403, description="Missing view-financial-reports")
     * )
     */
    public function show(OwnerSummaryRequest $request): JsonResponse
    {
        return ApiResponse::success('Owner summary retrieved successfully', $this->summaryService->forDay(
            $request->validated('date'),
            (int) ($request->validated('utc_offset_minutes') ?? 0),
            $request->validated('store_id') !== null ? (int) $request->validated('store_id') : null,
            (int) ($request->validated('top') ?? 5),
        ));
    }
}
