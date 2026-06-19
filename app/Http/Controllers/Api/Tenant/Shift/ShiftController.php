<?php

namespace App\Http\Controllers\Api\Tenant\Shift;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Shift\StoreShiftRequest;
use App\Http\Requests\Tenant\Shift\UpdateShiftRequest;
use App\Http\Resources\Tenant\Shift\ShiftResource;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant\Shift;
use App\Services\Tenant\Shift\ShiftService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShiftController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected ShiftService $shiftService
    ) {}

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/shifts",
     *     summary="List all shifts",
     *     description="Get paginated list of shifts with optional filters",
     *     operationId="listShifts",
     *     tags={"Shifts"},
     *     @OA\Parameter(
     *         name="store_id",
     *         in="query",
     *         description="Filter by store ID",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="is_active",
     *         in="query",
     *         description="Filter by active status",
     *         required=false,
     *         @OA\Schema(type="boolean", example=true)
     *     ),
     *     @OA\Parameter(
     *         name="day",
     *         in="query",
     *         description="Filter by day of week",
     *         required=false,
     *         @OA\Schema(type="string", example="monday")
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search by shift name",
     *         required=false,
     *         @OA\Schema(type="string", example="Morning")
     *     ),
     *     @OA\Parameter(
     *         name="company_wide",
     *         in="query",
     *         description="Filter company-wide shifts",
     *         required=false,
     *         @OA\Schema(type="boolean", example=true)
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
     *         description="Shifts retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Shifts retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="shifts",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="shift_name", type="string", example="Morning Shift"),
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
     *                         @OA\Property(property="created_at", type="string", format="date-time", example="2026-01-05T08:39:08.000000Z"),
     *                         @OA\Property(property="updated_at", type="string", format="date-time", example="2026-01-05T08:39:08.000000Z"),
     *                         @OA\Property(property="deleted_at", type="string", format="date-time", nullable=true, example=null)
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
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-01-05T08:40:27.187059Z"),
     *                 @OA\Property(property="request_id", type="string", example="5aad6605-0f53-4ca3-a04f-6a9286f66c68"),
     *                 @OA\Property(property="tenant_id", type="string", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Shift::class);

        $perPage = $request->input('per_page', 15);
        $storeId = $request->input('store_id');

        $filters = [
            'is_active' => $request->input('is_active'),
            'day' => $request->input('day'),
            'search' => $request->input('search'),
            'company_wide' => $request->input('company_wide'),
        ];

        $query = Shift::query()->with('store');

        // Apply filters
        if ($storeId) {
            $query->forStore($storeId);
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        if (isset($filters['day'])) {
            $query->forDay($filters['day']);
        }

        if (isset($filters['search'])) {
            $query->search($filters['search']);
        }

        if (isset($filters['company_wide'])) {
            if (filter_var($filters['company_wide'], FILTER_VALIDATE_BOOLEAN)) {
                $query->companyWide();
            } else {
                $query->storeSpecific();
            }
        }

        $shifts = $query->orderBy('shift_name')
            ->paginate($perPage);

        return ApiResponse::success(
            'Shifts retrieved successfully',
            [
                'shifts' => ShiftResource::collection($shifts->items()),
                'pagination' => [
                    'current_page' => $shifts->currentPage(),
                    'last_page' => $shifts->lastPage(),
                    'per_page' => $shifts->perPage(),
                    'total' => $shifts->total(),
                    'from' => $shifts->firstItem(),
                    'to' => $shifts->lastItem(),
                ],
            ]
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/shifts",
     *     summary="Create a new shift",
     *     description="Create a new shift with schedule and applicable days",
     *     operationId="createShift",
     *     tags={"Shifts"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"shift_name", "scheduled_start_time", "scheduled_end_time"},
     *             @OA\Property(property="shift_name", type="string", example="Morning Shift", description="The shift name"),
     *             @OA\Property(property="store_id", type="integer", nullable=true, example=1, description="Store ID (null for company-wide)"),
     *             @OA\Property(property="scheduled_start_time", type="string", example="06:00", description="Start time in HH:MM format"),
     *             @OA\Property(property="scheduled_end_time", type="string", example="14:00", description="End time in HH:MM format"),
     *             @OA\Property(
     *                 property="applicable_days",
     *                 type="array",
     *                 description="Days of week this shift applies to",
     *                 @OA\Items(type="string", example="monday")
     *             ),
     *             @OA\Property(property="is_active", type="boolean", example=true, description="Whether shift is active")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Shift created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Shift created successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="shift",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="shift_name", type="string", example="Morning Shift"),
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
     *                     @OA\Property(property="scheduled_start_time", type="string", example="06:00"),
     *                     @OA\Property(property="scheduled_end_time", type="string", example="14:00"),
     *                     @OA\Property(property="duration_minutes", type="integer", example=480),
     *                     @OA\Property(property="duration_hours", type="integer", example=8),
     *                     @OA\Property(property="shift_time_range", type="string", example="06:00 - 14:00"),
     *                     @OA\Property(
     *                         property="applicable_days",
     *                         type="array",
     *                         @OA\Items(type="string", example="monday")
     *                     ),
     *                     @OA\Property(property="is_company_wide", type="boolean", example=false),
     *                     @OA\Property(property="is_active", type="boolean", example=true),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2026-01-05T08:39:08.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2026-01-05T08:39:08.000000Z"),
     *                     @OA\Property(property="deleted_at", type="string", format="date-time", nullable=true, example=null)
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-01-05T08:39:08.250889Z"),
     *                 @OA\Property(property="request_id", type="string", example="4e4d2ede-e055-4ecf-bb31-9960f96f4b5f"),
     *                 @OA\Property(property="tenant_id", type="string", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     )
     * )
     */
    public function store(StoreShiftRequest $request): JsonResponse
    {
        $shift = $this->shiftService->createShift($request->validated());

        return ApiResponse::created(
            'Shift created successfully',
            ['shift' => new ShiftResource($shift)]
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/shifts/{id}",
     *     summary="Get shift details",
     *     description="Retrieve detailed information about a specific shift including assignments",
     *     operationId="getShift",
     *     tags={"Shifts"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Shift ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Shift retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Shift retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="shift",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=2),
     *                     @OA\Property(property="shift_name", type="string", example="Morning Shift"),
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
     *                     @OA\Property(property="scheduled_start_time", type="string", example="06:00"),
     *                     @OA\Property(property="scheduled_end_time", type="string", example="14:00"),
     *                     @OA\Property(property="duration_minutes", type="integer", example=480),
     *                     @OA\Property(property="duration_hours", type="integer", example=8),
     *                     @OA\Property(property="shift_time_range", type="string", example="06:00 - 14:00"),
     *                     @OA\Property(
     *                         property="applicable_days",
     *                         type="array",
     *                         @OA\Items(type="string", example="monday")
     *                     ),
     *                     @OA\Property(property="is_company_wide", type="boolean", example=false),
     *                     @OA\Property(property="is_active", type="boolean", example=true),
     *                     @OA\Property(
     *                         property="assignments",
     *                         type="array",
     *                         @OA\Items(
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="shift_id", type="integer", example=2),
     *                             @OA\Property(property="store_id", type="integer", example=1),
     *                             @OA\Property(property="user_id", type="integer", example=3),
     *                             @OA\Property(property="shift_date", type="string", format="date", example="2026-01-05"),
     *                             @OA\Property(property="scheduled_start_time", type="string", example="06:00"),
     *                             @OA\Property(property="scheduled_end_time", type="string", example="14:00"),
     *                             @OA\Property(property="actual_start", type="string", nullable=true, example=null),
     *                             @OA\Property(property="actual_end", type="string", nullable=true, example=null),
     *                             @OA\Property(property="actual_duration_minutes", type="integer", nullable=true, example=null),
     *                             @OA\Property(property="actual_duration_hours", type="number", format="float", nullable=true, example=null),
     *                             @OA\Property(property="status", type="string", example="scheduled"),
     *                             @OA\Property(property="status_label", type="string", example="Scheduled"),
     *                             @OA\Property(property="status_color", type="string", example="blue"),
     *                             @OA\Property(property="is_late", type="boolean", example=false),
     *                             @OA\Property(property="is_early_departure", type="boolean", example=false),
     *                             @OA\Property(property="has_significant_cash_variance", type="boolean", example=false),
     *                             @OA\Property(property="cash_variance_reason", type="string", nullable=true, example=null),
     *                             @OA\Property(property="overtime_minutes", type="integer", example=0),
     *                             @OA\Property(property="overtime_hours", type="number", format="float", example=0),
     *                             @OA\Property(property="has_overtime", type="boolean", example=false),
     *                             @OA\Property(property="notes", type="string", nullable=true, example="Morning coverage for peak hours"),
     *                             @OA\Property(property="issues_reported", type="string", nullable=true, example=null),
     *                             @OA\Property(property="is_approved", type="boolean", example=false),
     *                             @OA\Property(property="approved_by", type="integer", nullable=true, example=null),
     *                             @OA\Property(property="approved_at", type="string", format="date-time", nullable=true, example=null),
     *                             @OA\Property(property="created_at", type="string", format="date-time", example="2026-01-05T09:22:45.000000Z"),
     *                             @OA\Property(property="updated_at", type="string", format="date-time", example="2026-01-05T09:22:45.000000Z")
     *                         )
     *                     ),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2026-01-05T08:55:10.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2026-01-05T08:55:30.000000Z"),
     *                     @OA\Property(property="deleted_at", type="string", format="date-time", nullable=true, example=null)
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-01-05T09:23:54.394277Z"),
     *                 @OA\Property(property="request_id", type="string", example="5cf91d23-d52c-4c7f-9621-fb13c345de4a"),
     *                 @OA\Property(property="tenant_id", type="string", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     )
     * )
     */
    public function show(Shift $shift): JsonResponse
    {
        $this->authorize('view', $shift);

        $shift->load(['store', 'assignments' => function ($query) {
            $query->where('shift_date', '>=', now()->toDateString())
                ->orderBy('shift_date')
                ->limit(10);
        }]);

        return ApiResponse::success(
            'Shift retrieved successfully',
            ['shift' => new ShiftResource($shift)]
        );
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/tenant/shifts/{id}",
     *     summary="Update a shift",
     *     description="Update shift details with partial data",
     *     operationId="updateShift",
     *     tags={"Shifts"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Shift ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="shift_name", type="string", example="Evening Shift", description="The shift name"),
     *             @OA\Property(property="scheduled_start_time", type="string", example="16:00", description="Start time in HH:MM format"),
     *             @OA\Property(property="scheduled_end_time", type="string", example="00:00", description="End time in HH:MM format"),
     *             @OA\Property(
     *                 property="applicable_days",
     *                 type="array",
     *                 description="Days of week",
     *                 @OA\Items(type="string", example="monday")
     *             ),
     *             @OA\Property(property="is_active", type="boolean", example=false, description="Active status")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Shift updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Shift updated successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="shift",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="shift_name", type="string", example="Morning Shift"),
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
     *                     @OA\Property(property="scheduled_start_time", type="string", example="06:00"),
     *                     @OA\Property(property="scheduled_end_time", type="string", example="12:00"),
     *                     @OA\Property(property="duration_minutes", type="integer", example=360),
     *                     @OA\Property(property="duration_hours", type="integer", example=6),
     *                     @OA\Property(property="shift_time_range", type="string", example="06:00 - 12:00"),
     *                     @OA\Property(
     *                         property="applicable_days",
     *                         type="array",
     *                         @OA\Items(type="string", example="monday")
     *                     ),
     *                     @OA\Property(property="is_company_wide", type="boolean", example=false),
     *                     @OA\Property(property="is_active", type="boolean", example=true),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2026-01-05T08:39:08.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2026-01-05T08:50:55.000000Z"),
     *                     @OA\Property(property="deleted_at", type="string", format="date-time", nullable=true, example=null)
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-01-05T08:50:55.350637Z"),
     *                 @OA\Property(property="request_id", type="string", example="04600d9c-4aa9-44b6-b487-308640826378"),
     *                 @OA\Property(property="tenant_id", type="string", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     )
     * )
     */
    public function update(UpdateShiftRequest $request, Shift $shift): JsonResponse
    {
        $shift = $this->shiftService->updateShift($shift, $request->validated());

        return ApiResponse::success(
            'Shift updated successfully',
            ['shift' => new ShiftResource($shift)]
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/tenant/shifts/{id}",
     *     summary="Delete a shift",
     *     description="Soft delete a shift by ID",
     *     operationId="deleteShift",
     *     tags={"Shifts"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Shift ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Shift deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Shift deleted successfully"),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-01-05T08:52:36.952177Z"),
     *                 @OA\Property(property="request_id", type="string", example="e6f47e0b-c840-4e68-b3a8-2f91d1688a2b"),
     *                 @OA\Property(property="tenant_id", type="string", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     )
     * )
     */
    public function destroy(Shift $shift): JsonResponse
    {
        $this->authorize('delete', $shift);

        $this->shiftService->deleteShift($shift);

        return ApiResponse::success('Shift deleted successfully');
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/shifts/{id}/toggle-active",
     *     summary="Toggle shift active status",
     *     description="Toggle the active status of a shift between active and inactive",
     *     operationId="toggleShiftActive",
     *     tags={"Shifts"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Shift ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Shift status toggled successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Shift status toggled successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="shift",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=2),
     *                     @OA\Property(property="shift_name", type="string", example="Morning Shift"),
     *                     @OA\Property(property="store_id", type="integer", example=1),
     *                     @OA\Property(property="scheduled_start_time", type="string", example="06:00"),
     *                     @OA\Property(property="scheduled_end_time", type="string", example="14:00"),
     *                     @OA\Property(property="duration_minutes", type="integer", example=480),
     *                     @OA\Property(property="duration_hours", type="integer", example=8),
     *                     @OA\Property(property="shift_time_range", type="string", example="06:00 - 14:00"),
     *                     @OA\Property(
     *                         property="applicable_days",
     *                         type="array",
     *                         @OA\Items(type="string", example="monday")
     *                     ),
     *                     @OA\Property(property="is_company_wide", type="boolean", example=false),
     *                     @OA\Property(property="is_active", type="boolean", example=false),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2026-01-05T08:55:10.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2026-01-05T08:55:30.000000Z"),
     *                     @OA\Property(property="deleted_at", type="string", format="date-time", nullable=true, example=null)
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-01-05T08:55:30.458606Z"),
     *                 @OA\Property(property="request_id", type="string", example="76407c06-fe54-4869-98c4-5ded5969594a"),
     *                 @OA\Property(property="tenant_id", type="string", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     )
     * )
     */
    public function toggleActive(Shift $shift): JsonResponse
    {
        $this->authorize('update', $shift);

        $shift = $this->shiftService->toggleActiveStatus($shift);

        return ApiResponse::success(
            'Shift status toggled successfully',
            ['shift' => new ShiftResource($shift)]
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/shifts/{id}/duplicate",
     *     summary="Duplicate a shift",
     *     description="Create a copy of an existing shift with a new name",
     *     operationId="duplicateShift",
     *     tags={"Shifts"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Shift ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"shift_name"},
     *             @OA\Property(property="shift_name", type="string", example="Morning Shift (Copy)", description="Name for the new shift")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Shift duplicated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Shift duplicated successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="shift",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=3),
     *                     @OA\Property(property="shift_name", type="string", example="Morning Shift (copy)"),
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
     *                     @OA\Property(property="scheduled_start_time", type="string", example="06:00"),
     *                     @OA\Property(property="scheduled_end_time", type="string", example="14:00"),
     *                     @OA\Property(property="duration_minutes", type="integer", example=480),
     *                     @OA\Property(property="duration_hours", type="integer", example=8),
     *                     @OA\Property(property="shift_time_range", type="string", example="06:00 - 14:00"),
     *                     @OA\Property(
     *                         property="applicable_days",
     *                         type="array",
     *                         @OA\Items(type="string", example="monday")
     *                     ),
     *                     @OA\Property(property="is_company_wide", type="boolean", example=false),
     *                     @OA\Property(property="is_active", type="boolean", example=false),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2026-01-05T08:57:37.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2026-01-05T08:57:37.000000Z"),
     *                     @OA\Property(property="deleted_at", type="string", format="date-time", nullable=true, example=null)
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-01-05T08:57:37.922927Z"),
     *                 @OA\Property(property="request_id", type="string", example="453d6792-4a65-485a-9177-911ac6a53a0e"),
     *                 @OA\Property(property="tenant_id", type="string", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     )
     * )
     */
    public function duplicate(Request $request, Shift $shift): JsonResponse
    {
        $this->authorize('create', Shift::class);

        $request->validate([
            'shift_name' => 'required|string|max:255',
        ]);

        $newShift = $this->shiftService->duplicateShift($shift, [
            'shift_name' => $request->input('shift_name'),
        ]);

        return ApiResponse::created(
            'Shift duplicated successfully',
            ['shift' => new ShiftResource($newShift)]
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/shifts/statistics",
     *     summary="Get shift statistics",
     *     description="Retrieve statistical information about shifts",
     *     operationId="getShiftStatistics",
     *     tags={"Shifts"},
     *     @OA\Parameter(
     *         name="store_id",
     *         in="query",
     *         description="Filter by store ID",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Shift statistics retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Shift statistics retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="statistics",
     *                     type="object",
     *                     @OA\Property(property="total_shifts", type="integer", example=2),
     *                     @OA\Property(property="active_shifts", type="integer", example=0),
     *                     @OA\Property(property="inactive_shifts", type="integer", example=2),
     *                     @OA\Property(property="company_wide_shifts", type="integer", example=0),
     *                     @OA\Property(property="store_specific_shifts", type="integer", example=2)
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-01-05T08:59:35.967427Z"),
     *                 @OA\Property(property="request_id", type="string", example="8efbee82-7464-4655-86bc-d1d0ec8ad507"),
     *                 @OA\Property(property="tenant_id", type="string", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     )
     * )
     */
    public function statistics(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Shift::class);

        $storeId = $request->input('store_id');
        $stats = $this->shiftService->getShiftStatistics($storeId);

        return ApiResponse::success(
            'Shift statistics retrieved successfully',
            ['statistics' => $stats]
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/shifts/for-date",
     *     summary="Get shifts for a specific date",
     *     description="Retrieve all shifts applicable for a given date based on their applicable_days configuration",
     *     operationId="getShiftsForDate",
     *     tags={"Shifts"},
     *     @OA\Parameter(
     *         name="date",
     *         in="query",
     *         description="Date in Y-m-d format",
     *         required=true,
     *         @OA\Schema(type="string", format="date", example="2026-01-05")
     *     ),
     *     @OA\Parameter(
     *         name="store_id",
     *         in="query",
     *         description="Filter by store ID",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Shifts for date retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Shifts for date retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="shifts",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=2),
     *                         @OA\Property(property="shift_name", type="string", example="Morning Shift"),
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
     *                     )
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-01-05T09:25:02.502080Z"),
     *                 @OA\Property(property="request_id", type="string", example="7777a458-63b8-44a1-8500-3e8520d959be"),
     *                 @OA\Property(property="tenant_id", type="string", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     )
     * )
     */
    public function forDate(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Shift::class);

        $request->validate([
            'date' => 'required|date',
            'store_id' => 'nullable|integer|exists:stores,id',
        ]);

        $date = \Carbon\Carbon::parse($request->input('date'));
        $storeId = $request->input('store_id');

        $shifts = $this->shiftService->getShiftsForDate($date, $storeId);

        return ApiResponse::success(
            'Shifts for date retrieved successfully',
            ['shifts' => ShiftResource::collection($shifts)]
        );
    }
}
