<?php

namespace App\Http\Controllers\Api\Tenant\Shift;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Shift\StoreShiftSwapRequest;
use App\Http\Resources\Tenant\Shift\ShiftSwapRequestResource;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant\ShiftSwapRequest;
use App\Services\Tenant\Shift\ShiftSwapService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShiftSwapController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected ShiftSwapService $swapService
    ) {}

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/shift-swaps",
     *     summary="List shift swap records",
     *     description="Retrieves a paginated list of shift swap records with optional filtering by user or store",
     *     operationId="listShiftSwaps",
     *     tags={"Tenant - Shift Swaps"},
     *     @OA\Parameter(
     *         name="user_id",
     *         in="query",
     *         description="Filter by user ID (involved in swap)",
     *         required=false,
     *         example=1,
     *         @OA\Schema(
     *             type="integer"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="store_id",
     *         in="query",
     *         description="Filter by store",
     *         required=false,
     *         example=1,
     *         @OA\Schema(
     *             type="integer"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Items per page",
     *         required=false,
     *         example=15,
     *         @OA\Schema(
     *             type="integer"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Shift swap records retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="success",
     *                 type="boolean",
     *                 example=true
     *             ),
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Shift swap records retrieved successfully"
     *             ),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="swap_requests",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="requester_assignment_id", type="integer", example=2),
     *                         @OA\Property(
     *                             property="requester_assignment",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=2),
     *                             @OA\Property(property="shift_id", type="integer", example=2),
     *                             @OA\Property(
     *                                 property="shift",
     *                                 type="object",
     *                                 @OA\Property(property="id", type="integer", example=2),
     *                                 @OA\Property(property="shift_name", type="string", example="Morning Shift"),
     *                                 @OA\Property(property="store_id", type="integer", example=1),
     *                                 @OA\Property(property="scheduled_start_time", type="string", example="06:00"),
     *                                 @OA\Property(property="scheduled_end_time", type="string", example="13:00"),
     *                                 @OA\Property(property="duration_minutes", type="integer", example=480),
     *                                 @OA\Property(property="duration_hours", type="integer", example=8),
     *                                 @OA\Property(property="shift_time_range", type="string", example="06:00 - 13:00"),
     *                                 @OA\Property(property="applicable_days", type="array", @OA\Items(type="string"), example={"monday", "tuesday", "wednesday", "thursday", "friday"}),
     *                                 @OA\Property(property="is_company_wide", type="boolean", example=false),
     *                                 @OA\Property(property="is_active", type="boolean", example=true),
     *                                 @OA\Property(property="created_at", type="string", format="date-time", example="2026-01-05T08:55:10.000000Z"),
     *                                 @OA\Property(property="updated_at", type="string", format="date-time", example="2026-01-05T08:55:30.000000Z"),
     *                                 @OA\Property(property="deleted_at", type="string", format="date-time", nullable=true, example=null)
     *                             ),
     *                             @OA\Property(property="store_id", type="integer", example=1),
     *                             @OA\Property(property="user_id", type="integer", example=3),
     *                             @OA\Property(property="shift_date", type="string", format="date", example="2026-01-07"),
     *                             @OA\Property(property="scheduled_start_time", type="string", example="06:00"),
     *                             @OA\Property(property="scheduled_end_time", type="string", example="13:00"),
     *                             @OA\Property(property="actual_start", type="string", nullable=true, example=null),
     *                             @OA\Property(property="actual_end", type="string", nullable=true, example=null),
     *                             @OA\Property(property="actual_duration_minutes", type="integer", nullable=true, example=null),
     *                             @OA\Property(property="actual_duration_hours", type="number", nullable=true, example=null),
     *                             @OA\Property(property="status", type="string", example="scheduled"),
     *                             @OA\Property(property="status_label", type="string", example="Scheduled"),
     *                             @OA\Property(property="status_color", type="string", example="blue"),
     *                             @OA\Property(property="is_late", type="boolean", example=false),
     *                             @OA\Property(property="is_early_departure", type="boolean", example=false),
     *                             @OA\Property(property="has_significant_cash_variance", type="boolean", example=false),
     *                             @OA\Property(property="cash_variance_reason", type="string", nullable=true, example=null),
     *                             @OA\Property(property="overtime_minutes", type="integer", example=0),
     *                             @OA\Property(property="overtime_hours", type="number", example=0),
     *                             @OA\Property(property="has_overtime", type="boolean", example=false),
     *                             @OA\Property(property="notes", type="string", example="Swapped with user #3"),
     *                             @OA\Property(property="issues_reported", type="string", nullable=true, example=null),
     *                             @OA\Property(property="is_approved", type="boolean", example=false),
     *                             @OA\Property(property="approved_by", type="integer", nullable=true, example=null),
     *                             @OA\Property(property="approved_at", type="string", format="date-time", nullable=true, example=null),
     *                             @OA\Property(property="created_at", type="string", format="date-time", example="2026-01-05T09:58:40.000000Z"),
     *                             @OA\Property(property="updated_at", type="string", format="date-time", example="2026-01-05T20:16:53.000000Z")
     *                         ),
     *                         @OA\Property(property="target_assignment_id", type="integer", example=3),
     *                         @OA\Property(
     *                             property="target_assignment",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=3),
     *                             @OA\Property(property="shift_id", type="integer", example=2),
     *                             @OA\Property(
     *                                 property="shift",
     *                                 type="object",
     *                                 @OA\Property(property="id", type="integer", example=2),
     *                                 @OA\Property(property="shift_name", type="string", example="Morning Shift"),
     *                                 @OA\Property(property="store_id", type="integer", example=1),
     *                                 @OA\Property(property="scheduled_start_time", type="string", example="06:00"),
     *                                 @OA\Property(property="scheduled_end_time", type="string", example="13:00"),
     *                                 @OA\Property(property="duration_minutes", type="integer", example=480),
     *                                 @OA\Property(property="duration_hours", type="integer", example=8),
     *                                 @OA\Property(property="shift_time_range", type="string", example="06:00 - 13:00"),
     *                                 @OA\Property(property="applicable_days", type="array", @OA\Items(type="string"), example={"monday", "tuesday", "wednesday", "thursday", "friday"}),
     *                                 @OA\Property(property="is_company_wide", type="boolean", example=false),
     *                                 @OA\Property(property="is_active", type="boolean", example=true),
     *                                 @OA\Property(property="created_at", type="string", format="date-time", example="2026-01-05T08:55:10.000000Z"),
     *                                 @OA\Property(property="updated_at", type="string", format="date-time", example="2026-01-05T08:55:30.000000Z"),
     *                                 @OA\Property(property="deleted_at", type="string", format="date-time", nullable=true, example=null)
     *                             ),
     *                             @OA\Property(property="store_id", type="integer", example=1),
     *                             @OA\Property(property="user_id", type="integer", example=3),
     *                             @OA\Property(property="shift_date", type="string", format="date", example="2026-01-08"),
     *                             @OA\Property(property="scheduled_start_time", type="string", example="06:00"),
     *                             @OA\Property(property="scheduled_end_time", type="string", example="13:00"),
     *                             @OA\Property(property="actual_start", type="string", nullable=true, example=null),
     *                             @OA\Property(property="actual_end", type="string", nullable=true, example=null),
     *                             @OA\Property(property="actual_duration_minutes", type="integer", nullable=true, example=null),
     *                             @OA\Property(property="actual_duration_hours", type="number", nullable=true, example=null),
     *                             @OA\Property(property="status", type="string", example="scheduled"),
     *                             @OA\Property(property="status_label", type="string", example="Scheduled"),
     *                             @OA\Property(property="status_color", type="string", example="blue"),
     *                             @OA\Property(property="is_late", type="boolean", example=false),
     *                             @OA\Property(property="is_early_departure", type="boolean", example=false),
     *                             @OA\Property(property="has_significant_cash_variance", type="boolean", example=false),
     *                             @OA\Property(property="cash_variance_reason", type="string", nullable=true, example=null),
     *                             @OA\Property(property="overtime_minutes", type="integer", example=0),
     *                             @OA\Property(property="overtime_hours", type="number", example=0),
     *                             @OA\Property(property="has_overtime", type="boolean", example=false),
     *                             @OA\Property(property="notes", type="string", example="Swapped with user #3"),
     *                             @OA\Property(property="issues_reported", type="string", nullable=true, example=null),
     *                             @OA\Property(property="is_approved", type="boolean", example=false),
     *                             @OA\Property(property="approved_by", type="integer", nullable=true, example=null),
     *                             @OA\Property(property="approved_at", type="string", format="date-time", nullable=true, example=null),
     *                             @OA\Property(property="created_at", type="string", format="date-time", example="2026-01-05T09:58:40.000000Z"),
     *                             @OA\Property(property="updated_at", type="string", format="date-time", example="2026-01-05T20:16:53.000000Z")
     *                         ),
     *                         @OA\Property(property="requester_id", type="integer", example=3),
     *                         @OA\Property(
     *                             property="requester",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=3),
     *                             @OA\Property(property="name", type="string", example="Jane Cashier"),
     *                             @OA\Property(property="email", type="string", example="cashier@merchant.com")
     *                         ),
     *                         @OA\Property(property="target_user_id", type="integer", example=3),
     *                         @OA\Property(
     *                             property="target_user",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=3),
     *                             @OA\Property(property="name", type="string", example="Jane Cashier"),
     *                             @OA\Property(property="email", type="string", example="cashier@merchant.com")
     *                         ),
     *                         @OA\Property(property="reason", type="string", example="Employee requested to cover for colleague"),
     *                         @OA\Property(property="is_swapped", type="boolean", example=true),
     *                         @OA\Property(property="manager_id", type="integer", example=1),
     *                         @OA\Property(
     *                             property="manager",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="name", type="string", example="John Doe"),
     *                             @OA\Property(property="email", type="string", example="john@techhaven.com")
     *                         ),
     *                         @OA\Property(property="manager_note", type="string", example="Approved as both employees agreed"),
     *                         @OA\Property(property="swapped_at", type="string", format="date-time", example="2026-01-05T20:16:53.000000Z"),
     *                         @OA\Property(property="created_at", type="string", format="date-time", example="2026-01-05T20:16:53.000000Z"),
     *                         @OA\Property(property="updated_at", type="string", format="date-time", example="2026-01-05T20:16:53.000000Z")
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="pagination",
     *                     type="object",
     *                     @OA\Property(property="current_page", type="integer", example=1),
     *                     @OA\Property(property="last_page", type="integer", example=1),
     *                     @OA\Property(property="per_page", type="integer", example=15),
     *                     @OA\Property(property="total", type="integer", example=1),
     *                     @OA\Property(property="from", type="integer", example=1),
     *                     @OA\Property(property="to", type="integer", example=1)
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-01-05T20:20:47.662146Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="3471af2f-9bf6-4f67-a40b-b5e43d869131"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ShiftSwapRequest::class);

        $perPage = $request->input('per_page', 15);
        $userId = $request->input('user_id');
        $storeId = $request->input('store_id');

        $query = ShiftSwapRequest::with([
            'requesterAssignment.shift',
            'targetAssignment.shift',
            'requester',
            'targetUser',
            'manager'
        ]);

        // Filter by user (if they're involved in the swap)
        if ($userId) {
            $query->forUser($userId);
        }

        // Filter by store
        if ($storeId) {
            $query->whereHas('requesterAssignment', function ($q) use ($storeId) {
                $q->where('store_id', $storeId);
            });
        }

        $swapRequests = $query->recent()->paginate($perPage);

        return ApiResponse::success(
            'Shift swap records retrieved successfully',
            [
                'swap_requests' => ShiftSwapRequestResource::collection($swapRequests->items()),
                'pagination' => [
                    'current_page' => $swapRequests->currentPage(),
                    'last_page' => $swapRequests->lastPage(),
                    'per_page' => $swapRequests->perPage(),
                    'total' => $swapRequests->total(),
                    'from' => $swapRequests->firstItem(),
                    'to' => $swapRequests->lastItem(),
                ],
            ]
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/shift-swaps",
     *     summary="Execute a shift swap (manager only)",
     *     description="Manager swaps two shift assignments in one action. Both assignments must belong to different users.",
     *     operationId="createShiftSwap",
     *     tags={"Tenant - Shift Swaps"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"requester_assignment_id", "target_assignment_id", "reason"},
     *             @OA\Property(
     *                 property="requester_assignment_id",
     *                 type="integer",
     *                 description="First shift assignment ID",
     *                 example=2
     *             ),
     *             @OA\Property(
     *                 property="target_assignment_id",
     *                 type="integer",
     *                 description="Second shift assignment ID",
     *                 example=3
     *             ),
     *             @OA\Property(
     *                 property="reason",
     *                 type="string",
     *                 description="Reason for swap",
     *                 example="Employee requested to cover for colleague"
     *             ),
     *             @OA\Property(
     *                 property="manager_note",
     *                 type="string",
     *                 description="Optional manager note",
     *                 example="Approved as both employees agreed"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Shift swap executed successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Shift swap executed successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="swap_request",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="requester_assignment_id", type="integer", example=2),
     *                     @OA\Property(
     *                         property="requester_assignment",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=2),
     *                         @OA\Property(property="shift_id", type="integer", example=2),
     *                         @OA\Property(property="store_id", type="integer", example=1),
     *                         @OA\Property(property="user_id", type="integer", example=3),
     *                         @OA\Property(property="shift_date", type="string", format="date", example="2026-01-07"),
     *                         @OA\Property(property="scheduled_start_time", type="string", example="06:00"),
     *                         @OA\Property(property="scheduled_end_time", type="string", example="13:00"),
     *                         @OA\Property(property="actual_start", type="string", nullable=true, example=null),
     *                         @OA\Property(property="actual_end", type="string", nullable=true, example=null),
     *                         @OA\Property(property="actual_duration_minutes", type="integer", nullable=true, example=null),
     *                         @OA\Property(property="actual_duration_hours", type="number", nullable=true, example=null),
     *                         @OA\Property(property="status", type="string", example="scheduled"),
     *                         @OA\Property(property="status_label", type="string", example="Scheduled"),
     *                         @OA\Property(property="status_color", type="string", example="blue"),
     *                         @OA\Property(property="is_late", type="boolean", example=false),
     *                         @OA\Property(property="is_early_departure", type="boolean", example=false),
     *                         @OA\Property(property="has_significant_cash_variance", type="boolean", example=false),
     *                         @OA\Property(property="cash_variance_reason", type="string", nullable=true, example=null),
     *                         @OA\Property(property="overtime_minutes", type="integer", example=0),
     *                         @OA\Property(property="overtime_hours", type="number", example=0),
     *                         @OA\Property(property="has_overtime", type="boolean", example=false),
     *                         @OA\Property(property="notes", type="string", example="Swapped with user #3"),
     *                         @OA\Property(property="issues_reported", type="string", nullable=true, example=null),
     *                         @OA\Property(property="is_approved", type="boolean", example=false),
     *                         @OA\Property(property="approved_by", type="integer", nullable=true, example=null),
     *                         @OA\Property(property="approved_at", type="string", format="date-time", nullable=true, example=null),
     *                         @OA\Property(property="created_at", type="string", format="date-time", example="2026-01-05T09:58:40.000000Z"),
     *                         @OA\Property(property="updated_at", type="string", format="date-time", example="2026-01-05T20:16:53.000000Z")
     *                     ),
     *                     @OA\Property(property="target_assignment_id", type="integer", example=3),
     *                     @OA\Property(
     *                         property="target_assignment",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=3),
     *                         @OA\Property(property="shift_id", type="integer", example=2),
     *                         @OA\Property(property="store_id", type="integer", example=1),
     *                         @OA\Property(property="user_id", type="integer", example=3),
     *                         @OA\Property(property="shift_date", type="string", format="date", example="2026-01-08"),
     *                         @OA\Property(property="scheduled_start_time", type="string", example="06:00"),
     *                         @OA\Property(property="scheduled_end_time", type="string", example="13:00"),
     *                         @OA\Property(property="actual_start", type="string", nullable=true, example=null),
     *                         @OA\Property(property="actual_end", type="string", nullable=true, example=null),
     *                         @OA\Property(property="actual_duration_minutes", type="integer", nullable=true, example=null),
     *                         @OA\Property(property="actual_duration_hours", type="number", nullable=true, example=null),
     *                         @OA\Property(property="status", type="string", example="scheduled"),
     *                         @OA\Property(property="status_label", type="string", example="Scheduled"),
     *                         @OA\Property(property="status_color", type="string", example="blue"),
     *                         @OA\Property(property="is_late", type="boolean", example=false),
     *                         @OA\Property(property="is_early_departure", type="boolean", example=false),
     *                         @OA\Property(property="has_significant_cash_variance", type="boolean", example=false),
     *                         @OA\Property(property="cash_variance_reason", type="string", nullable=true, example=null),
     *                         @OA\Property(property="overtime_minutes", type="integer", example=0),
     *                         @OA\Property(property="overtime_hours", type="number", example=0),
     *                         @OA\Property(property="has_overtime", type="boolean", example=false),
     *                         @OA\Property(property="notes", type="string", example="Swapped with user #3"),
     *                         @OA\Property(property="issues_reported", type="string", nullable=true, example=null),
     *                         @OA\Property(property="is_approved", type="boolean", example=false),
     *                         @OA\Property(property="approved_by", type="integer", nullable=true, example=null),
     *                         @OA\Property(property="approved_at", type="string", format="date-time", nullable=true, example=null),
     *                         @OA\Property(property="created_at", type="string", format="date-time", example="2026-01-05T09:58:40.000000Z"),
     *                         @OA\Property(property="updated_at", type="string", format="date-time", example="2026-01-05T20:16:53.000000Z")
     *                     ),
     *                     @OA\Property(property="requester_id", type="integer", example=3),
     *                     @OA\Property(
     *                         property="requester",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=3),
     *                         @OA\Property(property="name", type="string", example="Jane Cashier"),
     *                         @OA\Property(property="email", type="string", example="cashier@merchant.com")
     *                     ),
     *                     @OA\Property(property="target_user_id", type="integer", example=3),
     *                     @OA\Property(
     *                         property="target_user",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=3),
     *                         @OA\Property(property="name", type="string", example="Jane Cashier"),
     *                         @OA\Property(property="email", type="string", example="cashier@merchant.com")
     *                     ),
     *                     @OA\Property(property="reason", type="string", example="Employee requested to cover for colleague"),
     *                     @OA\Property(property="is_swapped", type="boolean", example=true),
     *                     @OA\Property(property="manager_id", type="integer", example=1),
     *                     @OA\Property(
     *                         property="manager",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="John Doe"),
     *                         @OA\Property(property="email", type="string", example="john@techhaven.com")
     *                     ),
     *                     @OA\Property(property="manager_note", type="string", example="Approved as both employees agreed"),
     *                     @OA\Property(property="swapped_at", type="string", format="date-time", example="2026-01-05T20:16:53.000000Z"),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2026-01-05T20:16:53.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2026-01-05T20:16:53.000000Z")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-01-05T20:16:53.433285Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="eac6e8a4-62ce-4847-b0a0-3d2536f00031"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error - Cannot swap shifts with the same user",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Cannot swap shifts with the same user. Both assignments belong to the same employee."),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-01-05T21:17:43.286494Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="7764eeb3-76d4-4d90-83e6-0e8b0ba82a79"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     )
     * )
     */
    public function store(StoreShiftSwapRequest $request): JsonResponse
    {
        $swapRequest = $this->swapService->executeSwap($request->validated());

        return ApiResponse::created(
            'Shift swap executed successfully',
            ['swap_request' => new ShiftSwapRequestResource($swapRequest)]
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/shift-swaps/{id}",
     *     summary="Get swap record details",
     *     description="Retrieves detailed information about a specific shift swap record including all assignment details and involved users",
     *     operationId="getShiftSwapDetails",
     *     tags={"Tenant - Shift Swaps"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Swap request ID",
     *         required=true,
     *         example=1,
     *         @OA\Schema(
     *             type="integer"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Shift swap record retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Shift swap record retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="swap_request",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="requester_assignment_id", type="integer", example=2),
     *                     @OA\Property(
     *                         property="requester_assignment",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=2),
     *                         @OA\Property(property="shift_id", type="integer", example=2),
     *                         @OA\Property(
     *                             property="shift",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=2),
     *                             @OA\Property(property="shift_name", type="string", example="Morning Shift"),
     *                             @OA\Property(property="store_id", type="integer", example=1),
     *                             @OA\Property(property="scheduled_start_time", type="string", example="06:00"),
     *                             @OA\Property(property="scheduled_end_time", type="string", example="13:00"),
     *                             @OA\Property(property="duration_minutes", type="integer", example=480),
     *                             @OA\Property(property="duration_hours", type="integer", example=8),
     *                             @OA\Property(property="shift_time_range", type="string", example="06:00 - 13:00"),
     *                             @OA\Property(property="applicable_days", type="array", @OA\Items(type="string"), example={"monday", "tuesday", "wednesday", "thursday", "friday"}),
     *                             @OA\Property(property="is_company_wide", type="boolean", example=false),
     *                             @OA\Property(property="is_active", type="boolean", example=true),
     *                             @OA\Property(property="created_at", type="string", format="date-time", example="2026-01-05T08:55:10.000000Z"),
     *                             @OA\Property(property="updated_at", type="string", format="date-time", example="2026-01-05T08:55:30.000000Z"),
     *                             @OA\Property(property="deleted_at", type="string", format="date-time", nullable=true, example=null)
     *                         ),
     *                         @OA\Property(property="store_id", type="integer", example=1),
     *                         @OA\Property(property="user_id", type="integer", example=3),
     *                         @OA\Property(property="shift_date", type="string", format="date", example="2026-01-07"),
     *                         @OA\Property(property="scheduled_start_time", type="string", example="06:00"),
     *                         @OA\Property(property="scheduled_end_time", type="string", example="13:00"),
     *                         @OA\Property(property="actual_start", type="string", nullable=true, example=null),
     *                         @OA\Property(property="actual_end", type="string", nullable=true, example=null),
     *                         @OA\Property(property="actual_duration_minutes", type="integer", nullable=true, example=null),
     *                         @OA\Property(property="actual_duration_hours", type="number", nullable=true, example=null),
     *                         @OA\Property(property="status", type="string", example="scheduled"),
     *                         @OA\Property(property="status_label", type="string", example="Scheduled"),
     *                         @OA\Property(property="status_color", type="string", example="blue"),
     *                         @OA\Property(property="is_late", type="boolean", example=false),
     *                         @OA\Property(property="is_early_departure", type="boolean", example=false),
     *                         @OA\Property(property="has_significant_cash_variance", type="boolean", example=false),
     *                         @OA\Property(property="cash_variance_reason", type="string", nullable=true, example=null),
     *                         @OA\Property(property="overtime_minutes", type="integer", example=0),
     *                         @OA\Property(property="overtime_hours", type="number", example=0),
     *                         @OA\Property(property="has_overtime", type="boolean", example=false),
     *                         @OA\Property(property="notes", type="string", example="Swapped with user #3"),
     *                         @OA\Property(property="issues_reported", type="string", nullable=true, example=null),
     *                         @OA\Property(property="is_approved", type="boolean", example=false),
     *                         @OA\Property(property="approved_by", type="integer", nullable=true, example=null),
     *                         @OA\Property(property="approved_at", type="string", format="date-time", nullable=true, example=null),
     *                         @OA\Property(property="created_at", type="string", format="date-time", example="2026-01-05T09:58:40.000000Z"),
     *                         @OA\Property(property="updated_at", type="string", format="date-time", example="2026-01-05T20:16:53.000000Z")
     *                     ),
     *                     @OA\Property(property="target_assignment_id", type="integer", example=3),
     *                     @OA\Property(
     *                         property="target_assignment",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=3),
     *                         @OA\Property(property="shift_id", type="integer", example=2),
     *                         @OA\Property(
     *                             property="shift",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=2),
     *                             @OA\Property(property="shift_name", type="string", example="Morning Shift"),
     *                             @OA\Property(property="store_id", type="integer", example=1),
     *                             @OA\Property(property="scheduled_start_time", type="string", example="06:00"),
     *                             @OA\Property(property="scheduled_end_time", type="string", example="13:00"),
     *                             @OA\Property(property="duration_minutes", type="integer", example=480),
     *                             @OA\Property(property="duration_hours", type="integer", example=8),
     *                             @OA\Property(property="shift_time_range", type="string", example="06:00 - 13:00"),
     *                             @OA\Property(property="applicable_days", type="array", @OA\Items(type="string"), example={"monday", "tuesday", "wednesday", "thursday", "friday"}),
     *                             @OA\Property(property="is_company_wide", type="boolean", example=false),
     *                             @OA\Property(property="is_active", type="boolean", example=true),
     *                             @OA\Property(property="created_at", type="string", format="date-time", example="2026-01-05T08:55:10.000000Z"),
     *                             @OA\Property(property="updated_at", type="string", format="date-time", example="2026-01-05T08:55:30.000000Z"),
     *                             @OA\Property(property="deleted_at", type="string", format="date-time", nullable=true, example=null)
     *                         ),
     *                         @OA\Property(property="store_id", type="integer", example=1),
     *                         @OA\Property(property="user_id", type="integer", example=3),
     *                         @OA\Property(property="shift_date", type="string", format="date", example="2026-01-08"),
     *                         @OA\Property(property="scheduled_start_time", type="string", example="06:00"),
     *                         @OA\Property(property="scheduled_end_time", type="string", example="13:00"),
     *                         @OA\Property(property="actual_start", type="string", nullable=true, example=null),
     *                         @OA\Property(property="actual_end", type="string", nullable=true, example=null),
     *                         @OA\Property(property="actual_duration_minutes", type="integer", nullable=true, example=null),
     *                         @OA\Property(property="actual_duration_hours", type="number", nullable=true, example=null),
     *                         @OA\Property(property="status", type="string", example="scheduled"),
     *                         @OA\Property(property="status_label", type="string", example="Scheduled"),
     *                         @OA\Property(property="status_color", type="string", example="blue"),
     *                         @OA\Property(property="is_late", type="boolean", example=false),
     *                         @OA\Property(property="is_early_departure", type="boolean", example=false),
     *                         @OA\Property(property="has_significant_cash_variance", type="boolean", example=false),
     *                         @OA\Property(property="cash_variance_reason", type="string", nullable=true, example=null),
     *                         @OA\Property(property="overtime_minutes", type="integer", example=0),
     *                         @OA\Property(property="overtime_hours", type="number", example=0),
     *                         @OA\Property(property="has_overtime", type="boolean", example=false),
     *                         @OA\Property(property="notes", type="string", example="Swapped with user #3"),
     *                         @OA\Property(property="issues_reported", type="string", nullable=true, example=null),
     *                         @OA\Property(property="is_approved", type="boolean", example=false),
     *                         @OA\Property(property="approved_by", type="integer", nullable=true, example=null),
     *                         @OA\Property(property="approved_at", type="string", format="date-time", nullable=true, example=null),
     *                         @OA\Property(property="created_at", type="string", format="date-time", example="2026-01-05T09:58:40.000000Z"),
     *                         @OA\Property(property="updated_at", type="string", format="date-time", example="2026-01-05T20:16:53.000000Z")
     *                     ),
     *                     @OA\Property(property="requester_id", type="integer", example=3),
     *                     @OA\Property(
     *                         property="requester",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=3),
     *                         @OA\Property(property="name", type="string", example="Jane Cashier"),
     *                         @OA\Property(property="email", type="string", example="cashier@merchant.com")
     *                     ),
     *                     @OA\Property(property="target_user_id", type="integer", example=3),
     *                     @OA\Property(
     *                         property="target_user",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=3),
     *                         @OA\Property(property="name", type="string", example="Jane Cashier"),
     *                         @OA\Property(property="email", type="string", example="cashier@merchant.com")
     *                     ),
     *                     @OA\Property(property="reason", type="string", example="Employee requested to cover for colleague"),
     *                     @OA\Property(property="is_swapped", type="boolean", example=true),
     *                     @OA\Property(property="manager_id", type="integer", example=1),
     *                     @OA\Property(
     *                         property="manager",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="John Doe"),
     *                         @OA\Property(property="email", type="string", example="john@techhaven.com")
     *                     ),
     *                     @OA\Property(property="manager_note", type="string", example="Approved as both employees agreed"),
     *                     @OA\Property(property="swapped_at", type="string", format="date-time", example="2026-01-05T20:16:53.000000Z"),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2026-01-05T20:16:53.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2026-01-05T20:16:53.000000Z")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-01-05T20:22:51.369039Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="b5eb79a8-8e1e-4057-9ed6-f6968d984615"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     )
     * )
     */
    public function show(ShiftSwapRequest $swapRequest): JsonResponse
    {
        $this->authorize('view', $swapRequest);

        $swapRequest->load([
            'requesterAssignment.shift',
            'targetAssignment.shift',
            'requester',
            'targetUser',
            'manager'
        ]);

        return ApiResponse::success(
            'Shift swap record retrieved successfully',
            ['swap_request' => new ShiftSwapRequestResource($swapRequest)]
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/shift-swaps/statistics",
     *     summary="Get swap statistics",
     *     description="Retrieves statistical information about shift swaps including total swaps, swaps this month, and swaps this week",
     *     operationId="getShiftSwapStatistics",
     *     tags={"Tenant - Shift Swaps"},
     *     @OA\Parameter(
     *         name="store_id",
     *         in="query",
     *         description="Filter by store",
     *         required=false,
     *         example=1,
     *         @OA\Schema(
     *             type="integer"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Swap statistics retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="success",
     *                 type="boolean",
     *                 example=true
     *             ),
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Swap statistics retrieved successfully"
     *             ),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="statistics",
     *                     type="object",
     *                     @OA\Property(
     *                         property="total_swaps",
     *                         type="integer",
     *                         description="Total number of shift swaps",
     *                         example=1
     *                     ),
     *                     @OA\Property(
     *                         property="swaps_this_month",
     *                         type="integer",
     *                         description="Number of swaps in the current month",
     *                         example=1
     *                     ),
     *                     @OA\Property(
     *                         property="swaps_this_week",
     *                         type="integer",
     *                         description="Number of swaps in the current week",
     *                         example=1
     *                     )
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(
     *                     property="timestamp",
     *                     type="string",
     *                     format="date-time",
     *                     example="2026-01-05T20:25:00.591101Z"
     *                 ),
     *                 @OA\Property(
     *                     property="request_id",
     *                     type="string",
     *                     format="uuid",
     *                     example="c3a49823-3b5e-4bbf-8c35-0a98ceea73f1"
     *                 ),
     *                 @OA\Property(
     *                     property="tenant_id",
     *                     type="string",
     *                     format="uuid",
     *                     example="bbab2597-e1ae-466b-a071-83033841d2ed"
     *                 ),
     *                 @OA\Property(
     *                     property="tenant_name",
     *                     type="string",
     *                     nullable=true,
     *                     example=null
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function statistics(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ShiftSwapRequest::class);

        $storeId = $request->input('store_id');
        $stats = $this->swapService->getSwapStatistics($storeId);

        return ApiResponse::success(
            'Swap statistics retrieved successfully',
            ['statistics' => $stats]
        );
    }
}
