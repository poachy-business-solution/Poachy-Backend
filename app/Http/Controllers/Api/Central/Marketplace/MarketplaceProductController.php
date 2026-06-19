<?php

namespace App\Http\Controllers\Api\Central\Marketplace;

use App\Helpers\BusinessHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Marketplace\ListMarketplaceProductsRequest;
use App\Http\Resources\Central\Marketplace\MarketplaceProductCollection;
use App\Http\Resources\Central\Marketplace\MarketplaceProductResource;
use App\Http\Responses\ApiResponse;
use App\Services\Central\Marketplace\MarketplaceProductService;
use Illuminate\Http\JsonResponse;

/**
 * Public-facing marketplace product endpoints.
 *
 * No authentication is required — these power the consumer-facing website.
 */
class MarketplaceProductController extends Controller
{
    public function __construct(
        private readonly MarketplaceProductService $productService,
    ) {}

    /**
     * @OA\Get(
     *     path="/api/v1/central/marketplace/products",
     *     summary="List active marketplace products",
     *     description="Public endpoint — no authentication required. Returns a paginated list of active marketplace products. Supports filtering by category, brand, tenant, stock status, featured flag, and price range; plus full-text search and flexible sorting.",
     *     operationId="getMarketplaceProducts",
     *     tags={"Central - Marketplace - Products"},
     *     security={},
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Items per page (1-100)",
     *         required=false,
     *         @OA\Schema(type="integer", default=24, minimum=1, maximum=100, example=24)
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number",
     *         required=false,
     *         @OA\Schema(type="integer", default=1, minimum=1, example=1)
     *     ),
     *     @OA\Parameter(
     *         name="category",
     *         in="query",
     *         description="Filter by category slug",
     *         required=false,
     *         @OA\Schema(type="string", example="electronics")
     *     ),
     *     @OA\Parameter(
     *         name="brand",
     *         in="query",
     *         description="Filter by brand slug",
     *         required=false,
     *         @OA\Schema(type="string", example="dell")
     *     ),
     *     @OA\Parameter(
     *         name="tenant_id",
     *         in="query",
     *         description="Filter by tenant ID",
     *         required=false,
     *         @OA\Schema(type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed")
     *     ),
     *     @OA\Parameter(
     *         name="stock_status",
     *         in="query",
     *         description="Filter by stock availability status",
     *         required=false,
     *         @OA\Schema(type="string", enum={"in_stock", "out_of_stock", "low_stock"}, example="in_stock")
     *     ),
     *     @OA\Parameter(
     *         name="featured",
     *         in="query",
     *         description="Filter by featured flag",
     *         required=false,
     *         @OA\Schema(type="boolean", example=true)
     *     ),
     *     @OA\Parameter(
     *         name="min_price",
     *         in="query",
     *         description="Minimum price filter",
     *         required=false,
     *         @OA\Schema(type="number", format="float", example=50000)
     *     ),
     *     @OA\Parameter(
     *         name="max_price",
     *         in="query",
     *         description="Maximum price filter",
     *         required=false,
     *         @OA\Schema(type="number", format="float", example=150000)
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Full-text search query (searches name, description, SKU)",
     *         required=false,
     *         @OA\Schema(type="string", example="smart tv")
     *     ),
     *     @OA\Parameter(
     *         name="sort_by",
     *         in="query",
     *         description="Sort field",
     *         required=false,
     *         @OA\Schema(type="string", enum={"name", "online_price", "created_at", "view_count", "order_count", "average_rating"}, default="created_at", example="online_price")
     *     ),
     *     @OA\Parameter(
     *         name="sort_order",
     *         in="query",
     *         description="Sort order",
     *         required=false,
     *         @OA\Schema(type="string", enum={"asc", "desc"}, default="desc", example="asc")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Marketplace products retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Marketplace products retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="data",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="slug", type="string", example="lg-43-4k-uhd-smart-led-tv-43ur7550psc-bbab2597"),
     *                         @OA\Property(property="sku", type="string", example="ELEC-DELL-FK8K"),
     *                         @OA\Property(property="product_type", type="string", example="product"),
     *                         @OA\Property(property="name", type="string", example="LG 43 4K UHD Smart LED TV 43UR7550PSC"),
     *                         @OA\Property(property="description", type="string", example="Experience stunning 4K UHD picture quality with LG's webOS smart platform."),
     *                         @OA\Property(property="online_price", type="number", format="float", example=130000),
     *                         @OA\Property(property="tax_rate", type="number", format="float", example=10),
     *                         @OA\Property(
     *                             property="uom",
     *                             type="object",
     *                             @OA\Property(property="code", type="string", example="pcs"),
     *                             @OA\Property(property="name", type="string", example="Piece")
     *                         ),
     *                         @OA\Property(
     *                             property="seller",
     *                             type="object",
     *                             nullable=true,
     *                             @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                             @OA\Property(property="business_name", type="string", example="Tech Haven Electronics Solutions"),
     *                             @OA\Property(property="logo", type="string", nullable=true, example="business/logos/7cjtDAZssxGboFSLkiqEGqpG1f06dkzRQ9bz7JFI.jpg"),
     *                             @OA\Property(property="is_verified", type="boolean", example=true)
     *                         ),
     *                         @OA\Property(
     *                             property="category",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="name", type="string", example="Electronics"),
     *                             @OA\Property(property="slug", type="string", example="electronics"),
     *                             @OA\Property(property="source", type="string", example="marketplace")
     *                         ),
     *                         @OA\Property(
     *                             property="brand",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=7),
     *                             @OA\Property(property="name", type="string", example="Dell"),
     *                             @OA\Property(property="slug", type="string", example="dell"),
     *                             @OA\Property(property="logo_url", type="string", nullable=true, example=null),
     *                             @OA\Property(property="source", type="string", example="marketplace")
     *                         ),
     *                         @OA\Property(
     *                             property="images",
     *                             type="object",
     *                             @OA\Property(property="primary", type="string", example="products/images/primary_a54_1766233835.jpg"),
     *                             @OA\Property(
     *                                 property="secondary",
     *                                 type="array",
     *                                 @OA\Items(type="string"),
     *                                 example={"products/images/secondary_a54-extra_1766233835_0.jpg", "products/images/secondary_a54_1766233835_1.jpg"}
     *                             )
     *                         ),
     *                         @OA\Property(
     *                             property="stock",
     *                             type="object",
     *                             @OA\Property(property="status", type="string", enum={"in_stock", "out_of_stock", "low_stock"}, example="out_of_stock"),
     *                             @OA\Property(property="available_quantity", type="integer", example=0)
     *                         ),
     *                         @OA\Property(
     *                             property="metrics",
     *                             type="object",
     *                             @OA\Property(property="view_count", type="integer", example=0),
     *                             @OA\Property(property="order_count", type="integer", example=0),
     *                             @OA\Property(property="average_rating", type="number", format="float", example=0),
     *                             @OA\Property(property="rating_count", type="integer", example=0)
     *                         ),
     *                         @OA\Property(property="is_featured", type="boolean", example=false),
     *                         @OA\Property(property="last_synced_at", type="string", format="date-time", example="2026-01-16T15:16:32.000000Z")
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="pagination",
     *                     type="object",
     *                     @OA\Property(property="current_page", type="integer", example=1),
     *                     @OA\Property(property="last_page", type="integer", example=1),
     *                     @OA\Property(property="per_page", type="integer", example=24),
     *                     @OA\Property(property="total", type="integer", example=4),
     *                     @OA\Property(property="from", type="integer", example=1),
     *                     @OA\Property(property="to", type="integer", example=4)
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-02-14T18:26:21.788538Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="d493bcfb-e1b3-409b-abcc-b11b3e521de4"),
     *                 @OA\Property(property="tenant_id", type="string", nullable=true, example=null),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function index(ListMarketplaceProductsRequest $request): JsonResponse
    {
        /** @var \Illuminate\Pagination\LengthAwarePaginator $paginator */
        $paginator = $this->productService->listActiveProducts($request->validated());

        BusinessHelper::warmCache($paginator->getCollection()->pluck('tenant_id')->all());

        $collection = new MarketplaceProductCollection($paginator);

        return ApiResponse::paginated($collection, 'Marketplace products retrieved successfully');
    }

    /**
     * @OA\Get(
     *     path="/api/v1/central/marketplace/products/{slug}",
     *     summary="Get a single marketplace product by slug",
     *     description="Public endpoint — no authentication required. Returns the full details of a single active marketplace product. Increments the product's view count asynchronously so that popular items bubble up in sort-by-views queries without affecting response time.",
     *     operationId="getMarketplaceProductBySlug",
     *     tags={"Central - Marketplace - Products"},
     *     security={},
     *     @OA\Parameter(
     *         name="slug",
     *         in="path",
     *         description="Product slug (unique identifier)",
     *         required=true,
     *         @OA\Schema(type="string", example="lg-43-4k-uhd-smart-led-tv-43ur7550psc-bbab2597")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Product retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Product retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="slug", type="string", example="lg-43-4k-uhd-smart-led-tv-43ur7550psc-bbab2597"),
     *                 @OA\Property(property="sku", type="string", example="ELEC-DELL-FK8K"),
     *                 @OA\Property(property="product_type", type="string", example="product"),
     *                 @OA\Property(property="name", type="string", example="LG 43 4K UHD Smart LED TV 43UR7550PSC"),
     *                 @OA\Property(property="description", type="string", example="Experience stunning 4K UHD picture quality with LG's webOS smart platform."),
     *                 @OA\Property(property="online_price", type="number", format="float", example=130000),
     *                 @OA\Property(property="tax_rate", type="number", format="float", example=10),
     *                 @OA\Property(
     *                     property="uom",
     *                     type="object",
     *                     @OA\Property(property="code", type="string", example="pcs"),
     *                     @OA\Property(property="name", type="string", example="Piece")
     *                 ),
     *                 @OA\Property(
     *                     property="seller",
     *                     type="object",
     *                     nullable=true,
     *                     @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                     @OA\Property(property="business_name", type="string", example="Tech Haven Electronics Solutions"),
     *                     @OA\Property(property="logo", type="string", nullable=true, example="business/logos/7cjtDAZssxGboFSLkiqEGqpG1f06dkzRQ9bz7JFI.jpg"),
     *                     @OA\Property(property="is_verified", type="boolean", example=true)
     *                 ),
     *                 @OA\Property(
     *                     property="category",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Electronics"),
     *                     @OA\Property(property="slug", type="string", example="electronics"),
     *                     @OA\Property(property="source", type="string", example="marketplace")
     *                 ),
     *                 @OA\Property(
     *                     property="brand",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=7),
     *                     @OA\Property(property="name", type="string", example="Dell"),
     *                     @OA\Property(property="slug", type="string", example="dell"),
     *                     @OA\Property(property="logo_url", type="string", nullable=true, example=null),
     *                     @OA\Property(property="source", type="string", example="marketplace")
     *                 ),
     *                 @OA\Property(
     *                     property="images",
     *                     type="object",
     *                     @OA\Property(property="primary", type="string", example="products/images/primary_a54_1766233835.jpg"),
     *                     @OA\Property(
     *                         property="secondary",
     *                         type="array",
     *                         @OA\Items(type="string"),
     *                         example={"products/images/secondary_a54-extra_1766233835_0.jpg", "products/images/secondary_a54_1766233835_1.jpg"}
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="stock",
     *                     type="object",
     *                     @OA\Property(property="status", type="string", enum={"in_stock", "out_of_stock", "low_stock"}, example="out_of_stock"),
     *                     @OA\Property(property="available_quantity", type="integer", example=0)
     *                 ),
     *                 @OA\Property(
     *                     property="metrics",
     *                     type="object",
     *                     @OA\Property(property="view_count", type="integer", example=0),
     *                     @OA\Property(property="order_count", type="integer", example=0),
     *                     @OA\Property(property="average_rating", type="number", format="float", example=0),
     *                     @OA\Property(property="rating_count", type="integer", example=0)
     *                 ),
     *                 @OA\Property(property="is_featured", type="boolean", example=false),
     *                 @OA\Property(property="last_synced_at", type="string", format="date-time", example="2026-01-16T15:16:32.000000Z")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-02-14T18:30:09.199785Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="3ba4192b-85bf-4320-ad15-590b3da7a7a1"),
     *                 @OA\Property(property="tenant_id", type="string", nullable=true, example=null),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Product not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Product not found"),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(property="reason", type="string", example="product_not_found")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string", format="uuid")
     *             )
     *         )
     *     )
     * )
     */
    public function show(string $slug): JsonResponse
    {
        $product = $this->productService->findBySlug($slug);

        if (!$product) {
            return ApiResponse::notFound('Product not found.');
        }

        // Increment view count asynchronously — non-blocking, best-effort
        dispatch(function () use ($product) {
            $this->productService->incrementViewCount($product->id);
        })->afterResponse();

        return ApiResponse::success(
            'Product retrieved successfully',
            new MarketplaceProductResource($product),
        );
    }
}
