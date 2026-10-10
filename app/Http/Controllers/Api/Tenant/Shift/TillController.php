<?php

namespace App\Http\Controllers\Api\Tenant\Shift;

use App\Enums\Tenant\ShiftStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Shift\CurrentTillRequest;
use App\Http\Requests\Tenant\Shift\OpenTillRequest;
use App\Http\Requests\Tenant\Shift\TillHistoryRequest;
use App\Http\Requests\Tenant\Shift\UpdateTillSettingsRequest;
use App\Http\Resources\Tenant\Shift\ShiftAssignmentResource;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant\ShiftAssignment;
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
     *             required={"store_id"},
     *
     *             @OA\Property(property="store_id", type="integer", example=1),
     *             @OA\Property(property="opening_cash", type="number", nullable=true, example=2000, description="Counted float. Omit to carry the float forward from the branch's last close."),
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
            $request->validated('opening_cash') !== null ? (float) $request->validated('opening_cash') : null,
            $request->validated('notes'),
        );

        $data = ['assignment' => new ShiftAssignmentResource($result['assignment'])];

        return $result['already_open']
            ? ApiResponse::success('Till is already open', $data)
            : ApiResponse::created('Till opened', $data);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/tills/current",
     *     summary="The caller's open till at a store",
     *     description="Returns the caller's till session open today at the store (or null — one starts automatically with the first sale), the live expected cash in the drawer, whether the float is known, and whether this person must count to close.",
     *     operationId="currentTill",
     *     tags={"Shift Assignments"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="store_id", in="query", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=200, description="Current till (assignment may be null)"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function current(CurrentTillRequest $request): JsonResponse
    {
        $user = $request->user();
        $session = $this->tillService->openSessionFor($user->id, (int) $request->validated('store_id'));
        $carried = $session === null ? $this->tillService->carriedFloat((int) $request->validated('store_id')) : null;

        return ApiResponse::success('Current till', [
            'assignment' => $session ? new ShiftAssignmentResource($session->load(['shift', 'store', 'user'])) : null,
            'expected_cash' => $session?->expected_cash,
            'float_known' => $session ? $session->is_float_known : $carried['source'] !== ShiftAssignment::FLOAT_NOT_COUNTED,
            'next_float' => $carried,
            'count_required' => $this->tillService->cashCountRequired()
                && ! $user->hasAnyRole(['owner', 'manager', 'admin']),
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/tills/history",
     *     summary="Past till sessions",
     *     description="Closed and open till sessions, newest first. Staff see their own; owners and managers see everyone's and may filter by store and person.",
     *     operationId="tillHistory",
     *     tags={"Shift Assignments"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="store_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="user_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="from", in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="to", in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=200, description="Paginated till sessions")
     * )
     */
    public function history(TillHistoryRequest $request): JsonResponse
    {
        $user = $request->user();
        $management = $user->hasAnyRole(['owner', 'manager', 'admin']);

        $sessions = ShiftAssignment::query()
            ->whereIn('status', [ShiftStatus::IN_PROGRESS, ShiftStatus::COMPLETED])
            ->whereNotNull('actual_start')
            ->when(! $management, fn ($query) => $query->where('user_id', $user->id))
            ->when($management && $request->validated('user_id'), fn ($query, $userId) => $query->where('user_id', $userId))
            ->when($request->validated('store_id'), fn ($query, $storeId) => $query->where('store_id', $storeId))
            ->when($request->validated('from'), fn ($query, $from) => $query->whereDate('shift_date', '>=', $from))
            ->when($request->validated('to'), fn ($query, $to) => $query->whereDate('shift_date', '<=', $to))
            ->with(['shift', 'store', 'user'])
            ->orderByDesc('actual_start')
            ->paginate((int) ($request->validated('per_page') ?? 20));

        return ApiResponse::paginated(ShiftAssignmentResource::collection($sessions), 'Till history');
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/tills/settings",
     *     summary="How tills close in this shop",
     *     operationId="tillSettings",
     *     tags={"Shift Assignments"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(response=200, description="Till settings")
     * )
     */
    public function settings(): JsonResponse
    {
        return ApiResponse::success('Till settings', $this->settingsPayload());
    }

    /**
     * @OA\Put(
     *     path="/api/v1/tenant/tills/settings",
     *     summary="Change how tills close (owner/manager)",
     *     description="require_cash_count: staff must count the cash to close their till; when false closing can be skipped and owners still see the expected figure.",
     *     operationId="updateTillSettings",
     *     tags={"Shift Assignments"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(required={"require_cash_count"}, @OA\Property(property="require_cash_count", type="boolean"))),
     *
     *     @OA\Response(response=200, description="Updated"),
     *     @OA\Response(response=403, description="Not an owner or manager")
     * )
     */
    public function updateSettings(UpdateTillSettingsRequest $request): JsonResponse
    {
        $this->tillService->setCashCountRequired($request->boolean('require_cash_count'));

        return ApiResponse::success('Till settings saved', $this->settingsPayload());
    }

    /**
     * @return array{require_cash_count: bool, timezone: string}
     */
    protected function settingsPayload(): array
    {
        return [
            'require_cash_count' => $this->tillService->cashCountRequired(),
            'timezone' => $this->tillService->timezone(),
        ];
    }
}
