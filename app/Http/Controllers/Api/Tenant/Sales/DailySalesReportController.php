<?php

namespace App\Http\Controllers\Api\Tenant\Sales;

use App\Http\Controllers\Controller;
use App\Http\Resources\Tenant\Sales\SalesDailyAggregateResource;
use App\Http\Responses\ApiResponse;
use App\Jobs\Tenant\RecalculateDailyAggregatesJob;
use App\Services\Tenant\Sales\SalesDailyAggregateService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DailySalesReportController extends Controller
{
    public function __construct(
        protected SalesDailyAggregateService $aggregateService
    ) {}

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/reports/daily-sales",
     *     tags={"Reports - Daily Sales"},
     *     summary="Get daily sales aggregates",
     *     description="Retrieve detailed sales aggregates for a specific date and store",
     *     operationId="getDailySalesAggregates",
     *     @OA\Parameter(
     *         name="date",
     *         in="query",
     *         required=true,
     *         description="Date for which to retrieve sales data",
     *         @OA\Schema(type="string", format="date", example="2025-01-14")
     *     ),
     *     @OA\Parameter(
     *         name="store_id",
     *         in="query",
     *         required=true,
     *         description="Store ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Daily sales aggregates retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Daily sales aggregates retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="aggregate_date", type="string", format="date", example="2026-01-09"),
     *                     @OA\Property(
     *                         property="store",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="Branch Store - Mombasa"),
     *                         @OA\Property(property="code", type="string", example="STR-2025-74622")
     *                     ),
     *                     @OA\Property(
     *                         property="sellable",
     *                         type="object",
     *                         @OA\Property(property="type", type="string", example="variant"),
     *                         @OA\Property(property="id", type="integer", example=2),
     *                         @OA\Property(property="product_id", type="integer", example=4),
     *                         @OA\Property(property="name", type="string", example="TCL 55 4K UHD Smart LED TV - 55C725-GAL"),
     *                         @OA\Property(property="sku", type="string", example="ELEC-DELL-56QT-V14Q"),
     *                         @OA\Property(property="image", type="string", example="products/images/primary_a54_1766346778.jpg")
     *                     ),
     *                     @OA\Property(
     *                         property="category",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="Electronics"),
     *                         @OA\Property(property="slug", type="string", example="electronics")
     *                     ),
     *                     @OA\Property(
     *                         property="metrics",
     *                         type="object",
     *                         @OA\Property(property="total_quantity_sold", type="integer", example=9),
     *                         @OA\Property(property="total_revenue", type="number", example=1494350),
     *                         @OA\Property(property="total_cost", type="number", example=720),
     *                         @OA\Property(property="total_profit", type="number", example=1493630),
     *                         @OA\Property(property="total_tax", type="number", example=135850),
     *                         @OA\Property(property="total_discount", type="number", example=9500),
     *                         @OA\Property(property="transaction_count", type="integer", example=9),
     *                         @OA\Property(property="unique_customers", type="integer", example=1)
     *                     ),
     *                     @OA\Property(
     *                         property="calculated",
     *                         type="object",
     *                         @OA\Property(property="profit_margin_percentage", type="number", format="float", example=99.95),
     *                         @OA\Property(property="average_transaction_value", type="number", format="float", example=166038.89),
     *                         @OA\Property(property="average_quantity_per_transaction", type="integer", example=1),
     *                         @OA\Property(property="discount_rate", type="number", format="float", example=0.64)
     *                     ),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2026-01-13T09:34:26.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2026-01-13T09:34:26.000000Z")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-01-13T10:07:14.573607Z"),
     *                 @OA\Property(property="request_id", type="string", example="dcf5a9d6-48a4-4931-aeee-4157207110d3"),
     *                 @OA\Property(property="tenant_id", type="string", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'date' => 'required|date|before_or_equal:today',
            'store_id' => 'required|integer|exists:stores,id',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error($validator->errors());
        }

        $date = Carbon::parse($request->date);
        $storeId = $request->store_id;

        $aggregates = $this->aggregateService->getAggregatesForDate($date, $storeId);

        return ApiResponse::success(
            'Daily sales aggregates retrieved successfully',
            SalesDailyAggregateResource::collection($aggregates)
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/reports/daily-sales/range",
     *     tags={"Reports - Daily Sales"},
     *     summary="Get daily sales aggregates for date range",
     *     description="Retrieve sales aggregates for a date range and specific store",
     *     operationId="getDailySalesRange",
     *     @OA\Parameter(
     *         name="from",
     *         in="query",
     *         required=true,
     *         description="Start date of range",
     *         @OA\Schema(type="string", format="date", example="2026-01-01")
     *     ),
     *     @OA\Parameter(
     *         name="to",
     *         in="query",
     *         required=true,
     *         description="End date of range",
     *         @OA\Schema(type="string", format="date", example="2026-01-31")
     *     ),
     *     @OA\Parameter(
     *         name="store_id",
     *         in="query",
     *         required=true,
     *         description="Store ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Daily sales aggregates retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Daily sales aggregates retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=13),
     *                     @OA\Property(property="aggregate_date", type="string", format="date", example="2026-01-12"),
     *                     @OA\Property(
     *                         property="store",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="Branch Store - Mombasa"),
     *                         @OA\Property(property="code", type="string", example="STR-2025-74622")
     *                     ),
     *                     @OA\Property(
     *                         property="sellable",
     *                         type="object",
     *                         @OA\Property(property="type", type="string", example="variant"),
     *                         @OA\Property(property="id", type="integer", example=2),
     *                         @OA\Property(property="product_id", type="integer", example=4),
     *                         @OA\Property(property="name", type="string", example="TCL 55 4K UHD Smart LED TV - 55C725-GAL"),
     *                         @OA\Property(property="sku", type="string", example="ELEC-DELL-56QT-V14Q"),
     *                         @OA\Property(property="image", type="string", example="products/images/primary_a54_1766346778.jpg")
     *                     ),
     *                     @OA\Property(
     *                         property="category",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="Electronics"),
     *                         @OA\Property(property="slug", type="string", example="electronics")
     *                     ),
     *                     @OA\Property(
     *                         property="metrics",
     *                         type="object",
     *                         @OA\Property(property="total_quantity_sold", type="integer", example=3),
     *                         @OA\Property(property="total_revenue", type="number", example=491700),
     *                         @OA\Property(property="total_cost", type="number", example=240),
     *                         @OA\Property(property="total_profit", type="number", example=491460),
     *                         @OA\Property(property="total_tax", type="number", example=44700),
     *                         @OA\Property(property="total_discount", type="number", example=9000),
     *                         @OA\Property(property="transaction_count", type="integer", example=3),
     *                         @OA\Property(property="unique_customers", type="integer", example=1)
     *                     ),
     *                     @OA\Property(
     *                         property="calculated",
     *                         type="object",
     *                         @OA\Property(property="profit_margin_percentage", type="number", format="float", example=99.95),
     *                         @OA\Property(property="average_transaction_value", type="number", format="float", example=163900),
     *                         @OA\Property(property="average_quantity_per_transaction", type="integer", example=1),
     *                         @OA\Property(property="discount_rate", type="number", format="float", example=1.83)
     *                     ),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2026-01-13T10:03:46.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2026-01-13T10:03:46.000000Z")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-01-13T10:57:45.070173Z"),
     *                 @OA\Property(property="request_id", type="string", example="a5af4255-8968-4835-b9d4-705f4e63fd3d"),
     *                 @OA\Property(property="tenant_id", type="string", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     )
     * )
     */
    public function range(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'from' => 'required|date',
            'to' => 'required|date|after_or_equal:from|before_or_equal:today',
            'store_id' => 'required|integer|exists:stores,id',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error($validator->errors());
        }

        $from = Carbon::parse($request->from);
        $to = Carbon::parse($request->to);
        $storeId = $request->store_id;

        // Limit to 90 days range
        if ($from->diffInDays($to) > 90) {
            return ApiResponse::error(
                'Date range cannot exceed 90 days',
                ['max_range' => '90 days'],
                400
            );
        }

        $aggregates = $this->aggregateService->getAggregatesForDateRange($from, $to, $storeId);

        return ApiResponse::success(
            'Daily sales aggregates retrieved successfully',
            SalesDailyAggregateResource::collection($aggregates)
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/reports/daily-sales/summary",
     *     tags={"Reports - Daily Sales"},
     *     summary="Get store-level daily sales summary",
     *     description="Retrieve aggregated summary of all sales for a specific date and store",
     *     operationId="getDailySalesSummary",
     *     @OA\Parameter(
     *         name="date",
     *         in="query",
     *         required=true,
     *         description="Date for which to retrieve summary",
     *         @OA\Schema(type="string", format="date", example="2026-01-09")
     *     ),
     *     @OA\Parameter(
     *         name="store_id",
     *         in="query",
     *         required=true,
     *         description="Store ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Store summary retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Store summary retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="total_quantity", type="integer", example=9),
     *                 @OA\Property(property="total_revenue", type="number", example=1494350),
     *                 @OA\Property(property="total_cost", type="number", example=720),
     *                 @OA\Property(property="total_profit", type="number", example=1493630),
     *                 @OA\Property(property="total_tax", type="number", example=135850),
     *                 @OA\Property(property="total_discount", type="number", example=9500),
     *                 @OA\Property(property="total_transactions", type="integer", example=9),
     *                 @OA\Property(property="unique_customers", type="integer", example=1),
     *                 @OA\Property(property="profit_margin_percentage", type="number", format="float", example=99.95),
     *                 @OA\Property(property="average_transaction_value", type="number", format="float", example=166038.89)
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-01-13T10:58:13.082237Z"),
     *                 @OA\Property(property="request_id", type="string", example="9ed6153e-aebb-423d-a6db-6d9643142a7c"),
     *                 @OA\Property(property="tenant_id", type="string", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     )
     * )
     */
    public function summary(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'date' => 'required|date|before_or_equal:today',
            'store_id' => 'required|integer|exists:stores,id',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error($validator->errors());
        }

        $date = Carbon::parse($request->date);
        $storeId = $request->store_id;

        $summary = $this->aggregateService->getStoreSummary($date, $storeId);

        return ApiResponse::success(
            'Store summary retrieved successfully',
            $summary
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/reports/daily-sales/top-selling",
     *     tags={"Reports - Daily Sales"},
     *     summary="Get top selling products",
     *     description="Retrieve top selling products by quantity for a specific date and store",
     *     operationId="getTopSellingProducts",
     *     @OA\Parameter(
     *         name="date",
     *         in="query",
     *         required=true,
     *         description="Date for which to retrieve top sellers",
     *         @OA\Schema(type="string", format="date", example="2026-01-09")
     *     ),
     *     @OA\Parameter(
     *         name="store_id",
     *         in="query",
     *         required=true,
     *         description="Store ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         required=false,
     *         description="Number of top products to return",
     *         @OA\Schema(type="integer", default=10, maximum=50, example=10)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Top selling products retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Top selling products retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=25),
     *                     @OA\Property(property="aggregate_date", type="string", format="date", example="2026-01-09"),
     *                     @OA\Property(
     *                         property="store",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="Branch Store - Mombasa"),
     *                         @OA\Property(property="code", type="string", example="STR-2025-74622")
     *                     ),
     *                     @OA\Property(
     *                         property="sellable",
     *                         type="object",
     *                         @OA\Property(property="type", type="string", example="variant"),
     *                         @OA\Property(property="id", type="integer", example=2),
     *                         @OA\Property(property="product_id", type="integer", example=4),
     *                         @OA\Property(property="name", type="string", example="TCL 55 4K UHD Smart LED TV - 55C725-GAL"),
     *                         @OA\Property(property="sku", type="string", example="ELEC-DELL-56QT-V14Q"),
     *                         @OA\Property(property="image", type="string", example="products/images/primary_a54_1766346778.jpg")
     *                     ),
     *                     @OA\Property(
     *                         property="category",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="Electronics"),
     *                         @OA\Property(property="slug", type="string", example="electronics")
     *                     ),
     *                     @OA\Property(
     *                         property="metrics",
     *                         type="object",
     *                         @OA\Property(property="total_quantity_sold", type="integer", example=9),
     *                         @OA\Property(property="total_revenue", type="number", example=1494350),
     *                         @OA\Property(property="total_cost", type="number", example=720),
     *                         @OA\Property(property="total_profit", type="number", example=1493630),
     *                         @OA\Property(property="total_tax", type="number", example=135850),
     *                         @OA\Property(property="total_discount", type="number", example=9500),
     *                         @OA\Property(property="transaction_count", type="integer", example=9),
     *                         @OA\Property(property="unique_customers", type="integer", example=1)
     *                     ),
     *                     @OA\Property(
     *                         property="calculated",
     *                         type="object",
     *                         @OA\Property(property="profit_margin_percentage", type="number", format="float", example=99.95),
     *                         @OA\Property(property="average_transaction_value", type="number", format="float", example=166038.89),
     *                         @OA\Property(property="average_quantity_per_transaction", type="integer", example=1),
     *                         @OA\Property(property="discount_rate", type="number", format="float", example=0.64)
     *                     ),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2026-01-13T10:52:43.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2026-01-13T10:52:43.000000Z")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-01-13T10:58:32.795024Z"),
     *                 @OA\Property(property="request_id", type="string", example="bde77284-fab4-436b-986b-0d5c4e0ce131"),
     *                 @OA\Property(property="tenant_id", type="string", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     )
     * )
     */
    public function topSelling(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'date' => 'required|date|before_or_equal:today',
            'store_id' => 'required|integer|exists:stores,id',
            'limit' => 'nullable|integer|min:1|max:50',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error($validator->errors());
        }

        $date = Carbon::parse($request->date);
        $storeId = $request->store_id;
        $limit = $request->limit ?? 10;

        $products = $this->aggregateService->getTopSellingProducts($date, $storeId, $limit);

        return ApiResponse::success(
            'Top selling products retrieved successfully',
            SalesDailyAggregateResource::collection($products)
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/reports/daily-sales/top-revenue",
     *     tags={"Reports - Daily Sales"},
     *     summary="Get top revenue products",
     *     description="Retrieve top revenue-generating products for a specific date and store",
     *     operationId="getTopRevenueProducts",
     *     @OA\Parameter(
     *         name="date",
     *         in="query",
     *         required=true,
     *         description="Date for which to retrieve top revenue products",
     *         @OA\Schema(type="string", format="date", example="2026-01-09")
     *     ),
     *     @OA\Parameter(
     *         name="store_id",
     *         in="query",
     *         required=true,
     *         description="Store ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         required=false,
     *         description="Number of top products to return",
     *         @OA\Schema(type="integer", default=10, maximum=50, example=10)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Top revenue products retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Top revenue products retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=25),
     *                     @OA\Property(property="aggregate_date", type="string", format="date", example="2026-01-09"),
     *                     @OA\Property(
     *                         property="store",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="Branch Store - Mombasa"),
     *                         @OA\Property(property="code", type="string", example="STR-2025-74622")
     *                     ),
     *                     @OA\Property(
     *                         property="sellable",
     *                         type="object",
     *                         @OA\Property(property="type", type="string", example="variant"),
     *                         @OA\Property(property="id", type="integer", example=2),
     *                         @OA\Property(property="product_id", type="integer", example=4),
     *                         @OA\Property(property="name", type="string", example="TCL 55 4K UHD Smart LED TV - 55C725-GAL"),
     *                         @OA\Property(property="sku", type="string", example="ELEC-DELL-56QT-V14Q"),
     *                         @OA\Property(property="image", type="string", example="products/images/primary_a54_1766346778.jpg")
     *                     ),
     *                     @OA\Property(
     *                         property="category",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="Electronics"),
     *                         @OA\Property(property="slug", type="string", example="electronics")
     *                     ),
     *                     @OA\Property(
     *                         property="metrics",
     *                         type="object",
     *                         @OA\Property(property="total_quantity_sold", type="integer", example=9),
     *                         @OA\Property(property="total_revenue", type="number", example=1494350),
     *                         @OA\Property(property="total_cost", type="number", example=720),
     *                         @OA\Property(property="total_profit", type="number", example=1493630),
     *                         @OA\Property(property="total_tax", type="number", example=135850),
     *                         @OA\Property(property="total_discount", type="number", example=9500),
     *                         @OA\Property(property="transaction_count", type="integer", example=9),
     *                         @OA\Property(property="unique_customers", type="integer", example=1)
     *                     ),
     *                     @OA\Property(
     *                         property="calculated",
     *                         type="object",
     *                         @OA\Property(property="profit_margin_percentage", type="number", format="float", example=99.95),
     *                         @OA\Property(property="average_transaction_value", type="number", format="float", example=166038.89),
     *                         @OA\Property(property="average_quantity_per_transaction", type="integer", example=1),
     *                         @OA\Property(property="discount_rate", type="number", format="float", example=0.64)
     *                     ),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2026-01-13T10:52:43.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2026-01-13T10:52:43.000000Z")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-01-13T10:58:51.863218Z"),
     *                 @OA\Property(property="request_id", type="string", example="cda4bd89-f770-48a7-89d5-040d3b90dfa6"),
     *                 @OA\Property(property="tenant_id", type="string", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     )
     * )
     */
    public function topRevenue(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'date' => 'required|date|before_or_equal:today',
            'store_id' => 'required|integer|exists:stores,id',
            'limit' => 'nullable|integer|min:1|max:50',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error($validator->errors());
        }

        $date = Carbon::parse($request->date);
        $storeId = $request->store_id;
        $limit = $request->limit ?? 10;

        $products = $this->aggregateService->getTopRevenueProducts($date, $storeId, $limit);

        return ApiResponse::success(
            'Top revenue products retrieved successfully',
            SalesDailyAggregateResource::collection($products)
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/reports/daily-sales/by-category",
     *     tags={"Reports - Daily Sales"},
     *     summary="Get sales summary by category",
     *     description="Retrieve sales aggregates grouped by product category for a specific date and store",
     *     operationId="getSalesByCategory",
     *     @OA\Parameter(
     *         name="date",
     *         in="query",
     *         required=true,
     *         description="Date for which to retrieve category summary",
     *         @OA\Schema(type="string", format="date", example="2026-01-09")
     *     ),
     *     @OA\Parameter(
     *         name="store_id",
     *         in="query",
     *         required=true,
     *         description="Store ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Category summary retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Category summary retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="category_id", type="integer", example=1),
     *                     @OA\Property(property="total_quantity", type="string", example="9.0000"),
     *                     @OA\Property(property="total_revenue", type="string", example="1494350.00"),
     *                     @OA\Property(property="total_cost", type="string", example="720.00"),
     *                     @OA\Property(property="total_profit", type="string", example="1493630.00"),
     *                     @OA\Property(property="total_tax", type="string", example="135850.00"),
     *                     @OA\Property(property="total_discount", type="string", example="9500.00"),
     *                     @OA\Property(property="unique_items", type="integer", example=1),
     *                     @OA\Property(property="profit_margin_percentage", type="number", format="float", example=99.95),
     *                     @OA\Property(property="average_transaction_value", type="integer", example=0),
     *                     @OA\Property(property="average_quantity_per_transaction", type="integer", example=0),
     *                     @OA\Property(
     *                         property="category",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="Electronics"),
     *                         @OA\Property(property="slug", type="string", example="electronics")
     *                     )
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-01-13T10:59:17.212218Z"),
     *                 @OA\Property(property="request_id", type="string", example="9ac8c5ce-6fad-474d-8917-3bd198ce7786"),
     *                 @OA\Property(property="tenant_id", type="string", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     )
     * )
     */
    public function byCategory(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'date' => 'required|date|before_or_equal:today',
            'store_id' => 'required|integer|exists:stores,id',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error($validator->errors());
        }

        $date = Carbon::parse($request->date);
        $storeId = $request->store_id;

        $categorySummary = $this->aggregateService->getCategorySummary($date, $storeId);

        return ApiResponse::success(
            'Category summary retrieved successfully',
            $categorySummary
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/reports/daily-sales/recalculate",
     *     tags={"Reports - Daily Sales"},
     *     summary="Recalculate daily aggregates",
     *     description="Queue a job to recalculate daily sales aggregates for a specific date and store",
     *     operationId="recalculateDailyAggregates",
     *     @OA\RequestBody(
     *         required=true,
     *         description="Date and store for recalculation",
     *         @OA\JsonContent(
     *             required={"date", "store_id"},
     *             @OA\Property(property="date", type="string", format="date", example="2026-01-12"),
     *             @OA\Property(property="store_id", type="integer", example=1)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Recalculation job queued successfully. Aggregates will be rebuilt shortly.",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Recalculation job queued successfully. Aggregates will be rebuilt shortly."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="date", type="string", example="2026-01-12"),
     *                 @OA\Property(property="store_id", type="string", example="1"),
     *                 @OA\Property(property="status", type="string", example="queued")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-01-13T11:00:41.306172Z"),
     *                 @OA\Property(property="request_id", type="string", example="66b7463d-7285-4332-bc70-8367838c7c05"),
     *                 @OA\Property(property="tenant_id", type="string", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     )
     * )
     */
    public function recalculate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'date' => 'required|date',
            'store_id' => 'required|integer|exists:stores,id',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error($validator->errors());
        }

        $date = Carbon::parse($request->date)->toDateString();
        $storeId = $request->store_id;
        $tenantId = tenant()->id;

        // Dispatch recalculation job
        RecalculateDailyAggregatesJob::dispatch($tenantId, $date, $storeId);

        return ApiResponse::success(
            'Recalculation job queued successfully. Aggregates will be rebuilt shortly.',
            [
                'date' => $date,
                'store_id' => $storeId,
                'status' => 'queued',
            ]
        );
    }
}
