<?php

namespace App\Http\Controllers\Api\Tenant\Shift;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Shift\ApproveShiftRequest;
use App\Http\Requests\Tenant\Shift\BulkStoreShiftAssignmentRequest;
use App\Http\Requests\Tenant\Shift\CancelShiftAssignmentRequest;
use App\Http\Requests\Tenant\Shift\ClockInRequest;
use App\Http\Requests\Tenant\Shift\ClockOutRequest;
use App\Http\Requests\Tenant\Shift\StoreShiftAssignmentRequest;
use App\Http\Resources\Tenant\Shift\ShiftAssignmentResource;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant\Shift;
use App\Models\Tenant\ShiftAssignment;
use App\Services\Tenant\Shift\ShiftAssignmentService;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShiftAssignmentController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected ShiftAssignmentService $assignmentService
    ) {}

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/shift-assignments",
     *     summary="List shift assignments",
     *     description="Retrieve a paginated list of shift assignments with optional filtering",
     *     operationId="listShiftAssignments",
     *     tags={"Shift Assignments"},
     *     @OA\Parameter(
     *         name="user_id",
     *         in="query",
     *         description="Filter by user ID",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="store_id",
     *         in="query",
     *         description="Filter by store ID",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="shift_id",
     *         in="query",
     *         description="Filter by shift ID",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter by status",
     *         required=false,
     *         @OA\Schema(type="string", example="scheduled")
     *     ),
     *     @OA\Parameter(
     *         name="date_from",
     *         in="query",
     *         description="Start date filter",
     *         required=false,
     *         @OA\Schema(type="string", format="date", example="2025-01-01")
     *     ),
     *     @OA\Parameter(
     *         name="date_to",
     *         in="query",
     *         description="End date filter",
     *         required=false,
     *         @OA\Schema(type="string", format="date", example="2025-01-31")
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Items per page",
     *         required=false,
     *         @OA\Schema(type="integer", example=15)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Shift assignments retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Shift assignments retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="assignments",
     *                     type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="shift_id", type="integer", example=2),
     *                         @OA\Property(
     *                             property="shift",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=2),
     *                             @OA\Property(property="shift_name", type="string", example="Morning Shift"),
     *                             @OA\Property(property="store_id", type="integer", example=1),
     *                             @OA\Property(property="scheduled_start_time", type="string", example="06:00"),
     *                             @OA\Property(property="scheduled_end_time", type="string", example="14:00"),
     *                             @OA\Property(property="duration_minutes", type="integer", example=480),
     *                             @OA\Property(property="duration_hours", type="integer", example=8),
     *                             @OA\Property(property="shift_time_range", type="string", example="06:00 - 14:00"),
     *                             @OA\Property(
     *                                 property="applicable_days",
     *                                 type="array",
     *                                 @OA\Items(type="string", example="monday")
     *                             ),
     *                             @OA\Property(property="is_company_wide", type="boolean", example=false),
     *                             @OA\Property(property="is_active", type="boolean", example=true),
     *                             @OA\Property(property="created_at", type="string", format="date-time", example="2026-01-05T08:55:10.000000Z"),
     *                             @OA\Property(property="updated_at", type="string", format="date-time", example="2026-01-05T08:55:30.000000Z"),
     *                             @OA\Property(property="deleted_at", type="string", format="date-time", nullable=true, example=null)
     *                         ),
     *                         @OA\Property(property="store_id", type="integer", example=1),
     *                         @OA\Property(
     *                             property="store",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="code", type="string", example="STR-2025-74622"),
     *                             @OA\Property(property="name", type="string", example="Branch Store - Mombasa"),
     *                             @OA\Property(property="description", type="string", example="Mombasa branch location"),
     *                             @OA\Property(property="address", type="string", example="Gedi, Kwale"),
     *                             @OA\Property(property="city", type="string", example="Mombasa"),
     *                             @OA\Property(property="region", type="string", example="Coast"),
     *                             @OA\Property(property="phone", type="string", example="+254723456789"),
     *                             @OA\Property(property="email", type="string", example="info@store.com"),
     *                             @OA\Property(property="is_main_store", type="boolean", example=true),
     *                             @OA\Property(property="is_active", type="boolean", example=true),
     *                             @OA\Property(property="status_label", type="string", example="Active"),
     *                             @OA\Property(property="store_type_label", type="string", example="Main Store"),
     *                             @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-14T19:48:13.000000Z"),
     *                             @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-15T18:15:20.000000Z")
     *                         ),
     *                         @OA\Property(property="user_id", type="integer", example=3),
     *                         @OA\Property(
     *                             property="user",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=3),
     *                             @OA\Property(property="name", type="string", example="Jane Cashier"),
     *                             @OA\Property(property="email", type="string", example="cashier@merchant.com")
     *                         ),
     *                         @OA\Property(property="shift_date", type="string", format="date", example="2026-01-05"),
     *                         @OA\Property(property="scheduled_start_time", type="string", example="06:00"),
     *                         @OA\Property(property="scheduled_end_time", type="string", example="14:00"),
     *                         @OA\Property(property="actual_start", type="string", format="date-time", nullable=true, example=null),
     *                         @OA\Property(property="actual_end", type="string", format="date-time", nullable=true, example=null),
     *                         @OA\Property(property="actual_duration_minutes", type="integer", nullable=true, example=null),
     *                         @OA\Property(property="actual_duration_hours", type="number", format="float", nullable=true, example=null),
     *                         @OA\Property(property="status", type="string", example="scheduled"),
     *                         @OA\Property(property="status_label", type="string", example="Scheduled"),
     *                         @OA\Property(property="status_color", type="string", example="blue"),
     *                         @OA\Property(property="is_late", type="boolean", example=false),
     *                         @OA\Property(property="is_early_departure", type="boolean", example=false),
     *                         @OA\Property(property="has_significant_cash_variance", type="boolean", example=false),
     *                         @OA\Property(property="cash_variance_reason", type="string", nullable=true, example=null),
     *                         @OA\Property(property="overtime_minutes", type="integer", example=0),
     *                         @OA\Property(property="overtime_hours", type="integer", example=0),
     *                         @OA\Property(property="has_overtime", type="boolean", example=false),
     *                         @OA\Property(property="notes", type="string", example="Morning coverage for peak hours"),
     *                         @OA\Property(property="issues_reported", type="string", nullable=true, example=null),
     *                         @OA\Property(property="is_approved", type="boolean", example=false),
     *                         @OA\Property(property="approved_by", type="integer", nullable=true, example=null),
     *                         @OA\Property(property="approved_at", type="string", format="date-time", nullable=true, example=null),
     *                         @OA\Property(property="sales_summary", type="object", nullable=true, example=null),
     *                         @OA\Property(property="created_at", type="string", format="date-time", example="2026-01-05T09:22:45.000000Z"),
     *                         @OA\Property(property="updated_at", type="string", format="date-time", example="2026-01-05T09:22:45.000000Z")
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
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-01-05T09:41:25.508344Z"),
     *                 @OA\Property(property="request_id", type="string", example="9b2a6f88-f5e6-487b-920e-26263c722b03"),
     *                 @OA\Property(property="tenant_id", type="string", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     )
     * )*/
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ShiftAssignment::class);

        $perPage = $request->input('per_page', 15);

        $query = ShiftAssignment::query()
            ->with(['shift', 'store', 'user', 'salesSummary']);

        // Filter by user (regular users can only see their own)
        if ($request->user()->hasRole(['cashier'])) {
            $query->forUser($request->user()->id);
        } elseif ($request->has('user_id')) {
            $query->forUser($request->input('user_id'));
        }

        // Apply other filters
        if ($request->has('store_id')) {
            $query->forStore($request->input('store_id'));
        }

        if ($request->has('shift_id')) {
            $query->forShift($request->input('shift_id'));
        }

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('date_from') && $request->has('date_to')) {
            $query->forDateRange(
                Carbon::parse($request->input('date_from')),
                Carbon::parse($request->input('date_to'))
            );
        } elseif ($request->has('date')) {
            $query->forDate($request->input('date'));
        }

        $assignments = $query->orderBy('shift_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return ApiResponse::success(
            'Shift assignments retrieved successfully',
            [
                'assignments' => ShiftAssignmentResource::collection($assignments->items()),
                'pagination' => [
                    'current_page' => $assignments->currentPage(),
                    'last_page' => $assignments->lastPage(),
                    'per_page' => $assignments->perPage(),
                    'total' => $assignments->total(),
                    'from' => $assignments->firstItem(),
                    'to' => $assignments->lastItem(),
                ],
            ]
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/shift-assignments",
     *     summary="Create a shift assignment",
     *     description="Create a new shift assignment for a user",
     *     operationId="createShiftAssignment",
     *     tags={"Shift Assignments"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"shift_id", "store_id", "user_id", "shift_date"},
     *             @OA\Property(property="shift_id", type="integer", example=1, description="Shift ID"),
     *             @OA\Property(property="store_id", type="integer", example=1, description="Store ID"),
     *             @OA\Property(property="user_id", type="integer", example=2, description="User ID to assign"),
     *             @OA\Property(property="shift_date", type="string", format="date", example="2025-01-15", description="Shift date"),
     *             @OA\Property(property="notes", type="string", example="First shift for new employee", description="Optional notes")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Shift assignment created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Shift assignment created successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="assignment",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="shift_id", type="integer", example=2),
     *                     @OA\Property(
     *                         property="shift",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=2),
     *                         @OA\Property(property="shift_name", type="string", example="Morning Shift"),
     *                         @OA\Property(property="store_id", type="integer", example=1),
     *                         @OA\Property(property="scheduled_start_time", type="string", example="06:00"),
     *                         @OA\Property(property="scheduled_end_time", type="string", example="14:00"),
     *                         @OA\Property(property="duration_minutes", type="integer", example=480),
     *                         @OA\Property(property="duration_hours", type="integer", example=8),
     *                         @OA\Property(property="shift_time_range", type="string", example="06:00 - 14:00"),
     *                         @OA\Property(
     *                             property="applicable_days",
     *                             type="array",
     *                             @OA\Items(type="string", example="monday")
     *                         ),
     *                         @OA\Property(property="is_company_wide", type="boolean", example=false),
     *                         @OA\Property(property="is_active", type="boolean", example=true),
     *                         @OA\Property(property="created_at", type="string", format="date-time", example="2026-01-05T08:55:10.000000Z"),
     *                         @OA\Property(property="updated_at", type="string", format="date-time", example="2026-01-05T08:55:30.000000Z"),
     *                         @OA\Property(property="deleted_at", type="string", format="date-time", nullable=true, example=null)
     *                     ),
     *                     @OA\Property(property="store_id", type="integer", example=1),
     *                     @OA\Property(
     *                         property="store",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="code", type="string", example="STR-2025-74622"),
     *                         @OA\Property(property="name", type="string", example="Branch Store - Mombasa"),
     *                         @OA\Property(property="description", type="string", example="Mombasa branch location"),
     *                         @OA\Property(property="address", type="string", example="Gedi, Kwale"),
     *                         @OA\Property(property="city", type="string", example="Mombasa"),
     *                         @OA\Property(property="region", type="string", example="Coast"),
     *                         @OA\Property(property="phone", type="string", example="+254723456789"),
     *                         @OA\Property(property="email", type="string", example="info@store.com"),
     *                         @OA\Property(property="is_main_store", type="boolean", example=true),
     *                         @OA\Property(property="is_active", type="boolean", example=true),
     *                         @OA\Property(property="status_label", type="string", example="Active"),
     *                         @OA\Property(property="store_type_label", type="string", example="Main Store"),
     *                         @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-14T19:48:13.000000Z"),
     *                         @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-15T18:15:20.000000Z")
     *                     ),
     *                     @OA\Property(property="user_id", type="integer", example=3),
     *                     @OA\Property(
     *                         property="user",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=3),
     *                         @OA\Property(property="name", type="string", example="Jane Cashier"),
     *                         @OA\Property(property="email", type="string", example="cashier@merchant.com")
     *                     ),
     *                     @OA\Property(property="shift_date", type="string", format="date", example="2026-01-05"),
     *                     @OA\Property(property="scheduled_start_time", type="string", example="06:00"),
     *                     @OA\Property(property="scheduled_end_time", type="string", example="14:00"),
     *                     @OA\Property(property="actual_start", type="string", format="date-time", nullable=true, example=null),
     *                     @OA\Property(property="actual_end", type="string", format="date-time", nullable=true, example=null),
     *                     @OA\Property(property="actual_duration_minutes", type="integer", nullable=true, example=null),
     *                     @OA\Property(property="actual_duration_hours", type="number", format="float", nullable=true, example=null),
     *                     @OA\Property(property="status", type="string", example="scheduled"),
     *                     @OA\Property(property="status_label", type="string", example="Scheduled"),
     *                     @OA\Property(property="status_color", type="string", example="blue"),
     *                     @OA\Property(property="is_late", type="boolean", example=false),
     *                     @OA\Property(property="is_early_departure", type="boolean", example=false),
     *                     @OA\Property(property="has_significant_cash_variance", type="boolean", example=false),
     *                     @OA\Property(property="cash_variance_reason", type="string", nullable=true, example=null),
     *                     @OA\Property(property="overtime_minutes", type="integer", example=0),
     *                     @OA\Property(property="overtime_hours", type="integer", example=0),
     *                     @OA\Property(property="has_overtime", type="boolean", example=false),
     *                     @OA\Property(property="notes", type="string", example="Morning coverage for peak hours"),
     *                     @OA\Property(property="issues_reported", type="string", nullable=true, example=null),
     *                     @OA\Property(property="is_approved", type="boolean", example=false),
     *                     @OA\Property(property="approved_by", type="integer", nullable=true, example=null),
     *                     @OA\Property(property="approved_at", type="string", format="date-time", nullable=true, example=null),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2026-01-05T09:22:45.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2026-01-05T09:22:45.000000Z")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-01-05T09:22:45.592646Z"),
     *                 @OA\Property(property="request_id", type="string", example="fbef615b-6a6f-4007-8bfc-e502d748ba6d"),
     *                 @OA\Property(property="tenant_id", type="string", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="shift_id",
     *                     type="array",
     *                     @OA\Items(type="string", example="The selected shift does not exist or is inactive.")
     *                 ),
     *                 @OA\Property(
     *                     property="shift_date",
     *                     type="array",
     *                     @OA\Items(type="string", example="The shift date cannot be in the past.")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-01-05T09:21:26.652147Z"),
     *                 @OA\Property(property="request_id", type="string", example="afa4be60-d219-49e1-91c9-bc33b2efa8c8"),
     *                 @OA\Property(property="tenant_id", type="string", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     )
     * )*/
    public function store(StoreShiftAssignmentRequest $request): JsonResponse
    {
        $assignment = $this->assignmentService->assignShift($request->validated());

        return ApiResponse::created(
            'Shift assignment created successfully',
            ['assignment' => new ShiftAssignmentResource($assignment)]
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/shift-assignments/bulk",
     *     summary="Bulk create shift assignments",
     *     description="Create multiple shift assignments with recurrence patterns",
     *     operationId="bulkCreateShiftAssignments",
     *     tags={"Shift Assignments"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"shift_id", "store_id", "user_ids", "start_date", "end_date"},
     *             @OA\Property(property="shift_id", type="integer", example=1, description="Shift ID"),
     *             @OA\Property(property="store_id", type="integer", example=1, description="Store ID"),
     *             @OA\Property(
     *                 property="user_ids",
     *                 type="array",
     *                 description="Array of user IDs (min: 1, max: 100)",
     *                 @OA\Items(type="integer", example=1)
     *             ),
     *             @OA\Property(property="start_date", type="string", format="date", example="2025-01-01", description="Start date (must be today or future)"),
     *             @OA\Property(property="end_date", type="string", format="date", example="2025-01-31", description="End date (must be >= start_date)"),
     *             @OA\Property(
     *                 property="recurrence_pattern",
     *                 type="string",
     *                 enum={"daily", "weekly", "custom"},
     *                 example="weekly",
     *                 description="Recurrence pattern"
     *             ),
     *             @OA\Property(
     *                 property="recurrence_days",
     *                 type="array",
     *                 description="Custom recurrence days (required if recurrence_pattern is 'custom')",
     *                 nullable=true,
     *                 @OA\Items(
     *                     type="string",
     *                     enum={"monday", "tuesday", "wednesday", "thursday", "friday", "saturday", "sunday"},
     *                     example="monday"
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Shift assignments created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Successfully created 38 shift assignments"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="assignments",
     *                     type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="shift_id", type="integer", example=2),
     *                         @OA\Property(
     *                             property="shift",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=2),
     *                             @OA\Property(property="shift_name", type="string", example="Morning Shift"),
     *                             @OA\Property(property="store_id", type="integer", example=1),
     *                             @OA\Property(property="scheduled_start_time", type="string", example="06:00"),
     *                             @OA\Property(property="scheduled_end_time", type="string", example="14:00"),
     *                             @OA\Property(property="duration_minutes", type="integer", example=480),
     *                             @OA\Property(property="duration_hours", type="integer", example=8),
     *                             @OA\Property(property="shift_time_range", type="string", example="06:00 - 14:00"),
     *                             @OA\Property(
     *                                 property="applicable_days",
     *                                 type="array",
     *                                 @OA\Items(type="string", example="monday")
     *                             ),
     *                             @OA\Property(property="is_company_wide", type="boolean", example=false),
     *                             @OA\Property(property="is_active", type="boolean", example=true),
     *                             @OA\Property(property="created_at", type="string", format="date-time", example="2026-01-05T08:55:10.000000Z"),
     *                             @OA\Property(property="updated_at", type="string", format="date-time", example="2026-01-05T08:55:30.000000Z"),
     *                             @OA\Property(property="deleted_at", type="string", format="date-time", nullable=true, example=null)
     *                         ),
     *                         @OA\Property(property="store_id", type="integer", example=1),
     *                         @OA\Property(
     *                             property="store",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="code", type="string", example="STR-2025-74622"),
     *                             @OA\Property(property="name", type="string", example="Branch Store - Mombasa"),
     *                             @OA\Property(property="description", type="string", example="Mombasa branch location"),
     *                             @OA\Property(property="address", type="string", example="Gedi, Kwale"),
     *                             @OA\Property(property="city", type="string", example="Mombasa"),
     *                             @OA\Property(property="region", type="string", example="Coast"),
     *                             @OA\Property(property="phone", type="string", example="+254723456789"),
     *                             @OA\Property(property="email", type="string", example="info@store.com"),
     *                             @OA\Property(property="is_main_store", type="boolean", example=true),
     *                             @OA\Property(property="is_active", type="boolean", example=true),
     *                             @OA\Property(property="status_label", type="string", example="Active"),
     *                             @OA\Property(property="store_type_label", type="string", example="Main Store"),
     *                             @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-14T19:48:13.000000Z"),
     *                             @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-15T18:15:20.000000Z")
     *                         ),
     *                         @OA\Property(property="user_id", type="integer", example=3),
     *                         @OA\Property(
     *                             property="user",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=3),
     *                             @OA\Property(property="name", type="string", example="Jane Cashier"),
     *                             @OA\Property(property="email", type="string", example="cashier@merchant.com")
     *                         ),
     *                         @OA\Property(property="shift_date", type="string", format="date", example="2026-01-06"),
     *                         @OA\Property(property="scheduled_start_time", type="string", example="06:00"),
     *                         @OA\Property(property="scheduled_end_time", type="string", example="14:00"),
     *                         @OA\Property(property="actual_start", type="string", format="date-time", nullable=true, example=null),
     *                         @OA\Property(property="actual_end", type="string", format="date-time", nullable=true, example=null),
     *                         @OA\Property(property="actual_duration_minutes", type="integer", nullable=true, example=null),
     *                         @OA\Property(property="actual_duration_hours", type="number", format="float", nullable=true, example=null),
     *                         @OA\Property(property="status", type="string", example="scheduled"),
     *                         @OA\Property(property="status_label", type="string", example="Scheduled"),
     *                         @OA\Property(property="status_color", type="string", example="blue"),
     *                         @OA\Property(property="is_late", type="boolean", example=false),
     *                         @OA\Property(property="is_early_departure", type="boolean", example=false),
     *                         @OA\Property(property="has_significant_cash_variance", type="boolean", example=false),
     *                         @OA\Property(property="cash_variance_reason", type="string", nullable=true, example=null),
     *                         @OA\Property(property="overtime_minutes", type="integer", example=0),
     *                         @OA\Property(property="overtime_hours", type="integer", example=0),
     *                         @OA\Property(property="has_overtime", type="boolean", example=false),
     *                         @OA\Property(property="notes", type="string", nullable=true, example=null),
     *                         @OA\Property(property="issues_reported", type="string", nullable=true, example=null),
     *                         @OA\Property(property="is_approved", type="boolean", example=false),
     *                         @OA\Property(property="approved_by", type="integer", nullable=true, example=null),
     *                         @OA\Property(property="approved_at", type="string", format="date-time", nullable=true, example=null),
     *                         @OA\Property(property="created_at", type="string", format="date-time", example="2026-01-05T09:58:40.000000Z"),
     *                         @OA\Property(property="updated_at", type="string", format="date-time", example="2026-01-05T09:58:40.000000Z")
     *                     )
     *                 ),
     *                 @OA\Property(property="count", type="integer", example=38)
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-01-05T09:58:40.111693Z"),
     *                 @OA\Property(property="request_id", type="string", example="3a215256-b6eb-4fee-95b8-1c57c53a8874"),
     *                 @OA\Property(property="tenant_id", type="string", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     )
     * )*/
    public function bulkStore(BulkStoreShiftAssignmentRequest $request): JsonResponse
    {
        $shift = Shift::findOrFail($request->input('shift_id'));

        $assignments = $this->assignmentService->bulkAssignShift(
            $shift,
            $request->input('user_ids'),
            Carbon::parse($request->input('start_date')),
            Carbon::parse($request->input('end_date')),
            $request->input('recurrence_pattern', 'weekly'),
            $request->input('recurrence_days')
        );

        return ApiResponse::created(
            "Successfully created {$assignments->count()} shift assignments",
            [
                'assignments' => ShiftAssignmentResource::collection($assignments),
                'count' => $assignments->count(),
            ]
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/shift-assignments/{id}",
     *     summary="Get shift assignment details",
     *     description="Retrieve detailed information about a specific shift assignment",
     *     operationId="getShiftAssignment",
     *     tags={"Shift Assignments"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Assignment ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Shift assignment retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Shift assignment retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="assignment",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="shift_id", type="integer", example=2),
     *                     @OA\Property(
     *                         property="shift",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=2),
     *                         @OA\Property(property="shift_name", type="string", example="Morning Shift"),
     *                         @OA\Property(property="store_id", type="integer", example=1),
     *                         @OA\Property(property="scheduled_start_time", type="string", example="06:00"),
     *                         @OA\Property(property="scheduled_end_time", type="string", example="14:00"),
     *                         @OA\Property(property="duration_minutes", type="integer", example=480),
     *                         @OA\Property(property="duration_hours", type="integer", example=8),
     *                         @OA\Property(property="shift_time_range", type="string", example="06:00 - 14:00"),
     *                         @OA\Property(
     *                             property="applicable_days",
     *                             type="array",
     *                             @OA\Items(type="string", example="monday")
     *                         ),
     *                         @OA\Property(property="is_company_wide", type="boolean", example=false),
     *                         @OA\Property(property="is_active", type="boolean", example=true),
     *                         @OA\Property(property="created_at", type="string", format="date-time", example="2026-01-05T08:55:10.000000Z"),
     *                         @OA\Property(property="updated_at", type="string", format="date-time", example="2026-01-05T08:55:30.000000Z"),
     *                         @OA\Property(property="deleted_at", type="string", format="date-time", nullable=true, example=null)
     *                     ),
     *                     @OA\Property(property="store_id", type="integer", example=1),
     *                     @OA\Property(
     *                         property="store",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="code", type="string", example="STR-2025-74622"),
     *                         @OA\Property(property="name", type="string", example="Branch Store - Mombasa"),
     *                         @OA\Property(property="description", type="string", example="Mombasa branch location"),
     *                         @OA\Property(property="address", type="string", example="Gedi, Kwale"),
     *                         @OA\Property(property="city", type="string", example="Mombasa"),
     *                         @OA\Property(property="region", type="string", example="Coast"),
     *                         @OA\Property(property="phone", type="string", example="+254723456789"),
     *                         @OA\Property(property="email", type="string", example="info@store.com"),
     *                         @OA\Property(property="is_main_store", type="boolean", example=true),
     *                         @OA\Property(property="is_active", type="boolean", example=true),
     *                         @OA\Property(property="status_label", type="string", example="Active"),
     *                         @OA\Property(property="store_type_label", type="string", example="Main Store"),
     *                         @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-14T19:48:13.000000Z"),
     *                         @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-15T18:15:20.000000Z")
     *                     ),
     *                     @OA\Property(property="user_id", type="integer", example=3),
     *                     @OA\Property(
     *                         property="user",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=3),
     *                         @OA\Property(property="name", type="string", example="Jane Cashier"),
     *                         @OA\Property(property="email", type="string", example="cashier@merchant.com")
     *                     ),
     *                     @OA\Property(property="shift_date", type="string", format="date", example="2026-01-06"),
     *                     @OA\Property(property="scheduled_start_time", type="string", example="06:00"),
     *                     @OA\Property(property="scheduled_end_time", type="string", example="14:00"),
     *                     @OA\Property(property="actual_start", type="string", format="date-time", nullable=true, example=null),
     *                     @OA\Property(property="actual_end", type="string", format="date-time", nullable=true, example=null),
     *                     @OA\Property(property="actual_duration_minutes", type="integer", nullable=true, example=null),
     *                     @OA\Property(property="actual_duration_hours", type="number", format="float", nullable=true, example=null),
     *                     @OA\Property(property="status", type="string", example="scheduled"),
     *                     @OA\Property(property="status_label", type="string", example="Scheduled"),
     *                     @OA\Property(property="status_color", type="string", example="blue"),
     *                     @OA\Property(property="is_late", type="boolean", example=false),
     *                     @OA\Property(property="is_early_departure", type="boolean", example=false),
     *                     @OA\Property(property="has_significant_cash_variance", type="boolean", example=false),
     *                     @OA\Property(property="cash_variance_reason", type="string", nullable=true, example=null),
     *                     @OA\Property(property="overtime_minutes", type="integer", example=0),
     *                     @OA\Property(property="overtime_hours", type="integer", example=0),
     *                     @OA\Property(property="has_overtime", type="boolean", example=false),
     *                     @OA\Property(property="notes", type="string", nullable=true, example=null),
     *                     @OA\Property(property="issues_reported", type="string", nullable=true, example=null),
     *                     @OA\Property(property="is_approved", type="boolean", example=false),
     *                     @OA\Property(property="approved_by", type="integer", nullable=true, example=null),
     *                     @OA\Property(property="approver", type="object", nullable=true, example=null),
     *                     @OA\Property(property="approved_at", type="string", format="date-time", nullable=true, example=null),
     *                     @OA\Property(property="sales_summary", type="object", nullable=true, example=null),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2026-01-05T09:58:40.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2026-01-05T09:58:40.000000Z")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-01-05T10:09:02.192764Z"),
     *                 @OA\Property(property="request_id", type="string", example="0a0e53bb-1fa3-4d2d-be25-85b58dd107f2"),
     *                 @OA\Property(property="tenant_id", type="string", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     )
     * )*/
    public function show(ShiftAssignment $assignment): JsonResponse
    {
        $this->authorize('view', $assignment);

        $assignment->load(['shift', 'store', 'user', 'approver', 'salesSummary']);

        return ApiResponse::success(
            'Shift assignment retrieved successfully',
            ['assignment' => new ShiftAssignmentResource($assignment)]
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/shift-assignments/{id}/cancel",
     *     summary="Cancel a shift assignment",
     *     description="Cancel a scheduled shift assignment with a reason",
     *     operationId="cancelShiftAssignment",
     *     tags={"Shift Assignments"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Assignment ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"reason"},
     *             @OA\Property(property="reason", type="string", example="Employee called in sick", description="Cancellation reason")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Shift assignment cancelled successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Shift assignment cancelled successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="assignment",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="shift_id", type="integer", example=2),
     *                     @OA\Property(
     *                         property="shift",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=2),
     *                         @OA\Property(property="shift_name", type="string", example="Morning Shift"),
     *                         @OA\Property(property="store_id", type="integer", example=1),
     *                         @OA\Property(property="scheduled_start_time", type="string", example="06:00"),
     *                         @OA\Property(property="scheduled_end_time", type="string", example="14:00"),
     *                         @OA\Property(property="duration_minutes", type="integer", example=480),
     *                         @OA\Property(property="duration_hours", type="integer", example=8),
     *                         @OA\Property(property="shift_time_range", type="string", example="06:00 - 14:00"),
     *                         @OA\Property(
     *                             property="applicable_days",
     *                             type="array",
     *                             @OA\Items(type="string", example="monday")
     *                         ),
     *                         @OA\Property(property="is_company_wide", type="boolean", example=false),
     *                         @OA\Property(property="is_active", type="boolean", example=true),
     *                         @OA\Property(property="created_at", type="string", format="date-time", example="2026-01-05T08:55:10.000000Z"),
     *                         @OA\Property(property="updated_at", type="string", format="date-time", example="2026-01-05T08:55:30.000000Z"),
     *                         @OA\Property(property="deleted_at", type="string", format="date-time", nullable=true, example=null)
     *                     ),
     *                     @OA\Property(property="store_id", type="integer", example=1),
     *                     @OA\Property(
     *                         property="store",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="code", type="string", example="STR-2025-74622"),
     *                         @OA\Property(property="name", type="string", example="Branch Store - Mombasa"),
     *                         @OA\Property(property="description", type="string", example="Mombasa branch location"),
     *                         @OA\Property(property="address", type="string", example="Gedi, Kwale"),
     *                         @OA\Property(property="city", type="string", example="Mombasa"),
     *                         @OA\Property(property="region", type="string", example="Coast"),
     *                         @OA\Property(property="phone", type="string", example="+254723456789"),
     *                         @OA\Property(property="email", type="string", example="info@store.com"),
     *                         @OA\Property(property="is_main_store", type="boolean", example=true),
     *                         @OA\Property(property="is_active", type="boolean", example=true),
     *                         @OA\Property(property="status_label", type="string", example="Active"),
     *                         @OA\Property(property="store_type_label", type="string", example="Main Store"),
     *                         @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-14T19:48:13.000000Z"),
     *                         @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-15T18:15:20.000000Z")
     *                     ),
     *                     @OA\Property(property="user_id", type="integer", example=3),
     *                     @OA\Property(
     *                         property="user",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=3),
     *                         @OA\Property(property="name", type="string", example="Jane Cashier"),
     *                         @OA\Property(property="email", type="string", example="cashier@merchant.com")
     *                     ),
     *                     @OA\Property(property="shift_date", type="string", format="date", example="2026-01-06"),
     *                     @OA\Property(property="scheduled_start_time", type="string", example="06:00"),
     *                     @OA\Property(property="scheduled_end_time", type="string", example="14:00"),
     *                     @OA\Property(property="actual_start", type="string", format="date-time", nullable=true, example=null),
     *                     @OA\Property(property="actual_end", type="string", format="date-time", nullable=true, example=null),
     *                     @OA\Property(property="actual_duration_minutes", type="integer", nullable=true, example=null),
     *                     @OA\Property(property="actual_duration_hours", type="number", format="float", nullable=true, example=null),
     *                     @OA\Property(property="status", type="string", example="cancelled"),
     *                     @OA\Property(property="status_label", type="string", example="Cancelled"),
     *                     @OA\Property(property="status_color", type="string", example="gray"),
     *                     @OA\Property(property="is_late", type="boolean", example=false),
     *                     @OA\Property(property="is_early_departure", type="boolean", example=false),
     *                     @OA\Property(property="has_significant_cash_variance", type="boolean", example=false),
     *                     @OA\Property(property="cash_variance_reason", type="string", nullable=true, example=null),
     *                     @OA\Property(property="overtime_minutes", type="integer", example=0),
     *                     @OA\Property(property="overtime_hours", type="integer", example=0),
     *                     @OA\Property(property="has_overtime", type="boolean", example=false),
     *                     @OA\Property(property="notes", type="string", example="Cancellation Reason: Employee called in sick"),
     *                     @OA\Property(property="issues_reported", type="string", nullable=true, example=null),
     *                     @OA\Property(property="is_approved", type="boolean", example=false),
     *                     @OA\Property(property="approved_by", type="integer", nullable=true, example=null),
     *                     @OA\Property(property="approved_at", type="string", format="date-time", nullable=true, example=null),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2026-01-05T09:58:40.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2026-01-05T10:12:04.000000Z")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-01-05T10:12:04.367616Z"),
     *                 @OA\Property(property="request_id", type="string", example="4e37a369-bb0a-4fcf-8873-1779c978d47a"),
     *                 @OA\Property(property="tenant_id", type="string", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     )
     * )*/
    public function cancel(CancelShiftAssignmentRequest $request, ShiftAssignment $assignment): JsonResponse
    {
        $assignment = $this->assignmentService->cancelAssignment(
            $assignment,
            $request->input('reason')
        );

        return ApiResponse::success(
            'Shift assignment cancelled successfully',
            ['assignment' => new ShiftAssignmentResource($assignment)]
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/shift-assignments/{id}/clock-in",
     *     summary="Clock in to a shift",
     *     description="Start a shift by clocking in with opening cash amount",
     *     operationId="clockInShift",
     *     tags={"Shift Assignments"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Assignment ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"opening_cash"},
     *             @OA\Property(property="opening_cash", type="number", format="float", example=5000.00, description="Opening cash amount"),
     *             @OA\Property(property="notes", type="string", example="Register drawer was organized", description="Optional notes")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Clocked in successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Clocked in successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="assignment",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="shift_id", type="integer", example=4),
     *                     @OA\Property(
     *                         property="shift",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=4),
     *                         @OA\Property(property="shift_name", type="string", example="Afternoon Shift"),
     *                         @OA\Property(property="store_id", type="integer", example=1),
     *                         @OA\Property(property="scheduled_start_time", type="string", example="13:00"),
     *                         @OA\Property(property="scheduled_end_time", type="string", example="22:00"),
     *                         @OA\Property(property="duration_minutes", type="integer", example=480),
     *                         @OA\Property(property="duration_hours", type="integer", example=8),
     *                         @OA\Property(property="shift_time_range", type="string", example="13:00 - 22:00"),
     *                         @OA\Property(
     *                             property="applicable_days",
     *                             type="array",
     *                             @OA\Items(type="string", example="monday")
     *                         ),
     *                         @OA\Property(property="is_company_wide", type="boolean", example=false),
     *                         @OA\Property(property="is_active", type="boolean", example=true),
     *                         @OA\Property(property="created_at", type="string", format="date-time", example="2026-01-05T09:07:29.000000Z"),
     *                         @OA\Property(property="updated_at", type="string", format="date-time", example="2026-01-05T09:07:29.000000Z"),
     *                         @OA\Property(property="deleted_at", type="string", format="date-time", nullable=true, example=null)
     *                     ),
     *                     @OA\Property(property="store_id", type="integer", example=1),
     *                     @OA\Property(
     *                         property="store",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="code", type="string", example="STR-2025-74622"),
     *                         @OA\Property(property="name", type="string", example="Branch Store - Mombasa"),
     *                         @OA\Property(property="description", type="string", example="Mombasa branch location"),
     *                         @OA\Property(property="address", type="string", example="Gedi, Kwale"),
     *                         @OA\Property(property="city", type="string", example="Mombasa"),
     *                         @OA\Property(property="region", type="string", example="Coast"),
     *                         @OA\Property(property="phone", type="string", example="+254723456789"),
     *                         @OA\Property(property="email", type="string", example="info@store.com"),
     *                         @OA\Property(property="is_main_store", type="boolean", example=true),
     *                         @OA\Property(property="is_active", type="boolean", example=true),
     *                         @OA\Property(property="status_label", type="string", example="Active"),
     *                         @OA\Property(property="store_type_label", type="string", example="Main Store"),
     *                         @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-14T19:48:13.000000Z"),
     *                         @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-15T18:15:20.000000Z")
     *                     ),
     *                     @OA\Property(property="user_id", type="integer", example=3),
     *                     @OA\Property(
     *                         property="user",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=3),
     *                         @OA\Property(property="name", type="string", example="Jane Cashier"),
     *                         @OA\Property(property="email", type="string", example="cashier@merchant.com")
     *                     ),
     *                     @OA\Property(property="shift_date", type="string", format="date", example="2026-01-05"),
     *                     @OA\Property(property="scheduled_start_time", type="string", example="13:00"),
     *                     @OA\Property(property="scheduled_end_time", type="string", example="22:00"),
     *                     @OA\Property(property="actual_start", type="string", format="date-time", example="2026-01-05T10:26:58.000000Z"),
     *                     @OA\Property(property="actual_end", type="string", format="date-time", nullable=true, example=null),
     *                     @OA\Property(property="actual_duration_minutes", type="integer", nullable=true, example=null),
     *                     @OA\Property(property="actual_duration_hours", type="number", format="float", nullable=true, example=null),
     *                     @OA\Property(property="status", type="string", example="in_progress"),
     *                     @OA\Property(property="status_label", type="string", example="In Progress"),
     *                     @OA\Property(property="status_color", type="string", example="yellow"),
     *                     @OA\Property(property="is_late", type="boolean", example=true),
     *                     @OA\Property(property="minutes_late", type="integer", example=-26),
     *                     @OA\Property(property="is_early_departure", type="boolean", example=false),
     *                     @OA\Property(property="opening_cash", type="string", example="5000.00"),
     *                     @OA\Property(property="has_significant_cash_variance", type="boolean", example=false),
     *                     @OA\Property(property="cash_variance_reason", type="string", nullable=true, example=null),
     *                     @OA\Property(property="expected_cash", type="integer", example=5000),
     *                     @OA\Property(property="overtime_minutes", type="integer", example=0),
     *                     @OA\Property(property="overtime_hours", type="integer", example=0),
     *                     @OA\Property(property="has_overtime", type="boolean", example=false),
     *                     @OA\Property(property="notes", type="string", nullable=true, example=null),
     *                     @OA\Property(property="issues_reported", type="string", nullable=true, example=null),
     *                     @OA\Property(property="is_approved", type="boolean", example=false),
     *                     @OA\Property(property="approved_by", type="integer", nullable=true, example=null),
     *                     @OA\Property(property="approved_at", type="string", format="date-time", nullable=true, example=null),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2026-01-05T09:58:40.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2026-01-05T10:26:58.000000Z")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-01-05T10:26:58.106675Z"),
     *                 @OA\Property(property="request_id", type="string", example="2245efc0-b9f4-4151-9010-da99aaa38047"),
     *                 @OA\Property(property="tenant_id", type="string", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="opening_cash",
     *                     type="array",
     *                     @OA\Items(type="string", example="Opening cash amount is required.")
     *                 ),
     *                 @OA\Property(
     *                     property="shift_date",
     *                     type="array",
     *                     @OA\Items(type="string", example="Cannot clock in to future shifts. Shift is scheduled for 2026-01-06")
     *                 ),
     *                 @OA\Property(
     *                     property="clock_in_time",
     *                     type="array",
     *                     @OA\Items(type="string", example="Clock-in time is too late. Scheduled start was 07:15. Contact your manager for assistance.")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-01-05T10:21:35.164075Z"),
     *                 @OA\Property(property="request_id", type="string", example="1ace63f9-0052-429a-9cb4-6244e34cc752"),
     *                 @OA\Property(property="tenant_id", type="string", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     )
     * )*/
    public function clockIn(ClockInRequest $request, ShiftAssignment $assignment): JsonResponse
    {
        $assignment = $this->assignmentService->clockIn(
            $assignment,
            $request->input('opening_cash'),
            $request->input('notes')
        );

        return ApiResponse::success(
            'Clocked in successfully',
            ['assignment' => new ShiftAssignmentResource($assignment)]
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/shift-assignments/{id}/clock-out",
     *     summary="Clock out of a shift",
     *     description="End a shift by clocking out with closing cash amount and optional notes",
     *     operationId="clockOutShift",
     *     tags={"Shift Assignments"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Assignment ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"closing_cash"},
     *             @OA\Property(property="closing_cash", type="number", format="float", example=5500.00, description="Closing cash amount"),
     *             @OA\Property(property="notes", type="string", example="Smooth shift", description="Optional notes"),
     *             @OA\Property(property="issues_reported", type="string", example="Register printer jammed twice", description="Any issues during shift"),
     *             @OA\Property(property="cash_variance_reason", type="string", example="Made change for customer's large bill", description="Reason for cash variance if significant")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Clocked out successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Clocked out successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="assignment",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="shift_id", type="integer", example=4),
     *                     @OA\Property(
     *                         property="shift",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=4),
     *                         @OA\Property(property="shift_name", type="string", example="Afternoon Shift"),
     *                         @OA\Property(property="store_id", type="integer", example=1),
     *                         @OA\Property(property="scheduled_start_time", type="string", example="13:00"),
     *                         @OA\Property(property="scheduled_end_time", type="string", example="22:00"),
     *                         @OA\Property(property="duration_minutes", type="integer", example=480),
     *                         @OA\Property(property="duration_hours", type="integer", example=8),
     *                         @OA\Property(property="shift_time_range", type="string", example="13:00 - 22:00"),
     *                         @OA\Property(
     *                             property="applicable_days",
     *                             type="array",
     *                             @OA\Items(type="string", example="monday")
     *                         ),
     *                         @OA\Property(property="is_company_wide", type="boolean", example=false),
     *                         @OA\Property(property="is_active", type="boolean", example=true),
     *                         @OA\Property(property="created_at", type="string", format="date-time", example="2026-01-05T09:07:29.000000Z"),
     *                         @OA\Property(property="updated_at", type="string", format="date-time", example="2026-01-05T09:07:29.000000Z"),
     *                         @OA\Property(property="deleted_at", type="string", format="date-time", nullable=true, example=null)
     *                     ),
     *                     @OA\Property(property="store_id", type="integer", example=1),
     *                     @OA\Property(
     *                         property="store",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="code", type="string", example="STR-2025-74622"),
     *                         @OA\Property(property="name", type="string", example="Branch Store - Mombasa"),
     *                         @OA\Property(property="description", type="string", example="Mombasa branch location"),
     *                         @OA\Property(property="address", type="string", example="Gedi, Kwale"),
     *                         @OA\Property(property="city", type="string", example="Mombasa"),
     *                         @OA\Property(property="region", type="string", example="Coast"),
     *                         @OA\Property(property="phone", type="string", example="+254723456789"),
     *                         @OA\Property(property="email", type="string", example="info@store.com"),
     *                         @OA\Property(property="is_main_store", type="boolean", example=true),
     *                         @OA\Property(property="is_active", type="boolean", example=true),
     *                         @OA\Property(property="status_label", type="string", example="Active"),
     *                         @OA\Property(property="store_type_label", type="string", example="Main Store"),
     *                         @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-14T19:48:13.000000Z"),
     *                         @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-15T18:15:20.000000Z")
     *                     ),
     *                     @OA\Property(property="user_id", type="integer", example=3),
     *                     @OA\Property(
     *                         property="user",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=3),
     *                         @OA\Property(property="name", type="string", example="Jane Cashier"),
     *                         @OA\Property(property="email", type="string", example="cashier@merchant.com")
     *                     ),
     *                     @OA\Property(property="shift_date", type="string", format="date", example="2026-01-05"),
     *                     @OA\Property(property="scheduled_start_time", type="string", example="13:00"),
     *                     @OA\Property(property="scheduled_end_time", type="string", example="22:00"),
     *                     @OA\Property(property="actual_start", type="string", format="date-time", example="2026-01-05T10:26:58.000000Z"),
     *                     @OA\Property(property="actual_end", type="string", format="date-time", example="2026-01-05T10:35:24.000000Z"),
     *                     @OA\Property(property="actual_duration_minutes", type="integer", example=8),
     *                     @OA\Property(property="actual_duration_hours", type="number", format="float", example=0.13),
     *                     @OA\Property(property="status", type="string", example="completed"),
     *                     @OA\Property(property="status_label", type="string", example="Completed"),
     *                     @OA\Property(property="status_color", type="string", example="green"),
     *                     @OA\Property(property="is_late", type="boolean", example=true),
     *                     @OA\Property(property="minutes_late", type="integer", example=-26),
     *                     @OA\Property(property="is_early_departure", type="boolean", example=true),
     *                     @OA\Property(property="minutes_early", type="integer", example=-504),
     *                     @OA\Property(property="opening_cash", type="string", example="5000.00"),
     *                     @OA\Property(property="closing_cash", type="string", example="5000.00"),
     *                     @OA\Property(property="cash_variance", type="integer", example=0),
     *                     @OA\Property(property="has_significant_cash_variance", type="boolean", example=false),
     *                     @OA\Property(property="cash_variance_reason", type="string", nullable=true, example=null),
     *                     @OA\Property(property="expected_cash", type="integer", example=5000),
     *                     @OA\Property(property="overtime_minutes", type="integer", example=0),
     *                     @OA\Property(property="overtime_hours", type="integer", example=0),
     *                     @OA\Property(property="has_overtime", type="boolean", example=false),
     *                     @OA\Property(property="notes", type="string", example="Smooth shift"),
     *                     @OA\Property(property="issues_reported", type="string", example="Register printer jammed twice"),
     *                     @OA\Property(property="is_approved", type="boolean", example=false),
     *                     @OA\Property(property="approved_by", type="integer", nullable=true, example=null),
     *                     @OA\Property(property="approved_at", type="string", format="date-time", example="2026-01-05T10:35:24.000000Z"),
     *                     @OA\Property(property="sales_summary", type="object", nullable=true, example=null),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2026-01-05T09:58:40.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2026-01-05T10:35:24.000000Z")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-01-05T10:35:24.645035Z"),
     *                 @OA\Property(property="request_id", type="string", example="040f38ed-ab91-49e0-bd8a-2e9646e82117"),
     *                 @OA\Property(property="tenant_id", type="string", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     )
     * )*/
    public function clockOut(ClockOutRequest $request, ShiftAssignment $assignment): JsonResponse
    {
        $assignment = $this->assignmentService->clockOut(
            $assignment,
            $request->input('closing_cash'),
            $request->input('notes'),
            $request->input('issues_reported'),
            $request->input('cash_variance_reason')
        );

        return ApiResponse::success(
            'Clocked out successfully',
            ['assignment' => new ShiftAssignmentResource($assignment)]
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/shift-assignments/{id}/approve",
     *     summary="Approve a shift",
     *     description="Approve a completed shift assignment",
     *     operationId="approveShift",
     *     tags={"Shift Assignments"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Assignment ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="notes", type="string", example="Good work", description="Optional approval notes"),
     *             @OA\Property(property="override_cash_variance", type="boolean", example=true, description="Override significant cash variance")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Shift approved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Shift approved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="assignment",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="shift_id", type="integer", example=4),
     *                     @OA\Property(
     *                         property="shift",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=4),
     *                         @OA\Property(property="shift_name", type="string", example="Afternoon Shift"),
     *                         @OA\Property(property="store_id", type="integer", example=1),
     *                         @OA\Property(property="scheduled_start_time", type="string", example="13:00"),
     *                         @OA\Property(property="scheduled_end_time", type="string", example="22:00"),
     *                         @OA\Property(property="duration_minutes", type="integer", example=480),
     *                         @OA\Property(property="duration_hours", type="integer", example=8),
     *                         @OA\Property(property="shift_time_range", type="string", example="13:00 - 22:00"),
     *                         @OA\Property(
     *                             property="applicable_days",
     *                             type="array",
     *                             @OA\Items(type="string", example="monday")
     *                         ),
     *                         @OA\Property(property="is_company_wide", type="boolean", example=false),
     *                         @OA\Property(property="is_active", type="boolean", example=true),
     *                         @OA\Property(property="created_at", type="string", format="date-time", example="2026-01-05T09:07:29.000000Z"),
     *                         @OA\Property(property="updated_at", type="string", format="date-time", example="2026-01-05T09:07:29.000000Z"),
     *                         @OA\Property(property="deleted_at", type="string", format="date-time", nullable=true, example=null)
     *                     ),
     *                     @OA\Property(property="store_id", type="integer", example=1),
     *                     @OA\Property(
     *                         property="store",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="code", type="string", example="STR-2025-74622"),
     *                         @OA\Property(property="name", type="string", example="Branch Store - Mombasa"),
     *                         @OA\Property(property="description", type="string", example="Mombasa branch location"),
     *                         @OA\Property(property="address", type="string", example="Gedi, Kwale"),
     *                         @OA\Property(property="city", type="string", example="Mombasa"),
     *                         @OA\Property(property="region", type="string", example="Coast"),
     *                         @OA\Property(property="phone", type="string", example="+254723456789"),
     *                         @OA\Property(property="email", type="string", example="info@store.com"),
     *                         @OA\Property(property="is_main_store", type="boolean", example=true),
     *                         @OA\Property(property="is_active", type="boolean", example=true),
     *                         @OA\Property(property="status_label", type="string", example="Active"),
     *                         @OA\Property(property="store_type_label", type="string", example="Main Store"),
     *                         @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-14T19:48:13.000000Z"),
     *                         @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-15T18:15:20.000000Z")
     *                     ),
     *                     @OA\Property(property="user_id", type="integer", example=3),
     *                     @OA\Property(
     *                         property="user",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=3),
     *                         @OA\Property(property="name", type="string", example="Jane Cashier"),
     *                         @OA\Property(property="email", type="string", example="cashier@merchant.com")
     *                     ),
     *                     @OA\Property(property="shift_date", type="string", format="date", example="2026-01-05"),
     *                     @OA\Property(property="scheduled_start_time", type="string", example="13:00"),
     *                     @OA\Property(property="scheduled_end_time", type="string", example="22:00"),
     *                     @OA\Property(property="actual_start", type="string", format="date-time", example="2026-01-05T10:26:58.000000Z"),
     *                     @OA\Property(property="actual_end", type="string", format="date-time", example="2026-01-05T10:35:24.000000Z"),
     *                     @OA\Property(property="actual_duration_minutes", type="integer", example=8),
     *                     @OA\Property(property="actual_duration_hours", type="number", format="float", example=0.13),
     *                     @OA\Property(property="status", type="string", example="completed"),
     *                     @OA\Property(property="status_label", type="string", example="Completed"),
     *                     @OA\Property(property="status_color", type="string", example="green"),
     *                     @OA\Property(property="is_late", type="boolean", example=true),
     *                     @OA\Property(property="minutes_late", type="integer", example=-26),
     *                     @OA\Property(property="is_early_departure", type="boolean", example=true),
     *                     @OA\Property(property="minutes_early", type="integer", example=-504),
     *                     @OA\Property(property="opening_cash", type="string", example="5000.00"),
     *                     @OA\Property(property="closing_cash", type="string", example="5000.00"),
     *                     @OA\Property(property="cash_variance", type="integer", example=0),
     *                     @OA\Property(property="has_significant_cash_variance", type="boolean", example=false),
     *                     @OA\Property(property="cash_variance_reason", type="string", nullable=true, example=null),
     *                     @OA\Property(property="expected_cash", type="integer", example=5000),
     *                     @OA\Property(property="overtime_minutes", type="integer", example=0),
     *                     @OA\Property(property="overtime_hours", type="integer", example=0),
     *                     @OA\Property(property="has_overtime", type="boolean", example=false),
     *                     @OA\Property(property="notes", type="string", example="Smooth shift"),
     *                     @OA\Property(property="issues_reported", type="string", example="Register printer jammed twice"),
     *                     @OA\Property(property="is_approved", type="boolean", example=true),
     *                     @OA\Property(property="approved_by", type="integer", example=1),
     *                     @OA\Property(
     *                         property="approver",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="John Doe"),
     *                         @OA\Property(property="email", type="string", example="john@techhaven.com")
     *                     ),
     *                     @OA\Property(property="approved_at", type="string", format="date-time", example="2026-01-05T10:42:23.000000Z"),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2026-01-05T09:58:40.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2026-01-05T10:42:23.000000Z")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-01-05T10:42:23.342026Z"),
     *                 @OA\Property(property="request_id", type="string", example="def742e2-a111-46f1-b6b2-382f06f18f69"),
     *                 @OA\Property(property="tenant_id", type="string", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     )
     * )*/
    public function approve(ApproveShiftRequest $request, ShiftAssignment $assignment): JsonResponse
    {
        $assignment = $this->assignmentService->approveShift(
            $assignment,
            $request->user(),
            $request->input('notes')
        );

        return ApiResponse::success(
            'Shift approved successfully',
            ['assignment' => new ShiftAssignmentResource($assignment)]
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/users/{userId}/shift-assignments",
     *     summary="Get assignments for a specific user",
     *     description="Retrieve shift assignments for a specific user within a date range",
     *     operationId="getUserShiftAssignments",
     *     tags={"Shift Assignments"},
     *     @OA\Parameter(
     *         name="userId",
     *         in="path",
     *         description="User ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="date_from",
     *         in="query",
     *         description="Start date",
     *         required=true,
     *         @OA\Schema(type="string", format="date", example="2025-01-01")
     *     ),
     *     @OA\Parameter(
     *         name="date_to",
     *         in="query",
     *         description="End date",
     *         required=true,
     *         @OA\Schema(type="string", format="date", example="2025-01-31")
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter by status",
     *         required=false,
     *         @OA\Schema(type="string", example="completed")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="User assignments retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="User assignments retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="assignments",
     *                     type="array",
     *                     @OA\Items(
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
     *                             @OA\Property(
     *                                 property="applicable_days",
     *                                 type="array",
     *                                 @OA\Items(type="string", example="monday")
     *                             ),
     *                             @OA\Property(property="is_company_wide", type="boolean", example=false),
     *                             @OA\Property(property="is_active", type="boolean", example=true),
     *                             @OA\Property(property="created_at", type="string", format="date-time", example="2026-01-05T08:55:10.000000Z"),
     *                             @OA\Property(property="updated_at", type="string", format="date-time", example="2026-01-05T08:55:30.000000Z"),
     *                             @OA\Property(property="deleted_at", type="string", format="date-time", nullable=true, example=null)
     *                         ),
     *                         @OA\Property(property="store_id", type="integer", example=1),
     *                         @OA\Property(
     *                             property="store",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="code", type="string", example="STR-2025-74622"),
     *                             @OA\Property(property="name", type="string", example="Branch Store - Mombasa"),
     *                             @OA\Property(property="description", type="string", example="Mombasa branch location"),
     *                             @OA\Property(property="address", type="string", example="Gedi, Kwale"),
     *                             @OA\Property(property="city", type="string", example="Mombasa"),
     *                             @OA\Property(property="region", type="string", example="Coast"),
     *                             @OA\Property(property="phone", type="string", example="+254723456789"),
     *                             @OA\Property(property="email", type="string", example="info@store.com"),
     *                             @OA\Property(property="is_main_store", type="boolean", example=true),
     *                             @OA\Property(property="is_active", type="boolean", example=true),
     *                             @OA\Property(property="status_label", type="string", example="Active"),
     *                             @OA\Property(property="store_type_label", type="string", example="Main Store"),
     *                             @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-14T19:48:13.000000Z"),
     *                             @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-15T18:15:20.000000Z")
     *                         ),
     *                         @OA\Property(property="user_id", type="integer", example=3),
     *                         @OA\Property(property="shift_date", type="string", format="date", example="2026-01-07"),
     *                         @OA\Property(property="scheduled_start_time", type="string", example="06:00"),
     *                         @OA\Property(property="scheduled_end_time", type="string", example="13:00"),
     *                         @OA\Property(property="actual_start", type="string", format="date-time", nullable=true, example=null),
     *                         @OA\Property(property="actual_end", type="string", format="date-time", nullable=true, example=null),
     *                         @OA\Property(property="actual_duration_minutes", type="integer", nullable=true, example=null),
     *                         @OA\Property(property="actual_duration_hours", type="number", format="float", nullable=true, example=null),
     *                         @OA\Property(property="status", type="string", example="scheduled"),
     *                         @OA\Property(property="status_label", type="string", example="Scheduled"),
     *                         @OA\Property(property="status_color", type="string", example="blue"),
     *                         @OA\Property(property="is_late", type="boolean", example=false),
     *                         @OA\Property(property="is_early_departure", type="boolean", example=false),
     *                         @OA\Property(property="has_significant_cash_variance", type="boolean", example=false),
     *                         @OA\Property(property="cash_variance_reason", type="string", nullable=true, example=null),
     *                         @OA\Property(property="overtime_minutes", type="integer", example=0),
     *                         @OA\Property(property="overtime_hours", type="integer", example=0),
     *                         @OA\Property(property="has_overtime", type="boolean", example=false),
     *                         @OA\Property(property="notes", type="string", nullable=true, example=null),
     *                         @OA\Property(property="issues_reported", type="string", nullable=true, example=null),
     *                         @OA\Property(property="is_approved", type="boolean", example=false),
     *                         @OA\Property(property="approved_by", type="integer", nullable=true, example=null),
     *                         @OA\Property(property="approved_at", type="string", format="date-time", nullable=true, example=null),
     *                         @OA\Property(property="sales_summary", type="object", nullable=true, example=null),
     *                         @OA\Property(property="created_at", type="string", format="date-time", example="2026-01-05T09:58:40.000000Z"),
     *                         @OA\Property(property="updated_at", type="string", format="date-time", example="2026-01-05T09:58:40.000000Z")
     *                     )
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-01-05T10:47:35.249096Z"),
     *                 @OA\Property(property="request_id", type="string", example="8bbe68f5-e4e8-437b-8c18-2051c78dd0be"),
     *                 @OA\Property(property="tenant_id", type="string", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     )
     * )*/
    public function userAssignments(Request $request, int $userId): JsonResponse
    {
        // Users can view their own assignments, managers can view all
        if (!$request->user()->hasAnyRole(['manager', 'admin', 'owner']) && $request->user()->id !== $userId) {
            return ApiResponse::forbidden('Cannot view other users\' assignments');
        }

        $request->validate([
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
            'status' => 'nullable|string',
        ]);

        $filters = [];
        if ($request->has('status')) {
            $filters['status'] = $request->input('status');
        }

        $assignments = $this->assignmentService->getAssignmentsForUser(
            $userId,
            Carbon::parse($request->input('date_from')),
            Carbon::parse($request->input('date_to')),
            $filters
        );

        return ApiResponse::success(
            'User assignments retrieved successfully',
            ['assignments' => ShiftAssignmentResource::collection($assignments)]
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/stores/{storeId}/shift-assignments",
     *     summary="Get assignments for a specific store on a date",
     *     description="Retrieve shift assignments for a specific store with optional status filter",
     *     operationId="getStoreShiftAssignments",
     *     tags={"Shift Assignments"},
     *     @OA\Parameter(
     *         name="storeId",
     *         in="path",
     *         description="Store ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="date",
     *         in="query",
     *         description="Date",
     *         required=true,
     *         @OA\Schema(type="string", format="date", example="2025-01-15")
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter by status",
     *         required=false,
     *         @OA\Schema(type="string", example="in_progress")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Store assignments retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Store assignments retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="assignments",
     *                     type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="id", type="integer", example=20),
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
     *                             @OA\Property(
     *                                 property="applicable_days",
     *                                 type="array",
     *                                 @OA\Items(type="string", example="monday")
     *                             ),
     *                             @OA\Property(property="is_company_wide", type="boolean", example=false),
     *                             @OA\Property(property="is_active", type="boolean", example=true),
     *                             @OA\Property(property="created_at", type="string", format="date-time", example="2026-01-05T08:55:10.000000Z"),
     *                             @OA\Property(property="updated_at", type="string", format="date-time", example="2026-01-05T08:55:30.000000Z"),
     *                             @OA\Property(property="deleted_at", type="string", format="date-time", nullable=true, example=null)
     *                         ),
     *                         @OA\Property(property="store_id", type="integer", example=1),
     *                         @OA\Property(property="user_id", type="integer", example=5),
     *                         @OA\Property(
     *                             property="user",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=5),
     *                             @OA\Property(property="name", type="string", example="Mike cashier"),
     *                             @OA\Property(property="email", type="string", example="cashier2@merchant.com")
     *                         ),
     *                         @OA\Property(property="shift_date", type="string", format="date", example="2026-01-06"),
     *                         @OA\Property(property="scheduled_start_time", type="string", example="06:00"),
     *                         @OA\Property(property="scheduled_end_time", type="string", example="13:00"),
     *                         @OA\Property(property="actual_start", type="string", format="date-time", nullable=true, example=null),
     *                         @OA\Property(property="actual_end", type="string", format="date-time", nullable=true, example=null),
     *                         @OA\Property(property="actual_duration_minutes", type="integer", nullable=true, example=null),
     *                         @OA\Property(property="actual_duration_hours", type="number", format="float", nullable=true, example=null),
     *                         @OA\Property(property="status", type="string", example="scheduled"),
     *                         @OA\Property(property="status_label", type="string", example="Scheduled"),
     *                         @OA\Property(property="status_color", type="string", example="blue"),
     *                         @OA\Property(property="is_late", type="boolean", example=false),
     *                         @OA\Property(property="is_early_departure", type="boolean", example=false),
     *                         @OA\Property(property="has_significant_cash_variance", type="boolean", example=false),
     *                         @OA\Property(property="cash_variance_reason", type="string", nullable=true, example=null),
     *                         @OA\Property(property="overtime_minutes", type="integer", example=0),
     *                         @OA\Property(property="overtime_hours", type="integer", example=0),
     *                         @OA\Property(property="has_overtime", type="boolean", example=false),
     *                         @OA\Property(property="notes", type="string", nullable=true, example=null),
     *                         @OA\Property(property="issues_reported", type="string", nullable=true, example=null),
     *                         @OA\Property(property="is_approved", type="boolean", example=false),
     *                         @OA\Property(property="approved_by", type="integer", nullable=true, example=null),
     *                         @OA\Property(property="approved_at", type="string", format="date-time", nullable=true, example=null),
     *                         @OA\Property(property="sales_summary", type="object", nullable=true, example=null),
     *                         @OA\Property(property="created_at", type="string", format="date-time", example="2026-01-05T09:58:40.000000Z"),
     *                         @OA\Property(property="updated_at", type="string", format="date-time", example="2026-01-05T09:58:40.000000Z")
     *                     )
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-01-05T10:52:12.363150Z"),
     *                 @OA\Property(property="request_id", type="string", example="6808439f-cdd7-4293-a973-1e3e58159069"),
     *                 @OA\Property(property="tenant_id", type="string", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     )
     * )*/
    public function storeAssignments(Request $request, int $storeId): JsonResponse
    {
        $this->authorize('viewAny', ShiftAssignment::class);

        $request->validate([
            'date' => 'required|date',
            'status' => 'nullable|string',
        ]);

        $filters = [];
        if ($request->has('status')) {
            $filters['status'] = $request->input('status');
        }

        $assignments = $this->assignmentService->getAssignmentsForStore(
            $storeId,
            Carbon::parse($request->input('date')),
            $filters
        );

        return ApiResponse::success(
            'Store assignments retrieved successfully',
            ['assignments' => ShiftAssignmentResource::collection($assignments)]
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/shift-assignments/upcoming",
     *     summary="Get upcoming assignments for authenticated user",
     *     description="Retrieve upcoming shift assignments for the currently authenticated user",
     *     operationId="getUpcomingAssignments",
     *     tags={"Shift Assignments"},
     *     @OA\Parameter(
     *         name="days_ahead",
     *         in="query",
     *         description="Days to look ahead",
     *         required=false,
     *         @OA\Schema(type="integer", example=7)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Upcoming assignments retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Upcoming assignments retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="assignments",
     *                     type="array",
     *                     @OA\Items(
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
     *                             @OA\Property(
     *                                 property="applicable_days",
     *                                 type="array",
     *                                 @OA\Items(type="string", example="monday")
     *                             ),
     *                             @OA\Property(property="is_company_wide", type="boolean", example=false),
     *                             @OA\Property(property="is_active", type="boolean", example=true),
     *                             @OA\Property(property="created_at", type="string", format="date-time", example="2026-01-05T08:55:10.000000Z"),
     *                             @OA\Property(property="updated_at", type="string", format="date-time", example="2026-01-05T08:55:30.000000Z"),
     *                             @OA\Property(property="deleted_at", type="string", format="date-time", nullable=true, example=null)
     *                         ),
     *                         @OA\Property(property="store_id", type="integer", example=1),
     *                         @OA\Property(
     *                             property="store",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="code", type="string", example="STR-2025-74622"),
     *                             @OA\Property(property="name", type="string", example="Branch Store - Mombasa"),
     *                             @OA\Property(property="description", type="string", example="Mombasa branch location"),
     *                             @OA\Property(property="address", type="string", example="Gedi, Kwale"),
     *                             @OA\Property(property="city", type="string", example="Mombasa"),
     *                             @OA\Property(property="region", type="string", example="Coast"),
     *                             @OA\Property(property="phone", type="string", example="+254723456789"),
     *                             @OA\Property(property="email", type="string", example="info@store.com"),
     *                             @OA\Property(property="is_main_store", type="boolean", example=true),
     *                             @OA\Property(property="is_active", type="boolean", example=true),
     *                             @OA\Property(property="status_label", type="string", example="Active"),
     *                             @OA\Property(property="store_type_label", type="string", example="Main Store"),
     *                             @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-14T19:48:13.000000Z"),
     *                             @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-15T18:15:20.000000Z")
     *                         ),
     *                         @OA\Property(property="user_id", type="integer", example=3),
     *                         @OA\Property(property="shift_date", type="string", format="date", example="2026-01-07"),
     *                         @OA\Property(property="scheduled_start_time", type="string", example="06:00"),
     *                         @OA\Property(property="scheduled_end_time", type="string", example="13:00"),
     *                         @OA\Property(property="actual_start", type="string", format="date-time", nullable=true, example=null),
     *                         @OA\Property(property="actual_end", type="string", format="date-time", nullable=true, example=null),
     *                         @OA\Property(property="actual_duration_minutes", type="integer", nullable=true, example=null),
     *                         @OA\Property(property="actual_duration_hours", type="number", format="float", nullable=true, example=null),
     *                         @OA\Property(property="status", type="string", example="scheduled"),
     *                         @OA\Property(property="status_label", type="string", example="Scheduled"),
     *                         @OA\Property(property="status_color", type="string", example="blue"),
     *                         @OA\Property(property="is_late", type="boolean", example=false),
     *                         @OA\Property(property="is_early_departure", type="boolean", example=false),
     *                         @OA\Property(property="has_significant_cash_variance", type="boolean", example=false),
     *                         @OA\Property(property="cash_variance_reason", type="string", nullable=true, example=null),
     *                         @OA\Property(property="overtime_minutes", type="integer", example=0),
     *                         @OA\Property(property="overtime_hours", type="integer", example=0),
     *                         @OA\Property(property="has_overtime", type="boolean", example=false),
     *                         @OA\Property(property="notes", type="string", nullable=true, example=null),
     *                         @OA\Property(property="issues_reported", type="string", nullable=true, example=null),
     *                         @OA\Property(property="is_approved", type="boolean", example=false),
     *                         @OA\Property(property="approved_by", type="integer", nullable=true, example=null),
     *                         @OA\Property(property="approved_at", type="string", format="date-time", nullable=true, example=null),
     *                         @OA\Property(property="created_at", type="string", format="date-time", example="2026-01-05T09:58:40.000000Z"),
     *                         @OA\Property(property="updated_at", type="string", format="date-time", example="2026-01-05T09:58:40.000000Z")
     *                     )
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-01-05T11:01:31.933709Z"),
     *                 @OA\Property(property="request_id", type="string", example="d2c27a44-f7ad-4f7b-a2e1-a073ff0152a9"),
     *                 @OA\Property(property="tenant_id", type="string", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     )
     * )*/
    public function upcomingAssignments(Request $request): JsonResponse
    {
        $daysAhead = $request->input('days_ahead', 7);

        $assignments = $this->assignmentService->getUpcomingAssignments(
            $request->user()->id,
            $daysAhead
        );

        return ApiResponse::success(
            'Upcoming assignments retrieved successfully',
            ['assignments' => ShiftAssignmentResource::collection($assignments)]
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/shift-assignments/needing-approval",
     *     summary="Get assignments needing approval",
     *     description="Retrieve shift assignments that are completed but not yet approved",
     *     operationId="getAssignmentsNeedingApproval",
     *     tags={"Shift Assignments"},
     *     @OA\Parameter(
     *         name="store_id",
     *         in="query",
     *         description="Filter by store",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         description="Limit results",
     *         required=false,
     *         @OA\Schema(type="integer", example=10)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Assignments needing approval retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Assignments needing approval retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="assignments",
     *                     type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="shift_id", type="integer", example=4),
     *                         @OA\Property(
     *                             property="shift",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=4),
     *                             @OA\Property(property="shift_name", type="string", example="Afternoon Shift"),
     *                             @OA\Property(property="store_id", type="integer", example=1),
     *                             @OA\Property(property="scheduled_start_time", type="string", example="13:00"),
     *                             @OA\Property(property="scheduled_end_time", type="string", example="22:00"),
     *                             @OA\Property(property="duration_minutes", type="integer", example=480),
     *                             @OA\Property(property="duration_hours", type="integer", example=8),
     *                             @OA\Property(property="shift_time_range", type="string", example="13:00 - 22:00"),
     *                             @OA\Property(
     *                                 property="applicable_days",
     *                                 type="array",
     *                                 @OA\Items(type="string", example="monday")
     *                             ),
     *                             @OA\Property(property="is_company_wide", type="boolean", example=false),
     *                             @OA\Property(property="is_active", type="boolean", example=true),
     *                             @OA\Property(property="created_at", type="string", format="date-time", example="2026-01-05T09:07:29.000000Z"),
     *                             @OA\Property(property="updated_at", type="string", format="date-time", example="2026-01-05T09:07:29.000000Z"),
     *                             @OA\Property(property="deleted_at", type="string", format="date-time", nullable=true, example=null)
     *                         ),
     *                         @OA\Property(property="store_id", type="integer", example=1),
     *                         @OA\Property(
     *                             property="store",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="code", type="string", example="STR-2025-74622"),
     *                             @OA\Property(property="name", type="string", example="Branch Store - Mombasa"),
     *                             @OA\Property(property="description", type="string", example="Mombasa branch location"),
     *                             @OA\Property(property="address", type="string", example="Gedi, Kwale"),
     *                             @OA\Property(property="city", type="string", example="Mombasa"),
     *                             @OA\Property(property="region", type="string", example="Coast"),
     *                             @OA\Property(property="phone", type="string", example="+254723456789"),
     *                             @OA\Property(property="email", type="string", example="info@store.com"),
     *                             @OA\Property(property="is_main_store", type="boolean", example=true),
     *                             @OA\Property(property="is_active", type="boolean", example=true),
     *                             @OA\Property(property="status_label", type="string", example="Active"),
     *                             @OA\Property(property="store_type_label", type="string", example="Main Store"),
     *                             @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-14T19:48:13.000000Z"),
     *                             @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-15T18:15:20.000000Z")
     *                         ),
     *                         @OA\Property(property="user_id", type="integer", example=3),
     *                         @OA\Property(
     *                             property="user",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=3),
     *                             @OA\Property(property="name", type="string", example="Jane Cashier"),
     *                             @OA\Property(property="email", type="string", example="cashier@merchant.com")
     *                         ),
     *                         @OA\Property(property="shift_date", type="string", format="date", example="2026-01-05"),
     *                         @OA\Property(property="scheduled_start_time", type="string", example="13:00"),
     *                         @OA\Property(property="scheduled_end_time", type="string", example="22:00"),
     *                         @OA\Property(property="actual_start", type="string", format="date-time", example="2026-01-05T10:26:58.000000Z"),
     *                         @OA\Property(property="actual_end", type="string", format="date-time", example="2026-01-05T10:35:24.000000Z"),
     *                         @OA\Property(property="actual_duration_minutes", type="integer", example=8),
     *                         @OA\Property(property="actual_duration_hours", type="number", format="float", example=0.13),
     *                         @OA\Property(property="status", type="string", example="completed"),
     *                         @OA\Property(property="status_label", type="string", example="Completed"),
     *                         @OA\Property(property="status_color", type="string", example="green"),
     *                         @OA\Property(property="is_late", type="boolean", example=true),
     *                         @OA\Property(property="minutes_late", type="integer", example=-26),
     *                         @OA\Property(property="is_early_departure", type="boolean", example=true),
     *                         @OA\Property(property="minutes_early", type="integer", example=-504),
     *                         @OA\Property(property="opening_cash", type="string", example="5000.00"),
     *                         @OA\Property(property="closing_cash", type="string", example="5000.00"),
     *                         @OA\Property(property="cash_variance", type="integer", example=0),
     *                         @OA\Property(property="has_significant_cash_variance", type="boolean", example=false),
     *                         @OA\Property(property="cash_variance_reason", type="string", nullable=true, example=null),
     *                         @OA\Property(property="expected_cash", type="integer", example=5000),
     *                         @OA\Property(property="overtime_minutes", type="integer", example=0),
     *                         @OA\Property(property="overtime_hours", type="integer", example=0),
     *                         @OA\Property(property="has_overtime", type="boolean", example=false),
     *                         @OA\Property(property="notes", type="string", example="Smooth shift"),
     *                         @OA\Property(property="issues_reported", type="string", example="Register printer jammed twice"),
     *                         @OA\Property(property="is_approved", type="boolean", example=false),
     *                         @OA\Property(property="approved_by", type="integer", nullable=true, example=null),
     *                         @OA\Property(property="approved_at", type="string", format="date-time", nullable=true, example=null),
     *                         @OA\Property(property="sales_summary", type="object", nullable=true, example=null),
     *                         @OA\Property(property="created_at", type="string", format="date-time", example="2026-01-05T09:58:40.000000Z"),
     *                         @OA\Property(property="updated_at", type="string", format="date-time", example="2026-01-05T10:42:23.000000Z")
     *                     )
     *                 ),
     *                 @OA\Property(property="count", type="integer", example=1)
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-01-05T11:07:33.879777Z"),
     *                 @OA\Property(property="request_id", type="string", example="4b8cd8fd-fd98-4dca-a35b-a6491d5bd038"),
     *                 @OA\Property(property="tenant_id", type="string", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     )
     * )*/
    public function needingApproval(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ShiftAssignment::class);

        $storeId = $request->input('store_id');
        $limit = $request->input('limit', 50);

        $assignments = $this->assignmentService->getAssignmentsNeedingApproval($storeId, $limit);

        return ApiResponse::success(
            'Assignments needing approval retrieved successfully',
            [
                'assignments' => ShiftAssignmentResource::collection($assignments),
                'count' => $assignments->count(),
            ]
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/shift-assignments/statistics",
     *     summary="Get assignment statistics",
     *     description="Retrieve statistical data about shift assignments within a date range",
     *     operationId="getAssignmentStatistics",
     *     tags={"Shift Assignments"},
     *     @OA\Parameter(
     *         name="date_from",
     *         in="query",
     *         description="Start date",
     *         required=true,
     *         @OA\Schema(type="string", format="date", example="2025-01-01")
     *     ),
     *     @OA\Parameter(
     *         name="date_to",
     *         in="query",
     *         description="End date",
     *         required=true,
     *         @OA\Schema(type="string", format="date", example="2025-01-31")
     *     ),
     *     @OA\Parameter(
     *         name="store_id",
     *         in="query",
     *         description="Filter by store",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Assignment statistics retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Assignment statistics retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="statistics",
     *                     type="object",
     *                     @OA\Property(property="total_assignments", type="integer", example=1, description="Total number of assignments"),
     *                     @OA\Property(property="scheduled", type="integer", example=0, description="Number of scheduled assignments"),
     *                     @OA\Property(property="in_progress", type="integer", example=0, description="Number of in-progress assignments"),
     *                     @OA\Property(property="completed", type="integer", example=1, description="Number of completed assignments"),
     *                     @OA\Property(property="cancelled", type="integer", example=0, description="Number of cancelled assignments"),
     *                     @OA\Property(property="no_show", type="integer", example=0, description="Number of no-show assignments"),
     *                     @OA\Property(property="approved", type="integer", example=0, description="Number of approved assignments"),
     *                     @OA\Property(property="pending_approval", type="integer", example=1, description="Number of assignments pending approval"),
     *                     @OA\Property(property="with_cash_variance", type="integer", example=0, description="Number of assignments with cash variance"),
     *                     @OA\Property(property="with_overtime", type="integer", example=0, description="Number of assignments with overtime")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-01-05T11:12:38.265619Z"),
     *                 @OA\Property(property="request_id", type="string", example="92897783-32c2-433e-9631-2fb2fe810bfc"),
     *                 @OA\Property(property="tenant_id", type="string", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     )
     * )*/
    public function statistics(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ShiftAssignment::class);

        $request->validate([
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
            'store_id' => 'nullable|integer|exists:stores,id',
        ]);

        $stats = $this->assignmentService->getAssignmentStatistics(
            Carbon::parse($request->input('date_from')),
            Carbon::parse($request->input('date_to')),
            $request->input('store_id')
        );

        return ApiResponse::success(
            'Assignment statistics retrieved successfully',
            ['statistics' => $stats]
        );
    }
}
