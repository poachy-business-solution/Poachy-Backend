<?php

namespace App\Http\Controllers\Api\Tenant\Product;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Product\ProductPriceHistoryIndexRequest;
use App\Http\Resources\Tenant\Product\ProductPriceHistoryResource;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant\Product;
use App\Models\Tenant\ProductPriceHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductPriceHistoryController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/tenant/price-history",
     *     tags={"Price History"},
     *     summary="List price history records",
     *     description="Retrieve a paginated list of all price change records with extensive filtering and sorting options",
     *     operationId="listPriceHistory",
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         required=false,
     *         description="Page number",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         required=false,
     *         description="Items per page (maximum 100)",
     *         @OA\Schema(type="integer", maximum=100, example=20)
     *     ),
     *     @OA\Parameter(
     *         name="product_id",
     *         in="query",
     *         required=false,
     *         description="Filter by specific product",
     *         @OA\Schema(type="integer", example=5)
     *     ),
     *     @OA\Parameter(
     *         name="variant_id",
     *         in="query",
     *         required=false,
     *         description="Filter by specific variant",
     *         @OA\Schema(type="integer", example=3)
     *     ),
     *     @OA\Parameter(
     *         name="from_date",
     *         in="query",
     *         required=false,
     *         description="Filter from date (Y-m-d format)",
     *         @OA\Schema(type="string", format="date", example="2026-01-01")
     *     ),
     *     @OA\Parameter(
     *         name="to_date",
     *         in="query",
     *         required=false,
     *         description="Filter to date (Y-m-d format)",
     *         @OA\Schema(type="string", format="date", example="2026-01-31")
     *     ),
     *     @OA\Parameter(
     *         name="changed_by",
     *         in="query",
     *         required=false,
     *         description="Filter by user who made changes",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="sort_by",
     *         in="query",
     *         required=false,
     *         description="Sort field",
     *         @OA\Schema(type="string", enum={"created_at", "new_selling_price"}, default="created_at", example="created_at")
     *     ),
     *     @OA\Parameter(
     *         name="sort_order",
     *         in="query",
     *         required=false,
     *         description="Sort direction",
     *         @OA\Schema(type="string", enum={"asc", "desc"}, default="desc", example="desc")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Price history retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Price history retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="data",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=3),
     *                         @OA\Property(
     *                             property="product",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=4),
     *                             @OA\Property(property="name", type="string", example="TCL 55 4K UHD Smart LED TV"),
     *                             @OA\Property(property="sku", type="string", example="ELEC-DELL-56QT")
     *                         ),
     *                         @OA\Property(
     *                             property="variant",
     *                             type="object",
     *                             nullable=true,
     *                             @OA\Property(property="id", type="integer", example=2),
     *                             @OA\Property(property="name", type="string", example="55C725-GAL"),
     *                             @OA\Property(property="sku", type="string", example="ELEC-DELL-56QT-V14Q")
     *                         ),
     *                         @OA\Property(
     *                             property="base_uom",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=2),
     *                             @OA\Property(property="code", type="string", example="pair"),
     *                             @OA\Property(property="name", type="string", example="Pair")
     *                         ),
     *                         @OA\Property(
     *                             property="price_change",
     *                             type="object",
     *                             @OA\Property(property="old_price", type="number", example=140000),
     *                             @OA\Property(property="new_price", type="number", example=110000),
     *                             @OA\Property(property="change_amount", type="number", example=-30000),
     *                             @OA\Property(property="change_percentage", type="number", format="float", example=-21.43),
     *                             @OA\Property(property="is_increase", type="boolean", example=false),
     *                             @OA\Property(property="is_decrease", type="boolean", example=true)
     *                         ),
     *                         @OA\Property(
     *                             property="change_info",
     *                             type="object",
     *                             @OA\Property(property="reason", type="string", example="manual"),
     *                             @OA\Property(
     *                                 property="changed_by",
     *                                 type="object",
     *                                 @OA\Property(property="id", type="integer", example=1),
     *                                 @OA\Property(property="name", type="string", example="John Doe")
     *                             ),
     *                             @OA\Property(property="effective_from", type="string", format="date-time", example="2026-01-13 19:08:30")
     *                         ),
     *                         @OA\Property(property="created_at", type="string", format="date-time", example="2026-01-13 19:08:30")
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="pagination",
     *                     type="object",
     *                     @OA\Property(property="current_page", type="integer", example=1),
     *                     @OA\Property(property="last_page", type="integer", example=1),
     *                     @OA\Property(property="per_page", type="integer", example=20),
     *                     @OA\Property(property="total", type="integer", example=3),
     *                     @OA\Property(property="from", type="integer", example=1),
     *                     @OA\Property(property="to", type="integer", example=3)
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-01-13T20:34:23.313948Z"),
     *                 @OA\Property(property="request_id", type="string", example="ac822961-6ad0-478d-bece-e5f90aeb28e9"),
     *                 @OA\Property(property="tenant_id", type="string", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     )
     * )
     */
    public function index(ProductPriceHistoryIndexRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $query = ProductPriceHistory::query()
            ->with([
                'product:id,name,sku',
                'productVariant:id,product_id,variant_name,sku',
                'baseUom:id,code,name',
                'changedBy:id,name'
            ]);

        // Apply filters
        if (!empty($validated['product_id'])) {
            $query->byProduct($validated['product_id']);
        }

        if (isset($validated['variant_id'])) {
            $query->byVariant($validated['variant_id']);
        }

        if (!empty($validated['from_date'])) {
            $query->where('created_at', '>=', $validated['from_date']);
        }

        if (!empty($validated['to_date'])) {
            $query->where('created_at', '<=', $validated['to_date'] . ' 23:59:59');
        }

        if (!empty($validated['changed_by'])) {
            $query->where('changed_by', $validated['changed_by']);
        }

        // Apply sorting
        $sortBy = $validated['sort_by'] ?? 'created_at';
        $sortOrder = $validated['sort_order'] ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);

        // Paginate
        $perPage = min($validated['per_page'] ?? 20, 100);
        $history = $query->paginate($perPage);

        return ApiResponse::paginated(
            ProductPriceHistoryResource::collection($history),
            'Price history retrieved successfully'
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/price-history/products/{product}",
     *     tags={"Price History"},
     *     summary="Get product price history",
     *     description="Retrieve detailed price history for a specific product including summary statistics",
     *     operationId="getProductPriceHistory",
     *     @OA\Parameter(
     *         name="product",
     *         in="path",
     *         required=true,
     *         description="The product ID",
     *         @OA\Schema(type="integer", example=4)
     *     ),
     *     @OA\Parameter(
     *         name="variant_id",
     *         in="query",
     *         required=false,
     *         description="Filter by specific variant",
     *         @OA\Schema(type="integer", example=3)
     *     ),
     *     @OA\Parameter(
     *         name="from_date",
     *         in="query",
     *         required=false,
     *         description="Filter from date (Y-m-d format)",
     *         @OA\Schema(type="string", format="date", example="2026-01-01")
     *     ),
     *     @OA\Parameter(
     *         name="to_date",
     *         in="query",
     *         required=false,
     *         description="Filter to date (Y-m-d format)",
     *         @OA\Schema(type="string", format="date", example="2026-01-31")
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         required=false,
     *         description="Items per page (maximum 100)",
     *         @OA\Schema(type="integer", maximum=100, example=20)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Product price history retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Product price history retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="product",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=4),
     *                     @OA\Property(property="name", type="string", example="TCL 55 4K UHD Smart LED TV"),
     *                     @OA\Property(property="sku", type="string", example="ELEC-DELL-56QT"),
     *                     @OA\Property(property="current_price", type="number", example=100000),
     *                     @OA\Property(property="current_online_price", type="number", example=110000)
     *                 ),
     *                 @OA\Property(
     *                     property="price_changes",
     *                     type="object",
     *                     @OA\Property(
     *                         property="data",
     *                         type="array",
     *                         @OA\Items(
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=3),
     *                             @OA\Property(
     *                                 property="product",
     *                                 type="object",
     *                                 @OA\Property(property="id", type="integer", example=4),
     *                                 @OA\Property(property="name", type="string", example="TCL 55 4K UHD Smart LED TV"),
     *                                 @OA\Property(property="sku", type="string", example="ELEC-DELL-56QT")
     *                             ),
     *                             @OA\Property(
     *                                 property="variant",
     *                                 type="object",
     *                                 nullable=true,
     *                                 @OA\Property(property="id", type="integer", example=2),
     *                                 @OA\Property(property="name", type="string", example="55C725-GAL"),
     *                                 @OA\Property(property="sku", type="string", example="ELEC-DELL-56QT-V14Q")
     *                             ),
     *                             @OA\Property(
     *                                 property="base_uom",
     *                                 type="object",
     *                                 @OA\Property(property="id", type="integer", example=2),
     *                                 @OA\Property(property="code", type="string", example="pair"),
     *                                 @OA\Property(property="name", type="string", example="Pair")
     *                             ),
     *                             @OA\Property(
     *                                 property="price_change",
     *                                 type="object",
     *                                 @OA\Property(property="old_price", type="number", example=140000),
     *                                 @OA\Property(property="new_price", type="number", example=110000),
     *                                 @OA\Property(property="change_amount", type="number", example=-30000),
     *                                 @OA\Property(property="change_percentage", type="number", format="float", example=-21.43),
     *                                 @OA\Property(property="is_increase", type="boolean", example=false),
     *                                 @OA\Property(property="is_decrease", type="boolean", example=true)
     *                             ),
     *                             @OA\Property(
     *                                 property="change_info",
     *                                 type="object",
     *                                 @OA\Property(property="reason", type="string", example="manual"),
     *                                 @OA\Property(
     *                                     property="changed_by",
     *                                     type="object",
     *                                     @OA\Property(property="id", type="integer", example=1),
     *                                     @OA\Property(property="name", type="string", example="John Doe")
     *                                 ),
     *                                 @OA\Property(property="effective_from", type="string", format="date-time", example="2026-01-13 19:08:30")
     *                             ),
     *                             @OA\Property(property="created_at", type="string", format="date-time", example="2026-01-13 19:08:30")
     *                         )
     *                     ),
     *                     @OA\Property(
     *                         property="pagination",
     *                         type="object",
     *                         @OA\Property(property="current_page", type="integer", example=1),
     *                         @OA\Property(property="last_page", type="integer", example=1),
     *                         @OA\Property(property="per_page", type="integer", example=20),
     *                         @OA\Property(property="total", type="integer", example=3),
     *                         @OA\Property(property="from", type="integer", example=1),
     *                         @OA\Property(property="to", type="integer", example=3)
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="summary",
     *                     type="object",
     *                     @OA\Property(property="total_changes", type="integer", example=3),
     *                     @OA\Property(property="first_recorded_price", type="number", example=135000),
     *                     @OA\Property(property="current_price", type="number", example=100000),
     *                     @OA\Property(property="total_increase", type="number", example=-35000),
     *                     @OA\Property(property="percentage_change", type="number", format="float", example=-25.93),
     *                     @OA\Property(property="price_increases_count", type="integer", example=0),
     *                     @OA\Property(property="price_decreases_count", type="integer", example=3),
     *                     @OA\Property(property="average_change_amount", type="number", format="float", example=-38333.33),
     *                     @OA\Property(
     *                         property="largest_increase",
     *                         type="object",
     *                         nullable=true,
     *                         @OA\Property(property="amount", type="number"),
     *                         @OA\Property(property="from", type="number"),
     *                         @OA\Property(property="to", type="number"),
     *                         @OA\Property(property="date", type="string", format="date-time"),
     *                         @OA\Property(property="changed_by", type="string")
     *                     ),
     *                     @OA\Property(
     *                         property="largest_decrease",
     *                         type="object",
     *                         nullable=true,
     *                         @OA\Property(property="amount", type="number", example=-50000),
     *                         @OA\Property(property="from", type="number", example=152000),
     *                         @OA\Property(property="to", type="number", example=102000),
     *                         @OA\Property(property="date", type="string", format="date-time", example="2026-01-13 19:04:19"),
     *                         @OA\Property(property="changed_by", type="string", example="John Doe")
     *                     )
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-01-13T20:37:51.309390Z"),
     *                 @OA\Property(property="request_id", type="string", example="3acf1f5b-4e69-4dbe-a436-56bb9fd42473"),
     *                 @OA\Property(property="tenant_id", type="string", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     )
     * )
     */
    public function show(ProductPriceHistoryIndexRequest $request, int $productId): JsonResponse
    {
        $product = Product::findOrFail($productId);
        $validated = $request->validated();

        $query = ProductPriceHistory::byProduct($productId)
            ->with([
                'productVariant:id,product_id,variant_name,sku',
                'baseUom:id,code,name',
                'changedBy:id,name'
            ]);

        // Apply variant filter if provided
        if (isset($validated['variant_id'])) {
            $query->byVariant($validated['variant_id']);
        }

        // Apply date filters
        if (!empty($validated['from_date'])) {
            $query->where('created_at', '>=', $validated['from_date']);
        }

        if (!empty($validated['to_date'])) {
            $query->where('created_at', '<=', $validated['to_date'] . ' 23:59:59');
        }

        // Get all records for summary calculation
        $allRecords = clone $query;
        $allHistory = $allRecords->orderBy('created_at', 'asc')->get();

        // Paginate for response
        $perPage = min($validated['per_page'] ?? 20, 100);
        $query->orderBy('created_at', 'desc');
        $history = $query->paginate($perPage);

        // Calculate summary
        $summary = $this->calculatePriceChangeSummary($allHistory, $product);

        $data = [
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'current_price' => (float) $product->base_selling_price,
                'current_online_price' => $product->online_price ? (float) $product->online_price : null,
            ],
            'price_changes' => [
                'data' => ProductPriceHistoryResource::collection($history->items()),
                'pagination' => [
                    'current_page' => $history->currentPage(),
                    'last_page' => $history->lastPage(),
                    'per_page' => $history->perPage(),
                    'total' => $history->total(),
                    'from' => $history->firstItem(),
                    'to' => $history->lastItem(),
                ],
            ],
            'summary' => $summary,
        ];

        return ApiResponse::success(
            'Product price history retrieved successfully',
            $data
        );
    }

    private function calculatePriceChangeSummary($history, Product $product): array
    {
        if ($history->isEmpty()) {
            return [
                'total_changes' => 0,
                'first_recorded_price' => (float) $product->base_selling_price,
                'current_price' => (float) $product->base_selling_price,
                'total_increase' => 0,
                'percentage_change' => 0,
                'price_increases_count' => 0,
                'price_decreases_count' => 0,
                'average_change_amount' => 0,
                'largest_increase' => null,
                'largest_decrease' => null,
            ];
        }

        $firstPrice = $history->first()->old_selling_price ?? $history->first()->new_selling_price;
        $currentPrice = (float) $product->base_selling_price;
        $totalIncrease = $currentPrice - $firstPrice;
        $percentageChange = $firstPrice > 0 ? (($totalIncrease / $firstPrice) * 100) : 0;

        // Count increases and decreases
        $increases = $history->filter(function ($record) {
            return $record->price_change_amount > 0;
        });

        $decreases = $history->filter(function ($record) {
            return $record->price_change_amount < 0;
        });

        // Find largest changes
        $largestIncrease = $increases->sortByDesc('price_change_amount')->first();
        $largestDecrease = $decreases->sortBy('price_change_amount')->first();

        // Calculate average change
        $changes = $history->pluck('price_change_amount')->filter(fn($v) => $v !== null);
        $averageChange = $changes->isEmpty() ? 0 : $changes->avg();

        return [
            'total_changes' => $history->count(),
            'first_recorded_price' => round($firstPrice, 2),
            'current_price' => round($currentPrice, 2),
            'total_increase' => round($totalIncrease, 2),
            'percentage_change' => round($percentageChange, 2),
            'price_increases_count' => $increases->count(),
            'price_decreases_count' => $decreases->count(),
            'average_change_amount' => round($averageChange, 2),
            'largest_increase' => $largestIncrease ? [
                'amount' => round($largestIncrease->price_change_amount, 2),
                'from' => round($largestIncrease->old_selling_price, 2),
                'to' => round($largestIncrease->new_selling_price, 2),
                'date' => $largestIncrease->created_at->format('Y-m-d H:i:s'),
                'changed_by' => $largestIncrease->changedBy->name ?? 'System',
            ] : null,
            'largest_decrease' => $largestDecrease ? [
                'amount' => round($largestDecrease->price_change_amount, 2),
                'from' => round($largestDecrease->old_selling_price, 2),
                'to' => round($largestDecrease->new_selling_price, 2),
                'date' => $largestDecrease->created_at->format('Y-m-d H:i:s'),
                'changed_by' => $largestDecrease->changedBy->name ?? 'System',
            ] : null,
        ];
    }
}
