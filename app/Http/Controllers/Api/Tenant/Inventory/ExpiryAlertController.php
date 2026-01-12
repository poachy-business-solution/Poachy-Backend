<?php

namespace App\Http\Controllers\Api\Tenant\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Inventory\Alerts\ResolveExpiryAlertRequest;
use App\Http\Resources\Tenant\Inventory\ExpiryAlertResource;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant\ExpiryAlert;
use App\Services\Tenant\Inventory\ExpiryAlertService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExpiryAlertController extends Controller
{
    public function __construct(
        private ExpiryAlertService $expiryAlertService
    ) {}

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/expiry-alerts",
     *     tags={"Expiry Alerts"},
     *     summary="List expiry alerts",
     *     description="Retrieve a paginated list of expiry alerts with optional filters",
     *     operationId="listExpiryAlerts",
     *     @OA\Parameter(
     *         name="store_id",
     *         in="query",
     *         required=false,
     *         description="Filter by store ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="alert_level",
     *         in="query",
     *         required=false,
     *         description="Filter by alert level",
     *         @OA\Schema(type="string", enum={"warning", "urgent", "expired"}, example="urgent")
     *     ),
     *     @OA\Parameter(
     *         name="is_resolved",
     *         in="query",
     *         required=false,
     *         description="Filter by resolution status",
     *         @OA\Schema(type="boolean", example=false)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         required=false,
     *         description="Number of items per page",
     *         @OA\Schema(type="integer", default=20, example=20)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Expiry alerts retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Expiry alerts retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="alerts",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=2),
     *                         @OA\Property(property="alert_level", type="string", example="urgent"),
     *                         @OA\Property(property="alert_level_label", type="string", example="Urgent"),
     *                         @OA\Property(property="alert_date", type="string", format="date", example="2026-01-12"),
     *                         @OA\Property(property="days_until_expiry", type="integer", example=0),
     *                         @OA\Property(property="is_resolved", type="boolean", example=false),
     *                         @OA\Property(property="resolution_action", type="string", nullable=true, example=null),
     *                         @OA\Property(property="resolution_action_label", type="string", nullable=true, example=null),
     *                         @OA\Property(property="resolved_at", type="string", nullable=true, example=null),
     *                         @OA\Property(property="notes", type="string", nullable=true, example=null),
     *                         @OA\Property(property="severity", type="string", example="high"),
     *                         @OA\Property(property="age_in_days", type="integer", example=0),
     *                         @OA\Property(property="created_at", type="string", format="date-time", example="2026-01-12T17:53:13.000000Z"),
     *                         @OA\Property(property="updated_at", type="string", format="date-time", example="2026-01-12T17:57:07.000000Z"),
     *                         @OA\Property(
     *                             property="batch",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="batch_number", type="string", example="BATCH-202512-0001"),
     *                             @OA\Property(property="quantity_remaining", type="string", example="73.0000"),
     *                             @OA\Property(property="expiry_date", type="string", format="date", example="2026-01-13"),
     *                             @OA\Property(property="is_expired", type="boolean", example=false),
     *                             @OA\Property(
     *                                 property="product",
     *                                 type="object",
     *                                 @OA\Property(property="id", type="integer", example=4),
     *                                 @OA\Property(property="name", type="string", example="TCL 55 4K UHD Smart LED TV"),
     *                                 @OA\Property(property="sku", type="string", example="ELEC-DELL-56QT"),
     *                                 @OA\Property(property="base_uom", type="string", example="pair"),
     *                                 @OA\Property(property="primary_image", type="string", example="products/images/primary_a54_1766346778.jpg")
     *                             ),
     *                             @OA\Property(
     *                                 property="product_variant",
     *                                 type="object",
     *                                 nullable=true,
     *                                 @OA\Property(property="id", type="integer", example=2),
     *                                 @OA\Property(property="variant_name", type="string", example="55C725-GAL")
     *                             ),
     *                             @OA\Property(
     *                                 property="store",
     *                                 type="object",
     *                                 @OA\Property(property="id", type="integer", example=1),
     *                                 @OA\Property(property="name", type="string", example="Branch Store - Mombasa"),
     *                                 @OA\Property(property="code", type="string", example="STR-2025-74622")
     *                             )
     *                         )
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="pagination",
     *                     type="object",
     *                     @OA\Property(property="current_page", type="integer", example=1),
     *                     @OA\Property(property="last_page", type="integer", example=1),
     *                     @OA\Property(property="per_page", type="integer", example=20),
     *                     @OA\Property(property="total", type="integer", example=2),
     *                     @OA\Property(property="from", type="integer", example=1),
     *                     @OA\Property(property="to", type="integer", example=2)
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-01-12T17:59:01.603928Z"),
     *                 @OA\Property(property="request_id", type="string", example="77ae1f97-cee6-483e-bff4-fb88737e3b51"),
     *                 @OA\Property(property="tenant_id", type="string", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only([
            'store_id',
            'alert_level',
            'is_resolved',
            'per_page',
        ]);

        $alerts = $this->expiryAlertService->getAlerts($filters);

        return ApiResponse::success(
            'Expiry alerts retrieved successfully',
            [
                'alerts' => ExpiryAlertResource::collection($alerts->items()),
                'pagination' => [
                    'current_page' => $alerts->currentPage(),
                    'last_page' => $alerts->lastPage(),
                    'per_page' => $alerts->perPage(),
                    'total' => $alerts->total(),
                    'from' => $alerts->firstItem(),
                    'to' => $alerts->lastItem(),
                ],
            ]
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/expiry-alerts/{id}",
     *     tags={"Expiry Alerts"},
     *     summary="Get expiry alert details",
     *     description="Retrieve detailed information about a specific expiry alert",
     *     operationId="getExpiryAlertDetails",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Expiry alert ID",
     *         @OA\Schema(type="integer", example=2)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Expiry alert retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Expiry alert retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=2),
     *                 @OA\Property(property="alert_level", type="string", example="urgent"),
     *                 @OA\Property(property="alert_level_label", type="string", example="Urgent"),
     *                 @OA\Property(property="alert_date", type="string", format="date", example="2026-01-12"),
     *                 @OA\Property(property="days_until_expiry", type="integer", example=0),
     *                 @OA\Property(property="is_resolved", type="boolean", example=false),
     *                 @OA\Property(property="resolution_action", type="string", nullable=true, example=null),
     *                 @OA\Property(property="resolution_action_label", type="string", nullable=true, example=null),
     *                 @OA\Property(property="resolved_at", type="string", nullable=true, example=null),
     *                 @OA\Property(property="notes", type="string", nullable=true, example=null),
     *                 @OA\Property(property="severity", type="string", example="high"),
     *                 @OA\Property(property="age_in_days", type="integer", example=0),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2026-01-12T17:53:13.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2026-01-12T17:57:07.000000Z"),
     *                 @OA\Property(
     *                     property="batch",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="batch_number", type="string", example="BATCH-202512-0001"),
     *                     @OA\Property(property="quantity_remaining", type="string", example="73.0000"),
     *                     @OA\Property(property="expiry_date", type="string", format="date", example="2026-01-13"),
     *                     @OA\Property(property="is_expired", type="boolean", example=false),
     *                     @OA\Property(
     *                         property="product",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=4),
     *                         @OA\Property(property="name", type="string", example="TCL 55 4K UHD Smart LED TV"),
     *                         @OA\Property(property="sku", type="string", example="ELEC-DELL-56QT"),
     *                         @OA\Property(property="base_uom", type="string", example="pair"),
     *                         @OA\Property(property="primary_image", type="string", example="products/images/primary_a54_1766346778.jpg")
     *                     ),
     *                     @OA\Property(
     *                         property="product_variant",
     *                         type="object",
     *                         nullable=true,
     *                         @OA\Property(property="id", type="integer", example=2),
     *                         @OA\Property(property="variant_name", type="string", example="55C725-GAL")
     *                     ),
     *                     @OA\Property(
     *                         property="store",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="Branch Store - Mombasa"),
     *                         @OA\Property(property="code", type="string", example="STR-2025-74622")
     *                     )
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-01-12T17:59:45.897538Z"),
     *                 @OA\Property(property="request_id", type="string", example="27ed03e6-ef87-4794-ae6d-452ffe03f033"),
     *                 @OA\Property(property="tenant_id", type="string", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     )
     * )
     */
    public function show(int $id): JsonResponse
    {
        $alert = ExpiryAlert::withDetails()->findOrFail($id);

        return ApiResponse::success(
            'Expiry alert retrieved successfully',
            new ExpiryAlertResource($alert)
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/expiry-alerts/{id}/resolve",
     *     tags={"Expiry Alerts"},
     *     summary="Resolve expiry alert",
     *     description="Mark an expiry alert as resolved with required resolution action and optional notes",
     *     operationId="resolveExpiryAlert",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Expiry alert ID",
     *         @OA\Schema(type="integer", example=2)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Resolution details",
     *         @OA\JsonContent(
     *             required={"resolution_action"},
     *             @OA\Property(
     *                 property="resolution_action",
     *                 type="string",
     *                 enum={"sold", "discounted", "disposed", "returned", "other"},
     *                 example="discounted"
     *             ),
     *             @OA\Property(property="notes", type="string", example="Sold at 80% discount")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Expiry alert resolved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Expiry alert resolved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=2),
     *                 @OA\Property(property="alert_level", type="string", example="urgent"),
     *                 @OA\Property(property="alert_level_label", type="string", example="Urgent"),
     *                 @OA\Property(property="alert_date", type="string", format="date", example="2026-01-12"),
     *                 @OA\Property(property="days_until_expiry", type="integer", example=0),
     *                 @OA\Property(property="is_resolved", type="boolean", example=true),
     *                 @OA\Property(property="resolution_action", type="string", example="discounted"),
     *                 @OA\Property(property="resolution_action_label", type="string", example="Sold at Discount"),
     *                 @OA\Property(property="resolved_at", type="string", format="date-time", example="2026-01-12T18:02:21.000000Z"),
     *                 @OA\Property(property="notes", type="string", example="Sold at 80% discount"),
     *                 @OA\Property(property="severity", type="string", example="high"),
     *                 @OA\Property(property="age_in_days", type="integer", example=0),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2026-01-12T17:53:13.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2026-01-12T18:02:21.000000Z"),
     *                 @OA\Property(
     *                     property="batch",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="batch_number", type="string", example="BATCH-202512-0001"),
     *                     @OA\Property(property="quantity_remaining", type="string", example="73.0000"),
     *                     @OA\Property(property="expiry_date", type="string", format="date", example="2026-01-13"),
     *                     @OA\Property(property="is_expired", type="boolean", example=false),
     *                     @OA\Property(
     *                         property="product",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=4),
     *                         @OA\Property(property="name", type="string", example="TCL 55 4K UHD Smart LED TV"),
     *                         @OA\Property(property="sku", type="string", example="ELEC-DELL-56QT"),
     *                         @OA\Property(property="base_uom", type="string", example="pair"),
     *                         @OA\Property(property="primary_image", type="string", example="products/images/primary_a54_1766346778.jpg")
     *                     ),
     *                     @OA\Property(
     *                         property="product_variant",
     *                         type="object",
     *                         nullable=true,
     *                         @OA\Property(property="id", type="integer", example=2),
     *                         @OA\Property(property="variant_name", type="string", example="55C725-GAL")
     *                     ),
     *                     @OA\Property(
     *                         property="store",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="Branch Store - Mombasa"),
     *                         @OA\Property(property="code", type="string", example="STR-2025-74622")
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="resolved_by",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="John Doe")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-01-12T18:02:21.495598Z"),
     *                 @OA\Property(property="request_id", type="string", example="207e4783-4add-4b11-96ea-388fed8f14ac"),
     *                 @OA\Property(property="tenant_id", type="string", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     )
     * )
     */
    public function resolve(int $id, ResolveExpiryAlertRequest $request): JsonResponse
    {
        $alert = $this->expiryAlertService->resolveAlert(
            $id,
            $request->enum('resolution_action', \App\Enums\Tenant\ResolutionAction::class),
            $request->input('notes'),
            $request->user()->id
        );

        return ApiResponse::success(
            'Expiry alert resolved successfully',
            new ExpiryAlertResource($alert)
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/stores/{storeId}/expiry-alerts",
     *     tags={"Expiry Alerts"},
     *     summary="Get expiry alerts for a store",
     *     description="Retrieve expiry alerts for a specific store with optional resolution filter",
     *     operationId="getStoreExpiryAlerts",
     *     @OA\Parameter(
     *         name="storeId",
     *         in="path",
     *         required=true,
     *         description="Store ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="is_resolved",
     *         in="query",
     *         required=false,
     *         description="Filter by resolution status",
     *         @OA\Schema(type="boolean", example=false)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Store expiry alerts retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Store expiry alerts retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="alerts",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=3),
     *                         @OA\Property(property="alert_level", type="string", example="urgent"),
     *                         @OA\Property(property="alert_level_label", type="string", example="Urgent"),
     *                         @OA\Property(property="alert_date", type="string", format="date", example="2026-01-12"),
     *                         @OA\Property(property="days_until_expiry", type="integer", example=13),
     *                         @OA\Property(property="is_resolved", type="boolean", example=false),
     *                         @OA\Property(property="resolution_action", type="string", nullable=true, example=null),
     *                         @OA\Property(property="resolution_action_label", type="string", nullable=true, example=null),
     *                         @OA\Property(property="resolved_at", type="string", nullable=true, example=null),
     *                         @OA\Property(property="notes", type="string", nullable=true, example=null),
     *                         @OA\Property(property="severity", type="string", example="high"),
     *                         @OA\Property(property="age_in_days", type="integer", example=0),
     *                         @OA\Property(property="created_at", type="string", format="date-time", example="2026-01-12T17:57:07.000000Z"),
     *                         @OA\Property(property="updated_at", type="string", format="date-time", example="2026-01-12T17:57:07.000000Z"),
     *                         @OA\Property(
     *                             property="batch",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=4),
     *                             @OA\Property(property="batch_number", type="string", example="BATCH-202512-0004"),
     *                             @OA\Property(property="quantity_remaining", type="string", example="40.0000"),
     *                             @OA\Property(property="expiry_date", type="string", format="date", example="2026-01-26"),
     *                             @OA\Property(property="is_expired", type="boolean", example=false),
     *                             @OA\Property(
     *                                 property="product",
     *                                 type="object",
     *                                 @OA\Property(property="id", type="integer", example=4),
     *                                 @OA\Property(property="name", type="string", example="TCL 55 4K UHD Smart LED TV"),
     *                                 @OA\Property(property="sku", type="string", example="ELEC-DELL-56QT"),
     *                                 @OA\Property(property="base_uom", type="string", example="pair"),
     *                                 @OA\Property(property="primary_image", type="string", example="products/images/primary_a54_1766346778.jpg")
     *                             ),
     *                             @OA\Property(
     *                                 property="store",
     *                                 type="object",
     *                                 @OA\Property(property="id", type="integer", example=1),
     *                                 @OA\Property(property="name", type="string", example="Branch Store - Mombasa"),
     *                                 @OA\Property(property="code", type="string", example="STR-2025-74622")
     *                             )
     *                         )
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="pagination",
     *                     type="object",
     *                     @OA\Property(property="current_page", type="integer", example=1),
     *                     @OA\Property(property="last_page", type="integer", example=1),
     *                     @OA\Property(property="per_page", type="integer", example=20),
     *                     @OA\Property(property="total", type="integer", example=1)
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-01-12T18:03:55.582134Z"),
     *                 @OA\Property(property="request_id", type="string", example="ad9bf932-7684-4524-bc4c-723a31a16c72"),
     *                 @OA\Property(property="tenant_id", type="string", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     )
     * )
     */
    public function byStore(int $storeId, Request $request): JsonResponse
    {
        $filters = array_merge(
            $request->only(['is_resolved', 'alert_level', 'per_page']),
            ['store_id' => $storeId]
        );

        $alerts = $this->expiryAlertService->getAlerts($filters);

        return ApiResponse::success(
            'Store expiry alerts retrieved successfully',
            [
                'alerts' => ExpiryAlertResource::collection($alerts->items()),
                'pagination' => [
                    'current_page' => $alerts->currentPage(),
                    'last_page' => $alerts->lastPage(),
                    'per_page' => $alerts->perPage(),
                    'total' => $alerts->total(),
                ],
            ]
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/stores/{storeId}/expiry-alerts/summary",
     *     tags={"Expiry Alerts"},
     *     summary="Get expiry alert summary",
     *     description="Retrieve summary statistics of expiry alerts for a specific store",
     *     operationId="getExpiryAlertSummary",
     *     @OA\Parameter(
     *         name="storeId",
     *         in="path",
     *         required=true,
     *         description="Store ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Expiry alert summary retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Expiry alert summary retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="total_active_alerts", type="integer", example=1),
     *                 @OA\Property(property="expired_count", type="integer", example=0),
     *                 @OA\Property(property="urgent_count", type="integer", example=1),
     *                 @OA\Property(property="warning_count", type="integer", example=0),
     *                 @OA\Property(property="resolved_today", type="integer", example=1)
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-01-12T18:04:51.569426Z"),
     *                 @OA\Property(property="request_id", type="string", example="a21ae952-2a52-4ada-9126-9e01913de454"),
     *                 @OA\Property(property="tenant_id", type="string", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     )
     * )
     */
    public function summary(int $storeId): JsonResponse
    {
        $summary = $this->expiryAlertService->getStoreSummary($storeId);

        return ApiResponse::success(
            'Expiry alert summary retrieved successfully',
            $summary
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/stores/{storeId}/expiry-alerts/dashboard",
     *     tags={"Expiry Alerts"},
     *     summary="Get dashboard expiry alerts",
     *     description="Retrieve a limited list of expiry alerts for dashboard display",
     *     operationId="getDashboardExpiryAlerts",
     *     @OA\Parameter(
     *         name="storeId",
     *         in="path",
     *         required=true,
     *         description="Store ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         required=false,
     *         description="Number of alerts to return",
     *         @OA\Schema(type="integer", default=10, example=10)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Dashboard expiry alerts retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Dashboard expiry alerts retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=3),
     *                     @OA\Property(property="alert_level", type="string", example="urgent"),
     *                     @OA\Property(property="alert_level_label", type="string", example="Urgent"),
     *                     @OA\Property(property="alert_date", type="string", format="date", example="2026-01-12"),
     *                     @OA\Property(property="days_until_expiry", type="integer", example=13),
     *                     @OA\Property(property="is_resolved", type="boolean", example=false),
     *                     @OA\Property(property="resolution_action", type="string", nullable=true, example=null),
     *                     @OA\Property(property="resolution_action_label", type="string", nullable=true, example=null),
     *                     @OA\Property(property="resolved_at", type="string", nullable=true, example=null),
     *                     @OA\Property(property="notes", type="string", nullable=true, example=null),
     *                     @OA\Property(property="severity", type="string", example="high"),
     *                     @OA\Property(property="age_in_days", type="integer", example=0),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2026-01-12T17:57:07.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2026-01-12T17:57:07.000000Z"),
     *                     @OA\Property(
     *                         property="batch",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=4),
     *                         @OA\Property(property="batch_number", type="string", example="BATCH-202512-0004"),
     *                         @OA\Property(property="quantity_remaining", type="string", example="40.0000"),
     *                         @OA\Property(property="expiry_date", type="string", format="date", example="2026-01-26"),
     *                         @OA\Property(property="is_expired", type="boolean", example=false),
     *                         @OA\Property(
     *                             property="product",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=4),
     *                             @OA\Property(property="name", type="string", example="TCL 55 4K UHD Smart LED TV"),
     *                             @OA\Property(property="sku", type="string", example="ELEC-DELL-56QT"),
     *                             @OA\Property(property="base_uom", type="string", example="pair"),
     *                             @OA\Property(property="primary_image", type="string", example="products/images/primary_a54_1766346778.jpg")
     *                         ),
     *                         @OA\Property(
     *                             property="store",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="name", type="string", example="Branch Store - Mombasa"),
     *                             @OA\Property(property="code", type="string", example="STR-2025-74622")
     *                         )
     *                     )
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-01-12T18:05:41.927193Z"),
     *                 @OA\Property(property="request_id", type="string", example="c20b045e-7a14-4242-94bc-c44492302ff4"),
     *                 @OA\Property(property="tenant_id", type="string", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     )
     * )
     */
    public function dashboard(int $storeId, Request $request): JsonResponse
    {
        $limit = $request->input('limit', 10);
        $alerts = $this->expiryAlertService->getDashboardAlerts($storeId, $limit);

        return ApiResponse::success(
            'Dashboard expiry alerts retrieved successfully',
            ExpiryAlertResource::collection($alerts)
        );
    }
}
