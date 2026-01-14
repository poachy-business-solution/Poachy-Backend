<?php

namespace App\Http\Controllers\Api\Tenant\Product;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Product\StoreProductVariantRequest;
use App\Http\Requests\Tenant\Product\UpdateProductVariantRequest;
use App\Http\Requests\Tenant\Product\UpdateVariantInventoryRequest;
use App\Http\Resources\Tenant\Product\ProductVariantListResource;
use App\Http\Resources\Tenant\Product\ProductVariantResource;
use App\Http\Responses\ApiResponse;
use App\Services\Tenant\Product\ProductService;
use App\Services\Tenant\Product\ProductVariantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductVariantController extends Controller
{
    public function __construct(
        private ProductVariantService $variantService,
        private ProductService $productService
    ) {}

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/products/{uuid}/variants",
     *     summary="Get product variants",
     *     description="Retrieves all variants for a specific product with filtering capabilities. Variants represent different configurations of the same product (e.g., different sizes, colors, specifications).",
     *     tags={"Tenant Product Variants"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="uuid",
     *         in="path",
     *         description="Product UUID",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid"),
     *         example="67b466f5-8b6d-4122-af5d-1683d1dd7a72"
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search term to filter variants by name or SKU",
     *         required=false,
     *         @OA\Schema(type="string"),
     *         example="QLED"
     *     ),
     *     @OA\Parameter(
     *         name="is_active",
     *         in="query",
     *         description="Filter by active status",
     *         required=false,
     *         @OA\Schema(type="boolean"),
     *         example=true
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter by stock status",
     *         required=false,
     *         @OA\Schema(type="string", enum={"in_stock", "out_of_stock", "discontinued"}),
     *         example="in_stock"
     *     ),
     *     @OA\Parameter(
     *         name="attribute_key",
     *         in="query",
     *         description="Filter by attribute key (e.g., 'Panel Tech', 'Refresh Rate')",
     *         required=false,
     *         @OA\Schema(type="string"),
     *         example="Refresh Rate"
     *     ),
     *     @OA\Parameter(
     *         name="attribute_value",
     *         in="query",
     *         description="Filter by attribute value (requires attribute_key)",
     *         required=false,
     *         @OA\Schema(type="string"),
     *         example="144 Hz"
     *     ),
     *     @OA\Parameter(
     *         name="sort_by",
     *         in="query",
     *         description="Field to sort by",
     *         required=false,
     *         @OA\Schema(type="string"),
     *         example="created_at"
     *     ),
     *     @OA\Parameter(
     *         name="is_available_online",
     *         in="query",
     *         description="Filter by online availability",
     *         required=false,
     *         @OA\Schema(type="boolean"),
     *         example=true
     *     ),
     *     @OA\Parameter(
     *         name="sort_order",
     *         in="query",
     *         description="Sort direction",
     *         required=false,
     *         @OA\Schema(type="string", enum={"asc", "desc"}),
     *         example="desc"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Product variants retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Product variants retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="product",
     *                     type="object",
     *                     @OA\Property(property="uuid", type="string", format="uuid", example="67b466f5-8b6d-4122-af5d-1683d1dd7a72"),
     *                     @OA\Property(property="name", type="string", example="TCL 55 4K UHD Smart LED TV"),
     *                     @OA\Property(property="sku", type="string", example="ELEC-DELL-56QT"),
     *                     @OA\Property(property="product_type", type="string", example="variable"),
     *                     @OA\Property(property="product_available_online", type="boolean", example=true),
     *                 ),
     *                 @OA\Property(
     *                     property="variants",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=2),
     *                         @OA\Property(property="product_id", type="integer", example=4),
     *                         @OA\Property(property="product_name", type="string", example="TCL 55 4K UHD Smart LED TV"),
     *                         @OA\Property(property="product_sku", type="string", example="ELEC-DELL-56QT"),
     *                         @OA\Property(property="variant_name", type="string", example="55C725-QLED"),
     *                         @OA\Property(property="sku", type="string", example="ELEC-DELL-56QT-V14Q"),
     *                         @OA\Property(property="display_name", type="string", example="TCL 55 4K UHD Smart LED TV - 55C725-QLED"),
     *                         @OA\Property(
     *                             property="attributes",
     *                             type="object",
     *                             @OA\Property(property="HDR", type="string", example="10+"),
     *                             @OA\Property(property="Design ID", type="string", example="Slim bezel"),
     *                             @OA\Property(property="Panel Tech", type="string", example="QLED (Quantum-dot)"),
     *                             @OA\Property(property="Refresh Rate", type="string", example="144 Hz")
     *                         ),
     *                         @OA\Property(property="uom_display", type="string", example="1.0000 pair"),
     *                         @OA\Property(property="quantity_in_base_uom", type="string", example="1.0000"),
     *                         @OA\Property(property="computed_price", type="number", example=141499),
     *                         @OA\Property(property="formatted_price", type="string", example="KES 141,499.00"),
     *                         @OA\Property(property="online_price", type="number", example=141499),
     *                         @OA\Property(property="formatted_online_price", type="string", example="KES 141,499.00"),
     *                         @OA\Property(property="computed_online_price", type="number", example=141499),
     *                         @OA\Property(property="formatted_computed_online_price", type="string", example="KES 141,499.00"),
     *                         @OA\Property(property="is_available_online", type="boolean", example=true),
     *                         @OA\Property(property="stock_status", type="string", example="in_stock"),
     *                         @OA\Property(property="stock_status_label", type="string", example="In Stock"),
     *                         @OA\Property(property="is_active", type="boolean", example=true),
     *                         @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-22T18:17:37.000000Z")
     *                     )
     *                 ),
     *                 @OA\Property(property="total", type="integer", example=2, description="Total number of variants")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-22T18:23:36.920137Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="8e94173c-b021-4a33-8193-d707a18b654a"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized - Invalid or missing authentication token"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Product not found"
     *     )
     * )
     */
    public function index(Request $request, string $productUuid): JsonResponse
    {
        $product = $this->productService->getByUuid($productUuid);

        $filters = $request->only([
            'search',
            'is_active',
            'status',
            'attribute_key',
            'attribute_value',
            'is_available_online',
            'sort_by',
            'sort_order',
        ]);

        $variants = $this->variantService->listForProduct($product, $filters);

        return ApiResponse::success(
            message: 'Product variants retrieved successfully',
            data: [
                'product' => [
                    'uuid' => $product->uuid,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'product_type' => $product->product_type->value,
                ],
                'variants' => ProductVariantListResource::collection($variants),
                'total' => $variants->count(),
            ]
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/products/{uuid}/variants",
     *     summary="Create product variant",
     *     description="Creates a new variant for a product. Variants represent different configurations (e.g., size, color, specifications) with their own SKU, pricing, and attributes. SKU is auto-generated if not provided.",
     *     tags={"Tenant Product Variants"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="uuid",
     *         in="path",
     *         description="Product UUID",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid"),
     *         example="67b466f5-8b6d-4122-af5d-1683d1dd7a72"
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"variant_name", "uom_id", "uom_quantity"},
     *             @OA\Property(property="variant_name", type="string", maxLength=255, example="55C725-QLED", description="Variant name/identifier"),
     *             @OA\Property(property="sku", type="string", maxLength=20, example="ELEC-DELL-56QT-V14Q", description="Unique SKU (auto-generated if not provided)"),
     *             @OA\Property(
     *                 property="attributes",
     *                 type="object",
     *                 description="Key-value pairs of variant attributes",
     *                 example={
     *                     "Refresh Rate": "144 Hz",
     *                     "Panel Tech": "QLED (Quantum-dot)",
     *                     "Design ID": "Slim bezel",
     *                     "HDR": "10+"
     *                 }
     *             ),
     *             @OA\Property(property="uom_id", type="integer", example=2, description="Unit of Measure ID"),
     *             @OA\Property(property="uom_quantity", type="number", format="decimal", minimum=0.0001, maximum=999999.9999, example=1, description="Quantity in the specified UOM"),
     *             @OA\Property(property="quantity_in_base_uom", type="number", format="decimal", minimum=0.0001, maximum=999999.9999, example=1, description="Equivalent quantity in base UOM (auto-calculated if not provided)"),
     *             @OA\Property(property="base_selling_price_adjustment", type="number", format="decimal", minimum=-999999.99, maximum=999999.99, example=5500, description="Price adjustment relative to base product price (+/-)"),
     *             @OA\Property(property="variant_price", type="number", format="decimal", minimum=0, maximum=9999999999.99, example=141499, description="Fixed variant price (overrides adjustment calculation)"),
     *             @OA\Property(property="online_price", type="number", format="decimal", minimum=0, maximum=9999999999.99, example=141499, description="Online price for the variant"),
     *             @OA\Property(property="is_active", type="boolean", example=true, description="Active status")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Product variant created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Product variant created successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=2),
     *                 @OA\Property(property="product_id", type="integer", example=4),
     *                 @OA\Property(
     *                     property="product",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=4),
     *                     @OA\Property(property="uuid", type="string", format="uuid", example="67b466f5-8b6d-4122-af5d-1683d1dd7a72"),
     *                     @OA\Property(property="name", type="string", example="TCL 55 4K UHD Smart LED TV"),
     *                     @OA\Property(property="slug", type="string", example="tcl-55-4k-uhd-smart-led-tv"),
     *                     @OA\Property(property="sku", type="string", example="ELEC-DELL-56QT"),
     *                     @OA\Property(property="is_available_online", type="boolean", example=true),
     *                 ),
     *                 @OA\Property(property="variant_name", type="string", example="55C725-QLED"),
     *                 @OA\Property(property="sku", type="string", example="ELEC-DELL-56QT-V14Q"),
     *                 @OA\Property(property="display_name", type="string", example="TCL 55 4K UHD Smart LED TV - 55C725-QLED"),
     *                 @OA\Property(
     *                     property="attributes",
     *                     type="object",
     *                     @OA\Property(property="Refresh Rate", type="string", example="144 Hz"),
     *                     @OA\Property(property="Panel Tech", type="string", example="QLED (Quantum-dot)"),
     *                     @OA\Property(property="Design ID", type="string", example="Slim bezel"),
     *                     @OA\Property(property="HDR", type="string", example="10+")
     *                 ),
     *                 @OA\Property(
     *                     property="uom",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=2),
     *                     @OA\Property(property="code", type="string", example="pair"),
     *                     @OA\Property(property="name", type="string", example="Pair"),
     *                     @OA\Property(property="type", type="string", example="count"),
     *                     @OA\Property(property="source_type", type="string", example="system"),
     *                     @OA\Property(property="source_type_label", type="string", example="System Defined"),
     *                     @OA\Property(property="is_base_unit", type="boolean", example=false),
     *                     @OA\Property(property="is_active", type="boolean", example=true),
     *                     @OA\Property(property="is_system", type="boolean", example=true),
     *                     @OA\Property(property="is_custom", type="boolean", example=false),
     *                     @OA\Property(property="description", type="string", example="Two pieces"),
     *                     @OA\Property(property="display_name", type="string", example="Pair (pair)"),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-19T13:40:21.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-19T16:12:20.000000Z")
     *                 ),
     *                 @OA\Property(property="uom_id", type="integer", example=2),
     *                 @OA\Property(property="uom_quantity", type="string", example="1.0000"),
     *                 @OA\Property(property="quantity_in_base_uom", type="string", example="1.0000"),
     *                 @OA\Property(property="uom_display", type="string", example="1.0000 pair"),
     *                 @OA\Property(property="base_selling_price_adjustment", type="string", example="5500.00"),
     *                 @OA\Property(property="formatted_adjustment", type="string", example="+KES 5,500.00"),
     *                 @OA\Property(property="variant_price", type="string", example="141499.00"),
     *                 @OA\Property(property="computed_price", type="number", example=141499),
     *                 @OA\Property(property="formatted_variant_price", type="string", example="KES 141,499.00"),
     *                 @OA\Property(property="formatted_computed_price", type="string", example="KES 141,499.00"),
     *                 @OA\Property(property="online_price", type="number", example=141499),
     *                 @OA\Property(property="formatted_online_price", type="string", example="KES 141,499.00"),
     *                 @OA\Property(property="computed_online_price", type="number", example=141499),
     *                 @OA\Property(property="formatted_computed_online_price", type="string", example="KES 141,499.00"),
     *                 @OA\Property(property="is_available_online", type="boolean", example=true),
     *                 @OA\Property(property="stock_status", type="string", example="in_stock"),
     *                 @OA\Property(property="stock_status_label", type="string", example="In Stock"),
     *                 @OA\Property(property="reorder_level", type="string", example="0.0000"),
     *                 @OA\Property(property="shelf_life_days", type="integer", nullable=true, example=null),
     *                 @OA\Property(property="is_active", type="boolean", example=true),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-22T18:17:37.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-22T18:17:37.000000Z")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-22T18:17:37.779768Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="94cc411f-e553-498d-848d-421bac0aba8c"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized - Invalid or missing authentication token"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Product not found"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error - Invalid input data",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="variant_name",
     *                     type="array",
     *                     @OA\Items(type="string", example="The variant name field is required.")
     *                 ),
     *                 @OA\Property(
     *                     property="uom_id",
     *                     type="array",
     *                     @OA\Items(type="string", example="The uom id field is required.")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-22T18:17:37.779768Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="94cc411f-e553-498d-848d-421bac0aba8c"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     )
     * )
     */
    public function store(StoreProductVariantRequest $request, string $productUuid): JsonResponse
    {
        $product = $this->productService->getByUuid($productUuid);

        $variant = $this->variantService->create($product, $request->validated());

        return ApiResponse::created(
            message: 'Product variant created successfully',
            data: new ProductVariantResource($variant)
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/variants",
     *     summary="Get all variants across products",
     *     description="Retrieves a paginated list of all product variants across all products with filtering, search, and sorting capabilities. Returns up to 15 variants per page with a maximum of 100 per page.",
     *     tags={"Tenant Product Variants"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search term to filter variants by name, SKU, or product name",
     *         required=false,
     *         @OA\Schema(type="string"),
     *         example="QLED"
     *     ),
     *     @OA\Parameter(
     *         name="product_id",
     *         in="query",
     *         description="Filter by product ID",
     *         required=false,
     *         @OA\Schema(type="integer"),
     *         example=4
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter by stock status",
     *         required=false,
     *         @OA\Schema(type="string", enum={"in_stock", "out_of_stock", "discontinued"}),
     *         example="in_stock"
     *     ),
     *     @OA\Parameter(
     *         name="is_active",
     *         in="query",
     *         description="Filter by active status",
     *         required=false,
     *         @OA\Schema(type="boolean"),
     *         example=true
     *     ),
     *     @OA\Parameter(
     *         name="attribute_key",
     *         in="query",
     *         description="Filter by attribute key",
     *         required=false,
     *         @OA\Schema(type="string"),
     *         example="Panel Tech"
     *     ),
     *     @OA\Parameter(
     *         name="attribute_value",
     *         in="query",
     *         description="Filter by attribute value (requires attribute_key)",
     *         required=false,
     *         @OA\Schema(type="string"),
     *         example="QLED (Quantum-dot)"
     *     ),
     *     @OA\Parameter(
     *         name="sort_by",
     *         in="query",
     *         description="Field to sort by",
     *         required=false,
     *         @OA\Schema(type="string"),
     *         example="created_at"
     *     ),
     *     @OA\Parameter(
     *         name="sort_order",
     *         in="query",
     *         description="Sort direction",
     *         required=false,
     *         @OA\Schema(type="string", enum={"asc", "desc"}),
     *         example="desc"
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number for pagination",
     *         required=false,
     *         @OA\Schema(type="integer", default=1),
     *         example=1
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of items per page (max 100)",
     *         required=false,
     *         @OA\Schema(type="integer", default=15, maximum=100),
     *         example=15
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Variants retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Variants retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="variants",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=2),
     *                         @OA\Property(property="product_id", type="integer", example=4),
     *                         @OA\Property(property="product_name", type="string", example="TCL 55 4K UHD Smart LED TV"),
     *                         @OA\Property(property="product_sku", type="string", example="ELEC-DELL-56QT"),
     *                         @OA\Property(property="variant_name", type="string", example="55C725-QLED"),
     *                         @OA\Property(property="sku", type="string", example="ELEC-DELL-56QT-V14Q"),
     *                         @OA\Property(property="product_available_online", type="boolean", example=true),
     *                         @OA\Property(property="display_name", type="string", example="TCL 55 4K UHD Smart LED TV - 55C725-QLED"),
     *                         @OA\Property(
     *                             property="attributes",
     *                             type="object",
     *                             @OA\Property(property="HDR", type="string", example="10+"),
     *                             @OA\Property(property="Design ID", type="string", example="Slim bezel"),
     *                             @OA\Property(property="Panel Tech", type="string", example="QLED (Quantum-dot)"),
     *                             @OA\Property(property="Refresh Rate", type="string", example="144 Hz")
     *                         ),
     *                         @OA\Property(property="uom_display", type="string", example="1.0000 pair"),
     *                         @OA\Property(property="quantity_in_base_uom", type="string", example="1.0000"),
     *                         @OA\Property(property="computed_price", type="number", example=141499),
     *                         @OA\Property(property="formatted_price", type="string", example="KES 141,499.00"),
     *                         @OA\Property(property="online_price", type="number", example=141499),
     *                         @OA\Property(property="formatted_online_price", type="string", example="KES 141,499.00"),
     *                         @OA\Property(property="computed_online_price", type="number", example=141499),
     *                         @OA\Property(property="formatted_computed_online_price", type="string", example="KES 141,499.00"),   
     *                         @OA\Property(property="is_available_online", type="boolean", example=true),
     *                         @OA\Property(property="stock_status", type="string", example="in_stock"),
     *                         @OA\Property(property="stock_status_label", type="string", example="In Stock"),
     *                         @OA\Property(property="is_active", type="boolean", example=true),
     *                         @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-22T18:17:37.000000Z")
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="pagination",
     *                     type="object",
     *                     @OA\Property(property="current_page", type="integer", example=1),
     *                     @OA\Property(property="last_page", type="integer", example=1),
     *                     @OA\Property(property="per_page", type="integer", example=15),
     *                     @OA\Property(property="total", type="integer", example=2),
     *                     @OA\Property(property="from", type="integer", example=1),
     *                     @OA\Property(property="to", type="integer", example=2)
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-22T18:27:58.046660Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="9f144908-d630-458b-a3c6-74c56c88c158"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized - Invalid or missing authentication token"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error - Invalid query parameters"
     *     )
     * )
     */
    public function indexAll(Request $request): JsonResponse
    {
        $filters = $request->only([
            'search',
            'product_id',
            'status',
            'is_active',
            'attribute_key',
            'attribute_value',
            'sort_by',
            'sort_order',
        ]);

        $perPage = $request->integer('per_page', 15);
        $perPage = min($perPage, 100);

        $variants = $this->variantService->list($filters, $perPage);

        return ApiResponse::success(
            message: 'Variants retrieved successfully',
            data: [
                'variants' => ProductVariantListResource::collection($variants->items()),
                'pagination' => [
                    'current_page' => $variants->currentPage(),
                    'last_page' => $variants->lastPage(),
                    'per_page' => $variants->perPage(),
                    'total' => $variants->total(),
                    'from' => $variants->firstItem(),
                    'to' => $variants->lastItem(),
                ],
            ]
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/variants/{id}",
     *     summary="Get variant details",
     *     description="Retrieves detailed information about a specific product variant including product details, UOM, pricing, attributes, and inventory settings.",
     *     tags={"Tenant Product Variants"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Variant ID",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         example=1
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Variant retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Variant retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="product_id", type="integer", example=4),
     *                 @OA\Property(
     *                     property="product",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=4),
     *                     @OA\Property(property="uuid", type="string", format="uuid", example="67b466f5-8b6d-4122-af5d-1683d1dd7a72"),
     *                     @OA\Property(property="name", type="string", example="TCL 55 4K UHD Smart LED TV"),
     *                     @OA\Property(property="slug", type="string", example="tcl-55-4k-uhd-smart-led-tv"),
     *                     @OA\Property(property="sku", type="string", example="ELEC-DELL-56QT")
     *                 ),
     *                 @OA\Property(property="variant_name", type="string", example="55C725-GTV"),
     *                 @OA\Property(property="sku", type="string", example="ELEC-DELL-56QT-V60D"),
     *                 @OA\Property(property="display_name", type="string", example="TCL 55 4K UHD Smart LED TV - 55C725-GTV"),
     *                 @OA\Property(
     *                     property="attributes",
     *                     type="object",
     *                     @OA\Property(property="HDR", type="string", example="10"),
     *                     @OA\Property(property="Design ID", type="string", example="Slim bezel"),
     *                     @OA\Property(property="Panel Tech", type="string", example="Direct-lit LED"),
     *                     @OA\Property(property="Refresh Rate", type="string", example="60 Hz")
     *                 ),
     *                 @OA\Property(
     *                     property="uom",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=2),
     *                     @OA\Property(property="code", type="string", example="pair"),
     *                     @OA\Property(property="name", type="string", example="Pair"),
     *                     @OA\Property(property="type", type="string", example="count"),
     *                     @OA\Property(property="source_type", type="string", example="system"),
     *                     @OA\Property(property="source_type_label", type="string", example="System Defined"),
     *                     @OA\Property(property="is_base_unit", type="boolean", example=false),
     *                     @OA\Property(property="is_active", type="boolean", example=true),
     *                     @OA\Property(property="is_system", type="boolean", example=true),
     *                     @OA\Property(property="is_custom", type="boolean", example=false),
     *                     @OA\Property(property="description", type="string", example="Two pieces"),
     *                     @OA\Property(property="display_name", type="string", example="Pair (pair)"),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-19T13:40:21.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-19T16:12:20.000000Z")
     *                 ),
     *                 @OA\Property(property="uom_id", type="integer", example=2),
     *                 @OA\Property(property="uom_quantity", type="string", example="1.0000"),
     *                 @OA\Property(property="quantity_in_base_uom", type="string", example="1.0000"),
     *                 @OA\Property(property="uom_display", type="string", example="1.0000 pair"),
     *                 @OA\Property(property="base_selling_price_adjustment", type="string", example="0.00"),
     *                 @OA\Property(property="formatted_adjustment", type="string", example="KES 0.00"),
     *                 @OA\Property(property="variant_price", type="string", example="135999.00"),
     *                 @OA\Property(property="computed_price", type="number", example=135999),
     *                 @OA\Property(property="formatted_variant_price", type="string", example="KES 135,999.00"),
     *                 @OA\Property(property="formatted_computed_price", type="string", example="KES 135,999.00"),
     *                 @OA\Property(property="stock_status", type="string", example="in_stock"),
     *                 @OA\Property(property="stock_status_label", type="string", example="In Stock"),
     *                 @OA\Property(property="reorder_level", type="string", example="0.0000"),
     *                 @OA\Property(property="shelf_life_days", type="integer", nullable=true, example=null),
     *                 @OA\Property(property="is_active", type="boolean", example=true),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-22T18:15:42.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-22T18:15:42.000000Z")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-22T18:33:36.552595Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="a84279ec-08b3-42cb-94b5-3a26f574d924"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized - Invalid or missing authentication token"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Variant not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Variant not found"),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-22T18:33:36.552595Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="a84279ec-08b3-42cb-94b5-3a26f574d924"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     )
     * )
     */
    public function show(int $id): JsonResponse
    {
        $variant = $this->variantService->getById($id);

        return ApiResponse::success(
            message: 'Variant retrieved successfully',
            data: new ProductVariantResource($variant)
        );
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/tenant/variants/{id}",
     *     summary="Update variant",
     *     description="Updates a product variant's basic information including name, SKU, attributes, and pricing. All fields are optional - only provided fields will be updated.",
     *     tags={"Tenant Product Variants"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Variant ID",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         example=2
     *     ),
     *     @OA\RequestBody(
     *         required=false,
     *         description="Variant fields to update (all fields optional)",
     *         @OA\JsonContent(
     *             @OA\Property(property="variant_name", type="string", maxLength=255, example="55C725-GAL", description="Variant name/identifier"),
     *             @OA\Property(property="sku", type="string", maxLength=20, example="ELEC-DELL-56QT-V14Q", description="Unique variant SKU"),
     *             @OA\Property(
     *                 property="attributes",
     *                 type="object",
     *                 nullable=true,
     *                 description="Key-value pairs of variant attributes",
     *                 example={
     *                     "Refresh Rate": "144 Hz",
     *                     "Panel Tech": "QLED (Quantum-dot)"
     *                 }
     *             ),
     *             @OA\Property(property="base_selling_price_adjustment", type="number", format="decimal", minimum=-999999.99, maximum=999999.99, example=16000, description="Price adjustment relative to base product price"),
     *             @OA\Property(property="variant_price", type="number", format="decimal", nullable=true, minimum=0, maximum=9999999999.99, example=151999, description="Fixed variant price (overrides adjustment)"),
     *             @OA\Property(property="online_price", type="number", format="decimal", nullable=true, minimum=0, maximum=9999999999.99, example=151999, description="Online price (overrides adjustment)"),
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Variant updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Variant updated successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=2),
     *                 @OA\Property(property="product_id", type="integer", example=4),
     *                 @OA\Property(
     *                     property="product",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=4),
     *                     @OA\Property(property="uuid", type="string", format="uuid", example="67b466f5-8b6d-4122-af5d-1683d1dd7a72"),
     *                     @OA\Property(property="name", type="string", example="TCL 55 4K UHD Smart LED TV"),
     *                     @OA\Property(property="slug", type="string", example="tcl-55-4k-uhd-smart-led-tv"),
     *                     @OA\Property(property="sku", type="string", example="ELEC-DELL-56QT"),
     *                     @OA\Property(property="product_available_online", type="boolean", example=true),
     *                 ),
     *                 @OA\Property(property="variant_name", type="string", example="55C725-GAL"),
     *                 @OA\Property(property="sku", type="string", example="ELEC-DELL-56QT-V14Q"),
     *                 @OA\Property(property="display_name", type="string", example="TCL 55 4K UHD Smart LED TV - 55C725-GAL"),
     *                 @OA\Property(
     *                     property="attributes",
     *                     type="object",
     *                     @OA\Property(property="HDR", type="string", example="10+"),
     *                     @OA\Property(property="Design ID", type="string", example="Slim bezel"),
     *                     @OA\Property(property="Panel Tech", type="string", example="QLED (Quantum-dot)"),
     *                     @OA\Property(property="Refresh Rate", type="string", example="144 Hz")
     *                 ),
     *                 @OA\Property(
     *                     property="uom",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=2),
     *                     @OA\Property(property="code", type="string", example="pair"),
     *                     @OA\Property(property="name", type="string", example="Pair"),
     *                     @OA\Property(property="type", type="string", example="count"),
     *                     @OA\Property(property="source_type", type="string", example="system"),
     *                     @OA\Property(property="source_type_label", type="string", example="System Defined"),
     *                     @OA\Property(property="is_base_unit", type="boolean", example=false),
     *                     @OA\Property(property="is_active", type="boolean", example=true),
     *                     @OA\Property(property="is_system", type="boolean", example=true),
     *                     @OA\Property(property="is_custom", type="boolean", example=false),
     *                     @OA\Property(property="description", type="string", example="Two pieces"),
     *                     @OA\Property(property="display_name", type="string", example="Pair (pair)"),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-19T13:40:21.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-19T16:12:20.000000Z")
     *                 ),
     *                 @OA\Property(property="uom_id", type="integer", example=2),
     *                 @OA\Property(property="uom_quantity", type="string", example="1.0000"),
     *                 @OA\Property(property="quantity_in_base_uom", type="string", example="1.0000"),
     *                 @OA\Property(property="uom_display", type="string", example="1.0000 pair"),
     *                 @OA\Property(property="base_selling_price_adjustment", type="string", example="16000.00"),
     *                 @OA\Property(property="formatted_adjustment", type="string", example="+KES 16,000.00"),
     *                 @OA\Property(property="variant_price", type="string", example="151999.00"),
     *                 @OA\Property(property="computed_price", type="number", example=151999),
     *                 @OA\Property(property="formatted_variant_price", type="string", example="KES 151,999.00"),
     *                 @OA\Property(property="formatted_computed_price", type="string", example="KES 151,999.00"),  
     *                 @OA\Property(property="online_price", type="number", example=151999),
     *                 @OA\Property(property="formatted_online_price", type="string", example="KES 151,999.00"),
     *                 @OA\Property(property="computed_online_price", type="number", example=151999),
     *                 @OA\Property(property="formatted_computed_online_price", type="string", example="KES 151,999.00"),
     *                 @OA\Property(property="is_available_online", type="boolean", example=true),
     *                 @OA\Property(property="stock_status", type="string", example="in_stock"),
     *                 @OA\Property(property="stock_status_label", type="string", example="In Stock"),
     *                 @OA\Property(property="reorder_level", type="string", example="0.0000"),
     *                 @OA\Property(property="shelf_life_days", type="integer", nullable=true, example=null),
     *                 @OA\Property(property="is_active", type="boolean", example=true),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-22T18:17:37.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-22T18:47:01.000000Z")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-22T18:47:01.094532Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="e3fa4ac5-5913-4855-bb38-46459428705d"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized - Invalid or missing authentication token"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Variant not found"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error - Invalid input data",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="sku",
     *                     type="array",
     *                     @OA\Items(type="string", example="The sku has already been taken.")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-22T18:47:01.094532Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="e3fa4ac5-5913-4855-bb38-46459428705d"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     )
     * )
     */
    public function update(UpdateProductVariantRequest $request, int $id): JsonResponse
    {
        $variant = $this->variantService->getById($id);

        $updatedVariant = $this->variantService->update($variant, $request->validated());

        return ApiResponse::success(
            message: 'Variant updated successfully',
            data: new ProductVariantResource($updatedVariant)
        );
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/tenant/variants/{id}/inventory",
     *     summary="Update variant inventory settings",
     *     description="Updates inventory-related settings for a product variant including stock status, reorder level, and shelf life. All fields are optional.",
     *     tags={"Tenant Product Variants"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Variant ID",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         example=2
     *     ),
     *     @OA\RequestBody(
     *         required=false,
     *         description="Inventory settings to update (all fields optional)",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="stock_status",
     *                 type="string",
     *                 enum={"in_stock", "out_of_stock", "discontinued"},
     *                 example="out_of_stock",
     *                 description="Current stock availability status"
     *             ),
     *             @OA\Property(
     *                 property="reorder_level",
     *                 type="number",
     *                 format="decimal",
     *                 minimum=0,
     *                 maximum=999999.9999,
     *                 example=20,
     *                 description="Minimum stock level before reorder alert (in base UOM)"
     *             ),
     *             @OA\Property(
     *                 property="shelf_life_days",
     *                 type="integer",
     *                 nullable=true,
     *                 minimum=0,
     *                 maximum=3650,
     *                 example=0,
     *                 description="Product shelf life in days (null for non-perishable items)"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Variant inventory details updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Variant inventory details updated successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=2),
     *                 @OA\Property(property="product_id", type="integer", example=4),
     *                 @OA\Property(
     *                     property="product",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=4),
     *                     @OA\Property(property="uuid", type="string", format="uuid", example="67b466f5-8b6d-4122-af5d-1683d1dd7a72"),
     *                     @OA\Property(property="name", type="string", example="TCL 55 4K UHD Smart LED TV"),
     *                     @OA\Property(property="slug", type="string", example="tcl-55-4k-uhd-smart-led-tv"),
     *                     @OA\Property(property="sku", type="string", example="ELEC-DELL-56QT"),
     *                     @OA\Property(property="product_available_online", type="boolean", example=true),
     *                 ),
     *                 @OA\Property(property="variant_name", type="string", example="55C725-GAL"),
     *                 @OA\Property(property="sku", type="string", example="ELEC-DELL-56QT-V14Q"),
     *                 @OA\Property(property="display_name", type="string", example="TCL 55 4K UHD Smart LED TV - 55C725-GAL"),
     *                 @OA\Property(
     *                     property="attributes",
     *                     type="object",
     *                     @OA\Property(property="HDR", type="string", example="10+"),
     *                     @OA\Property(property="Design ID", type="string", example="Slim bezel"),
     *                     @OA\Property(property="Panel Tech", type="string", example="QLED (Quantum-dot)"),
     *                     @OA\Property(property="Refresh Rate", type="string", example="144 Hz")
     *                 ),
     *                 @OA\Property(
     *                     property="uom",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=2),
     *                     @OA\Property(property="code", type="string", example="pair"),
     *                     @OA\Property(property="name", type="string", example="Pair"),
     *                     @OA\Property(property="type", type="string", example="count"),
     *                     @OA\Property(property="source_type", type="string", example="system"),
     *                     @OA\Property(property="source_type_label", type="string", example="System Defined"),
     *                     @OA\Property(property="is_base_unit", type="boolean", example=false),
     *                     @OA\Property(property="is_active", type="boolean", example=true),
     *                     @OA\Property(property="is_system", type="boolean", example=true),
     *                     @OA\Property(property="is_custom", type="boolean", example=false),
     *                     @OA\Property(property="description", type="string", example="Two pieces"),
     *                     @OA\Property(property="display_name", type="string", example="Pair (pair)"),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-19T13:40:21.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-19T16:12:20.000000Z")
     *                 ),
     *                 @OA\Property(property="uom_id", type="integer", example=2),
     *                 @OA\Property(property="uom_quantity", type="string", example="1.0000"),
     *                 @OA\Property(property="quantity_in_base_uom", type="string", example="1.0000"),
     *                 @OA\Property(property="uom_display", type="string", example="1.0000 pair"),
     *                 @OA\Property(property="base_selling_price_adjustment", type="string", example="16000.00"),
     *                 @OA\Property(property="formatted_adjustment", type="string", example="+KES 16,000.00"),
     *                 @OA\Property(property="variant_price", type="string", example="151999.00"),
     *                 @OA\Property(property="computed_price", type="number", example=151999),
     *                 @OA\Property(property="formatted_variant_price", type="string", example="KES 151,999.00"),
     *                 @OA\Property(property="formatted_computed_price", type="string", example="KES 151,999.00"),
     *                 @OA\Property(property="online_price", type="number", example=151999),
     *                 @OA\Property(property="formatted_online_price", type="string", example="KES 151,999.00"),
     *                 @OA\Property(property="computed_online_price", type="number", example=151999),
     *                 @OA\Property(property="formatted_computed_online_price", type="string", example="KES 151,999.00"),
     *                 @OA\Property(property="is_available_online", type="boolean", example=true),
     *                 @OA\Property(property="stock_status", type="string", example="out_of_stock"),
     *                 @OA\Property(property="stock_status_label", type="string", example="Out of Stock"),
     *                 @OA\Property(property="reorder_level", type="string", example="20.0000"),
     *                 @OA\Property(property="shelf_life_days", type="integer", example=0),
     *                 @OA\Property(property="is_active", type="boolean", example=true),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-22T18:17:37.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-22T19:41:07.000000Z")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-22T19:41:07.639765Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="48bac098-8400-4cfc-a227-f1d7ec933b7e"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized - Invalid or missing authentication token"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Variant not found"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error - Invalid input data",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="stock_status",
     *                     type="array",
     *                     @OA\Items(type="string", example="The selected stock status is invalid.")
     *                 ),
     *                 @OA\Property(
     *                     property="reorder_level",
     *                     type="array",
     *                     @OA\Items(type="string", example="The reorder level must be at least 0.")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-22T19:41:07.639765Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="48bac098-8400-4cfc-a227-f1d7ec933b7e"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     )
     * )
     */
    public function updateInventory(UpdateVariantInventoryRequest $request, int $id): JsonResponse
    {
        $variant = $this->variantService->getById($id);

        $updatedVariant = $this->variantService->updateInventoryDetails(
            $variant,
            $request->validated()
        );

        return ApiResponse::success(
            message: 'Variant inventory details updated successfully',
            data: new ProductVariantResource($updatedVariant)
        );
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/tenant/variants/{id}/toggle-active",
     *     summary="Toggle variant active status",
     *     description="Toggles the active/inactive status of a product variant. Activates inactive variants and deactivates active variants. No request body required.",
     *     tags={"Tenant Product Variants"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Variant ID",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         example=2
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Variant status toggled successfully (activated)",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Variant activated successfully"),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-22T20:09:36.284835Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="55957ed0-be42-4faa-acee-9eddc2c17134"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response="200 ",
     *         description="Variant status toggled successfully (deactivated)",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Variant deactivated successfully"),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-22T20:09:25.045376Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="34930745-f00c-42b9-a302-a0d3b6338593"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized - Invalid or missing authentication token"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Variant not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Variant not found"),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-22T20:09:25.045376Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="34930745-f00c-42b9-a302-a0d3b6338593"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     )
     * )
     */
    public function toggleActive(int $id): JsonResponse
    {
        $variant = $this->variantService->getById($id);

        $updatedVariant = $this->variantService->toggleActive($variant);

        return ApiResponse::success(
            message: $updatedVariant->is_active
                ? 'Variant activated successfully'
                : 'Variant deactivated successfully'
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/tenant/variants/{id}",
     *     summary="Delete variant",
     *     description="Permanently deletes a product variant. This action cannot be undone. The variant will be removed from inventory and all associated data will be deleted.",
     *     tags={"Tenant Product Variants"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Variant ID",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         example=2
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Variant deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Variant deleted successfully"),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-22T20:10:11.339777Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="ce74b46d-8469-4e82-a33b-52d8323445d8"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized - Invalid or missing authentication token"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Variant not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Variant not found"),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-22T20:10:11.339777Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="ce74b46d-8469-4e82-a33b-52d8323445d8"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     )
     * )
     */
    public function destroy(int $id): JsonResponse
    {
        $variant = $this->variantService->getById($id);

        try {
            $this->variantService->delete($variant);

            return ApiResponse::success(
                message: 'Variant deleted successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::error(
                message: $e->getMessage(),
                status: 400
            );
        }
    }
}
