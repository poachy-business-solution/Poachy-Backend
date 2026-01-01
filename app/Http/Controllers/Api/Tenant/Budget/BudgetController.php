<?php

namespace App\Http\Controllers\Api\Tenant\Budget;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Budget\StoreBudgetRequest;
use App\Http\Requests\Tenant\Budget\UpdateBudgetRequest;
use App\Http\Resources\Tenant\Budget\BudgetCollection;
use App\Http\Resources\Tenant\Budget\BudgetPerformanceResource;
use App\Http\Resources\Tenant\Budget\BudgetResource;
use App\Http\Resources\Tenant\Expense\ExpenseResource;
use App\Http\Responses\ApiResponse;
use App\Services\Tenant\Expenses\BudgetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BudgetController extends Controller
{
    public function __construct(
        protected BudgetService $service
    ) {}

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/budgets",
     *     summary="Get paginated list of budgets",
     *     description="Retrieves a paginated list of all budgets for the current tenant with comprehensive filtering options",
     *     operationId="getBudgets",
     *     tags={"Tenant - Budget Management"},
     *     security={{"sanctum": {}}},
     *     
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number for pagination",
     *         required=false,
     *         @OA\Schema(type="integer", default=1, example=1)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of items per page",
     *         required=false,
     *         @OA\Schema(type="integer", default=15, example=15)
     *     ),
     *     @OA\Parameter(
     *         name="category_id",
     *         in="query",
     *         description="Filter by expense category ID",
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
     *         name="company_wide",
     *         in="query",
     *         description="Filter company-wide budgets (budgets with no specific store)",
     *         required=false,
     *         @OA\Schema(type="boolean", example=true)
     *     ),
     *     @OA\Parameter(
     *         name="period_type",
     *         in="query",
     *         description="Filter by budget period type",
     *         required=false,
     *         @OA\Schema(
     *             type="string",
     *             enum={"monthly", "quarterly", "yearly", "custom"}
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="is_active",
     *         in="query",
     *         description="Filter by active status",
     *         required=false,
     *         @OA\Schema(type="boolean", example=true)
     *     ),
     *     @OA\Parameter(
     *         name="alert_triggered",
     *         in="query",
     *         description="Filter budgets with triggered alerts (spending exceeded threshold)",
     *         required=false,
     *         @OA\Schema(type="boolean", example=true)
     *     ),
     *     @OA\Parameter(
     *         name="over_budget",
     *         in="query",
     *         description="Filter budgets that have exceeded their allocated amount",
     *         required=false,
     *         @OA\Schema(type="boolean", example=true)
     *     ),
     *     @OA\Parameter(
     *         name="current",
     *         in="query",
     *         description="Filter currently active budgets (today falls within period_start and period_end)",
     *         required=false,
     *         @OA\Schema(type="boolean", example=true)
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search in budget name or notes",
     *         required=false,
     *         @OA\Schema(type="string", example="Marketing")
     *     ),
     *     
     *     @OA\Response(
     *         response=200,
     *         description="Budgets retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Budgets retrieved successfully."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="data",
     *                     type="array",
     *                     @OA\Items(ref="#/components/schemas/BudgetResource")
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
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-31T09:47:24.376114Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="e6629e5e-311e-440b-b5a8-2ef9de951e63"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - Insufficient permissions",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="This action is unauthorized.")
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="An unexpected error occurred.")
     *         )
     *     )
     * )
     * 
     * @OA\Schema(
     *     schema="BudgetResource",
     *     type="object",
     *     @OA\Property(property="id", type="integer", example=1),
     *     @OA\Property(property="budget_name", type="string", example="Q1 2025 Marketing Budget"),
     *     @OA\Property(property="store_id", type="integer", nullable=true, example=1, description="Null for company-wide budgets"),
     *     @OA\Property(property="category_id", type="integer", example=5),
     *     @OA\Property(property="period_type", type="string", example="quarterly"),
     *     @OA\Property(property="period_type_label", type="string", example="Quarterly"),
     *     @OA\Property(property="period_start", type="string", format="date", example="2025-01-01"),
     *     @OA\Property(property="period_end", type="string", format="date", example="2025-03-31"),
     *     @OA\Property(property="period_label", type="string", example="Quarterly (Jan 01, 2025 - Mar 31, 2025)"),
     *     @OA\Property(property="budget_amount", type="string", example="500000.00"),
     *     @OA\Property(property="formatted_budget_amount", type="string", example="KES 500,000.00"),
     *     @OA\Property(property="spent_amount", type="string", example="0.00", description="Total expenses in this period"),
     *     @OA\Property(property="formatted_spent_amount", type="string", example="KES 0.00"),
     *     @OA\Property(property="remaining_amount", type="string", example="500000.00", description="Budget minus spent"),
     *     @OA\Property(property="formatted_remaining_amount", type="string", example="KES 500,000.00"),
     *     @OA\Property(property="committed_amount", type="string", example="0.00", description="Reserved/pending amounts"),
     *     @OA\Property(property="percentage_spent", type="number", format="float", example=0, description="Percentage of budget used"),
     *     @OA\Property(property="percentage_remaining", type="number", format="float", example=100),
     *     @OA\Property(property="alert_threshold_percentage", type="string", example="80.00"),
     *     @OA\Property(property="alert_triggered", type="boolean", example=false, description="True if spending exceeds threshold"),
     *     @OA\Property(property="alert_triggered_at", type="string", format="date-time", nullable=true, example=null),
     *     @OA\Property(property="is_active", type="boolean", example=true),
     *     @OA\Property(property="is_active_now", type="boolean", example=false, description="True if today is within budget period"),
     *     @OA\Property(property="status", type="string", example="on_track", description="Budget status: on_track, warning, over_budget"),
     *     @OA\Property(property="status_label", type="string", example="On Track"),
     *     @OA\Property(property="status_color", type="string", example="success", description="UI color: success, warning, danger"),
     *     @OA\Property(property="is_over_budget", type="boolean", example=false),
     *     @OA\Property(property="is_near_threshold", type="boolean", example=false, description="True if close to alert threshold"),
     *     @OA\Property(property="notes", type="string", nullable=true, example="Allocated for digital marketing campaigns"),
     *     @OA\Property(
     *         property="category",
     *         type="object",
     *         @OA\Property(property="id", type="integer", example=5),
     *         @OA\Property(property="name", type="string", example="Internet & Phone"),
     *         @OA\Property(property="code", type="string", example="NETPHONE"),
     *         @OA\Property(property="full_path", type="string", example="Utilities > Internet & Phone")
     *     ),
     *     @OA\Property(
     *         property="store",
     *         type="object",
     *         nullable=true,
     *         @OA\Property(property="id", type="integer", example=1),
     *         @OA\Property(property="code", type="string", example="STR-2025-74622"),
     *         @OA\Property(property="name", type="string", example="Branch Store - Mombasa")
     *     ),
     *     @OA\Property(
     *         property="creator",
     *         type="object",
     *         @OA\Property(property="id", type="integer", example=1),
     *         @OA\Property(property="name", type="string", example="John Doe"),
     *         @OA\Property(property="email", type="string", example="john@techhaven.com")
     *     ),
     *     @OA\Property(property="created_by", type="integer", example=1),
     *     @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-31T09:22:02.000000Z"),
     *     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-31T09:22:02.000000Z")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->only([
                'category_id',
                'store_id',
                'company_wide',
                'period_type',
                'is_active',
                'alert_triggered',
                'over_budget',
                'period_start',
                'period_end',
                'current',
                'search',
            ]);

            $perPage = $request->integer('per_page', 15);
            $budgets = $this->service->getPaginatedBudgets($filters, $perPage);

            return ApiResponse::paginated(
                new BudgetCollection($budgets),
                'Budgets retrieved successfully.'
            );
        } catch (\Exception $e) {
            return ApiResponse::error(
                'Failed to retrieve budgets.',
                ['error' => $e->getMessage()],
                500
            );
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/budgets",
     *     summary="Create a new budget",
     *     description="Creates a new budget for expense tracking and monitoring within a specified period",
     *     operationId="createBudget",
     *     tags={"Tenant - Budget Management"},
     *     security={{"sanctum": {}}},
     *     
     *     @OA\RequestBody(
     *         required=true,
     *         description="Budget data",
     *         @OA\JsonContent(
     *             required={"budget_name", "category_id", "period_type", "period_start", "period_end", "budget_amount", "alert_threshold_percentage"},
     *             @OA\Property(
     *                 property="budget_name",
     *                 type="string",
     *                 description="Descriptive name for the budget (max: 255 characters)",
     *                 example="Q1 2025 Marketing Budget",
     *                 maxLength=255
     *             ),
     *             @OA\Property(
     *                 property="category_id",
     *                 type="integer",
     *                 description="ID of the expense category this budget applies to (must be active)",
     *                 example=5
     *             ),
     *             @OA\Property(
     *                 property="store_id",
     *                 type="integer",
     *                 nullable=true,
     *                 description="ID of the store this budget applies to. If null, budget is company-wide",
     *                 example=1
     *             ),
     *             @OA\Property(
     *                 property="period_type",
     *                 type="string",
     *                 description="Type of budget period",
     *                 enum={"monthly", "quarterly", "yearly", "custom"},
     *                 example="quarterly"
     *             ),
     *             @OA\Property(
     *                 property="period_start",
     *                 type="string",
     *                 format="date",
     *                 description="Start date of the budget period (YYYY-MM-DD)",
     *                 example="2025-01-01"
     *             ),
     *             @OA\Property(
     *                 property="period_end",
     *                 type="string",
     *                 format="date",
     *                 description="End date of the budget period (YYYY-MM-DD, must be after period_start)",
     *                 example="2025-03-31"
     *             ),
     *             @OA\Property(
     *                 property="budget_amount",
     *                 type="number",
     *                 format="float",
     *                 description="Total budget amount allocated (min: 0.01, max: 9999999999.99)",
     *                 example=500000.00,
     *                 minimum=0.01,
     *                 maximum=9999999999.99
     *             ),
     *             @OA\Property(
     *                 property="alert_threshold_percentage",
     *                 type="number",
     *                 format="float",
     *                 description="Percentage of budget spent that triggers an alert (0-100)",
     *                 example=80,
     *                 minimum=0,
     *                 maximum=100
     *             ),
     *             @OA\Property(
     *                 property="notes",
     *                 type="string",
     *                 nullable=true,
     *                 description="Additional notes or description (max: 5000 characters)",
     *                 example="Allocated for digital marketing campaigns",
     *                 maxLength=5000
     *             ),
     *             @OA\Property(
     *                 property="is_active",
     *                 type="boolean",
     *                 description="Whether the budget is active",
     *                 example=true
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=201,
     *         description="Budget created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Budget created successfully."),
     *             @OA\Property(property="data", ref="#/components/schemas/BudgetResource"),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-31T09:22:02.587618Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="231f13dc-3d88-46f5-9833-25c1ceed4e1d"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     
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
     *                     property="budget_name",
     *                     type="array",
     *                     @OA\Items(type="string", example="The budget name field is required.")
     *                 ),
     *                 @OA\Property(
     *                     property="category_id",
     *                     type="array",
     *                     @OA\Items(type="string", example="The category id field is required.")
     *                 ),
     *                 @OA\Property(
     *                     property="period_end",
     *                     type="array",
     *                     @OA\Items(type="string", example="The period end must be a date after period start.")
     *                 ),
     *                 @OA\Property(
     *                     property="budget_amount",
     *                     type="array",
     *                     @OA\Items(type="string", example="The budget amount must be at least 0.01.")
     *                 ),
     *                 @OA\Property(
     *                     property="alert_threshold_percentage",
     *                     type="array",
     *                     @OA\Items(type="string", example="The alert threshold percentage must be between 0 and 100.")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true)
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - Insufficient permissions",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="This action is unauthorized.")
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="An unexpected error occurred.")
     *         )
     *     )
     * )
     */
    public function store(StoreBudgetRequest $request): JsonResponse
    {
        try {
            $budget = $this->service->createBudget($request->validated());

            return ApiResponse::created(
                'Budget created successfully.',
                new BudgetResource($budget)
            );
        } catch (\Exception $e) {
            return ApiResponse::error(
                'Failed to create budget.',
                ['error' => $e->getMessage()],
                500
            );
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/budgets/{id}",
     *     summary="Get a single budget by ID",
     *     description="Retrieves detailed information about a specific budget including full category hierarchy, store details, creator information, and all calculated metrics (spent, remaining, percentages, status)",
     *     operationId="getBudgetById",
     *     tags={"Tenant - Budget Management"},
     *     security={{"sanctum": {}}},
     *     
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Budget ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     
     *     @OA\Response(
     *         response=200,
     *         description="Budget retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Budget retrieved successfully."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="budget_name", type="string", example="Q1 2025 Marketing Budget"),
     *                 @OA\Property(property="store_id", type="integer", nullable=true, example=1),
     *                 @OA\Property(property="category_id", type="integer", example=5),
     *                 @OA\Property(property="period_type", type="string", example="quarterly"),
     *                 @OA\Property(property="period_type_label", type="string", example="Quarterly"),
     *                 @OA\Property(property="period_start", type="string", format="date", example="2025-01-01"),
     *                 @OA\Property(property="period_end", type="string", format="date", example="2025-03-31"),
     *                 @OA\Property(property="period_label", type="string", example="Quarterly (Jan 01, 2025 - Mar 31, 2025)"),
     *                 @OA\Property(property="budget_amount", type="string", example="500000.00"),
     *                 @OA\Property(property="formatted_budget_amount", type="string", example="KES 500,000.00"),
     *                 @OA\Property(property="spent_amount", type="string", example="0.00"),
     *                 @OA\Property(property="formatted_spent_amount", type="string", example="KES 0.00"),
     *                 @OA\Property(property="remaining_amount", type="string", example="500000.00"),
     *                 @OA\Property(property="formatted_remaining_amount", type="string", example="KES 500,000.00"),
     *                 @OA\Property(property="committed_amount", type="string", example="0.00"),
     *                 @OA\Property(property="percentage_spent", type="number", format="float", example=0),
     *                 @OA\Property(property="percentage_remaining", type="number", format="float", example=100),
     *                 @OA\Property(property="alert_threshold_percentage", type="string", example="80.00"),
     *                 @OA\Property(property="alert_triggered", type="boolean", example=false),
     *                 @OA\Property(property="alert_triggered_at", type="string", format="date-time", nullable=true, example=null),
     *                 @OA\Property(property="is_active", type="boolean", example=true),
     *                 @OA\Property(property="is_active_now", type="boolean", example=false),
     *                 @OA\Property(property="status", type="string", example="on_track"),
     *                 @OA\Property(property="status_label", type="string", example="On Track"),
     *                 @OA\Property(property="status_color", type="string", example="success"),
     *                 @OA\Property(property="is_over_budget", type="boolean", example=false),
     *                 @OA\Property(property="is_near_threshold", type="boolean", example=false),
     *                 @OA\Property(property="notes", type="string", nullable=true, example="Allocated for digital marketing campaigns"),
     *                 @OA\Property(
     *                     property="category",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=5),
     *                     @OA\Property(property="name", type="string", example="Internet & Phone"),
     *                     @OA\Property(property="code", type="string", example="NETPHONE"),
     *                     @OA\Property(property="description", type="string", example="Internet service and business phone lines"),
     *                     @OA\Property(property="parent_id", type="integer", nullable=true, example=2),
     *                     @OA\Property(property="is_recurring_eligible", type="boolean", example=true),
     *                     @OA\Property(property="requires_receipt", type="boolean", example=true),
     *                     @OA\Property(property="requires_approval", type="boolean", example=false),
     *                     @OA\Property(property="is_active", type="boolean", example=true),
     *                     @OA\Property(property="display_order", type="integer", example=23),
     *                     @OA\Property(property="full_path", type="string", example="Utilities > Internet & Phone"),
     *                     @OA\Property(property="level", type="integer", example=1),
     *                     @OA\Property(property="has_children", type="boolean", example=false),
     *                     @OA\Property(property="has_expenses", type="boolean", example=false),
     *                     @OA\Property(property="is_deletable", type="boolean", example=false),
     *                     @OA\Property(
     *                         property="parent",
     *                         type="object",
     *                         nullable=true,
     *                         @OA\Property(property="id", type="integer", example=2),
     *                         @OA\Property(property="name", type="string", example="Utilities"),
     *                         @OA\Property(property="code", type="string", example="UTILITIES"),
     *                         @OA\Property(property="description", type="string", example="Electricity, water, internet, etc."),
     *                         @OA\Property(property="parent_id", type="integer", nullable=true, example=null),
     *                         @OA\Property(property="is_recurring_eligible", type="boolean", example=true),
     *                         @OA\Property(property="requires_receipt", type="boolean", example=true),
     *                         @OA\Property(property="requires_approval", type="boolean", example=false),
     *                         @OA\Property(property="is_active", type="boolean", example=false),
     *                         @OA\Property(property="display_order", type="integer", example=20),
     *                         @OA\Property(property="full_path", type="string", example="Utilities"),
     *                         @OA\Property(property="level", type="integer", example=0),
     *                         @OA\Property(property="has_children", type="boolean", example=true),
     *                         @OA\Property(property="has_expenses", type="boolean", example=false),
     *                         @OA\Property(property="is_deletable", type="boolean", example=false),
     *                         @OA\Property(property="parent", type="object", nullable=true, example=null),
     *                         @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-29T07:20:22.000000Z"),
     *                         @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-29T07:55:39.000000Z")
     *                     ),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-29T07:26:47.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-29T07:26:47.000000Z")
     *                 ),
     *                 @OA\Property(
     *                     property="store",
     *                     type="object",
     *                     nullable=true,
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="code", type="string", example="STR-2025-74622"),
     *                     @OA\Property(property="name", type="string", example="Branch Store - Mombasa"),
     *                     @OA\Property(property="description", type="string", example="Mombasa branch location"),
     *                     @OA\Property(property="address", type="string", example="Gedi, Kwale"),
     *                     @OA\Property(property="city", type="string", example="Mombasa"),
     *                     @OA\Property(property="region", type="string", example="Coast"),
     *                     @OA\Property(property="phone", type="string", example="+254723456789"),
     *                     @OA\Property(property="email", type="string", example="info@store.com"),
     *                     @OA\Property(property="is_main_store", type="boolean", example=true),
     *                     @OA\Property(property="is_active", type="boolean", example=true),
     *                     @OA\Property(property="status_label", type="string", example="Active"),
     *                     @OA\Property(property="store_type_label", type="string", example="Main Store"),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-14T19:48:13.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-15T18:15:20.000000Z")
     *                 ),
     *                 @OA\Property(
     *                     property="creator",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="John Doe"),
     *                     @OA\Property(property="email", type="string", example="john@techhaven.com")
     *                 ),
     *                 @OA\Property(property="created_by", type="integer", example=1),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-31T09:22:02.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-31T09:22:02.000000Z")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-31T10:08:07.375283Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="642675ba-475d-4823-932a-4506fd34680d"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=404,
     *         description="Budget not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Resource not found.")
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - Insufficient permissions",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="This action is unauthorized.")
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="An unexpected error occurred.")
     *         )
     *     )
     * )
     */
    public function show(int $id): JsonResponse
    {
        try {
            $budget = $this->service->getBudgetById($id);

            if (!$budget) {
                return ApiResponse::notFound('Budget not found.');
            }

            return ApiResponse::success(
                'Budget retrieved successfully.',
                new BudgetResource($budget)
            );
        } catch (\Exception $e) {
            return ApiResponse::error(
                'Failed to retrieve budget.',
                ['error' => $e->getMessage()],
                500
            );
        }
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/tenant/budgets/{id}",
     *     summary="Update an existing budget",
     *     description="Updates a budget's configuration. All fields are optional, allowing partial updates. Only provided fields will be updated.",
     *     operationId="updateBudget",
     *     tags={"Tenant - Budget Management"},
     *     security={{"sanctum": {}}},
     *     
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Budget ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     
     *     @OA\RequestBody(
     *         required=true,
     *         description="Budget update data (all fields optional)",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="budget_name",
     *                 type="string",
     *                 description="Descriptive name for the budget (max: 255 characters)",
     *                 example="Q1 2025 Marketing Budget",
     *                 maxLength=255
     *             ),
     *             @OA\Property(
     *                 property="category_id",
     *                 type="integer",
     *                 description="ID of the expense category (must be active)",
     *                 example=5
     *             ),
     *             @OA\Property(
     *                 property="store_id",
     *                 type="integer",
     *                 nullable=true,
     *                 description="ID of the store. Set to null for company-wide budget",
     *                 example=1
     *             ),
     *             @OA\Property(
     *                 property="period_type",
     *                 type="string",
     *                 description="Type of budget period",
     *                 enum={"monthly", "quarterly", "yearly", "custom"},
     *                 example="quarterly"
     *             ),
     *             @OA\Property(
     *                 property="period_start",
     *                 type="string",
     *                 format="date",
     *                 description="Start date of the budget period (YYYY-MM-DD)",
     *                 example="2026-01-01"
     *             ),
     *             @OA\Property(
     *                 property="period_end",
     *                 type="string",
     *                 format="date",
     *                 description="End date of the budget period (YYYY-MM-DD, must be after period_start)",
     *                 example="2026-03-31"
     *             ),
     *             @OA\Property(
     *                 property="budget_amount",
     *                 type="number",
     *                 format="float",
     *                 description="Total budget amount (min: 0.01, max: 9999999999.99)",
     *                 example=500000.00,
     *                 minimum=0.01,
     *                 maximum=9999999999.99
     *             ),
     *             @OA\Property(
     *                 property="alert_threshold_percentage",
     *                 type="number",
     *                 format="float",
     *                 description="Percentage threshold for alerts (0-100)",
     *                 example=80,
     *                 minimum=0,
     *                 maximum=100
     *             ),
     *             @OA\Property(
     *                 property="notes",
     *                 type="string",
     *                 nullable=true,
     *                 description="Additional notes (max: 5000 characters)",
     *                 example="Allocated for digital marketing campaigns",
     *                 maxLength=5000
     *             ),
     *             @OA\Property(
     *                 property="is_active",
     *                 type="boolean",
     *                 description="Whether the budget is active",
     *                 example=true
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=200,
     *         description="Budget updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Budget updated successfully."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="budget_name", type="string", example="Q1 2025 Marketing Budget"),
     *                 @OA\Property(property="store_id", type="integer", nullable=true, example=1),
     *                 @OA\Property(property="category_id", type="integer", example=5),
     *                 @OA\Property(property="period_type", type="string", example="quarterly"),
     *                 @OA\Property(property="period_type_label", type="string", example="Quarterly"),
     *                 @OA\Property(property="period_start", type="string", format="date", example="2026-01-01"),
     *                 @OA\Property(property="period_end", type="string", format="date", example="2026-03-31"),
     *                 @OA\Property(property="period_label", type="string", example="Quarterly (Jan 01, 2026 - Mar 31, 2026)"),
     *                 @OA\Property(property="budget_amount", type="string", example="500000.00"),
     *                 @OA\Property(property="formatted_budget_amount", type="string", example="KES 500,000.00"),
     *                 @OA\Property(property="spent_amount", type="string", example="0.00"),
     *                 @OA\Property(property="formatted_spent_amount", type="string", example="KES 0.00"),
     *                 @OA\Property(property="remaining_amount", type="string", example="500000.00"),
     *                 @OA\Property(property="formatted_remaining_amount", type="string", example="KES 500,000.00"),
     *                 @OA\Property(property="committed_amount", type="string", example="0.00"),
     *                 @OA\Property(property="percentage_spent", type="number", format="float", example=0),
     *                 @OA\Property(property="percentage_remaining", type="number", format="float", example=100),
     *                 @OA\Property(property="alert_threshold_percentage", type="string", example="80.00"),
     *                 @OA\Property(property="alert_triggered", type="boolean", example=false),
     *                 @OA\Property(property="alert_triggered_at", type="string", format="date-time", nullable=true, example=null),
     *                 @OA\Property(property="is_active", type="boolean", example=true),
     *                 @OA\Property(property="is_active_now", type="boolean", example=false),
     *                 @OA\Property(property="status", type="string", example="on_track"),
     *                 @OA\Property(property="status_label", type="string", example="On Track"),
     *                 @OA\Property(property="status_color", type="string", example="success"),
     *                 @OA\Property(property="is_over_budget", type="boolean", example=false),
     *                 @OA\Property(property="is_near_threshold", type="boolean", example=false),
     *                 @OA\Property(property="notes", type="string", nullable=true, example="Allocated for digital marketing campaigns"),
     *                 @OA\Property(
     *                     property="category",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=5),
     *                     @OA\Property(property="name", type="string", example="Internet & Phone"),
     *                     @OA\Property(property="code", type="string", example="NETPHONE"),
     *                     @OA\Property(property="description", type="string", example="Internet service and business phone lines"),
     *                     @OA\Property(property="parent_id", type="integer", nullable=true, example=2),
     *                     @OA\Property(property="is_recurring_eligible", type="boolean", example=true),
     *                     @OA\Property(property="requires_receipt", type="boolean", example=true),
     *                     @OA\Property(property="requires_approval", type="boolean", example=false),
     *                     @OA\Property(property="is_active", type="boolean", example=true),
     *                     @OA\Property(property="display_order", type="integer", example=23),
     *                     @OA\Property(property="full_path", type="string", example="Utilities > Internet & Phone"),
     *                     @OA\Property(property="level", type="integer", example=1),
     *                     @OA\Property(property="has_children", type="boolean", example=false),
     *                     @OA\Property(property="has_expenses", type="boolean", example=false),
     *                     @OA\Property(property="is_deletable", type="boolean", example=false),
     *                     @OA\Property(
     *                         property="parent",
     *                         type="object",
     *                         nullable=true,
     *                         @OA\Property(property="id", type="integer", example=2),
     *                         @OA\Property(property="name", type="string", example="Utilities"),
     *                         @OA\Property(property="code", type="string", example="UTILITIES"),
     *                         @OA\Property(property="description", type="string", example="Electricity, water, internet, etc."),
     *                         @OA\Property(property="parent_id", type="integer", nullable=true, example=null),
     *                         @OA\Property(property="is_recurring_eligible", type="boolean", example=true),
     *                         @OA\Property(property="requires_receipt", type="boolean", example=true),
     *                         @OA\Property(property="requires_approval", type="boolean", example=false),
     *                         @OA\Property(property="is_active", type="boolean", example=false),
     *                         @OA\Property(property="display_order", type="integer", example=20),
     *                         @OA\Property(property="full_path", type="string", example="Utilities"),
     *                         @OA\Property(property="level", type="integer", example=0),
     *                         @OA\Property(property="has_children", type="boolean", example=true),
     *                         @OA\Property(property="has_expenses", type="boolean", example=false),
     *                         @OA\Property(property="is_deletable", type="boolean", example=false),
     *                         @OA\Property(property="parent", type="object", nullable=true, example=null),
     *                         @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-29T07:20:22.000000Z"),
     *                         @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-29T07:55:39.000000Z")
     *                     ),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-29T07:26:47.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-29T07:26:47.000000Z")
     *                 ),
     *                 @OA\Property(
     *                     property="store",
     *                     type="object",
     *                     nullable=true,
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="code", type="string", example="STR-2025-74622"),
     *                     @OA\Property(property="name", type="string", example="Branch Store - Mombasa"),
     *                     @OA\Property(property="description", type="string", example="Mombasa branch location"),
     *                     @OA\Property(property="address", type="string", example="Gedi, Kwale"),
     *                     @OA\Property(property="city", type="string", example="Mombasa"),
     *                     @OA\Property(property="region", type="string", example="Coast"),
     *                     @OA\Property(property="phone", type="string", example="+254723456789"),
     *                     @OA\Property(property="email", type="string", example="info@store.com"),
     *                     @OA\Property(property="is_main_store", type="boolean", example=true),
     *                     @OA\Property(property="is_active", type="boolean", example=true),
     *                     @OA\Property(property="status_label", type="string", example="Active"),
     *                     @OA\Property(property="store_type_label", type="string", example="Main Store"),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-14T19:48:13.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-15T18:15:20.000000Z")
     *                 ),
     *                 @OA\Property(
     *                     property="creator",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="John Doe"),
     *                     @OA\Property(property="email", type="string", example="john@techhaven.com")
     *                 ),
     *                 @OA\Property(property="created_by", type="integer", example=1),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-31T09:22:02.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-31T10:11:09.000000Z")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-31T10:11:09.397023Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="7ab2c875-f389-4d20-9142-576b2832a400"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     
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
     *                     property="period_end",
     *                     type="array",
     *                     @OA\Items(type="string", example="The period end must be a date after period start.")
     *                 ),
     *                 @OA\Property(
     *                     property="budget_amount",
     *                     type="array",
     *                     @OA\Items(type="string", example="The budget amount must be at least 0.01.")
     *                 ),
     *                 @OA\Property(
     *                     property="category_id",
     *                     type="array",
     *                     @OA\Items(type="string", example="The selected category id is invalid.")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true)
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=404,
     *         description="Budget not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Resource not found.")
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - Insufficient permissions",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="This action is unauthorized.")
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="An unexpected error occurred.")
     *         )
     *     )
     * )
     */
    public function update(UpdateBudgetRequest $request, int $id): JsonResponse
    {
        try {
            $budget = $this->service->updateBudget($id, $request->validated());

            return ApiResponse::success(
                'Budget updated successfully.',
                new BudgetResource($budget)
            );
        } catch (\Exception $e) {
            return ApiResponse::error(
                'Failed to update budget.',
                ['error' => $e->getMessage()],
                500
            );
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/tenant/budgets/{id}",
     *     summary="Delete a budget",
     *     description="Soft deletes a budget record. The budget will be marked as deleted but retained in the database for audit purposes.",
     *     operationId="deleteBudget",
     *     tags={"Tenant - Budget Management"},
     *     security={{"sanctum": {}}},
     *     
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Budget ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     
     *     @OA\Response(
     *         response=200,
     *         description="Budget deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Budget deleted successfully."),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-31T10:15:07.080437Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="4950edc3-f2ff-41f6-b8ed-0cf64f45b48e"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=404,
     *         description="Budget not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Resource not found.")
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - Insufficient permissions",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="This action is unauthorized.")
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error or business logic error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to delete budget."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(property="error", type="string", example="Cannot delete budget with associated expenses.")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true)
     *             )
     *         )
     *     )
     * )
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $this->service->deleteBudget($id);

            return ApiResponse::success('Budget deleted successfully.');
        } catch (\Exception $e) {
            return ApiResponse::error(
                'Failed to delete budget.',
                ['error' => $e->getMessage()],
                500
            );
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/budgets/{id}/recalculate",
     *     summary="Recalculate budget spent amounts",
     *     description="Manually triggers recalculation of spent_amount, remaining_amount, percentage_spent, percentage_remaining, and budget status based on actual approved expenses within the budget period. This is useful when expenses are added, modified, or deleted.",
     *     operationId="recalculateBudget",
     *     tags={"Tenant - Budget Management"},
     *     security={{"sanctum": {}}},
     *     
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Budget ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=2)
     *     ),
     *     
     *     @OA\Response(
     *         response=200,
     *         description="Budget recalculated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Budget recalculated successfully."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=2),
     *                 @OA\Property(property="budget_name", type="string", example="Q1 2025 Marketing Budget"),
     *                 @OA\Property(property="store_id", type="integer", nullable=true, example=1),
     *                 @OA\Property(property="category_id", type="integer", example=5),
     *                 @OA\Property(property="period_type", type="string", example="quarterly"),
     *                 @OA\Property(property="period_type_label", type="string", example="Quarterly"),
     *                 @OA\Property(property="period_start", type="string", format="date", example="2026-01-01"),
     *                 @OA\Property(property="period_end", type="string", format="date", example="2026-03-31"),
     *                 @OA\Property(property="period_label", type="string", example="Quarterly (Jan 01, 2026 - Mar 31, 2026)"),
     *                 @OA\Property(property="budget_amount", type="string", example="500000.00"),
     *                 @OA\Property(property="formatted_budget_amount", type="string", example="KES 500,000.00"),
     *                 @OA\Property(property="spent_amount", type="string", example="0.00", description="Recalculated from actual expenses"),
     *                 @OA\Property(property="formatted_spent_amount", type="string", example="KES 0.00"),
     *                 @OA\Property(property="remaining_amount", type="string", example="500000.00", description="budget_amount - spent_amount"),
     *                 @OA\Property(property="formatted_remaining_amount", type="string", example="KES 500,000.00"),
     *                 @OA\Property(property="committed_amount", type="string", example="0.00"),
     *                 @OA\Property(property="percentage_spent", type="number", format="float", example=0, description="Recalculated percentage"),
     *                 @OA\Property(property="percentage_remaining", type="number", format="float", example=100),
     *                 @OA\Property(property="alert_threshold_percentage", type="string", example="80.00"),
     *                 @OA\Property(property="alert_triggered", type="boolean", example=false, description="Updated based on new percentage_spent"),
     *                 @OA\Property(property="alert_triggered_at", type="string", format="date-time", nullable=true, example=null),
     *                 @OA\Property(property="is_active", type="boolean", example=true),
     *                 @OA\Property(property="is_active_now", type="boolean", example=false),
     *                 @OA\Property(property="status", type="string", example="on_track", description="Recalculated status: on_track, warning, or over_budget"),
     *                 @OA\Property(property="status_label", type="string", example="On Track"),
     *                 @OA\Property(property="status_color", type="string", example="success"),
     *                 @OA\Property(property="is_over_budget", type="boolean", example=false, description="True if spent_amount > budget_amount"),
     *                 @OA\Property(property="is_near_threshold", type="boolean", example=false, description="True if close to alert threshold"),
     *                 @OA\Property(property="notes", type="string", nullable=true, example="Allocated for digital marketing campaigns"),
     *                 @OA\Property(property="created_by", type="integer", example=1),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-31T10:28:11.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-31T10:28:11.000000Z")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-31T10:30:30.504586Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="de7c03f3-15af-42ae-b7fa-8e954b6817b6"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=404,
     *         description="Budget not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Resource not found.")
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - Insufficient permissions",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="This action is unauthorized.")
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to recalculate budget."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(property="error", type="string", example="Database error during recalculation.")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true)
     *             )
     *         )
     *     )
     * )
     */
    public function recalculate(int $id): JsonResponse
    {
        try {
            $budget = $this->service->recalculateBudget($id);

            return ApiResponse::success(
                'Budget recalculated successfully.',
                new BudgetResource($budget)
            );
        } catch (\Exception $e) {
            return ApiResponse::error(
                'Failed to recalculate budget.',
                ['error' => $e->getMessage()],
                500
            );
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/budgets/{id}/expenses",
     *     summary="Get expenses for a specific budget",
     *     description="Retrieves all expenses that fall within the budget's category, store (if specified), and period. This shows which expenses are counted against the budget's spent_amount.",
     *     operationId="getBudgetExpenses",
     *     tags={"Tenant - Budget Management"},
     *     security={{"sanctum": {}}},
     *     
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Budget ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=2)
     *     ),
     *     
     *     @OA\Response(
     *         response=200,
     *         description="Budget expenses retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Budget expenses retrieved successfully."),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 description="Array of expenses matching budget criteria (category, store, and period)",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=12),
     *                     @OA\Property(property="expense_number", type="string", example="EXP-2026-000001"),
     *                     @OA\Property(property="store_id", type="integer", example=1),
     *                     @OA\Property(property="category_id", type="integer", example=5),
     *                     @OA\Property(property="amount", type="string", example="3500.00"),
     *                     @OA\Property(property="formatted_amount", type="string", example="KES 3,500.00"),
     *                     @OA\Property(property="description", type="string", example="Internet Fees"),
     *                     @OA\Property(property="expense_date", type="string", format="date", example="2026-01-01", description="Falls within budget period"),
     *                     @OA\Property(property="payment_method", type="string", example="cash"),
     *                     @OA\Property(property="payment_method_label", type="string", example="Cash"),
     *                     @OA\Property(property="payment_reference", type="string", nullable=true, example=null),
     *                     @OA\Property(property="payment_status", type="string", example="pending"),
     *                     @OA\Property(property="payment_status_label", type="string", example="Pending"),
     *                     @OA\Property(property="receipt_path", type="string", nullable=true, example=null),
     *                     @OA\Property(property="receipt_url", type="string", nullable=true, example=null),
     *                     @OA\Property(property="receipt_number", type="string", nullable=true, example=null),
     *                     @OA\Property(property="has_receipt", type="boolean", example=false),
     *                     @OA\Property(property="is_recurring", type="boolean", example=false),
     *                     @OA\Property(property="recurrence_frequency", type="string", nullable=true, example=null),
     *                     @OA\Property(property="recurrence_interval", type="integer", example=1),
     *                     @OA\Property(property="recurrence_start_date", type="string", format="date", nullable=true, example=null),
     *                     @OA\Property(property="recurrence_end_date", type="string", format="date", nullable=true, example=null),
     *                     @OA\Property(property="next_occurrence_date", type="string", format="date", nullable=true, example=null),
     *                     @OA\Property(property="parent_expense_id", type="integer", nullable=true, example=null),
     *                     @OA\Property(property="is_recurrence_instance", type="boolean", example=false),
     *                     @OA\Property(property="supplier_id", type="integer", nullable=true, example=null),
     *                     @OA\Property(property="approval_status", type="string", example="approved", description="Only approved expenses count toward budget"),
     *                     @OA\Property(property="approval_status_label", type="string", example="Approved"),
     *                     @OA\Property(property="approved_by", type="integer", nullable=true, example=1),
     *                     @OA\Property(property="approved_at", type="string", format="date-time", nullable=true, example="2026-01-01T13:18:27.000000Z"),
     *                     @OA\Property(property="rejection_reason", type="string", nullable=true, example=null),
     *                     @OA\Property(property="is_editable", type="boolean", example=false),
     *                     @OA\Property(property="is_deletable", type="boolean", example=false),
     *                     @OA\Property(property="can_be_approved", type="boolean", example=false),
     *                     @OA\Property(
     *                         property="creator",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="John Doe"),
     *                         @OA\Property(property="email", type="string", example="john@techhaven.com")
     *                     ),
     *                     @OA\Property(
     *                         property="approver",
     *                         type="object",
     *                         nullable=true,
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="John Doe"),
     *                         @OA\Property(property="email", type="string", example="john@techhaven.com")
     *                     ),
     *                     @OA\Property(property="created_by", type="integer", example=1),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2026-01-01T13:18:27.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2026-01-01T13:18:27.000000Z")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-01-01T13:26:50.815569Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="8c79bd57-b14d-4edc-a6d3-75021c1e2d7b"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=404,
     *         description="Budget not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Resource not found.")
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - Insufficient permissions",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="This action is unauthorized.")
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="An unexpected error occurred.")
     *         )
     *     )
     * )
     */
    public function expenses(int $id): JsonResponse
    {
        try {
            $expenses = $this->service->getBudgetExpenses($id);

            return ApiResponse::success(
                'Budget expenses retrieved successfully.',
                ExpenseResource::collection($expenses)
            );
        } catch (\Exception $e) {
            return ApiResponse::error(
                'Failed to retrieve budget expenses.',
                ['error' => $e->getMessage()],
                500
            );
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/budgets/current",
     *     summary="Get currently active budgets",
     *     description="Retrieves all budgets where today's date falls within the period_start and period_end range. These are budgets that are currently in effect and being tracked.",
     *     operationId="getCurrentBudgets",
     *     tags={"Tenant - Budget Management"},
     *     security={{"sanctum": {}}},
     *     
     *     @OA\Response(
     *         response=200,
     *         description="Current active budgets retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Current active budgets retrieved successfully."),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=2),
     *                     @OA\Property(property="budget_name", type="string", example="Q1 2025 Marketing Budget"),
     *                     @OA\Property(property="store_id", type="integer", nullable=true, example=1),
     *                     @OA\Property(property="category_id", type="integer", example=5),
     *                     @OA\Property(property="period_type", type="string", example="quarterly"),
     *                     @OA\Property(property="period_type_label", type="string", example="Quarterly"),
     *                     @OA\Property(property="period_start", type="string", format="date", example="2025-12-01"),
     *                     @OA\Property(property="period_end", type="string", format="date", example="2026-03-31"),
     *                     @OA\Property(property="period_label", type="string", example="Quarterly (Dec 01, 2025 - Mar 31, 2026)"),
     *                     @OA\Property(property="budget_amount", type="string", example="500000.00"),
     *                     @OA\Property(property="formatted_budget_amount", type="string", example="KES 500,000.00"),
     *                     @OA\Property(property="spent_amount", type="string", example="0.00"),
     *                     @OA\Property(property="formatted_spent_amount", type="string", example="KES 0.00"),
     *                     @OA\Property(property="remaining_amount", type="string", example="500000.00"),
     *                     @OA\Property(property="formatted_remaining_amount", type="string", example="KES 500,000.00"),
     *                     @OA\Property(property="committed_amount", type="string", example="0.00"),
     *                     @OA\Property(property="percentage_spent", type="number", format="float", example=0),
     *                     @OA\Property(property="percentage_remaining", type="number", format="float", example=100),
     *                     @OA\Property(property="alert_threshold_percentage", type="string", example="80.00"),
     *                     @OA\Property(property="alert_triggered", type="boolean", example=false),
     *                     @OA\Property(property="alert_triggered_at", type="string", format="date-time", nullable=true, example=null),
     *                     @OA\Property(property="is_active", type="boolean", example=true),
     *                     @OA\Property(property="is_active_now", type="boolean", example=true, description="Always true for current budgets"),
     *                     @OA\Property(property="status", type="string", example="on_track"),
     *                     @OA\Property(property="status_label", type="string", example="On Track"),
     *                     @OA\Property(property="status_color", type="string", example="success"),
     *                     @OA\Property(property="is_over_budget", type="boolean", example=false),
     *                     @OA\Property(property="is_near_threshold", type="boolean", example=false),
     *                     @OA\Property(property="notes", type="string", nullable=true, example="Allocated for digital marketing campaigns"),
     *                     @OA\Property(
     *                         property="category",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=5),
     *                         @OA\Property(property="name", type="string", example="Internet & Phone"),
     *                         @OA\Property(property="code", type="string", example="NETPHONE"),
     *                         @OA\Property(property="description", type="string", example="Internet service and business phone lines"),
     *                         @OA\Property(property="parent_id", type="integer", nullable=true, example=2),
     *                         @OA\Property(property="is_recurring_eligible", type="boolean", example=true),
     *                         @OA\Property(property="requires_receipt", type="boolean", example=true),
     *                         @OA\Property(property="requires_approval", type="boolean", example=false),
     *                         @OA\Property(property="is_active", type="boolean", example=true),
     *                         @OA\Property(property="display_order", type="integer", example=23),
     *                         @OA\Property(property="full_path", type="string", example="Utilities > Internet & Phone"),
     *                         @OA\Property(property="level", type="integer", example=1),
     *                         @OA\Property(property="has_children", type="boolean", example=false),
     *                         @OA\Property(property="has_expenses", type="boolean", example=false),
     *                         @OA\Property(property="is_deletable", type="boolean", example=false),
     *                         @OA\Property(
     *                             property="parent",
     *                             type="object",
     *                             nullable=true,
     *                             @OA\Property(property="id", type="integer", example=2),
     *                             @OA\Property(property="name", type="string", example="Utilities"),
     *                             @OA\Property(property="code", type="string", example="UTILITIES"),
     *                             @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-29T07:20:22.000000Z"),
     *                             @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-29T07:55:39.000000Z")
     *                         ),
     *                         @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-29T07:26:47.000000Z"),
     *                         @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-29T07:26:47.000000Z")
     *                     ),
     *                     @OA\Property(
     *                         property="store",
     *                         type="object",
     *                         nullable=true,
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
     *                     @OA\Property(property="created_by", type="integer", example=1),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-31T10:28:11.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-31T10:28:11.000000Z")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-31T18:00:06.353827Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="6ab5d258-6e0e-4676-a147-5dee09d6caa3"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - Insufficient permissions",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="This action is unauthorized.")
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="An unexpected error occurred.")
     *         )
     *     )
     * )
     */
    public function current(): JsonResponse
    {
        try {
            $budgets = $this->service->getCurrentActiveBudgets();

            return ApiResponse::success(
                'Current active budgets retrieved successfully.',
                BudgetResource::collection($budgets)
            );
        } catch (\Exception $e) {
            return ApiResponse::error(
                'Failed to retrieve current budgets.',
                ['error' => $e->getMessage()],
                500
            );
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/budgets/alerts",
     *     summary="Get budgets with triggered alerts",
     *     description="Retrieves all budgets where spending has exceeded the configured alert threshold percentage. These budgets require attention as they are approaching or have exceeded their limits.",
     *     operationId="getBudgetsWithAlerts",
     *     tags={"Tenant - Budget Management"},
     *     security={{"sanctum": {}}},
     *     
     *     @OA\Response(
     *         response=200,
     *         description="Budgets with alerts retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Budgets with alerts retrieved successfully."),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 description="Array of budgets where alert_triggered is true (percentage_spent >= alert_threshold_percentage)",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=3),
     *                     @OA\Property(property="budget_name", type="string", example="Q1 2025 Utilities Budget"),
     *                     @OA\Property(property="store_id", type="integer", nullable=true, example=1),
     *                     @OA\Property(property="category_id", type="integer", example=2),
     *                     @OA\Property(property="period_type", type="string", example="monthly"),
     *                     @OA\Property(property="period_type_label", type="string", example="Monthly"),
     *                     @OA\Property(property="period_start", type="string", format="date", example="2025-12-01"),
     *                     @OA\Property(property="period_end", type="string", format="date", example="2025-12-31"),
     *                     @OA\Property(property="period_label", type="string", example="Monthly (Dec 01, 2025 - Dec 31, 2025)"),
     *                     @OA\Property(property="budget_amount", type="string", example="50000.00"),
     *                     @OA\Property(property="formatted_budget_amount", type="string", example="KES 50,000.00"),
     *                     @OA\Property(property="spent_amount", type="string", example="42000.00"),
     *                     @OA\Property(property="formatted_spent_amount", type="string", example="KES 42,000.00"),
     *                     @OA\Property(property="remaining_amount", type="string", example="8000.00"),
     *                     @OA\Property(property="formatted_remaining_amount", type="string", example="KES 8,000.00"),
     *                     @OA\Property(property="committed_amount", type="string", example="0.00"),
     *                     @OA\Property(property="percentage_spent", type="number", format="float", example=84, description="Exceeds alert threshold"),
     *                     @OA\Property(property="percentage_remaining", type="number", format="float", example=16),
     *                     @OA\Property(property="alert_threshold_percentage", type="string", example="80.00"),
     *                     @OA\Property(property="alert_triggered", type="boolean", example=true, description="Always true in this response"),
     *                     @OA\Property(property="alert_triggered_at", type="string", format="date-time", example="2025-12-20T14:30:00.000000Z", description="When threshold was first exceeded"),
     *                     @OA\Property(property="is_active", type="boolean", example=true),
     *                     @OA\Property(property="is_active_now", type="boolean", example=true),
     *                     @OA\Property(property="status", type="string", example="warning", description="warning or over_budget"),
     *                     @OA\Property(property="status_label", type="string", example="Warning"),
     *                     @OA\Property(property="status_color", type="string", example="warning", description="warning or danger"),
     *                     @OA\Property(property="is_over_budget", type="boolean", example=false),
     *                     @OA\Property(property="is_near_threshold", type="boolean", example=true),
     *                     @OA\Property(property="notes", type="string", nullable=true, example="Monthly utilities allocation"),
     *                     @OA\Property(
     *                         property="category",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=2),
     *                         @OA\Property(property="name", type="string", example="Utilities"),
     *                         @OA\Property(property="code", type="string", example="UTILITIES"),
     *                         @OA\Property(property="full_path", type="string", example="Utilities")
     *                     ),
     *                     @OA\Property(
     *                         property="store",
     *                         type="object",
     *                         nullable=true,
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="code", type="string", example="STR-2025-74622"),
     *                         @OA\Property(property="name", type="string", example="Branch Store - Mombasa")
     *                     ),
     *                     @OA\Property(property="created_by", type="integer", example=1),
     *                     @OA\Property(property="created_at", type="string", format="date-time"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-31T18:05:13.324179Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="27f3af48-a86a-4250-a586-dba6021ba1fe"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - Insufficient permissions",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="This action is unauthorized.")
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="An unexpected error occurred.")
     *         )
     *     )
     * )
     */
    public function alerts(): JsonResponse
    {
        try {
            $budgets = $this->service->getBudgetsWithAlerts();

            return ApiResponse::success(
                'Budgets with alerts retrieved successfully.',
                BudgetResource::collection($budgets)
            );
        } catch (\Exception $e) {
            return ApiResponse::error(
                'Failed to retrieve budgets with alerts.',
                ['error' => $e->getMessage()],
                500
            );
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/budgets/over-budget",
     *     summary="Get over-budget budgets",
     *     description="Retrieves all budgets where the spent amount has exceeded the allocated budget amount. These budgets require immediate attention as they have overspent their limits.",
     *     operationId="getOverBudgets",
     *     tags={"Tenant - Budget Management"},
     *     security={{"sanctum": {}}},
     *     
     *     @OA\Response(
     *         response=200,
     *         description="Over-budget budgets retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Over-budget budgets retrieved successfully."),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 description="Array of budgets where spent_amount > budget_amount (is_over_budget = true)",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=4),
     *                     @OA\Property(property="budget_name", type="string", example="December Operations Budget"),
     *                     @OA\Property(property="store_id", type="integer", nullable=true, example=2),
     *                     @OA\Property(property="category_id", type="integer", example=1),
     *                     @OA\Property(property="period_type", type="string", example="monthly"),
     *                     @OA\Property(property="period_type_label", type="string", example="Monthly"),
     *                     @OA\Property(property="period_start", type="string", format="date", example="2025-12-01"),
     *                     @OA\Property(property="period_end", type="string", format="date", example="2025-12-31"),
     *                     @OA\Property(property="period_label", type="string", example="Monthly (Dec 01, 2025 - Dec 31, 2025)"),
     *                     @OA\Property(property="budget_amount", type="string", example="100000.00"),
     *                     @OA\Property(property="formatted_budget_amount", type="string", example="KES 100,000.00"),
     *                     @OA\Property(property="spent_amount", type="string", example="125000.00", description="Exceeds budget_amount"),
     *                     @OA\Property(property="formatted_spent_amount", type="string", example="KES 125,000.00"),
     *                     @OA\Property(property="remaining_amount", type="string", example="-25000.00", description="Negative value indicates overspending"),
     *                     @OA\Property(property="formatted_remaining_amount", type="string", example="KES -25,000.00"),
     *                     @OA\Property(property="committed_amount", type="string", example="0.00"),
     *                     @OA\Property(property="percentage_spent", type="number", format="float", example=125, description="Over 100%"),
     *                     @OA\Property(property="percentage_remaining", type="number", format="float", example=-25, description="Negative percentage"),
     *                     @OA\Property(property="alert_threshold_percentage", type="string", example="85.00"),
     *                     @OA\Property(property="alert_triggered", type="boolean", example=true, description="Always true for over-budget"),
     *                     @OA\Property(property="alert_triggered_at", type="string", format="date-time", example="2025-12-15T10:30:00.000000Z"),
     *                     @OA\Property(property="is_active", type="boolean", example=true),
     *                     @OA\Property(property="is_active_now", type="boolean", example=true),
     *                     @OA\Property(property="status", type="string", example="over_budget", description="Always over_budget in this response"),
     *                     @OA\Property(property="status_label", type="string", example="Over Budget"),
     *                     @OA\Property(property="status_color", type="string", example="danger", description="Always danger for over-budget"),
     *                     @OA\Property(property="is_over_budget", type="boolean", example=true, description="Always true in this response"),
     *                     @OA\Property(property="is_near_threshold", type="boolean", example=false),
     *                     @OA\Property(property="notes", type="string", nullable=true, example="Monthly operations and maintenance"),
     *                     @OA\Property(
     *                         property="category",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="Operations"),
     *                         @OA\Property(property="code", type="string", example="OPS"),
     *                         @OA\Property(property="full_path", type="string", example="Operations")
     *                     ),
     *                     @OA\Property(
     *                         property="store",
     *                         type="object",
     *                         nullable=true,
     *                         @OA\Property(property="id", type="integer", example=2),
     *                         @OA\Property(property="code", type="string", example="STR-2025-74623"),
     *                         @OA\Property(property="name", type="string", example="Branch Store - Nairobi")
     *                     ),
     *                     @OA\Property(property="created_by", type="integer", example=1),
     *                     @OA\Property(property="created_at", type="string", format="date-time"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-31T18:06:52.295386Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="a00fc0da-03e8-46ad-b368-0a17e96bd5a6"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - Insufficient permissions",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="This action is unauthorized.")
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="An unexpected error occurred.")
     *         )
     *     )
     * )
     */
    public function overBudget(): JsonResponse
    {
        try {
            $budgets = $this->service->getOverBudgetBudgets();

            return ApiResponse::success(
                'Over-budget budgets retrieved successfully.',
                BudgetResource::collection($budgets)
            );
        } catch (\Exception $e) {
            return ApiResponse::error(
                'Failed to retrieve over-budget budgets.',
                ['error' => $e->getMessage()],
                500
            );
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/budgets/performance",
     *     summary="Get budget performance analytics",
     *     description="Retrieves aggregated budget performance metrics including total allocations, spending, and status breakdown. Supports filtering by category, store, and date range.",
     *     operationId="getBudgetPerformance",
     *     tags={"Tenant - Budget Management"},
     *     security={{"sanctum": {}}},
     *     
     *     @OA\Parameter(
     *         name="category_id",
     *         in="query",
     *         description="Filter by expense category ID",
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
     *         name="period_start",
     *         in="query",
     *         description="Filter budgets starting from this date (YYYY-MM-DD)",
     *         required=false,
     *         @OA\Schema(type="string", format="date", example="2025-01-01")
     *     ),
     *     @OA\Parameter(
     *         name="period_end",
     *         in="query",
     *         description="Filter budgets ending until this date (YYYY-MM-DD)",
     *         required=false,
     *         @OA\Schema(type="string", format="date", example="2025-12-31")
     *     ),
     *     
     *     @OA\Response(
     *         response=200,
     *         description="Budget performance retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Budget performance retrieved successfully."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="summary",
     *                     type="object",
     *                     description="Aggregated financial metrics across all filtered budgets",
     *                     @OA\Property(property="total_budgets", type="integer", example=1, description="Number of budgets matching filters"),
     *                     @OA\Property(property="total_allocated", type="number", format="float", example=500000, description="Sum of all budget_amount values"),
     *                     @OA\Property(property="formatted_total_allocated", type="string", example="KES 500,000.00"),
     *                     @OA\Property(property="total_spent", type="number", format="float", example=0, description="Sum of all spent_amount values"),
     *                     @OA\Property(property="formatted_total_spent", type="string", example="KES 0.00"),
     *                     @OA\Property(property="total_remaining", type="number", format="float", example=500000, description="total_allocated - total_spent"),
     *                     @OA\Property(property="formatted_total_remaining", type="string", example="KES 500,000.00"),
     *                     @OA\Property(property="percentage_spent", type="number", format="float", example=0, description="(total_spent / total_allocated) * 100")
     *                 ),
     *                 @OA\Property(
     *                     property="status_breakdown",
     *                     type="object",
     *                     description="Count of budgets by status category",
     *                     @OA\Property(property="on_track", type="integer", example=1, description="Budgets with status = on_track"),
     *                     @OA\Property(property="warning", type="integer", example=0, description="Budgets with status = warning (near threshold)"),
     *                     @OA\Property(property="over_budget", type="integer", example=0, description="Budgets with status = over_budget")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-31T18:13:30.206718Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="607a56ba-3457-4708-8ccd-4a785c2956c5"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - Insufficient permissions",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="This action is unauthorized.")
     *         )
     *     ),
     *     
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
     *                     property="period_start",
     *                     type="array",
     *                     @OA\Items(type="string", example="The period start must be a valid date.")
     *                 ),
     *                 @OA\Property(
     *                     property="period_end",
     *                     type="array",
     *                     @OA\Items(type="string", example="The period end must be a date after period start.")
     *                 )
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="An unexpected error occurred.")
     *         )
     *     )
     * )
     */
    public function performance(Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['category_id', 'store_id', 'period_start', 'period_end']);
            $performance = $this->service->getBudgetPerformance($filters);

            return ApiResponse::success(
                'Budget performance retrieved successfully.',
                new BudgetPerformanceResource($performance)
            );
        } catch (\Exception $e) {
            return ApiResponse::error(
                'Failed to retrieve budget performance.',
                ['error' => $e->getMessage()],
                500
            );
        }
    }
}
