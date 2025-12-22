<?php

namespace App\Http\Controllers\Api\Tenant\Product;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Product\AddProductImagesRequest;
use App\Http\Requests\Tenant\Product\RemoveProductImageRequest;
use App\Http\Requests\Tenant\Product\StoreProductRequest;
use App\Http\Requests\Tenant\Product\UpdateInventoryConfigRequest;
use App\Http\Requests\Tenant\Product\UpdateOnlineConfigRequest;
use App\Http\Requests\Tenant\Product\UpdateProductRequest;
use App\Http\Resources\Tenant\Product\ProductListResource;
use App\Http\Resources\Tenant\Product\ProductResource;
use App\Http\Responses\ApiResponse;
use App\Services\Tenant\Product\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function __construct(
        private ProductService $productService
    ) {}

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/products",
     *     summary="Get paginated list of products",
     *     description="Retrieves a paginated list of products with filtering, search, and sorting capabilities. Returns up to 15 products per page with a maximum of 100 per page.",
     *     tags={"Tenant Products"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search term to filter products by name, SKU, or description",
     *         required=false,
     *         @OA\Schema(type="string"),
     *         example="Samsung Galaxy"
     *     ),
     *     @OA\Parameter(
     *         name="category_id",
     *         in="query",
     *         description="Filter by category ID",
     *         required=false,
     *         @OA\Schema(type="integer"),
     *         example=1
     *     ),
     *     @OA\Parameter(
     *         name="brand_id",
     *         in="query",
     *         description="Filter by brand ID",
     *         required=false,
     *         @OA\Schema(type="integer"),
     *         example=1
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
     *         name="is_featured",
     *         in="query",
     *         description="Filter by featured status",
     *         required=false,
     *         @OA\Schema(type="boolean"),
     *         example=false
     *     ),
     *     @OA\Parameter(
     *         name="is_available_online",
     *         in="query",
     *         description="Filter by online availability",
     *         required=false,
     *         @OA\Schema(type="boolean"),
     *         example=false
     *     ),
     *     @OA\Parameter(
     *         name="sort_by",
     *         in="query",
     *         description="Sort field and direction (e.g., name:asc, price:desc)",
     *         required=false,
     *         @OA\Schema(type="string"),
     *         example="created_at:desc"
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
     *         description="Products retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Products retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="products",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=4),
     *                         @OA\Property(property="uuid", type="string", format="uuid", example="67b466f5-8b6d-4122-af5d-1683d1dd7a72"),
     *                         @OA\Property(property="name", type="string", example="TCL 55 4K UHD Smart LED TV 43UR7550PSC"),
     *                         @OA\Property(property="slug", type="string", example="tcl-55-4k-uhd-smart-led-tv-43ur7550psc"),
     *                         @OA\Property(property="sku", type="string", example="ELEC-DELL-56QT"),
     *                         @OA\Property(
     *                             property="category",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="name", type="string", example="Electronics"),
     *                             @OA\Property(property="slug", type="string", example="electronics")
     *                         ),
     *                         @OA\Property(
     *                             property="brand",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=7),
     *                             @OA\Property(property="name", type="string", example="Dell"),
     *                             @OA\Property(property="slug", type="string", example="dell")
     *                         ),
     *                         @OA\Property(property="base_selling_price", type="string", example="135999.00"),
     *                         @OA\Property(property="online_price", type="string", nullable=true, example=null),
     *                         @OA\Property(property="formatted_base_price", type="string", example="KES 135,999.00"),
     *                         @OA\Property(property="formatted_online_price", type="string", nullable=true, example=null),
     *                         @OA\Property(property="stock_status", type="string", example="in_stock"),
     *                         @OA\Property(property="stock_status_label", type="string", example="In Stock"),
     *                         @OA\Property(property="is_active", type="boolean", example=true),
     *                         @OA\Property(property="is_featured", type="boolean", example=false),
     *                         @OA\Property(property="is_available_online", type="boolean", example=false),
     *                         @OA\Property(property="primary_image", type="string", format="url", example="http://localhost/storage/products/images/primary_a54_1766233929.jpg"),
     *                         @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-20T12:32:09.000000Z")
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="pagination",
     *                     type="object",
     *                     @OA\Property(property="current_page", type="integer", example=1),
     *                     @OA\Property(property="last_page", type="integer", example=1),
     *                     @OA\Property(property="per_page", type="integer", example=15),
     *                     @OA\Property(property="total", type="integer", example=4),
     *                     @OA\Property(property="from", type="integer", example=1),
     *                     @OA\Property(property="to", type="integer", example=4)
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-21T15:16:00.759857Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="1ad5c0a7-0890-4a7d-a614-22a27b2c4782"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized - Invalid or missing authentication token",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthenticated."),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-21T15:16:00.759857Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="1ad5c0a7-0890-4a7d-a614-22a27b2c4782"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", nullable=true, example=null),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error - Invalid query parameters",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="per_page",
     *                     type="array",
     *                     @OA\Items(type="string", example="The per page must not be greater than 100.")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-21T15:16:00.759857Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="1ad5c0a7-0890-4a7d-a614-22a27b2c4782"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only([
            'search',
            'category_id',
            'brand_id',
            'status',
            'is_active',
            'is_featured',
            'is_available_online',
            'sort_by',
            'sort_order',
        ]);

        $perPage = $request->integer('per_page', 15);
        $perPage = min($perPage, 100); // Max 100 per page

        $products = $this->productService->list($filters, $perPage);

        return ApiResponse::success(
            message: 'Products retrieved successfully',
            data: [
                'products' => ProductListResource::collection($products->items()),
                'pagination' => [
                    'current_page' => $products->currentPage(),
                    'last_page' => $products->lastPage(),
                    'per_page' => $products->perPage(),
                    'total' => $products->total(),
                    'from' => $products->firstItem(),
                    'to' => $products->lastItem(),
                ],
            ]
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/products",
     *     summary="Create a new product",
     *     description="Creates a new product with all necessary details including images, pricing, and inventory configuration. SKU is auto-generated if not provided.",
     *     tags={"Tenant Products"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"name", "category_id", "base_selling_price", "tax_rate_id", "base_uom_id"},
     *                 @OA\Property(property="name", type="string", example="TCL 55 4K UHD Smart LED TV 43UR7550PSC", description="Product name"),
     *                 @OA\Property(property="description", type="string", example="Experience stunning 4K UHD picture quality with TCL's webOS smart platform. Features Active HDR for enhanced contrast, ThinQ AI for voice control, and built-in streaming apps including Netflix, YouTube, and Prime Video. Screen mirroring and Bluetooth connectivity included.", description="Detailed product description"),
     *                 @OA\Property(property="sku", type="string", example="ELEC-DELL-56QT", description="Stock Keeping Unit (auto-generated if not provided)"),
     *                 @OA\Property(property="category_id", type="integer", example=1, description="Product category ID"),
     *                 @OA\Property(property="brand_id", type="integer", example=7, description="Product brand ID (optional)"),     *                 
     *                 @OA\Property(property="product_type", type="string", enum={"simple", "variable"}, example="simple", description="Product type"),
     *                 
     *                 @OA\Property(property="base_selling_price", type="number", format="decimal", example=135999.00, description="Base selling price per base UOM"),
     *                 
     *                 @OA\Property(property="base_uom_id", type="integer", example=1, description="Base unit of measure ID"),     *                 
     *                 @OA\Property(property="primary_image", type="string", format="binary", description="Primary product image file"),
     *                 @OA\Property(
     *                     property="secondary_images[]",
     *                     type="array",
     *                     description="Additional product images (array of files)",
     *                     @OA\Items(type="string", format="binary")
     *                 ),
     *                 @OA\Property(property="is_active", type="boolean", example=true, description="Product active status"),
     *                 @OA\Property(property="is_featured", type="boolean", example=false, description="Mark as featured product"),     *                 
     *                 @OA\Property(property="notes", type="string", example="Internal notes about the product", description="Internal notes")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Product created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Product created successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=4),
     *                 @OA\Property(property="uuid", type="string", format="uuid", example="67b466f5-8b6d-4122-af5d-1683d1dd7a72"),
     *                 @OA\Property(property="name", type="string", example="TCL 55 4K UHD Smart LED TV 43UR7550PSC"),
     *                 @OA\Property(property="slug", type="string", example="tcl-55-4k-uhd-smart-led-tv-43ur7550psc"),
     *                 @OA\Property(property="description", type="string", example="Experience stunning 4K UHD picture quality with TCL's webOS smart platform. Features Active HDR for enhanced contrast, ThinQ AI for voice control, and built-in streaming apps including Netflix, YouTube, and Prime Video. Screen mirroring and Bluetooth connectivity included."),
     *                 @OA\Property(property="sku", type="string", example="ELEC-DELL-56QT"),
     *                 @OA\Property(
     *                     property="category",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Electronics"),
     *                     @OA\Property(property="slug", type="string", example="electronics"),
     *                     @OA\Property(property="description", type="string", example="Electronic devices, gadgets, and accessories"),
     *                     @OA\Property(property="parent_id", type="integer", nullable=true, example=null),
     *                     @OA\Property(property="display_order", type="integer", example=1),
     *                     @OA\Property(property="is_active", type="boolean", example=true),
     *                     @OA\Property(property="is_root", type="boolean", example=true),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-15T21:18:21.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-15T21:18:21.000000Z")
     *                 ),
     *                 @OA\Property(
     *                     property="brand",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=7),
     *                     @OA\Property(property="name", type="string", example="Dell"),
     *                     @OA\Property(property="slug", type="string", example="dell"),
     *                     @OA\Property(property="description", type="string", example="Laptops, desktops, and enterprise computing devices"),
     *                     @OA\Property(property="logo_url", type="string", nullable=true, example=null),
     *                     @OA\Property(property="is_active", type="boolean", example=true),
     *                     @OA\Property(property="is_featured", type="boolean", example=false),
     *                     @OA\Property(property="display_order", type="integer", example=7),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-16T12:47:04.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-16T12:47:04.000000Z")
     *                 ),
     *                 @OA\Property(property="product_type", type="string", example="simple"),
     *                 @OA\Property(property="base_selling_price", type="string", example="135999.00"),
     *                 @OA\Property(property="formatted_base_price", type="string", example="KES 135,999.00"),
     *                 @OA\Property(property="primary_image", type="string", format="url", example="http://localhost/storage/products/images/primary_a54_1766233929.jpg"),
     *                 @OA\Property(
     *                     property="secondary_images",
     *                     type="array",
     *                     @OA\Items(type="string", format="url", example="http://localhost/storage/products/images/secondary_a54-extra_1766233929_0.jpg")
     *                 ),
     *                 @OA\Property(property="image_count", type="integer", example=2),
     *                 @OA\Property(property="is_active", type="boolean", example=true),
     *                 @OA\Property(property="is_featured", type="boolean", example=false),
     *                 @OA\Property(property="notes", type="string", nullable=true, example=null),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-20T12:32:09.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-20T12:32:09.000000Z")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-20T12:32:09.919670Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="2fa0f94b-d26b-4f1a-8593-1e19288e626e"),
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
     *         description="Validation error - Invalid input data",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="name",
     *                     type="array",
     *                     @OA\Items(type="string", example="The name field is required.")
     *                 ),
     *                 @OA\Property(
     *                     property="category_id",
     *                     type="array",
     *                     @OA\Items(type="string", example="The category id field is required.")
     *                 ),
     *                 @OA\Property(
     *                     property="base_selling_price",
     *                     type="array",
     *                     @OA\Items(type="string", example="The base selling price field is required.")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-20T12:32:09.919670Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="2fa0f94b-d26b-4f1a-8593-1e19288e626e"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     )
     * )
     */
    public function store(StoreProductRequest $request): JsonResponse
    {
        $product = $this->productService->create($request->validated());

        return ApiResponse::created(
            message: 'Product created successfully',
            data: new ProductResource($product)
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/products/{uuid}",
     *     summary="Get product by UUID",
     *     description="Retrieves detailed information about a specific product including all associated data such as category, brand, supplier, tax rate, UOM, and images.",
     *     tags={"Tenant Products"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="uuid",
     *         in="path",
     *         description="Product UUID",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid"),
     *         example="67b466f5-8b6d-4122-af5d-1683d1dd7a72"
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
     *                 @OA\Property(property="id", type="integer", example=4),
     *                 @OA\Property(property="uuid", type="string", format="uuid", example="67b466f5-8b6d-4122-af5d-1683d1dd7a72"),
     *                 @OA\Property(property="name", type="string", example="TCL 55 4K UHD Smart LED TV"),
     *                 @OA\Property(property="slug", type="string", example="tcl-55-4k-uhd-smart-led-tv"),
     *                 @OA\Property(property="description", type="string", example="Experience stunning 4K UHD picture quality with TCL's webOS smart platform. Features Active HDR for enhanced contrast, ThinQ AI for voice control, and built-in streaming apps including Netflix, YouTube, and Prime Video. Screen mirroring and Bluetooth connectivity included."),
     *                 @OA\Property(property="sku", type="string", example="ELEC-DELL-56QT"),
     *                 @OA\Property(
     *                     property="category",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Electronics"),
     *                     @OA\Property(property="slug", type="string", example="electronics"),
     *                     @OA\Property(property="description", type="string", example="Electronic devices, gadgets, and accessories"),
     *                     @OA\Property(property="parent_id", type="integer", nullable=true, example=null),
     *                     @OA\Property(property="display_order", type="integer", example=1),
     *                     @OA\Property(property="is_active", type="boolean", example=true),
     *                     @OA\Property(property="is_root", type="boolean", example=true),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-15T21:18:21.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-15T21:18:21.000000Z")
     *                 ),
     *                 @OA\Property(
     *                     property="brand",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=7),
     *                     @OA\Property(property="name", type="string", example="Dell"),
     *                     @OA\Property(property="slug", type="string", example="dell"),
     *                     @OA\Property(property="description", type="string", example="Laptops, desktops, and enterprise computing devices"),
     *                     @OA\Property(property="logo_url", type="string", nullable=true, example=null),
     *                     @OA\Property(property="is_active", type="boolean", example=true),
     *                     @OA\Property(property="is_featured", type="boolean", example=false),
     *                     @OA\Property(property="display_order", type="integer", example=7),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-16T12:47:04.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-16T12:47:04.000000Z")
     *                 ),
     *                 @OA\Property(
     *                     property="supplier",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="TechPro Manufacturing Ltd"),
     *                     @OA\Property(property="supplier_type", type="string", example="retailer"),
     *                     @OA\Property(property="supplier_type_display", type="string", example="Retailer"),
     *                     @OA\Property(property="supplier_type_description", type="string", example="Sells products directly to consumers"),
     *                     @OA\Property(property="contact_person", type="string", example="Mike Doe"),
     *                     @OA\Property(property="email", type="string", example="mike.doe@techpro.com"),
     *                     @OA\Property(property="phone", type="string", example="+254712345678"),
     *                     @OA\Property(property="address", type="string", example="123 Industrial Area, Nairobi, Kenya"),
     *                     @OA\Property(property="registration_number", type="string", example="PVT-2023-001234"),
     *                     @OA\Property(property="credit_limit", type="string", example="1000000.00"),
     *                     @OA\Property(property="outstanding_balance", type="string", example="0.00"),
     *                     @OA\Property(property="payment_terms", type="string", example="net_30"),
     *                     @OA\Property(property="payment_terms_display", type="string", example="Net 30 Days"),
     *                     @OA\Property(property="payment_terms_description", type="string", example="Payment due within 30 days of invoice date"),
     *                     @OA\Property(property="payment_terms_days", type="integer", example=30),
     *                     @OA\Property(
     *                         property="bank_account_details",
     *                         type="object",
     *                         @OA\Property(property="bank", type="string", example="Equity Bank Kenya"),
     *                         @OA\Property(property="branch", type="string", example="Industrial Area Branch"),
     *                         @OA\Property(property="account_name", type="string", example="TechPro Manufacturing Ltd"),
     *                         @OA\Property(property="account_number", type="string", example="0123456789")
     *                     ),
     *                     @OA\Property(property="rating", type="string", example="0.00"),
     *                     @OA\Property(property="total_orders", type="integer", example=0),
     *                     @OA\Property(property="is_active", type="boolean", example=true),
     *                     @OA\Property(property="notes", type="string", example="Specializes in electronic components and hardware manufacturing"),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-19T12:33:47.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-19T12:35:43.000000Z")
     *                 ),
     *                 @OA\Property(property="product_type", type="string", example="simple"),
     *                 @OA\Property(property="stock_status", type="string", example="in_stock"),
     *                 @OA\Property(property="stock_status_label", type="string", example="In Stock"),
     *                 @OA\Property(property="base_selling_price", type="string", example="135999.00"),
     *                 @OA\Property(property="formatted_base_price", type="string", example="KES 135,999.00"),
     *                 @OA\Property(
     *                     property="tax_rate",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="tax_name", type="string", example="VAT"),
     *                     @OA\Property(property="rate", type="string", example="16.00"),
     *                     @OA\Property(property="effective_from", type="string", format="date", example="2025-12-01"),
     *                     @OA\Property(property="effective_until", type="string", format="date", nullable=true, example=null),
     *                     @OA\Property(property="is_active", type="boolean", example=true),
     *                     @OA\Property(property="is_default", type="boolean", example=true),
     *                     @OA\Property(property="is_currently_effective", type="boolean", example=true),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-19T09:10:47.000000Z")
     *                 ),
     *                 @OA\Property(
     *                     property="base_uom",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="code", type="string", example="pcs"),
     *                     @OA\Property(property="name", type="string", example="Piece"),
     *                     @OA\Property(property="type", type="string", example="count"),
     *                     @OA\Property(property="source_type", type="string", example="system"),
     *                     @OA\Property(property="source_type_label", type="string", example="System Defined"),
     *                     @OA\Property(property="is_base_unit", type="boolean", example=true),
     *                     @OA\Property(property="is_active", type="boolean", example=true),
     *                     @OA\Property(property="is_system", type="boolean", example=true),
     *                     @OA\Property(property="is_custom", type="boolean", example=false),
     *                     @OA\Property(property="description", type="string", example="Single item"),
     *                     @OA\Property(property="display_name", type="string", example="Piece (pcs)"),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-19T13:40:21.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-19T16:12:55.000000Z")
     *                 ),
     *                 @OA\Property(property="reorder_level", type="string", example="10.0000"),
     *                 @OA\Property(property="shelf_life_days", type="integer", nullable=true, example=null),
     *                 @OA\Property(property="is_weighed", type="boolean", example=false),
     *                 @OA\Property(property="requires_batch_tracking", type="boolean", example=false),
     *                 @OA\Property(property="requires_serial_tracking", type="boolean", example=true),
     *                 @OA\Property(property="primary_image", type="string", format="url", example="http://localhost/storage/products/images/primary_a54_1766346778.jpg"),
     *                 @OA\Property(
     *                     property="secondary_images",
     *                     type="array",
     *                     @OA\Items(type="string", format="url", example="http://localhost/storage/products/images/secondary_a54-extra_1766233929_0.jpg")
     *                 ),
     *                 @OA\Property(property="image_count", type="integer", example=3),
     *                 @OA\Property(property="is_active", type="boolean", example=false),
     *                 @OA\Property(property="is_featured", type="boolean", example=false),
     *                 @OA\Property(property="is_available_online", type="boolean", example=true),
     *                 @OA\Property(property="online_price", type="string", example="140000.00"),
     *                 @OA\Property(property="formatted_online_price", type="string", example="KES 140,000.00"),
     *                 @OA\Property(property="online_description", type="string", example="Experience stunning 4K UHD picture quality with TCL's webOS smart platform."),
     *                 @OA\Property(property="notes", type="string", example="Online notes"),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-20T12:32:09.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-21T20:09:13.000000Z")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-22T05:04:00.989501Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="516f9c13-b96e-4a1c-9769-f6e770d350f7"),
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
     *         description="Product not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Product not found"),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-22T05:04:00.989501Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="516f9c13-b96e-4a1c-9769-f6e770d350f7"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     )
     * )
     */
    public function show(string $uuid): JsonResponse
    {
        $product = $this->productService->getByUuid($uuid);

        return ApiResponse::success(
            message: 'Product retrieved successfully',
            data: new ProductResource($product)
        );
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/tenant/products/{uuid}",
     *     summary="Update product details",
     *     description="Updates basic product information such as name, description, pricing, and categorization. All fields are optional - only provided fields will be updated.",
     *     tags={"Tenant Products"},
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
     *         required=false,
     *         description="Product fields to update (all fields are optional)",
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", example="TCL 55 4K UHD Smart LED TV", description="Product name"),
     *             @OA\Property(property="description", type="string", example="Experience stunning 4K UHD picture quality", description="Product description"),
     *             @OA\Property(property="sku", type="string", example="ELEC-TCL-5500", description="Stock Keeping Unit"),
     *             @OA\Property(property="category_id", type="integer", example=1, description="Product category ID"),
     *             @OA\Property(property="brand_id", type="integer", example=7, description="Product brand ID"),
     *             @OA\Property(property="product_type", type="string", enum={"simple", "variable"}, example="simple", description="Product type"),
     *             @OA\Property(property="base_selling_price", type="number", format="decimal", example=139999.00, description="Base selling price"),
     *             @OA\Property(property="is_active", type="boolean", example=true, description="Active status"),
     *             @OA\Property(property="is_featured", type="boolean", example=true, description="Featured status"),
     *             @OA\Property(property="notes", type="string", example="Updated product notes", description="Internal notes")
     *         )
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
     *                 @OA\Property(property="id", type="integer", example=4),
     *                 @OA\Property(property="uuid", type="string", format="uuid", example="67b466f5-8b6d-4122-af5d-1683d1dd7a72"),
     *                 @OA\Property(property="name", type="string", example="TCL 55 4K UHD Smart LED TV"),
     *                 @OA\Property(property="slug", type="string", example="tcl-55-4k-uhd-smart-led-tv"),
     *                 @OA\Property(property="description", type="string", example="Experience stunning 4K UHD picture quality with TCL's webOS smart platform. Features Active HDR for enhanced contrast, ThinQ AI for voice control, and built-in streaming apps including Netflix, YouTube, and Prime Video. Screen mirroring and Bluetooth connectivity included."),
     *                 @OA\Property(property="sku", type="string", example="ELEC-DELL-56QT"),
     *                 @OA\Property(
     *                     property="category",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Electronics"),
     *                     @OA\Property(property="slug", type="string", example="electronics"),
     *                     @OA\Property(property="description", type="string", example="Electronic devices, gadgets, and accessories"),
     *                     @OA\Property(property="parent_id", type="integer", nullable=true, example=null),
     *                     @OA\Property(property="display_order", type="integer", example=1),
     *                     @OA\Property(property="is_active", type="boolean", example=true),
     *                     @OA\Property(property="is_root", type="boolean", example=true),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-15T21:18:21.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-15T21:18:21.000000Z")
     *                 ),
     *                 @OA\Property(
     *                     property="brand",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=7),
     *                     @OA\Property(property="name", type="string", example="Dell"),
     *                     @OA\Property(property="slug", type="string", example="dell"),
     *                     @OA\Property(property="description", type="string", example="Laptops, desktops, and enterprise computing devices"),
     *                     @OA\Property(property="logo_url", type="string", nullable=true, example=null),
     *                     @OA\Property(property="is_active", type="boolean", example=true),
     *                     @OA\Property(property="is_featured", type="boolean", example=false),
     *                     @OA\Property(property="display_order", type="integer", example=7),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-16T12:47:04.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-16T12:47:04.000000Z")
     *                 ),
     *                 @OA\Property(
     *                     property="supplier",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="TechPro Manufacturing Ltd"),
     *                     @OA\Property(property="supplier_type", type="string", example="retailer"),
     *                     @OA\Property(property="supplier_type_display", type="string", example="Retailer"),
     *                     @OA\Property(property="supplier_type_description", type="string", example="Sells products directly to consumers"),
     *                     @OA\Property(property="contact_person", type="string", example="Mike Doe"),
     *                     @OA\Property(property="email", type="string", example="mike.doe@techpro.com"),
     *                     @OA\Property(property="phone", type="string", example="+254712345678"),
     *                     @OA\Property(property="address", type="string", example="123 Industrial Area, Nairobi, Kenya"),
     *                     @OA\Property(property="registration_number", type="string", example="PVT-2023-001234"),
     *                     @OA\Property(property="credit_limit", type="string", example="1000000.00"),
     *                     @OA\Property(property="outstanding_balance", type="string", example="0.00"),
     *                     @OA\Property(property="payment_terms", type="string", example="net_30"),
     *                     @OA\Property(property="payment_terms_display", type="string", example="Net 30 Days"),
     *                     @OA\Property(property="payment_terms_description", type="string", example="Payment due within 30 days of invoice date"),
     *                     @OA\Property(property="payment_terms_days", type="integer", example=30),
     *                     @OA\Property(
     *                         property="bank_account_details",
     *                         type="object",
     *                         @OA\Property(property="bank", type="string", example="Equity Bank Kenya"),
     *                         @OA\Property(property="branch", type="string", example="Industrial Area Branch"),
     *                         @OA\Property(property="account_name", type="string", example="TechPro Manufacturing Ltd"),
     *                         @OA\Property(property="account_number", type="string", example="0123456789")
     *                     ),
     *                     @OA\Property(property="rating", type="string", example="0.00"),
     *                     @OA\Property(property="total_orders", type="integer", example=0),
     *                     @OA\Property(property="is_active", type="boolean", example=true),
     *                     @OA\Property(property="notes", type="string", example="Specializes in electronic components and hardware manufacturing"),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-19T12:33:47.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-19T12:35:43.000000Z")
     *                 ),
     *                 @OA\Property(property="product_type", type="string", example="simple"),
     *                 @OA\Property(property="stock_status", type="string", example="in_stock"),
     *                 @OA\Property(property="stock_status_label", type="string", example="In Stock"),
     *                 @OA\Property(property="base_selling_price", type="string", example="135999.00"),
     *                 @OA\Property(property="formatted_base_price", type="string", example="KES 135,999.00"),
     *                 @OA\Property(
     *                     property="tax_rate",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="tax_name", type="string", example="VAT"),
     *                     @OA\Property(property="rate", type="string", example="16.00"),
     *                     @OA\Property(property="effective_from", type="string", format="date", example="2025-12-01"),
     *                     @OA\Property(property="effective_until", type="string", format="date", nullable=true, example=null),
     *                     @OA\Property(property="is_active", type="boolean", example=true),
     *                     @OA\Property(property="is_default", type="boolean", example=true),
     *                     @OA\Property(property="is_currently_effective", type="boolean", example=true),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-19T09:10:47.000000Z")
     *                 ),
     *                 @OA\Property(
     *                     property="base_uom",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="code", type="string", example="pcs"),
     *                     @OA\Property(property="name", type="string", example="Piece"),
     *                     @OA\Property(property="type", type="string", example="count"),
     *                     @OA\Property(property="source_type", type="string", example="system"),
     *                     @OA\Property(property="source_type_label", type="string", example="System Defined"),
     *                     @OA\Property(property="is_base_unit", type="boolean", example=true),
     *                     @OA\Property(property="is_active", type="boolean", example=true),
     *                     @OA\Property(property="is_system", type="boolean", example=true),
     *                     @OA\Property(property="is_custom", type="boolean", example=false),
     *                     @OA\Property(property="description", type="string", example="Single item"),
     *                     @OA\Property(property="display_name", type="string", example="Piece (pcs)"),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-19T13:40:21.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-19T16:12:55.000000Z")
     *                 ),
     *                 @OA\Property(property="reorder_level", type="string", example="10.0000"),
     *                 @OA\Property(property="shelf_life_days", type="integer", nullable=true, example=null),
     *                 @OA\Property(property="is_weighed", type="boolean", example=false),
     *                 @OA\Property(property="requires_batch_tracking", type="boolean", example=false),
     *                 @OA\Property(property="requires_serial_tracking", type="boolean", example=true),
     *                 @OA\Property(property="primary_image", type="string", format="url", example="http://localhost/storage/products/images/primary_a54_1766346778.jpg"),
     *                 @OA\Property(
     *                     property="secondary_images",
     *                     type="array",
     *                     @OA\Items(type="string", format="url", example="http://localhost/storage/products/images/secondary_a54-extra_1766233929_0.jpg")
     *                 ),
     *                 @OA\Property(property="image_count", type="integer", example=3),
     *                 @OA\Property(property="is_active", type="boolean", example=false),
     *                 @OA\Property(property="is_featured", type="boolean", example=false),
     *                 @OA\Property(property="is_available_online", type="boolean", example=true),
     *                 @OA\Property(property="online_price", type="string", example="140000.00"),
     *                 @OA\Property(property="formatted_online_price", type="string", example="KES 140,000.00"),
     *                 @OA\Property(property="online_description", type="string", example="Experience stunning 4K UHD picture quality with TCL's webOS smart platform."),
     *                 @OA\Property(property="notes", type="string", example="Online notes"),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-20T12:32:09.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-21T20:09:13.000000Z")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-22T05:04:00.989501Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="516f9c13-b96e-4a1c-9769-f6e770d350f7"),
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
     *                     property="base_selling_price",
     *                     type="array",
     *                     @OA\Items(type="string", example="The base selling price must be a number.")
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
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-21T15:50:55.489078Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="16bdfa7a-2980-4e99-85d5-17b8fc624be4"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     )
     * )
     */
    public function update(UpdateProductRequest $request, string $uuid): JsonResponse
    {
        $product = $this->productService->getByUuid($uuid);

        $updatedProduct = $this->productService->update($product, $request->validated());

        return ApiResponse::success(
            message: 'Product updated successfully',
            data: new ProductResource($updatedProduct)
        );
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/tenant/products/{uuid}/inventory",
     *     summary="Update product inventory configuration",
     *     description="Updates inventory-related settings for a product including supplier, tax rate, stock status, reorder levels, and tracking requirements. All fields are optional.",
     *     tags={"Tenant Products"},
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
     *         required=false,
     *         description="Inventory configuration fields to update (all fields are optional)",
     *         @OA\JsonContent(
     *             @OA\Property(property="supplier_id", type="integer", example=1, description="Default supplier ID"),
     *             @OA\Property(property="tax_rate_id", type="integer", example=1, description="Applicable tax rate ID"),
     *             @OA\Property(property="stock_status", type="string", enum={"in_stock", "out_of_stock", "discontinued"}, example="in_stock", description="Current stock status"),
     *             @OA\Property(property="reorder_level", type="number", format="decimal", example=10, description="Minimum stock level before reorder alert (in base UOM)"),
     *             @OA\Property(property="shelf_life_days", type="integer", example=365, description="Product shelf life in days"),
     *             @OA\Property(property="is_weighed", type="boolean", example=false, description="Whether product is sold by weight"),
     *             @OA\Property(property="requires_batch_tracking", type="boolean", example=true, description="Enable batch/lot number tracking"),
     *             @OA\Property(property="requires_serial_tracking", type="boolean", example=true, description="Enable serial number tracking")
     *         )
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
     *                 @OA\Property(property="id", type="integer", example=4),
     *                 @OA\Property(property="uuid", type="string", format="uuid", example="67b466f5-8b6d-4122-af5d-1683d1dd7a72"),
     *                 @OA\Property(property="name", type="string", example="TCL 55 4K UHD Smart LED TV"),
     *                 @OA\Property(property="slug", type="string", example="tcl-55-4k-uhd-smart-led-tv"),
     *                 @OA\Property(property="description", type="string", example="Experience stunning 4K UHD picture quality with TCL's webOS smart platform. Features Active HDR for enhanced contrast, ThinQ AI for voice control, and built-in streaming apps including Netflix, YouTube, and Prime Video. Screen mirroring and Bluetooth connectivity included."),
     *                 @OA\Property(property="sku", type="string", example="ELEC-DELL-56QT"),
     *                 @OA\Property(
     *                     property="category",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Electronics"),
     *                     @OA\Property(property="slug", type="string", example="electronics"),
     *                     @OA\Property(property="description", type="string", example="Electronic devices, gadgets, and accessories"),
     *                     @OA\Property(property="parent_id", type="integer", nullable=true, example=null),
     *                     @OA\Property(property="display_order", type="integer", example=1),
     *                     @OA\Property(property="is_active", type="boolean", example=true),
     *                     @OA\Property(property="is_root", type="boolean", example=true),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-15T21:18:21.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-15T21:18:21.000000Z")
     *                 ),
     *                 @OA\Property(
     *                     property="brand",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=7),
     *                     @OA\Property(property="name", type="string", example="Dell"),
     *                     @OA\Property(property="slug", type="string", example="dell"),
     *                     @OA\Property(property="description", type="string", example="Laptops, desktops, and enterprise computing devices"),
     *                     @OA\Property(property="logo_url", type="string", nullable=true, example=null),
     *                     @OA\Property(property="is_active", type="boolean", example=true),
     *                     @OA\Property(property="is_featured", type="boolean", example=false),
     *                     @OA\Property(property="display_order", type="integer", example=7),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-16T12:47:04.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-16T12:47:04.000000Z")
     *                 ),
     *                 @OA\Property(
     *                     property="supplier",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="TechPro Manufacturing Ltd"),
     *                     @OA\Property(property="supplier_type", type="string", example="retailer"),
     *                     @OA\Property(property="supplier_type_display", type="string", example="Retailer"),
     *                     @OA\Property(property="supplier_type_description", type="string", example="Sells products directly to consumers"),
     *                     @OA\Property(property="contact_person", type="string", example="Mike Doe"),
     *                     @OA\Property(property="email", type="string", example="mike.doe@techpro.com"),
     *                     @OA\Property(property="phone", type="string", example="+254712345678"),
     *                     @OA\Property(property="address", type="string", example="123 Industrial Area, Nairobi, Kenya"),
     *                     @OA\Property(property="registration_number", type="string", example="PVT-2023-001234"),
     *                     @OA\Property(property="credit_limit", type="string", example="1000000.00"),
     *                     @OA\Property(property="outstanding_balance", type="string", example="0.00"),
     *                     @OA\Property(property="payment_terms", type="string", example="net_30"),
     *                     @OA\Property(property="payment_terms_display", type="string", example="Net 30 Days"),
     *                     @OA\Property(property="payment_terms_description", type="string", example="Payment due within 30 days of invoice date"),
     *                     @OA\Property(property="payment_terms_days", type="integer", example=30),
     *                     @OA\Property(
     *                         property="bank_account_details",
     *                         type="object",
     *                         @OA\Property(property="bank", type="string", example="Equity Bank Kenya"),
     *                         @OA\Property(property="branch", type="string", example="Industrial Area Branch"),
     *                         @OA\Property(property="account_name", type="string", example="TechPro Manufacturing Ltd"),
     *                         @OA\Property(property="account_number", type="string", example="0123456789")
     *                     ),
     *                     @OA\Property(property="rating", type="string", example="0.00"),
     *                     @OA\Property(property="total_orders", type="integer", example=0),
     *                     @OA\Property(property="is_active", type="boolean", example=true),
     *                     @OA\Property(property="notes", type="string", example="Specializes in electronic components and hardware manufacturing"),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-19T12:33:47.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-19T12:35:43.000000Z")
     *                 ),
     *                 @OA\Property(property="product_type", type="string", example="simple"),
     *                 @OA\Property(property="stock_status", type="string", example="in_stock"),
     *                 @OA\Property(property="stock_status_label", type="string", example="In Stock"),
     *                 @OA\Property(property="base_selling_price", type="string", example="135999.00"),
     *                 @OA\Property(property="formatted_base_price", type="string", example="KES 135,999.00"),
     *                 @OA\Property(
     *                     property="tax_rate",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="tax_name", type="string", example="VAT"),
     *                     @OA\Property(property="rate", type="string", example="16.00"),
     *                     @OA\Property(property="effective_from", type="string", format="date", example="2025-12-01"),
     *                     @OA\Property(property="effective_until", type="string", format="date", nullable=true, example=null),
     *                     @OA\Property(property="is_active", type="boolean", example=true),
     *                     @OA\Property(property="is_default", type="boolean", example=true),
     *                     @OA\Property(property="is_currently_effective", type="boolean", example=true),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-19T09:10:47.000000Z")
     *                 ),
     *                 @OA\Property(
     *                     property="base_uom",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="code", type="string", example="pcs"),
     *                     @OA\Property(property="name", type="string", example="Piece"),
     *                     @OA\Property(property="type", type="string", example="count"),
     *                     @OA\Property(property="source_type", type="string", example="system"),
     *                     @OA\Property(property="source_type_label", type="string", example="System Defined"),
     *                     @OA\Property(property="is_base_unit", type="boolean", example=true),
     *                     @OA\Property(property="is_active", type="boolean", example=true),
     *                     @OA\Property(property="is_system", type="boolean", example=true),
     *                     @OA\Property(property="is_custom", type="boolean", example=false),
     *                     @OA\Property(property="description", type="string", example="Single item"),
     *                     @OA\Property(property="display_name", type="string", example="Piece (pcs)"),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-19T13:40:21.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-19T16:12:55.000000Z")
     *                 ),
     *                 @OA\Property(property="reorder_level", type="string", example="10.0000"),
     *                 @OA\Property(property="shelf_life_days", type="integer", nullable=true, example=null),
     *                 @OA\Property(property="is_weighed", type="boolean", example=false),
     *                 @OA\Property(property="requires_batch_tracking", type="boolean", example=false),
     *                 @OA\Property(property="requires_serial_tracking", type="boolean", example=true),
     *                 @OA\Property(property="primary_image", type="string", format="url", example="http://localhost/storage/products/images/primary_a54_1766346778.jpg"),
     *                 @OA\Property(
     *                     property="secondary_images",
     *                     type="array",
     *                     @OA\Items(type="string", format="url", example="http://localhost/storage/products/images/secondary_a54-extra_1766233929_0.jpg")
     *                 ),
     *                 @OA\Property(property="image_count", type="integer", example=3),
     *                 @OA\Property(property="is_active", type="boolean", example=false),
     *                 @OA\Property(property="is_featured", type="boolean", example=false),
     *                 @OA\Property(property="is_available_online", type="boolean", example=true),
     *                 @OA\Property(property="online_price", type="string", example="140000.00"),
     *                 @OA\Property(property="formatted_online_price", type="string", example="KES 140,000.00"),
     *                 @OA\Property(property="online_description", type="string", example="Experience stunning 4K UHD picture quality with TCL's webOS smart platform."),
     *                 @OA\Property(property="notes", type="string", example="Online notes"),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-20T12:32:09.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-21T20:09:13.000000Z")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-22T05:04:00.989501Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="516f9c13-b96e-4a1c-9769-f6e770d350f7"),
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
     *                     property="supplier_id",
     *                     type="array",
     *                     @OA\Items(type="string", example="The selected supplier id is invalid.")
     *                 ),
     *                 @OA\Property(
     *                     property="tax_rate_id",
     *                     type="array",
     *                     @OA\Items(type="string", example="The selected tax rate id is invalid.")
     *                 ),
     *                 @OA\Property(
     *                     property="stock_status",
     *                     type="array",
     *                     @OA\Items(type="string", example="The selected stock status is invalid.")
     *                 ),
     *                 @OA\Property(
     *                     property="reorder_level",
     *                     type="array",
     *                     @OA\Items(type="string", example="The reorder level must be a number.")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-21T16:20:12.598909Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="c832989c-6155-441a-bdf4-cc6d9d65a920"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     )
     * )
     */
    public function updateInventoryConfig(UpdateInventoryConfigRequest $request, string $uuid): JsonResponse
    {
        $product = $this->productService->getByUuid($uuid);

        $updatedProduct = $this->productService->updateInventoryConfig(
            $product,
            $request->validated()
        );

        return ApiResponse::success(
            message: 'Product inventory configuration updated successfully',
            data: new ProductResource($updatedProduct)
        );
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/tenant/products/{uuid}/online",
     *     summary="Update product online marketplace configuration",
     *     description="Updates online marketplace settings for a product including availability, online price, and online-specific description. When making a product available online (is_available_online=true), online_price is required.",
     *     tags={"Tenant Products"},
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
     *         required=false,
     *         description="Online marketplace configuration fields (all fields optional, but online_price required when is_available_online is true)",
     *         @OA\JsonContent(
     *             @OA\Property(property="is_available_online", type="boolean", example=true, description="Make product available on online marketplace"),
     *             @OA\Property(property="online_price", type="number", format="decimal", example=140000, description="Online marketplace price (required when is_available_online is true)"),
     *             @OA\Property(property="online_description", type="string", example="Experience stunning 4K UHD picture quality with TCL's webOS smart platform.", description="Online-specific product description"),
     *             @OA\Property(property="notes", type="string", example="Online notes", description="Internal notes about online availability")
     *         )
     *     ),
     *@OA\Response(
     *         response=200,
     *         description="Product retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Product retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=4),
     *                 @OA\Property(property="uuid", type="string", format="uuid", example="67b466f5-8b6d-4122-af5d-1683d1dd7a72"),
     *                 @OA\Property(property="name", type="string", example="TCL 55 4K UHD Smart LED TV"),
     *                 @OA\Property(property="slug", type="string", example="tcl-55-4k-uhd-smart-led-tv"),
     *                 @OA\Property(property="description", type="string", example="Experience stunning 4K UHD picture quality with TCL's webOS smart platform. Features Active HDR for enhanced contrast, ThinQ AI for voice control, and built-in streaming apps including Netflix, YouTube, and Prime Video. Screen mirroring and Bluetooth connectivity included."),
     *                 @OA\Property(property="sku", type="string", example="ELEC-DELL-56QT"),
     *                 @OA\Property(
     *                     property="category",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Electronics"),
     *                     @OA\Property(property="slug", type="string", example="electronics"),
     *                     @OA\Property(property="description", type="string", example="Electronic devices, gadgets, and accessories"),
     *                     @OA\Property(property="parent_id", type="integer", nullable=true, example=null),
     *                     @OA\Property(property="display_order", type="integer", example=1),
     *                     @OA\Property(property="is_active", type="boolean", example=true),
     *                     @OA\Property(property="is_root", type="boolean", example=true),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-15T21:18:21.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-15T21:18:21.000000Z")
     *                 ),
     *                 @OA\Property(
     *                     property="brand",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=7),
     *                     @OA\Property(property="name", type="string", example="Dell"),
     *                     @OA\Property(property="slug", type="string", example="dell"),
     *                     @OA\Property(property="description", type="string", example="Laptops, desktops, and enterprise computing devices"),
     *                     @OA\Property(property="logo_url", type="string", nullable=true, example=null),
     *                     @OA\Property(property="is_active", type="boolean", example=true),
     *                     @OA\Property(property="is_featured", type="boolean", example=false),
     *                     @OA\Property(property="display_order", type="integer", example=7),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-16T12:47:04.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-16T12:47:04.000000Z")
     *                 ),
     *                 @OA\Property(
     *                     property="supplier",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="TechPro Manufacturing Ltd"),
     *                     @OA\Property(property="supplier_type", type="string", example="retailer"),
     *                     @OA\Property(property="supplier_type_display", type="string", example="Retailer"),
     *                     @OA\Property(property="supplier_type_description", type="string", example="Sells products directly to consumers"),
     *                     @OA\Property(property="contact_person", type="string", example="Mike Doe"),
     *                     @OA\Property(property="email", type="string", example="mike.doe@techpro.com"),
     *                     @OA\Property(property="phone", type="string", example="+254712345678"),
     *                     @OA\Property(property="address", type="string", example="123 Industrial Area, Nairobi, Kenya"),
     *                     @OA\Property(property="registration_number", type="string", example="PVT-2023-001234"),
     *                     @OA\Property(property="credit_limit", type="string", example="1000000.00"),
     *                     @OA\Property(property="outstanding_balance", type="string", example="0.00"),
     *                     @OA\Property(property="payment_terms", type="string", example="net_30"),
     *                     @OA\Property(property="payment_terms_display", type="string", example="Net 30 Days"),
     *                     @OA\Property(property="payment_terms_description", type="string", example="Payment due within 30 days of invoice date"),
     *                     @OA\Property(property="payment_terms_days", type="integer", example=30),
     *                     @OA\Property(
     *                         property="bank_account_details",
     *                         type="object",
     *                         @OA\Property(property="bank", type="string", example="Equity Bank Kenya"),
     *                         @OA\Property(property="branch", type="string", example="Industrial Area Branch"),
     *                         @OA\Property(property="account_name", type="string", example="TechPro Manufacturing Ltd"),
     *                         @OA\Property(property="account_number", type="string", example="0123456789")
     *                     ),
     *                     @OA\Property(property="rating", type="string", example="0.00"),
     *                     @OA\Property(property="total_orders", type="integer", example=0),
     *                     @OA\Property(property="is_active", type="boolean", example=true),
     *                     @OA\Property(property="notes", type="string", example="Specializes in electronic components and hardware manufacturing"),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-19T12:33:47.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-19T12:35:43.000000Z")
     *                 ),
     *                 @OA\Property(property="product_type", type="string", example="simple"),
     *                 @OA\Property(property="stock_status", type="string", example="in_stock"),
     *                 @OA\Property(property="stock_status_label", type="string", example="In Stock"),
     *                 @OA\Property(property="base_selling_price", type="string", example="135999.00"),
     *                 @OA\Property(property="formatted_base_price", type="string", example="KES 135,999.00"),
     *                 @OA\Property(
     *                     property="tax_rate",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="tax_name", type="string", example="VAT"),
     *                     @OA\Property(property="rate", type="string", example="16.00"),
     *                     @OA\Property(property="effective_from", type="string", format="date", example="2025-12-01"),
     *                     @OA\Property(property="effective_until", type="string", format="date", nullable=true, example=null),
     *                     @OA\Property(property="is_active", type="boolean", example=true),
     *                     @OA\Property(property="is_default", type="boolean", example=true),
     *                     @OA\Property(property="is_currently_effective", type="boolean", example=true),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-19T09:10:47.000000Z")
     *                 ),
     *                 @OA\Property(
     *                     property="base_uom",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="code", type="string", example="pcs"),
     *                     @OA\Property(property="name", type="string", example="Piece"),
     *                     @OA\Property(property="type", type="string", example="count"),
     *                     @OA\Property(property="source_type", type="string", example="system"),
     *                     @OA\Property(property="source_type_label", type="string", example="System Defined"),
     *                     @OA\Property(property="is_base_unit", type="boolean", example=true),
     *                     @OA\Property(property="is_active", type="boolean", example=true),
     *                     @OA\Property(property="is_system", type="boolean", example=true),
     *                     @OA\Property(property="is_custom", type="boolean", example=false),
     *                     @OA\Property(property="description", type="string", example="Single item"),
     *                     @OA\Property(property="display_name", type="string", example="Piece (pcs)"),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-19T13:40:21.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-19T16:12:55.000000Z")
     *                 ),
     *                 @OA\Property(property="reorder_level", type="string", example="10.0000"),
     *                 @OA\Property(property="shelf_life_days", type="integer", nullable=true, example=null),
     *                 @OA\Property(property="is_weighed", type="boolean", example=false),
     *                 @OA\Property(property="requires_batch_tracking", type="boolean", example=false),
     *                 @OA\Property(property="requires_serial_tracking", type="boolean", example=true),
     *                 @OA\Property(property="primary_image", type="string", format="url", example="http://localhost/storage/products/images/primary_a54_1766346778.jpg"),
     *                 @OA\Property(
     *                     property="secondary_images",
     *                     type="array",
     *                     @OA\Items(type="string", format="url", example="http://localhost/storage/products/images/secondary_a54-extra_1766233929_0.jpg")
     *                 ),
     *                 @OA\Property(property="image_count", type="integer", example=3),
     *                 @OA\Property(property="is_active", type="boolean", example=false),
     *                 @OA\Property(property="is_featured", type="boolean", example=false),
     *                 @OA\Property(property="is_available_online", type="boolean", example=true),
     *                 @OA\Property(property="online_price", type="string", example="140000.00"),
     *                 @OA\Property(property="formatted_online_price", type="string", example="KES 140,000.00"),
     *                 @OA\Property(property="online_description", type="string", example="Experience stunning 4K UHD picture quality with TCL's webOS smart platform."),
     *                 @OA\Property(property="notes", type="string", example="Online notes"),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-20T12:32:09.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-21T20:09:13.000000Z")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-22T05:04:00.989501Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="516f9c13-b96e-4a1c-9769-f6e770d350f7"),
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
     *         description="Validation error - Invalid input data or missing required fields",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="online_price",
     *                     type="array",
     *                     @OA\Items(type="string", example="Online price is required when making product available online")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-21T16:45:17.400001Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="65f4a49c-ff1e-40c5-8840-d79e7745222a"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     )
     * )
     */
    public function updateOnlineConfig(UpdateOnlineConfigRequest $request, string $uuid): JsonResponse
    {
        $product = $this->productService->getByUuid($uuid);

        $updatedProduct = $this->productService->updateOnlineConfig(
            $product,
            $request->validated()
        );

        return ApiResponse::success(
            message: 'Product online configuration updated successfully',
            data: new ProductResource($updatedProduct)
        );
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/tenant/products/{uuid}/toggle-active",
     *     summary="Toggle product active status",
     *     description="Toggles the active status of a product. If currently active, it will be deactivated. If currently inactive, it will be activated. No request body required.",
     *     tags={"Tenant Products"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="uuid",
     *         in="path",
     *         description="Product UUID",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid"),
     *         example="67b466f5-8b6d-4122-af5d-1683d1dd7a72"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Product active status toggled successfully - Deactivated",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Product deactivated successfully"),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-21T17:03:34.606288Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="505368d2-965f-4840-bb86-f2e2a6f280ce"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response="200-activated",
     *         description="Product active status toggled successfully - Activated",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Product activated successfully"),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-21T17:03:53.358091Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="733395d2-2dfb-4f20-b391-65e490b61643"),
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
     *         description="Product not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Product not found"),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-21T17:03:34.606288Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="505368d2-965f-4840-bb86-f2e2a6f280ce"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     )
     * )
     */
    public function toggleActive(string $uuid): JsonResponse
    {
        $product = $this->productService->getByUuid($uuid);

        $updatedProduct = $this->productService->toggleActive($product);

        return ApiResponse::success(
            message: $updatedProduct->is_active
                ? 'Product activated successfully'
                : 'Product deactivated successfully'
        );
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/tenant/products/{uuid}/toggle-featured",
     *     summary="Toggle product featured status",
     *     description="Toggles the featured status of a product. If currently featured, it will be unmarked as featured. If currently not featured, it will be marked as featured. No request body required.",
     *     tags={"Tenant Products"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="uuid",
     *         in="path",
     *         description="Product UUID",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid"),
     *         example="67b466f5-8b6d-4122-af5d-1683d1dd7a72"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Product featured status toggled successfully - Marked as featured",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Product marked as featured"),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-21T17:56:20.296679Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="67260003-4ece-4e73-9778-28918ee07dc1"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response="200-unfeatured",
     *         description="Product featured status toggled successfully - Unmarked as featured",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Product unmarked as featured"),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-21T18:01:58.107195Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="a203c930-67f4-4ec0-9ae6-49303fcabf56"),
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
     *         description="Product not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Product not found"),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-21T17:56:20.296679Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="67260003-4ece-4e73-9778-28918ee07dc1"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     )
     * )
     */
    public function toggleFeatured(string $uuid): JsonResponse
    {
        $product = $this->productService->getByUuid($uuid);

        $updatedProduct = $this->productService->toggleFeatured($product);

        return ApiResponse::success(
            message: $updatedProduct->is_featured
                ? 'Product marked as featured'
                : 'Product unmarked as featured'
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/products/{uuid}/images",
     *     summary="Add secondary images to product",
     *     description="Adds one or more secondary images to a product. Accepts multiple image files via multipart/form-data. Images are stored and associated with the product.",
     *     tags={"Tenant Products"},
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
     *         description="Array of image files to upload",
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"images"},
     *                 @OA\Property(
     *                     property="images[]",
     *                     type="array",
     *                     description="Array of image files (max size per file: 5MB, allowed formats: jpg, jpeg, png, webp)",
     *                     @OA\Items(
     *                         type="string",
     *                         format="binary"
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Images added successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Images added successfully"),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-21T18:01:58.107195Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="a203c930-67f4-4ec0-9ae6-49303fcabf56"),
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
     *         description="Product not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Product not found"),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-21T18:01:58.107195Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="a203c930-67f4-4ec0-9ae6-49303fcabf56"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error - Invalid file type or size",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="images.0",
     *                     type="array",
     *                     @OA\Items(type="string", example="The images.0 must be an image.")
     *                 ),
     *                 @OA\Property(
     *                     property="images.1",
     *                     type="array",
     *                     @OA\Items(type="string", example="The images.1 must not be greater than 5120 kilobytes.")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-21T18:01:58.107195Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="a203c930-67f4-4ec0-9ae6-49303fcabf56"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     )
     * )
     */
    public function addImages(AddProductImagesRequest $request, string $uuid): JsonResponse
    {
        $product = $this->productService->getByUuid($uuid);

        $updatedProduct = $this->productService->addImages(
            $product,
            $request->validated('images')
        );

        return ApiResponse::success(
            message: 'Images added successfully'
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/tenant/products/{uuid}/images",
     *     summary="Delete product secondary images",
     *     description="Deletes one or more secondary images from a product. Accepts an array of image paths to delete. Returns count of successfully deleted images, failed deletions, and remaining images.",
     *     tags={"Tenant Products"},
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
     *         description="Array of image paths to delete",
     *         @OA\JsonContent(
     *             required={"images"},
     *             @OA\Property(
     *                 property="images",
     *                 type="array",
     *                 description="Array of image paths (relative paths from storage/public)",
     *                 @OA\Items(
     *                     type="string",
     *                     example="products/images/secondary_a54-extra_1766341988_1.jpg"
     *                 ),
     *                 example={"products/images/secondary_a54-extra_1766341988_1.jpg", "products/images/secondary_a54_1766233929_2.jpg"}
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Images deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Successfully deleted 1 image(s)"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="deleted_count", type="integer", example=1, description="Number of images successfully deleted"),
     *                 @OA\Property(property="failed_count", type="integer", example=0, description="Number of images that failed to delete"),
     *                 @OA\Property(property="remaining_images", type="integer", example=3, description="Total number of secondary images remaining")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-21T20:09:13.206072Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="98dfe367-6811-43b5-8d0f-b167c3c3c2d1"),
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
     *         description="Product not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Product not found"),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-21T20:09:13.206072Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="98dfe367-6811-43b5-8d0f-b167c3c3c2d1"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error - Invalid or missing images array",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="images",
     *                     type="array",
     *                     @OA\Items(type="string", example="The images field is required.")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-21T20:09:13.206072Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="98dfe367-6811-43b5-8d0f-b167c3c3c2d1"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     )
     * )
     */
    public function removeImage(string $uuid, RemoveProductImageRequest $request): JsonResponse
    {
        $product = $this->productService->getByUuid($uuid);

        $result = $this->productService->deleteImages(
            $product,
            $request->validated('images')
        );

        return ApiResponse::success(
            data: [
                'deleted_count' => $result['deleted_count'],
                'failed_count' => $result['failed_count'],
                'remaining_images' => $result['remaining_images'],
            ],
            message: $result['deleted_count'] > 0
                ? "Successfully deleted {$result['deleted_count']} image(s)"
                : 'No images were deleted'
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/products/{uuid}/primary-image",
     *     summary="Update product primary image",
     *     description="Updates the primary/main image of a product. The previous primary image is replaced with the new uploaded image. Accepts a single image file via multipart/form-data.",
     *     tags={"Tenant Products"},
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
     *         description="Primary image file to upload",
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"primary_image"},
     *                 @OA\Property(
     *                     property="primary_image",
     *                     type="string",
     *                     format="binary",
     *                     description="Primary image file (max size: 5MB, allowed formats: jpg, jpeg, png, webp)"
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Primary image updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Primary image updated successfully"),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-21T19:52:58.908076Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="1e3bf537-cb23-41c7-b0a0-f1a8887cf860"),
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
     *         description="Product not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Product not found"),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-21T19:52:58.908076Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="1e3bf537-cb23-41c7-b0a0-f1a8887cf860"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error - Invalid file type, size, or missing file",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="primary_image",
     *                     type="array",
     *                     @OA\Items(type="string", example="The primary image field is required.")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-21T19:52:58.908076Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="1e3bf537-cb23-41c7-b0a0-f1a8887cf860"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     )
     * )
     */
    public function updatePrimaryImage(string $uuid, Request $request): JsonResponse
    {
        // validate request
        $request->validate([
            'primary_image' => 'required|file|image|mimes:jpeg,jpg,png|max:2048',
        ]);

        $product = $this->productService->getByUuid($uuid);
        $newImagePath = $this->productService->replacePrimaryImage(
            $product,
            $request->file('primary_image')
        );

        // Actually update the product with the new image path
        $product->update(['primary_image' => $newImagePath]);

        $this->productService->clearProductCache($product->uuid);

        return ApiResponse::success(
            message: 'Primary image updated successfully'
        );
    }
}
