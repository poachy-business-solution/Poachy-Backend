<?php

namespace App\Http\Controllers\Api\Central\Sync;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Sync\InboundSaleDaysRequest;
use App\Http\Responses\ApiResponse;
use App\Services\Central\Billing\SaleDaysIntake;
use Illuminate\Http\JsonResponse;

class SaleDaysSyncController extends Controller
{
    /**
     * Receive a shop's sale-days report (which branches sold on which days).
     *
     * Server-to-server; the response is the acknowledgement. Idempotent.
     *
     * @OA\Post(
     *     path="/api/v1/central/sync/inbound/sale-days",
     *     summary="Report branch trading days (tenant → central)",
     *     description="Server-to-server. A shop reports, per branch, the Africa/Nairobi days in the last 7 days + today on which it recorded sales. Upserted on (tenant, store, date); re-sent hourly. Bearer = the shared central API token; X-Tenant-ID must equal tenant_id.",
     *     operationId="syncInboundSaleDays",
     *     tags={"Central - Sync"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Parameter(name="X-Tenant-ID", in="header", required=true, @OA\Schema(type="string")),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"tenant_id", "days"},
     *
     *             @OA\Property(property="tenant_id", type="string", example="da904b63-06a4-4519-9b33-b7aca5bf356d"),
     *             @OA\Property(
     *                 property="days",
     *                 type="array",
     *
     *                 @OA\Items(
     *                     required={"store_id", "date", "sales_count", "is_main_branch"},
     *
     *                     @OA\Property(property="store_id", type="integer", example=1),
     *                     @OA\Property(property="date", type="string", format="date", example="2026-10-11"),
     *                     @OA\Property(property="sales_count", type="integer", minimum=1, example=12),
     *                     @OA\Property(property="is_main_branch", type="boolean", example=true)
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=200, description="Stored", @OA\JsonContent(
     *
     *         @OA\Property(property="success", type="boolean", example=true),
     *         @OA\Property(property="message", type="string", example="Sale days received"),
     *         @OA\Property(property="data", type="object", @OA\Property(property="accepted", type="integer", example=4))
     *     )),
     *
     *     @OA\Response(response=403, description="Missing or wrong sync token"),
     *     @OA\Response(response=422, description="Unknown tenant, tenant/header mismatch, or a day outside the window"),
     *     @OA\Response(response=429, description="Too many reports")
     * )
     */
    public function store(InboundSaleDaysRequest $request, SaleDaysIntake $intake): JsonResponse
    {
        $accepted = $intake->store($request->string('tenant_id')->toString(), $request->validated('days'));

        return ApiResponse::success('Sale days received', ['accepted' => $accepted]);
    }
}
