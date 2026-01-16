<?php

namespace App\Http\Controllers\Api\Tenant\Audit;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Audit\AuditLogRequest;
use App\Http\Resources\Tenant\Audit\AuditLogResource;
use App\Http\Responses\ApiResponse;
use App\Services\Tenant\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\JsonResponse;

class AuditLogController extends Controller
{
    public function __construct(
        private readonly AuditService $auditService
    ) {}

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/audit-logs",
     *     summary="Get paginated list of audit logs",
     *     description="Retrieve a paginated list of audit logs with comprehensive filtering options including date range, model type, actions, users, tags, and search capabilities.",
     *     operationId="getAuditLogs",
     *     tags={"Audit Logs"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Items per page (1-100)",
     *         required=false,
     *         @OA\Schema(type="integer", default=20, minimum=1, maximum=100, example=20)
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number",
     *         required=false,
     *         @OA\Schema(type="integer", default=1, minimum=1, example=1)
     *     ),
     *     @OA\Parameter(
     *         name="date_from",
     *         in="query",
     *         description="Start date (Y-m-d format)",
     *         required=false,
     *         @OA\Schema(type="string", format="date", example="2025-01-01")
     *     ),
     *     @OA\Parameter(
     *         name="date_to",
     *         in="query",
     *         description="End date (Y-m-d format)",
     *         required=false,
     *         @OA\Schema(type="string", format="date", example="2025-01-31")
     *     ),
     *     @OA\Parameter(
     *         name="model_type",
     *         in="query",
     *         description="Filter by model type",
     *         required=false,
     *         @OA\Schema(type="string", example="Product")
     *     ),
     *     @OA\Parameter(
     *         name="model_id",
     *         in="query",
     *         description="Filter by specific model ID",
     *         required=false,
     *         @OA\Schema(type="integer", example=123)
     *     ),
     *     @OA\Parameter(
     *         name="action",
     *         in="query",
     *         description="Filter by action type",
     *         required=false,
     *         @OA\Schema(type="string", example="created")
     *     ),
     *     @OA\Parameter(
     *         name="actions",
     *         in="query",
     *         description="Multiple actions to filter (comma-separated)",
     *         required=false,
     *         @OA\Schema(type="string", example="created,updated,deleted")
     *     ),
     *     @OA\Parameter(
     *         name="user_id",
     *         in="query",
     *         description="Filter by user ID",
     *         required=false,
     *         @OA\Schema(type="integer", example=5)
     *     ),
     *     @OA\Parameter(
     *         name="tag",
     *         in="query",
     *         description="Filter by single tag",
     *         required=false,
     *         @OA\Schema(type="string", example="financial")
     *     ),
     *     @OA\Parameter(
     *         name="tags",
     *         in="query",
     *         description="Multiple tags to filter (comma-separated)",
     *         required=false,
     *         @OA\Schema(type="string", example="financial,critical")
     *     ),
     *     @OA\Parameter(
     *         name="tag_match",
     *         in="query",
     *         description="Tag matching mode (any/all)",
     *         required=false,
     *         @OA\Schema(type="string", enum={"any", "all"}, default="any", example="any")
     *     ),
     *     @OA\Parameter(
     *         name="category",
     *         in="query",
     *         description="Filter by category",
     *         required=false,
     *         @OA\Schema(type="string", example="financial")
     *     ),
     *     @OA\Parameter(
     *         name="critical_only",
     *         in="query",
     *         description="Show only critical actions",
     *         required=false,
     *         @OA\Schema(type="boolean", example=true)
     *     ),
     *     @OA\Parameter(
     *         name="financial_only",
     *         in="query",
     *         description="Show only financial actions",
     *         required=false,
     *         @OA\Schema(type="boolean", example=true)
     *     ),
     *     @OA\Parameter(
     *         name="bulk_only",
     *         in="query",
     *         description="Show only bulk operations",
     *         required=false,
     *         @OA\Schema(type="boolean", example=false)
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search in descriptions, users, models",
     *         required=false,
     *         @OA\Schema(type="string", example="sale")
     *     ),
     *     @OA\Parameter(
     *         name="sort_by",
     *         in="query",
     *         description="Sort field",
     *         required=false,
     *         @OA\Schema(type="string", enum={"created_at", "user_name", "action", "model_type"}, default="created_at", example="created_at")
     *     ),
     *     @OA\Parameter(
     *         name="sort_order",
     *         in="query",
     *         description="Sort order",
     *         required=false,
     *         @OA\Schema(type="string", enum={"asc", "desc"}, default="desc", example="desc")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Audit logs retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Audit logs retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="data",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=24),
     *                         @OA\Property(
     *                             property="user",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="name", type="string", example="John Doe")
     *                         ),
     *                         @OA\Property(property="action", type="string", example="updated"),
     *                         @OA\Property(property="description", type="string", example="John Doe changed Import Duty effective until date from ongoing to Jan 31, 2026"),
     *                         @OA\Property(
     *                             property="model",
     *                             type="object",
     *                             @OA\Property(property="type", type="string", example="TaxRate"),
     *                             @OA\Property(property="full_type", type="string", example="App\\Models\\Tenant\\TaxRate"),
     *                             @OA\Property(property="id", type="integer", example=2)
     *                         ),
     *                         @OA\Property(
     *                             property="changes",
     *                             type="object",
     *                             @OA\Property(property="type", type="string", example="update"),
     *                             @OA\Property(property="fields_changed", type="integer", example=1),
     *                             @OA\Property(
     *                                 property="differences",
     *                                 type="object",
     *                                 @OA\Property(
     *                                     property="effective_until",
     *                                     type="object",
     *                                     @OA\Property(property="field", type="string", example="Effective Until"),
     *                                     @OA\Property(property="from", type="string", example="N/A"),
     *                                     @OA\Property(property="to", type="string", example="2026-01-31 00:00:00")
     *                                 ),
     *                                 additionalProperties=@OA\Property(
     *                                     type="object",
     *                                     @OA\Property(property="field", type="string"),
     *                                     @OA\Property(property="from", type="string"),
     *                                     @OA\Property(property="to", oneOf={@OA\Schema(type="string"), @OA\Schema(type="number")})
     *                                 )
     *                             )
     *                         ),
     *                         @OA\Property(
     *                             property="tags",
     *                             type="array",
     *                             @OA\Items(type="string", example="tax")
     *                         ),
     *                         @OA\Property(property="ip_address", type="string", example="127.0.0.1"),
     *                         @OA\Property(property="is_creation", type="boolean", example=false),
     *                         @OA\Property(property="is_update", type="boolean", example=true),
     *                         @OA\Property(property="is_deletion", type="boolean", example=false),
     *                         @OA\Property(property="is_bulk", type="boolean", example=false),
     *                         @OA\Property(property="is_financial", type="boolean", example=false),
     *                         @OA\Property(property="is_critical", type="boolean", example=true),
     *                         @OA\Property(property="created_at", type="string", format="date-time", example="2026-01-16T08:39:32.000000Z"),
     *                         @OA\Property(property="created_at_human", type="string", example="1 hour ago"),
     *                         @OA\Property(property="created_at_formatted", type="string", example="Jan 16, 2026 11:39 AM")
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="pagination",
     *                     type="object",
     *                     @OA\Property(property="current_page", type="integer", example=1),
     *                     @OA\Property(property="last_page", type="integer", example=2),
     *                     @OA\Property(property="per_page", type="integer", example=20),
     *                     @OA\Property(property="total", type="integer", example=24),
     *                     @OA\Property(property="from", type="integer", example=1),
     *                     @OA\Property(property="to", type="integer", example=20)
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-01-16T10:32:49.935662Z"),
     *                 @OA\Property(property="request_id", type="string", example="cb0a91d8-0baf-45c2-a076-95780167fad4"),
     *                 @OA\Property(property="tenant_id", type="string", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function index(AuditLogRequest $request): JsonResponse
    {
        $filters = $request->getFilters();

        // Get paginated audits
        $audits = $this->auditService->getPaginatedAudits($filters);

        return ApiResponse::success(
            message: 'Audit logs retrieved successfully',
            data: [
                'data' => AuditLogResource::collection($audits->items()),
                'pagination' => [
                    'current_page' => $audits->currentPage(),
                    'last_page' => $audits->lastPage(),
                    'per_page' => $audits->perPage(),
                    'total' => $audits->total(),
                    'from' => $audits->firstItem(),
                    'to' => $audits->lastItem(),
                ],
            ]
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/audit-logs/statistics",
     *     summary="Get audit logs statistics",
     *     description="Retrieve comprehensive statistics about audit logs including breakdowns by action, model type, user, date, and special flags (critical, financial, bulk operations).",
     *     operationId="getAuditLogsStatistics",
     *     tags={"Audit Logs"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="date_from",
     *         in="query",
     *         description="Start date for statistics (Y-m-d format)",
     *         required=false,
     *         @OA\Schema(type="string", format="date", example="2025-01-01")
     *     ),
     *     @OA\Parameter(
     *         name="date_to",
     *         in="query",
     *         description="End date for statistics (Y-m-d format)",
     *         required=false,
     *         @OA\Schema(type="string", format="date", example="2025-01-31")
     *     ),
     *     @OA\Parameter(
     *         name="category",
     *         in="query",
     *         description="Filter statistics by category",
     *         required=false,
     *         @OA\Schema(type="string", example="financial")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Audit statistics retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Audit statistics retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="total_count", type="integer", example=24),
     *                 @OA\Property(
     *                     property="by_action",
     *                     type="object",
     *                     @OA\Property(property="updated", type="integer", example=12),
     *                     @OA\Property(property="created", type="integer", example=11),
     *                     @OA\Property(property="deleted", type="integer", example=1),
     *                     additionalProperties=@OA\Property(type="integer")
     *                 ),
     *                 @OA\Property(
     *                     property="by_model",
     *                     type="object",
     *                     @OA\Property(property="LoyaltyTransaction", type="integer", example=4),
     *                     @OA\Property(property="Coupon", type="integer", example=3),
     *                     @OA\Property(property="Supplier", type="integer", example=3),
     *                     @OA\Property(property="Customer", type="integer", example=2),
     *                     @OA\Property(property="InventoryWaste", type="integer", example=2),
     *                     @OA\Property(property="StockTransfer", type="integer", example=2),
     *                     @OA\Property(property="Store", type="integer", example=2),
     *                     @OA\Property(property="Product", type="integer", example=1),
     *                     @OA\Property(property="ProductBatch", type="integer", example=1),
     *                     @OA\Property(property="ProductVariant", type="integer", example=1),
     *                     additionalProperties=@OA\Property(type="integer")
     *                 ),
     *                 @OA\Property(
     *                     property="by_user",
     *                     type="object",
     *                     additionalProperties=@OA\Property(type="integer"),
     *                     example={"John Doe": 24}
     *                 ),
     *                 @OA\Property(
     *                     property="by_date",
     *                     type="object",
     *                     additionalProperties=@OA\Property(type="integer"),
     *                     example={"Jan 15": 19, "Jan 16": 5}
     *                 ),
     *                 @OA\Property(property="critical_count", type="integer", example=10),
     *                 @OA\Property(property="financial_count", type="integer", example=3),
     *                 @OA\Property(property="bulk_count", type="integer", example=0)
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-01-16T10:35:11.146936Z"),
     *                 @OA\Property(property="request_id", type="string", example="1bfce211-98a7-415d-a939-6ff421b1f946"),
     *                 @OA\Property(property="tenant_id", type="string", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function statistics(AuditLogRequest $request): JsonResponse
    {
        $filters = $request->getFilters();

        // Cache statistics for 5 minutes
        $cacheKey = $this->auditService->getStatisticsCacheKey($filters);

        $stats = Cache::tags(['tenant', tenant()->id, 'audit-stats'])
            ->remember($cacheKey, now()->addMinutes(5), function () use ($filters) {
                return $this->auditService->getStatistics($filters);
            });

        return ApiResponse::success(
            message: 'Audit statistics retrieved successfully',
            data: $stats
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/audit-logs/grouped-summary",
     *     summary="Get grouped audit logs summary",
     *     description="Retrieve audit logs grouped by a specific dimension (date, model, user, action, or tag). Returns counts for each group within the specified dimension.",
     *     operationId="getAuditLogsGroupedSummary",
     *     tags={"Audit Logs"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="group_by",
     *         in="query",
     *         description="Group dimension (date, model, user, action, or tag)",
     *         required=true,
     *         @OA\Schema(type="string", enum={"date", "model", "user", "action", "tag"}, example="tag")
     *     ),
     *     @OA\Parameter(
     *         name="date_from",
     *         in="query",
     *         description="Start date for grouping (Y-m-d format)",
     *         required=false,
     *         @OA\Schema(type="string", format="date", example="2025-01-01")
     *     ),
     *     @OA\Parameter(
     *         name="date_to",
     *         in="query",
     *         description="End date for grouping (Y-m-d format)",
     *         required=false,
     *         @OA\Schema(type="string", format="date", example="2025-01-31")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Grouped audit summary retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Grouped audit summary retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="group_by", type="string", example="tag"),
     *                 @OA\Property(
     *                     property="groups",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="tag", type="string", example="critical", description="Group identifier (field name varies based on group_by parameter: 'tag', 'date', 'model', 'user', or 'action')"),
     *                         @OA\Property(property="count", type="integer", example=10)
     *                     )
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-01-16T10:37:42.132609Z"),
     *                 @OA\Property(property="request_id", type="string", example="2218741e-33bf-4bc6-b096-8b24b124f570"),
     *                 @OA\Property(property="tenant_id", type="string", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error - group_by parameter is required or invalid"
     *     )
     * )
     */
    public function groupedSummary(AuditLogRequest $request): JsonResponse
    {
        $filters = $request->getFilters();
        $groupBy = $filters['group_by'];

        if (!$groupBy) {
            return ApiResponse::validationError([
                'group_by' => ['The group_by parameter is required.']
            ]);
        }

        $summary = $this->auditService->getGroupedSummary($filters, $groupBy);

        return ApiResponse::success(
            message: 'Grouped audit summary retrieved successfully',
            data: [
                'group_by' => $groupBy,
                'groups' => $summary,
            ]
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/audit-logs/recent-activity",
     *     summary="Get recent audit activity",
     *     description="Retrieve recent audit activity for quick overview. Returns the most recent actions within a specified time period (default: last 7 days) with a configurable limit (default: 10 activities).",
     *     operationId="getAuditLogsRecentActivity",
     *     tags={"Audit Logs"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="days",
     *         in="query",
     *         description="Number of days to look back",
     *         required=false,
     *         @OA\Schema(type="integer", default=7, minimum=1, example=7)
     *     ),
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         description="Maximum number of activities to return",
     *         required=false,
     *         @OA\Schema(type="integer", default=10, minimum=1, example=10)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Recent activity retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Recent activity retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=24),
     *                     @OA\Property(property="action", type="string", example="updated"),
     *                     @OA\Property(property="model", type="string", example="TaxRate"),
     *                     @OA\Property(property="user", type="string", example="John Doe"),
     *                     @OA\Property(property="description", type="string", example="John Doe changed Import Duty effective until date from ongoing to Jan 31, 2026"),
     *                     @OA\Property(property="is_critical", type="boolean", example=true),
     *                     @OA\Property(property="is_financial", type="boolean", example=false),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2026-01-16T08:39:32.000000Z"),
     *                     @OA\Property(property="created_at_human", type="string", example="2 hours ago")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-01-16T10:39:36.445876Z"),
     *                 @OA\Property(property="request_id", type="string", example="0ff803aa-3e55-4a35-b9ec-d23d29e3beb8"),
     *                 @OA\Property(property="tenant_id", type="string", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function recentActivity(AuditLogRequest $request): JsonResponse
    {
        $days = (int) $request->input('days', 7);
        $limit = (int) $request->input('limit', 10);

        // Validate limits
        $days = max(1, min($days, 30)); // 1-30 days
        $limit = max(1, min($limit, 50)); // 1-50 items

        $activity = $this->auditService->getRecentActivity($days, $limit);

        return ApiResponse::success(
            message: 'Recent activity retrieved successfully',
            data: $activity
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/audit-logs/available-filters",
     *     summary="Get available filter options",
     *     description="Retrieve all available filter options for audit logs including actions, categories, sort options, group options, and tag matching modes. This endpoint helps clients build dynamic filter interfaces.",
     *     operationId="getAuditLogsAvailableFilters",
     *     tags={"Audit Logs"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Available filters retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Available filters retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="actions",
     *                     type="array",
     *                     @OA\Items(type="string"),
     *                     example={"created", "updated", "deleted", "restored", "approved", "rejected", "cancelled", "completed", "soft_deleted"}
     *                 ),
     *                 @OA\Property(
     *                     property="categories",
     *                     type="array",
     *                     @OA\Items(type="string"),
     *                     example={"financial", "inventory", "customer", "configuration", "sale", "purchase_order", "expense", "product", "supplier"}
     *                 ),
     *                 @OA\Property(
     *                     property="sort_options",
     *                     type="object",
     *                     @OA\Property(
     *                         property="fields",
     *                         type="array",
     *                         @OA\Items(type="string"),
     *                         example={"created_at", "user_name", "action", "model_type"}
     *                     ),
     *                     @OA\Property(
     *                         property="orders",
     *                         type="array",
     *                         @OA\Items(type="string"),
     *                         example={"asc", "desc"}
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="group_options",
     *                     type="array",
     *                     @OA\Items(type="string"),
     *                     example={"date", "model", "user", "action", "tag"}
     *                 ),
     *                 @OA\Property(
     *                     property="tag_match_modes",
     *                     type="array",
     *                     @OA\Items(type="string"),
     *                     example={"any", "all"}
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-01-16T10:42:04.952135Z"),
     *                 @OA\Property(property="request_id", type="string", example="43b69050-4810-47ca-83b3-052336a3e5e1"),
     *                 @OA\Property(property="tenant_id", type="string", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     )
     * )
     */
    public function availableFilters(): JsonResponse
    {
        return ApiResponse::success(
            message: 'Available filters retrieved successfully',
            data: [
                'actions' => [
                    'created',
                    'updated',
                    'deleted',
                    'restored',
                    'approved',
                    'rejected',
                    'cancelled',
                    'completed',
                    'soft_deleted',
                ],
                'categories' => [
                    'financial',
                    'inventory',
                    'customer',
                    'configuration',
                    'sale',
                    'purchase_order',
                    'expense',
                    'product',
                    'supplier',
                ],
                'sort_options' => [
                    'fields' => ['created_at', 'user_name', 'action', 'model_type'],
                    'orders' => ['asc', 'desc'],
                ],
                'group_options' => ['date', 'model', 'user', 'action', 'tag'],
                'tag_match_modes' => ['any', 'all'],
            ]
        );
    }
}
