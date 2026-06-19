<?php

namespace App\Http\Controllers\Api\Tenant\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Inventory\Alerts\ResolveStockAlertRequest;
use App\Http\Resources\Tenant\Inventory\StockAlertResource;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant\StockAlert;
use App\Services\Tenant\Inventory\StockAlertService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockAlertController extends Controller
{
    public function __construct(
        private StockAlertService $stockAlertService
    ) {}

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/stock-alerts",
     *     tags={"Stock Alerts"},
     *     summary="List stock alerts",
     *     description="Retrieve a paginated list of stock alerts with optional filters",
     *     operationId="listStockAlerts",
     *     @OA\Parameter(
     *         name="store_id",
     *         in="query",
     *         required=false,
     *         description="Filter by store ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="product_id",
     *         in="query",
     *         required=false,
     *         description="Filter by product ID",
     *         @OA\Schema(type="integer", example=4)
     *     ),
     *     @OA\Parameter(
     *         name="alert_type",
     *         in="query",
     *         required=false,
     *         description="Filter by alert type",
     *         @OA\Schema(type="string", enum={"low_stock", "out_of_stock"}, example="low_stock")
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
     *         description="Stock alerts retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Stock alerts retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="alerts",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=3),
     *                         @OA\Property(property="alert_type", type="string", example="out_of_stock"),
     *                         @OA\Property(property="alert_type_label", type="string", example="Out of Stock"),
     *                         @OA\Property(property="current_quantity", type="string", example="0.0000"),
     *                         @OA\Property(property="threshold_quantity", type="string", example="1.0000"),
     *                         @OA\Property(property="is_resolved", type="boolean", example=false),
     *                         @OA\Property(property="resolved_at", type="string", nullable=true, example=null),
     *                         @OA\Property(property="notes", type="string", nullable=true, example=null),
     *                         @OA\Property(property="severity", type="string", example="critical"),
     *                         @OA\Property(property="age_in_days", type="integer", example=0),
     *                         @OA\Property(property="created_at", type="string", format="date-time", example="2026-01-12T13:03:38.000000Z"),
     *                         @OA\Property(property="updated_at", type="string", format="date-time", example="2026-01-12T13:03:38.000000Z"),
     *                         @OA\Property(
     *                             property="store",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="name", type="string", example="Branch Store - Mombasa"),
     *                             @OA\Property(property="code", type="string", example="STR-2025-74622")
     *                         ),
     *                         @OA\Property(
     *                             property="product",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=4),
     *                             @OA\Property(property="name", type="string", example="TCL 55 4K UHD Smart LED TV"),
     *                             @OA\Property(property="sku", type="string", example="ELEC-DELL-56QT"),
     *                             @OA\Property(property="base_uom", type="string", example="pair"),
     *                             @OA\Property(property="reorder_level", type="string", example="1.0000"),
     *                             @OA\Property(property="primary_image", type="string", example="products/images/primary_a54_1766346778.jpg")
     *                         ),
     *                         @OA\Property(
     *                             property="product_variant",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=2),
     *                             @OA\Property(property="variant_name", type="string", example="55C725-GAL"),
     *                             @OA\Property(property="sku", type="string", example="ELEC-DELL-56QT-V14Q")
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
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-01-12T13:03:48.045254Z"),
     *                 @OA\Property(property="request_id", type="string", example="c07c3fc2-f22c-4b49-bb84-a8c685e0033a"),
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
            'product_id',
            'alert_type',
            'is_resolved',
            'per_page',
        ]);

        $alerts = $this->stockAlertService->getAlerts($filters);

        return ApiResponse::success(
            'Stock alerts retrieved successfully',
            [
                'alerts' => StockAlertResource::collection($alerts->items()),
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
     *     path="/api/v1/tenant/stock-alerts/{id}",
     *     tags={"Stock Alerts"},
     *     summary="Get stock alert details",
     *     description="Retrieve detailed information about a specific stock alert",
     *     operationId="getStockAlertDetails",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Stock alert ID",
     *         @OA\Schema(type="integer", example=2)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Stock alert retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Stock alert retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=2),
     *                 @OA\Property(property="alert_type", type="string", example="low_stock"),
     *                 @OA\Property(property="alert_type_label", type="string", example="Low Stock"),
     *                 @OA\Property(property="current_quantity", type="string", example="1.0000"),
     *                 @OA\Property(property="threshold_quantity", type="string", example="1.0000"),
     *                 @OA\Property(property="is_resolved", type="boolean", example=false),
     *                 @OA\Property(property="resolved_at", type="string", nullable=true, example=null),
     *                 @OA\Property(property="notes", type="string", nullable=true, example=null),
     *                 @OA\Property(property="severity", type="string", example="warning"),
     *                 @OA\Property(property="age_in_days", type="integer", example=0),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2026-01-12T12:59:29.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2026-01-12T12:59:29.000000Z"),
     *                 @OA\Property(
     *                     property="store",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Branch Store - Mombasa"),
     *                     @OA\Property(property="code", type="string", example="STR-2025-74622")
     *                 ),
     *                 @OA\Property(
     *                     property="product",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=4),
     *                     @OA\Property(property="name", type="string", example="TCL 55 4K UHD Smart LED TV"),
     *                     @OA\Property(property="sku", type="string", example="ELEC-DELL-56QT"),
     *                     @OA\Property(property="base_uom", type="string", example="pair"),
     *                     @OA\Property(property="reorder_level", type="string", example="1.0000"),
     *                     @OA\Property(property="primary_image", type="string", example="products/images/primary_a54_1766346778.jpg")
     *                 ),
     *                 @OA\Property(
     *                     property="product_variant",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=2),
     *                     @OA\Property(property="variant_name", type="string", example="55C725-GAL"),
     *                     @OA\Property(property="sku", type="string", example="ELEC-DELL-56QT-V14Q")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-01-12T13:04:50.172690Z"),
     *                 @OA\Property(property="request_id", type="string", example="dc4379d5-d5ef-407c-960f-77bf8597fa67"),
     *                 @OA\Property(property="tenant_id", type="string", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     )
     * )
     */
    public function show(int $id): JsonResponse
    {
        $alert = StockAlert::withDetails()->findOrFail($id);

        return ApiResponse::success(
            'Stock alert retrieved successfully',
            new StockAlertResource($alert)
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/stock-alerts/{id}/resolve",
     *     tags={"Stock Alerts"},
     *     summary="Resolve stock alert",
     *     description="Mark a stock alert as resolved with optional notes",
     *     operationId="resolveStockAlert",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Stock alert ID",
     *         @OA\Schema(type="integer", example=2)
     *     ),
     *     @OA\RequestBody(
     *         required=false,
     *         description="Optional notes about the resolution",
     *         @OA\JsonContent(
     *             @OA\Property(property="notes", type="string", example="Stock replenished via PO-2026-001")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Stock alert resolved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Stock alert resolved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=2),
     *                 @OA\Property(property="alert_type", type="string", example="low_stock"),
     *                 @OA\Property(property="alert_type_label", type="string", example="Low Stock"),
     *                 @OA\Property(property="current_quantity", type="string", example="1.0000"),
     *                 @OA\Property(property="threshold_quantity", type="string", example="1.0000"),
     *                 @OA\Property(property="is_resolved", type="boolean", example=true),
     *                 @OA\Property(property="resolved_at", type="string", format="date-time", example="2026-01-12T17:17:26.000000Z"),
     *                 @OA\Property(property="notes", type="string", example="Stock replenished via PO-2026-001"),
     *                 @OA\Property(property="severity", type="string", example="warning"),
     *                 @OA\Property(property="age_in_days", type="integer", example=0),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2026-01-12T12:59:29.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2026-01-12T17:17:26.000000Z"),
     *                 @OA\Property(
     *                     property="store",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Branch Store - Mombasa"),
     *                     @OA\Property(property="code", type="string", example="STR-2025-74622")
     *                 ),
     *                 @OA\Property(
     *                     property="product",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=4),
     *                     @OA\Property(property="name", type="string", example="TCL 55 4K UHD Smart LED TV"),
     *                     @OA\Property(property="sku", type="string", example="ELEC-DELL-56QT"),
     *                     @OA\Property(property="base_uom", type="string", example="pair"),
     *                     @OA\Property(property="reorder_level", type="string", example="1.0000"),
     *                     @OA\Property(property="primary_image", type="string", example="products/images/primary_a54_1766346778.jpg")
     *                 ),
     *                 @OA\Property(
     *                     property="product_variant",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=2),
     *                     @OA\Property(property="variant_name", type="string", example="55C725-GAL"),
     *                     @OA\Property(property="sku", type="string", example="ELEC-DELL-56QT-V14Q")
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
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-01-12T17:17:26.663110Z"),
     *                 @OA\Property(property="request_id", type="string", example="f0dd1460-67b8-4190-b554-ab5c332fd090"),
     *                 @OA\Property(property="tenant_id", type="string", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     )
     * )
     */
    public function resolve(int $id, ResolveStockAlertRequest $request): JsonResponse
    {
        $alert = $this->stockAlertService->resolveAlert(
            $id,
            $request->input('notes'),
            $request->user()->id
        );

        return ApiResponse::success(
            'Stock alert resolved successfully',
            new StockAlertResource($alert)
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/stores/{storeId}/stock-alerts",
     *     tags={"Stock Alerts"},
     *     summary="Get stock alerts for a store",
     *     description="Retrieve stock alerts for a specific store with optional resolution filter",
     *     operationId="getStoreStockAlerts",
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
     *         description="Store stock alerts retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Store stock alerts retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="alerts",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=3),
     *                         @OA\Property(property="alert_type", type="string", example="out_of_stock"),
     *                         @OA\Property(property="alert_type_label", type="string", example="Out of Stock"),
     *                         @OA\Property(property="current_quantity", type="string", example="0.0000"),
     *                         @OA\Property(property="threshold_quantity", type="string", example="1.0000"),
     *                         @OA\Property(property="is_resolved", type="boolean", example=false),
     *                         @OA\Property(property="resolved_at", type="string", nullable=true, example=null),
     *                         @OA\Property(property="notes", type="string", nullable=true, example=null),
     *                         @OA\Property(property="severity", type="string", example="critical"),
     *                         @OA\Property(property="age_in_days", type="integer", example=0),
     *                         @OA\Property(property="created_at", type="string", format="date-time", example="2026-01-12T13:03:38.000000Z"),
     *                         @OA\Property(property="updated_at", type="string", format="date-time", example="2026-01-12T13:03:38.000000Z"),
     *                         @OA\Property(
     *                             property="store",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="name", type="string", example="Branch Store - Mombasa"),
     *                             @OA\Property(property="code", type="string", example="STR-2025-74622")
     *                         ),
     *                         @OA\Property(
     *                             property="product",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=4),
     *                             @OA\Property(property="name", type="string", example="TCL 55 4K UHD Smart LED TV"),
     *                             @OA\Property(property="sku", type="string", example="ELEC-DELL-56QT"),
     *                             @OA\Property(property="base_uom", type="string", example="pair"),
     *                             @OA\Property(property="reorder_level", type="string", example="1.0000"),
     *                             @OA\Property(property="primary_image", type="string", example="products/images/primary_a54_1766346778.jpg")
     *                         ),
     *                         @OA\Property(
     *                             property="product_variant",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=2),
     *                             @OA\Property(property="variant_name", type="string", example="55C725-GAL"),
     *                             @OA\Property(property="sku", type="string", example="ELEC-DELL-56QT-V14Q")
     *                         )
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="pagination",
     *                     type="object",
     *                     @OA\Property(property="current_page", type="integer", example=1),
     *                     @OA\Property(property="last_page", type="integer", example=1),
     *                     @OA\Property(property="per_page", type="integer", example=20),
     *                     @OA\Property(property="total", type="integer", example=2)
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-01-12T17:08:56.681670Z"),
     *                 @OA\Property(property="request_id", type="string", example="11436913-d548-45b4-9d85-4ec1dcc77ba3"),
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
            $request->only(['is_resolved', 'alert_type', 'per_page']),
            ['store_id' => $storeId]
        );

        $alerts = $this->stockAlertService->getAlerts($filters);

        return ApiResponse::success(
            'Store stock alerts retrieved successfully',
            [
                'alerts' => StockAlertResource::collection($alerts->items()),
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
     *     path="/api/v1/tenant/stores/{storeId}/stock-alerts/summary",
     *     tags={"Stock Alerts"},
     *     summary="Get stock alert summary",
     *     description="Retrieve summary statistics of stock alerts for a specific store",
     *     operationId="getStockAlertSummary",
     *     @OA\Parameter(
     *         name="storeId",
     *         in="path",
     *         required=true,
     *         description="Store ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Stock alert summary retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Stock alert summary retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="total_active_alerts", type="integer", example=2),
     *                 @OA\Property(property="low_stock_count", type="integer", example=1),
     *                 @OA\Property(property="out_of_stock_count", type="integer", example=1),
     *                 @OA\Property(property="resolved_today", type="integer", example=0)
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-01-12T17:11:20.394957Z"),
     *                 @OA\Property(property="request_id", type="string", example="ee5427d4-fca6-49f4-8451-a18086d6dd69"),
     *                 @OA\Property(property="tenant_id", type="string", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     )
     * )
     */
    public function summary(int $storeId): JsonResponse
    {
        $summary = $this->stockAlertService->getStoreSummary($storeId);

        return ApiResponse::success(
            'Stock alert summary retrieved successfully',
            $summary
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/stores/{storeId}/stock-alerts/dashboard",
     *     tags={"Stock Alerts"},
     *     summary="Get dashboard stock alerts",
     *     description="Retrieve a limited list of stock alerts for dashboard display",
     *     operationId="getDashboardStockAlerts",
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
     *         description="Dashboard stock alerts retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Dashboard stock alerts retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=3),
     *                     @OA\Property(property="alert_type", type="string", example="out_of_stock"),
     *                     @OA\Property(property="alert_type_label", type="string", example="Out of Stock"),
     *                     @OA\Property(property="current_quantity", type="string", example="0.0000"),
     *                     @OA\Property(property="threshold_quantity", type="string", example="1.0000"),
     *                     @OA\Property(property="is_resolved", type="boolean", example=false),
     *                     @OA\Property(property="resolved_at", type="string", nullable=true, example=null),
     *                     @OA\Property(property="notes", type="string", nullable=true, example=null),
     *                     @OA\Property(property="severity", type="string", example="critical"),
     *                     @OA\Property(property="age_in_days", type="integer", example=0),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2026-01-12T13:03:38.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2026-01-12T13:03:38.000000Z"),
     *                     @OA\Property(
     *                         property="store",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="Branch Store - Mombasa"),
     *                         @OA\Property(property="code", type="string", example="STR-2025-74622")
     *                     ),
     *                     @OA\Property(
     *                         property="product",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=4),
     *                         @OA\Property(property="name", type="string", example="TCL 55 4K UHD Smart LED TV"),
     *                         @OA\Property(property="sku", type="string", example="ELEC-DELL-56QT"),
     *                         @OA\Property(property="base_uom", type="string", example="pair"),
     *                         @OA\Property(property="reorder_level", type="string", example="1.0000"),
     *                         @OA\Property(property="primary_image", type="string", example="products/images/primary_a54_1766346778.jpg")
     *                     ),
     *                     @OA\Property(
     *                         property="product_variant",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=2),
     *                         @OA\Property(property="variant_name", type="string", example="55C725-GAL"),
     *                         @OA\Property(property="sku", type="string", example="ELEC-DELL-56QT-V14Q")
     *                     )
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-01-12T17:13:47.036219Z"),
     *                 @OA\Property(property="request_id", type="string", example="e4e4b2f9-e56d-4756-bd89-d04e16bc1b1e"),
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
        $alerts = $this->stockAlertService->getDashboardAlerts($storeId, $limit);

        return ApiResponse::success(
            'Dashboard stock alerts retrieved successfully',
            StockAlertResource::collection($alerts)
        );
    }
}
