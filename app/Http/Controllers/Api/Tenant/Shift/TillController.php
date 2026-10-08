<?php

namespace App\Http\Controllers\Api\Tenant\Shift;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Shift\OpenTillRequest;
use App\Http\Resources\Tenant\Shift\ShiftAssignmentResource;
use App\Http\Responses\ApiResponse;
use App\Services\Tenant\Shift\TillService;
use Illuminate\Http\JsonResponse;

class TillController extends Controller
{
    public function __construct(protected TillService $tillService) {}

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/tills/open",
     *     summary="Open the caller's till at a store",
     *     description="Starts a till session for the signed-in user without needing a pre-scheduled shift or a clock-in window. Uses today's scheduled assignment at the store when one exists. Idempotent: returns the open till if it is already open here. Close it with the shift-assignment clock-out endpoint.",
     *     operationId="openTill",
     *     tags={"Shift Assignments"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"store_id","opening_cash"},
     *
     *             @OA\Property(property="store_id", type="integer", example=1),
     *             @OA\Property(property="opening_cash", type="number", example=2000),
     *             @OA\Property(property="notes", type="string", nullable=true)
     *         )
     *     ),
     *
     *     @OA\Response(response=201, description="Till opened"),
     *     @OA\Response(response=200, description="Till was already open at this store"),
     *     @OA\Response(response=422, description="Validation error, till open at another store, or today's till already closed")
     * )
     */
    public function open(OpenTillRequest $request): JsonResponse
    {
        $result = $this->tillService->open(
            $request->user()->id,
            (int) $request->validated('store_id'),
            (float) $request->validated('opening_cash'),
            $request->validated('notes'),
        );

        $data = ['assignment' => new ShiftAssignmentResource($result['assignment'])];

        return $result['already_open']
            ? ApiResponse::success('Till is already open', $data)
            : ApiResponse::created('Till opened', $data);
    }
}
