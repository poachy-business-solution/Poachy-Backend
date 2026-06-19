<?php

namespace App\Http\Controllers\Api\Tenant\Shift;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant\ShiftAssignment;
use App\Services\Tenant\Shift\ShiftAnalyticsService;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShiftAnalyticsController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected ShiftAnalyticsService $analyticsService
    ) {}

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/shift-analytics/attendance-rate",
     *     summary="Get attendance rate for a user",
     *     description="Retrieves the attendance rate for a specific user within a given date range",
     *     operationId="getAttendanceRate",
     *     tags={"Tenant - Shift Analytics"},
     *     @OA\Parameter(
     *         name="user_id",
     *         in="query",
     *         description="User ID",
     *         required=true,
     *         example=1,
     *         @OA\Schema(
     *             type="integer"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="date_from",
     *         in="query",
     *         description="Start date",
     *         required=true,
     *         example="2025-01-01",
     *         @OA\Schema(
     *             type="string",
     *             format="date"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="date_to",
     *         in="query",
     *         description="End date",
     *         required=true,
     *         example="2025-01-31",
     *         @OA\Schema(
     *             type="string",
     *             format="date"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Attendance rate calculated successfully",
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
     *                 example="Attendance rate calculated successfully"
     *             ),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="user_id",
     *                     type="string",
     *                     example="3"
     *                 ),
     *                 @OA\Property(
     *                     property="attendance_rate",
     *                     type="integer",
     *                     example=25
     *                 ),
     *                 @OA\Property(
     *                     property="period",
     *                     type="object",
     *                     @OA\Property(
     *                         property="from",
     *                         type="string",
     *                         format="date",
     *                         example="2026-01-02"
     *                     ),
     *                     @OA\Property(
     *                         property="to",
     *                         type="string",
     *                         format="date",
     *                         example="2026-01-10"
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
     *                     example="2026-01-05T11:25:23.326702Z"
     *                 ),
     *                 @OA\Property(
     *                     property="request_id",
     *                     type="string",
     *                     format="uuid",
     *                     example="7d426609-18b7-4a8b-9d09-0ce51f4e3dae"
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
    public function attendanceRate(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ShiftAssignment::class);

        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
        ]);

        $rate = $this->analyticsService->calculateAttendanceRate(
            $request->input('user_id'),
            Carbon::parse($request->input('date_from')),
            Carbon::parse($request->input('date_to'))
        );

        return ApiResponse::success(
            'Attendance rate calculated successfully',
            [
                'user_id' => $request->input('user_id'),
                'attendance_rate' => $rate,
                'period' => [
                    'from' => $request->input('date_from'),
                    'to' => $request->input('date_to'),
                ],
            ]
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/shift-analytics/cash-variances",
     *     summary="Get cash variance analysis",
     *     description="Retrieves cash variance analysis for a specific store within a given date range",
     *     operationId="getCashVariances",
     *     tags={"Tenant - Shift Analytics"},
     *     @OA\Parameter(
     *         name="store_id",
     *         in="query",
     *         description="Store ID",
     *         required=true,
     *         example=1,
     *         @OA\Schema(
     *             type="integer"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="date_from",
     *         in="query",
     *         description="Start date",
     *         required=true,
     *         example="2025-01-01",
     *         @OA\Schema(
     *             type="string",
     *             format="date"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="date_to",
     *         in="query",
     *         description="End date",
     *         required=true,
     *         example="2025-01-31",
     *         @OA\Schema(
     *             type="string",
     *             format="date"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Cash variances calculated successfully",
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
     *                 example="Cash variances calculated successfully"
     *             ),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="cash_variances",
     *                     type="object",
     *                     @OA\Property(
     *                         property="total_shifts",
     *                         type="integer",
     *                         example=1
     *                     ),
     *                     @OA\Property(
     *                         property="total_variance",
     *                         type="number",
     *                         example=0
     *                     ),
     *                     @OA\Property(
     *                         property="positive_variance",
     *                         type="number",
     *                         example=0
     *                     ),
     *                     @OA\Property(
     *                         property="negative_variance",
     *                         type="number",
     *                         example=0
     *                     ),
     *                     @OA\Property(
     *                         property="average_variance",
     *                         type="number",
     *                         example=0
     *                     ),
     *                     @OA\Property(
     *                         property="significant_variances",
     *                         type="array",
     *                         @OA\Items(type="object")
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
     *                     example="2026-01-05T11:28:12.327034Z"
     *                 ),
     *                 @OA\Property(
     *                     property="request_id",
     *                     type="string",
     *                     format="uuid",
     *                     example="72af1ab1-12f6-44bf-8f9b-84b8ab4cda94"
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
    public function cashVariances(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ShiftAssignment::class);

        $request->validate([
            'store_id' => 'required|integer|exists:stores,id',
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
        ]);

        $variances = $this->analyticsService->calculateCashVariances(
            $request->input('store_id'),
            Carbon::parse($request->input('date_from')),
            Carbon::parse($request->input('date_to'))
        );

        return ApiResponse::success(
            'Cash variances calculated successfully',
            ['cash_variances' => $variances]
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/shift-analytics/top-performers",
     *     summary="Get top performing employees",
     *     description="Retrieves top performing employees for a specific store based on selected metric within a given date range",
     *     operationId="getTopPerformers",
     *     tags={"Tenant - Shift Analytics"},
     *     @OA\Parameter(
     *         name="store_id",
     *         in="query",
     *         description="Store ID",
     *         required=true,
     *         example=1,
     *         @OA\Schema(
     *             type="integer"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="metric",
     *         in="query",
     *         description="Performance metric",
     *         required=false,
     *         example="attendance",
     *         @OA\Schema(
     *             type="string",
     *             enum={"attendance", "punctuality", "sales"}
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="date_from",
     *         in="query",
     *         description="Start date",
     *         required=true,
     *         example="2025-01-01",
     *         @OA\Schema(
     *             type="string",
     *             format="date"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="date_to",
     *         in="query",
     *         description="End date",
     *         required=true,
     *         example="2025-01-31",
     *         @OA\Schema(
     *             type="string",
     *             format="date"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         description="Number of top performers to return",
     *         required=false,
     *         example=10,
     *         @OA\Schema(
     *             type="integer"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Top performers retrieved successfully",
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
     *                 example="Top performers retrieved successfully"
     *             ),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="top_performers",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(
     *                             property="user_id",
     *                             type="integer",
     *                             example=5
     *                         ),
     *                         @OA\Property(
     *                             property="user_name",
     *                             type="string",
     *                             example="Mike cashier"
     *                         ),
     *                         @OA\Property(
     *                             property="metric",
     *                             type="string",
     *                             example="punctuality"
     *                         ),
     *                         @OA\Property(
     *                             property="score",
     *                             type="number",
     *                             example=100
     *                         ),
     *                         @OA\Property(
     *                             property="total_shifts",
     *                             type="integer",
     *                             example=4
     *                         ),
     *                         @OA\Property(
     *                             property="completed_shifts",
     *                             type="integer",
     *                             example=0
     *                         )
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
     *                     example="2026-01-05T11:32:37.122656Z"
     *                 ),
     *                 @OA\Property(
     *                     property="request_id",
     *                     type="string",
     *                     format="uuid",
     *                     example="726af45b-a0f4-4c9d-a7a6-28cb431a0d9b"
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
    public function topPerformers(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ShiftAssignment::class);

        $request->validate([
            'store_id' => 'required|integer|exists:stores,id',
            'metric' => 'nullable|string|in:attendance,punctuality,sales',
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
            'limit' => 'nullable|integer|min:1|max:50',
        ]);

        $performers = $this->analyticsService->getTopPerformingEmployees(
            $request->input('store_id'),
            $request->input('metric', 'attendance'),
            Carbon::parse($request->input('date_from')),
            Carbon::parse($request->input('date_to')),
            $request->input('limit', 10)
        );

        return ApiResponse::success(
            'Top performers retrieved successfully',
            ['top_performers' => $performers]
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/shift-analytics/coverage-report",
     *     summary="Get shift coverage report",
     *     description="Retrieves detailed shift coverage report for a specific store within a given date range, including daily breakdown and summary statistics",
     *     operationId="getCoverageReport",
     *     tags={"Tenant - Shift Analytics"},
     *     @OA\Parameter(
     *         name="store_id",
     *         in="query",
     *         description="Store ID",
     *         required=true,
     *         example=1,
     *         @OA\Schema(
     *             type="integer"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="date_from",
     *         in="query",
     *         description="Start date",
     *         required=true,
     *         example="2025-01-01",
     *         @OA\Schema(
     *             type="string",
     *             format="date"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="date_to",
     *         in="query",
     *         description="End date",
     *         required=true,
     *         example="2025-01-31",
     *         @OA\Schema(
     *             type="string",
     *             format="date"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Coverage report generated successfully",
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
     *                 example="Coverage report generated successfully"
     *             ),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="coverage_report",
     *                     type="object",
     *                     @OA\Property(
     *                         property="daily_coverage",
     *                         type="array",
     *                         @OA\Items(
     *                             type="object",
     *                             @OA\Property(
     *                                 property="date",
     *                                 type="string",
     *                                 format="date",
     *                                 example="2026-01-02"
     *                             ),
     *                             @OA\Property(
     *                                 property="day_of_week",
     *                                 type="string",
     *                                 example="Friday"
     *                             ),
     *                             @OA\Property(
     *                                 property="total_shifts",
     *                                 type="integer",
     *                                 example=0
     *                             ),
     *                             @OA\Property(
     *                                 property="scheduled",
     *                                 type="integer",
     *                                 example=0
     *                             ),
     *                             @OA\Property(
     *                                 property="in_progress",
     *                                 type="integer",
     *                                 example=0
     *                             ),
     *                             @OA\Property(
     *                                 property="completed",
     *                                 type="integer",
     *                                 example=0
     *                             ),
     *                             @OA\Property(
     *                                 property="no_show",
     *                                 type="integer",
     *                                 example=0
     *                             ),
     *                             @OA\Property(
     *                                 property="cancelled",
     *                                 type="integer",
     *                                 example=0
     *                             ),
     *                             @OA\Property(
     *                                 property="coverage_rate",
     *                                 type="number",
     *                                 example=0
     *                             )
     *                         )
     *                     ),
     *                     @OA\Property(
     *                         property="summary",
     *                         type="object",
     *                         @OA\Property(
     *                             property="total_shifts",
     *                             type="integer",
     *                             example=8
     *                         ),
     *                         @OA\Property(
     *                             property="completed_shifts",
     *                             type="integer",
     *                             example=1
     *                         ),
     *                         @OA\Property(
     *                             property="no_shows",
     *                             type="integer",
     *                             example=0
     *                         ),
     *                         @OA\Property(
     *                             property="overall_coverage_rate",
     *                             type="number",
     *                             example=12.5
     *                         ),
     *                         @OA\Property(
     *                             property="no_show_rate",
     *                             type="number",
     *                             example=0
     *                         )
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
     *                     example="2026-01-05T11:38:46.120374Z"
     *                 ),
     *                 @OA\Property(
     *                     property="request_id",
     *                     type="string",
     *                     format="uuid",
     *                     example="e47b02d6-131f-4dcb-aaf8-9d8c024d463b"
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
    public function coverageReport(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ShiftAssignment::class);

        $request->validate([
            'store_id' => 'required|integer|exists:stores,id',
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
        ]);

        $report = $this->analyticsService->getShiftCoverageReport(
            $request->input('store_id'),
            Carbon::parse($request->input('date_from')),
            Carbon::parse($request->input('date_to'))
        );

        return ApiResponse::success(
            'Coverage report generated successfully',
            ['coverage_report' => $report]
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/shift-analytics/overtime-analysis",
     *     summary="Get overtime analysis",
     *     description="Retrieves overtime analysis for a specific store within a given date range, including total overtime hours, rates, and breakdown by user",
     *     operationId="getOvertimeAnalysis",
     *     tags={"Tenant - Shift Analytics"},
     *     @OA\Parameter(
     *         name="store_id",
     *         in="query",
     *         description="Store ID",
     *         required=true,
     *         example=1,
     *         @OA\Schema(
     *             type="integer"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="date_from",
     *         in="query",
     *         description="Start date",
     *         required=true,
     *         example="2025-01-01",
     *         @OA\Schema(
     *             type="string",
     *             format="date"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="date_to",
     *         in="query",
     *         description="End date",
     *         required=true,
     *         example="2025-01-31",
     *         @OA\Schema(
     *             type="string",
     *             format="date"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Overtime analysis generated successfully",
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
     *                 example="Overtime analysis generated successfully"
     *             ),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="overtime_analysis",
     *                     type="object",
     *                     @OA\Property(
     *                         property="total_overtime_minutes",
     *                         type="integer",
     *                         example=0
     *                     ),
     *                     @OA\Property(
     *                         property="total_overtime_hours",
     *                         type="number",
     *                         example=0
     *                     ),
     *                     @OA\Property(
     *                         property="shifts_with_overtime",
     *                         type="integer",
     *                         example=0
     *                     ),
     *                     @OA\Property(
     *                         property="total_shifts",
     *                         type="integer",
     *                         example=1
     *                     ),
     *                     @OA\Property(
     *                         property="overtime_rate",
     *                         type="number",
     *                         example=0
     *                     ),
     *                     @OA\Property(
     *                         property="by_user",
     *                         type="array",
     *                         @OA\Items(type="object")
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
     *                     example="2026-01-05T11:42:58.267907Z"
     *                 ),
     *                 @OA\Property(
     *                     property="request_id",
     *                     type="string",
     *                     format="uuid",
     *                     example="87b3695b-46c8-4f20-845b-867295d5f000"
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
    public function overtimeAnalysis(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ShiftAssignment::class);

        $request->validate([
            'store_id' => 'required|integer|exists:stores,id',
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
        ]);

        $analysis = $this->analyticsService->getOvertimeAnalysis(
            $request->input('store_id'),
            Carbon::parse($request->input('date_from')),
            Carbon::parse($request->input('date_to'))
        );

        return ApiResponse::success(
            'Overtime analysis generated successfully',
            ['overtime_analysis' => $analysis]
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/shift-analytics/punctuality-analysis",
     *     summary="Get punctuality analysis",
     *     description="Retrieves punctuality analysis for a specific store within a given date range, including late arrivals, early departures, and punctuality rates",
     *     operationId="getPunctualityAnalysis",
     *     tags={"Tenant - Shift Analytics"},
     *     @OA\Parameter(
     *         name="store_id",
     *         in="query",
     *         description="Store ID",
     *         required=true,
     *         example=1,
     *         @OA\Schema(
     *             type="integer"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="date_from",
     *         in="query",
     *         description="Start date",
     *         required=true,
     *         example="2025-01-01",
     *         @OA\Schema(
     *             type="string",
     *             format="date"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="date_to",
     *         in="query",
     *         description="End date",
     *         required=true,
     *         example="2025-01-31",
     *         @OA\Schema(
     *             type="string",
     *             format="date"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Punctuality analysis generated successfully",
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
     *                 example="Punctuality analysis generated successfully"
     *             ),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="punctuality_analysis",
     *                     type="object",
     *                     @OA\Property(
     *                         property="total_shifts",
     *                         type="integer",
     *                         example=1
     *                     ),
     *                     @OA\Property(
     *                         property="late_arrivals",
     *                         type="integer",
     *                         example=1
     *                     ),
     *                     @OA\Property(
     *                         property="early_departures",
     *                         type="integer",
     *                         example=0
     *                     ),
     *                     @OA\Property(
     *                         property="punctual",
     *                         type="integer",
     *                         example=0
     *                     ),
     *                     @OA\Property(
     *                         property="late_rate",
     *                         type="number",
     *                         example=100
     *                     ),
     *                     @OA\Property(
     *                         property="early_departure_rate",
     *                         type="number",
     *                         example=0
     *                     ),
     *                     @OA\Property(
     *                         property="punctuality_rate",
     *                         type="number",
     *                         example=0
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
     *                     example="2026-01-05T11:44:55.312412Z"
     *                 ),
     *                 @OA\Property(
     *                     property="request_id",
     *                     type="string",
     *                     format="uuid",
     *                     example="3afc5178-3d60-4d4e-aa64-6f3a1e88fc4a"
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
    public function punctualityAnalysis(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ShiftAssignment::class);

        $request->validate([
            'store_id' => 'required|integer|exists:stores,id',
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
        ]);

        $analysis = $this->analyticsService->getPunctualityAnalysis(
            $request->input('store_id'),
            Carbon::parse($request->input('date_from')),
            Carbon::parse($request->input('date_to'))
        );

        return ApiResponse::success(
            'Punctuality analysis generated successfully',
            ['punctuality_analysis' => $analysis]
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/shift-analytics/dashboard-summary",
     *     summary="Dashboard summary (combines multiple metrics)",
     *     description="Retrieves a comprehensive dashboard summary for a specific store within a given date range, combining coverage, cash variances, overtime, punctuality, and top performers metrics",
     *     operationId="getDashboardSummary",
     *     tags={"Tenant - Shift Analytics"},
     *     @OA\Parameter(
     *         name="store_id",
     *         in="query",
     *         description="Store ID",
     *         required=true,
     *         example=1,
     *         @OA\Schema(
     *             type="integer"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="date_from",
     *         in="query",
     *         description="Start date",
     *         required=true,
     *         example="2025-01-01",
     *         @OA\Schema(
     *             type="string",
     *             format="date"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="date_to",
     *         in="query",
     *         description="End date",
     *         required=true,
     *         example="2025-01-31",
     *         @OA\Schema(
     *             type="string",
     *             format="date"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Dashboard summary generated successfully",
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
     *                 example="Dashboard summary generated successfully"
     *             ),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="dashboard",
     *                     type="object",
     *                     @OA\Property(
     *                         property="coverage",
     *                         type="object",
     *                         @OA\Property(
     *                             property="daily_coverage",
     *                             type="array",
     *                             @OA\Items(
     *                                 type="object",
     *                                 @OA\Property(
     *                                     property="date",
     *                                     type="string",
     *                                     format="date",
     *                                     example="2026-01-01"
     *                                 ),
     *                                 @OA\Property(
     *                                     property="day_of_week",
     *                                     type="string",
     *                                     example="Thursday"
     *                                 ),
     *                                 @OA\Property(
     *                                     property="total_shifts",
     *                                     type="integer",
     *                                     example=0
     *                                 ),
     *                                 @OA\Property(
     *                                     property="scheduled",
     *                                     type="integer",
     *                                     example=0
     *                                 ),
     *                                 @OA\Property(
     *                                     property="in_progress",
     *                                     type="integer",
     *                                     example=0
     *                                 ),
     *                                 @OA\Property(
     *                                     property="completed",
     *                                     type="integer",
     *                                     example=0
     *                                 ),
     *                                 @OA\Property(
     *                                     property="no_show",
     *                                     type="integer",
     *                                     example=0
     *                                 ),
     *                                 @OA\Property(
     *                                     property="cancelled",
     *                                     type="integer",
     *                                     example=0
     *                                 ),
     *                                 @OA\Property(
     *                                     property="coverage_rate",
     *                                     type="number",
     *                                     example=0
     *                                 )
     *                             )
     *                         ),
     *                         @OA\Property(
     *                             property="summary",
     *                             type="object",
     *                             @OA\Property(
     *                                 property="total_shifts",
     *                                 type="integer",
     *                                 example=2
     *                             ),
     *                             @OA\Property(
     *                                 property="completed_shifts",
     *                                 type="integer",
     *                                 example=1
     *                             ),
     *                             @OA\Property(
     *                                 property="no_shows",
     *                                 type="integer",
     *                                 example=0
     *                             ),
     *                             @OA\Property(
     *                                 property="overall_coverage_rate",
     *                                 type="number",
     *                                 example=50
     *                             ),
     *                             @OA\Property(
     *                                 property="no_show_rate",
     *                                 type="number",
     *                                 example=0
     *                             )
     *                         )
     *                     ),
     *                     @OA\Property(
     *                         property="cash_variances",
     *                         type="object",
     *                         @OA\Property(
     *                             property="total_shifts",
     *                             type="integer",
     *                             example=1
     *                         ),
     *                         @OA\Property(
     *                             property="total_variance",
     *                             type="number",
     *                             example=0
     *                         ),
     *                         @OA\Property(
     *                             property="positive_variance",
     *                             type="number",
     *                             example=0
     *                         ),
     *                         @OA\Property(
     *                             property="negative_variance",
     *                             type="number",
     *                             example=0
     *                         ),
     *                         @OA\Property(
     *                             property="average_variance",
     *                             type="number",
     *                             example=0
     *                         ),
     *                         @OA\Property(
     *                             property="significant_variances",
     *                             type="array",
     *                             @OA\Items(type="object")
     *                         )
     *                     ),
     *                     @OA\Property(
     *                         property="overtime",
     *                         type="object",
     *                         @OA\Property(
     *                             property="total_overtime_minutes",
     *                             type="integer",
     *                             example=0
     *                         ),
     *                         @OA\Property(
     *                             property="total_overtime_hours",
     *                             type="number",
     *                             example=0
     *                         ),
     *                         @OA\Property(
     *                             property="shifts_with_overtime",
     *                             type="integer",
     *                             example=0
     *                         ),
     *                         @OA\Property(
     *                             property="total_shifts",
     *                             type="integer",
     *                             example=1
     *                         ),
     *                         @OA\Property(
     *                             property="overtime_rate",
     *                             type="number",
     *                             example=0
     *                         ),
     *                         @OA\Property(
     *                             property="by_user",
     *                             type="array",
     *                             @OA\Items(type="object")
     *                         )
     *                     ),
     *                     @OA\Property(
     *                         property="punctuality",
     *                         type="object",
     *                         @OA\Property(
     *                             property="total_shifts",
     *                             type="integer",
     *                             example=1
     *                         ),
     *                         @OA\Property(
     *                             property="late_arrivals",
     *                             type="integer",
     *                             example=1
     *                         ),
     *                         @OA\Property(
     *                             property="early_departures",
     *                             type="integer",
     *                             example=0
     *                         ),
     *                         @OA\Property(
     *                             property="punctual",
     *                             type="integer",
     *                             example=0
     *                         ),
     *                         @OA\Property(
     *                             property="late_rate",
     *                             type="number",
     *                             example=100
     *                         ),
     *                         @OA\Property(
     *                             property="early_departure_rate",
     *                             type="number",
     *                             example=0
     *                         ),
     *                         @OA\Property(
     *                             property="punctuality_rate",
     *                             type="number",
     *                             example=0
     *                         )
     *                     ),
     *                     @OA\Property(
     *                         property="top_performers",
     *                         type="array",
     *                         @OA\Items(
     *                             type="object",
     *                             @OA\Property(
     *                                 property="user_id",
     *                                 type="integer",
     *                                 example=3
     *                             ),
     *                             @OA\Property(
     *                                 property="user_name",
     *                                 type="string",
     *                                 example="Jane Cashier"
     *                             ),
     *                             @OA\Property(
     *                                 property="metric",
     *                                 type="string",
     *                                 example="attendance"
     *                             ),
     *                             @OA\Property(
     *                                 property="score",
     *                                 type="number",
     *                                 example=0
     *                             ),
     *                             @OA\Property(
     *                                 property="total_shifts",
     *                                 type="integer",
     *                                 example=1
     *                             ),
     *                             @OA\Property(
     *                                 property="completed_shifts",
     *                                 type="integer",
     *                                 example=1
     *                             )
     *                         )
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
     *                     example="2026-01-05T11:49:44.841883Z"
     *                 ),
     *                 @OA\Property(
     *                     property="request_id",
     *                     type="string",
     *                     format="uuid",
     *                     example="60ff6ee9-9109-402a-9586-8cecbf777baa"
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
    public function dashboardSummary(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ShiftAssignment::class);

        $request->validate([
            'store_id' => 'required|integer|exists:stores,id',
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
        ]);

        $storeId = $request->input('store_id');
        $dateFrom = Carbon::parse($request->input('date_from'));
        $dateTo = Carbon::parse($request->input('date_to'));

        // Gather multiple metrics for dashboard
        $summary = [
            'coverage' => $this->analyticsService->getShiftCoverageReport($storeId, $dateFrom, $dateTo),
            'cash_variances' => $this->analyticsService->calculateCashVariances($storeId, $dateFrom, $dateTo),
            'overtime' => $this->analyticsService->getOvertimeAnalysis($storeId, $dateFrom, $dateTo),
            'punctuality' => $this->analyticsService->getPunctualityAnalysis($storeId, $dateFrom, $dateTo),
            'top_performers' => $this->analyticsService->getTopPerformingEmployees($storeId, 'attendance', $dateFrom, $dateTo, 5),
        ];

        return ApiResponse::success(
            'Dashboard summary generated successfully',
            ['dashboard' => $summary]
        );
    }
}
