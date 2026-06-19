<?php

namespace App\Http\Controllers\Api\Tenant\Product;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Product\AddBundleImagesRequest;
use App\Http\Requests\Tenant\Product\AddBundleItemRequest;
use App\Http\Requests\Tenant\Product\StoreProductBundleRequest;
use App\Http\Requests\Tenant\Product\UpdateBundleItemRequest;
use App\Http\Requests\Tenant\Product\UpdateBundlePricingRequest;
use App\Http\Requests\Tenant\Product\UpdateProductBundleRequest;
use App\Http\Resources\Tenant\Product\ProductBundleResource;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant\ProductBundleItem;
use App\Services\Tenant\Product\ProductBundleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductBundleController extends Controller
{
    public function __construct(
        private ProductBundleService $bundleService
    ) {}

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/bundles",
     *     summary="Get all product bundles",
     *     description="Retrieves a paginated list of all product bundles with their items, pricing, and availability information. Returns up to 15 bundles per page.",
     *     tags={"Tenant Product Bundles"},
     *     security={{"sanctum": {}}},
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
     *         description="Number of items per page",
     *         required=false,
     *         @OA\Schema(type="integer", default=15),
     *         example=15
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Bundles retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Bundles retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="bundles",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="bundle_name", type="string", example="Breakfast Combo"),
     *                         @OA\Property(property="bundle_sku", type="string", example="BNDL-GENR-BU8V"),
     *                         @OA\Property(property="description", type="string", example="Complete breakfast package for family"),
     *                         @OA\Property(
     *                             property="images",
     *                             type="array",
     *                             @OA\Items(
     *                                 type="object",
     *                                 @OA\Property(property="path", type="string", example="bundles/images/bundle_1_a54-extra_1766482359_0.jpg"),
     *                                 @OA\Property(property="url", type="string", example="http://localhost/storage/bundles/images/bundle_1_a54-extra_1766482359_0.jpg"),
     *                                 @OA\Property(property="filename", type="string", example="bundle_1_a54-extra_1766482359_0.jpg")
     *                             )
     *                         ),
     *                         @OA\Property(property="image_count", type="integer", example=1),
     *                         @OA\Property(
     *                             property="base_uom",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="code", type="string", example="pcs"),
     *                             @OA\Property(property="name", type="string", example="Piece"),
     *                             @OA\Property(property="type", type="string", example="count"),
     *                             @OA\Property(property="display_name", type="string", example="Piece (pcs)")
     *                         ),
     *                         @OA\Property(
     *                             property="tax_rate",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="tax_name", type="string", example="VAT"),
     *                             @OA\Property(property="rate", type="string", example="16.00"),
     *                             @OA\Property(property="is_currently_effective", type="boolean", example=true)
     *                         ),
     *                         @OA\Property(property="bundle_price", type="string", example="450.00"),
     *                         @OA\Property(property="calculated_individual_price", type="string", example="45999.00"),
     *                         @OA\Property(property="discount_amount", type="string", example="45549.00"),
     *                         @OA\Property(property="savings_percentage", type="number", example=99.02),
     *                         @OA\Property(property="formatted_bundle_price", type="string", example="KES 450.00"),
     *                         @OA\Property(property="formatted_discount", type="string", example="KES 45,549.00"),
     *                         @OA\Property(property="is_available_online", type="boolean", example=true),
     *                         @OA\Property(property="online_price", type="string", example="420.00"),
     *                         @OA\Property(property="formatted_online_price", type="string", example="KES 420.00"),
     *                         @OA\Property(property="online_description", type="string", example="Order now and save KES 30!"),
     *                         @OA\Property(property="items_count", type="integer", example=2),
     *                         @OA\Property(property="is_active", type="boolean", example=true),
     *                         @OA\Property(property="has_minimum_items", type="boolean", example=true),
     *                         @OA\Property(property="all_items_active", type="boolean", example=false),
     *                         @OA\Property(property="is_available_for_sale", type="boolean", example=false),
     *                         @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-23T09:31:52.000000Z"),
     *                         @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-23T09:32:39.000000Z")
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="pagination",
     *                     type="object",
     *                     @OA\Property(property="current_page", type="integer", example=1),
     *                     @OA\Property(property="last_page", type="integer", example=1),
     *                     @OA\Property(property="per_page", type="integer", example=15),
     *                     @OA\Property(property="total", type="integer", example=1)
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-23T09:32:48.710687Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="6a2adbdc-575e-4b42-b78c-23209a72d7be"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized - Invalid or missing authentication token"
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['search', 'is_active', 'is_online', 'sort_by', 'sort_order']);
        $perPage = min($request->integer('per_page', 15), 100);

        $bundles = $this->bundleService->list($filters, $perPage);

        return ApiResponse::success(
            message: 'Bundles retrieved successfully',
            data: [
                'bundles' => ProductBundleResource::collection($bundles->items()),
                'pagination' => [
                    'current_page' => $bundles->currentPage(),
                    'last_page' => $bundles->lastPage(),
                    'per_page' => $bundles->perPage(),
                    'total' => $bundles->total(),
                ],
            ]
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/bundles",
     *     summary="Create a new product bundle",
     *     description="Creates a new product bundle with optional items. Bundle price must be provided, and if items are included, at least 2 items are required.",
     *     tags={"Tenant Product Bundles"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"bundle_name", "base_uom_id", "bundle_price", "tax_rate_id"},
     *             @OA\Property(property="bundle_name", type="string", maxLength=255, example="Breakfast Combo"),
     *             @OA\Property(property="bundle_sku", type="string", maxLength=30, nullable=true, example="BNDL-BRKFST-001", description="Auto-generated if not provided"),
     *             @OA\Property(property="description", type="string", maxLength=5000, nullable=true, example="Complete breakfast package for family"),
     *             @OA\Property(property="base_uom_id", type="integer", example=1, description="Base unit of measure ID (e.g., pieces)"),
     *             @OA\Property(property="bundle_price", type="number", format="decimal", example=450.00, description="Bundle selling price (max: 9999999999.99)"),
     *             @OA\Property(property="tax_rate_id", type="integer", example=1, description="Tax rate ID to apply"),
     *             @OA\Property(property="is_available_online", type="boolean", nullable=true, example=true),
     *             @OA\Property(property="is_active", type="boolean", nullable=true, example=true),
     *             @OA\Property(property="online_price", type="number", format="decimal", nullable=true, example=420.00, description="Special online price (max: 9999999999.99)"),
     *             @OA\Property(property="online_description", type="string", maxLength=5000, nullable=true, example="Order now and save KES 30!"),
     *             @OA\Property(
     *                 property="items",
     *                 type="array",
     *                 nullable=true,
     *                 description="Array of bundle items (minimum 2 items if provided)",
     *                 @OA\Items(
     *                     required={"product_id", "uom_id", "quantity"},
     *                     @OA\Property(property="product_id", type="integer", example=1),
     *                     @OA\Property(property="product_variant_id", type="integer", nullable=true, example=null, description="Specific variant ID or null for base product"),
     *                     @OA\Property(property="uom_id", type="integer", example=1, description="Unit of measure ID"),
     *                     @OA\Property(property="quantity", type="number", format="decimal", example=2.0, description="Quantity (min: 0.0001, max: 999999.9999)")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Bundle created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Bundle created successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=2),
     *                 @OA\Property(property="bundle_name", type="string", example="Breakfast Combo"),
     *                 @OA\Property(property="bundle_sku", type="string", example="BNDL-GENR-BKMX"),
     *                 @OA\Property(property="description", type="string", example="Complete breakfast package for family"),
     *                 @OA\Property(property="images", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="image_count", type="integer", example=0),
     *                 @OA\Property(property="primary_image", type="string", nullable=true, example=null),
     *                 @OA\Property(
     *                     property="base_uom",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="code", type="string", example="pcs"),
     *                     @OA\Property(property="name", type="string", example="Piece"),
     *                     @OA\Property(property="display_name", type="string", example="Piece (pcs)")
     *                 ),
     *                 @OA\Property(
     *                     property="tax_rate",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="tax_name", type="string", example="VAT"),
     *                     @OA\Property(property="rate", type="string", example="16.00")
     *                 ),
     *                 @OA\Property(property="bundle_price", type="string", example="450.00"),
     *                 @OA\Property(property="calculated_individual_price", type="string", example="91998.00"),
     *                 @OA\Property(property="discount_amount", type="string", example="91548.00"),
     *                 @OA\Property(property="savings_percentage", type="number", format="float", example=99.51),
     *                 @OA\Property(property="formatted_bundle_price", type="string", example="KES 450.00"),
     *                 @OA\Property(property="formatted_discount", type="string", example="KES 91,548.00"),
     *                 @OA\Property(property="is_available_online", type="boolean", example=true),
     *                 @OA\Property(property="online_price", type="string", example="420.00"),
     *                 @OA\Property(property="formatted_online_price", type="string", example="KES 420.00"),
     *                 @OA\Property(property="online_description", type="string", example="Order now and save KES 30!"),
     *                 @OA\Property(
     *                     property="items",
     *                     type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="bundle_id", type="integer", example=2),
     *                         @OA\Property(property="product_id", type="integer", example=1),
     *                         @OA\Property(
     *                             property="product",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="name", type="string", example="Samsung Galaxy A54 5G 128GB"),
     *                             @OA\Property(property="sku", type="string", example="ELEC-SAMS-VTFM")
     *                         ),
     *                         @OA\Property(property="product_variant_id", type="integer", nullable=true, example=null),
     *                         @OA\Property(property="display_name", type="string", example="Samsung Galaxy A54 5G 128GB"),
     *                         @OA\Property(property="sku", type="string", example="ELEC-SAMS-VTFM"),
     *                         @OA\Property(property="is_using_variant", type="boolean", example=false),
     *                         @OA\Property(property="quantity", type="string", example="2.0000"),
     *                         @OA\Property(property="quantity_in_base_uom", type="string", example="2.0000"),
     *                         @OA\Property(property="uom_display", type="string", example="2.0000 pcs"),
     *                         @OA\Property(property="item_price", type="number", example=45999),
     *                         @OA\Property(property="total_price", type="number", example=91998),
     *                         @OA\Property(property="formatted_item_price", type="string", example="KES 45,999.00"),
     *                         @OA\Property(property="formatted_total_price", type="string", example="KES 91,998.00")
     *                     )
     *                 ),
     *                 @OA\Property(property="items_count", type="integer", example=2),
     *                 @OA\Property(property="is_active", type="boolean", example=true),
     *                 @OA\Property(property="has_minimum_items", type="boolean", example=true),
     *                 @OA\Property(property="all_items_active", type="boolean", example=false),
     *                 @OA\Property(property="is_available_for_sale", type="boolean", example=false),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-23T06:23:15.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-23T06:23:15.000000Z")
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
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(property="bundle_name", type="array", @OA\Items(type="string", example="The bundle name field is required.")),
     *                 @OA\Property(property="bundle_sku", type="array", @OA\Items(type="string", example="The bundle sku has already been taken.")),
     *                 @OA\Property(property="items", type="array", @OA\Items(type="string", example="The items must have at least 2 items."))
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Forbidden - User lacks manage-products permission")
     * )
     */
    public function store(StoreProductBundleRequest $request): JsonResponse
    {
        $bundle = $this->bundleService->create($request->validated());

        return ApiResponse::created(
            message: 'Bundle created successfully',
            data: new ProductBundleResource($bundle)
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/bundles/{id}",
     *     summary="Get bundle details",
     *     description="Retrieves complete information about a specific product bundle including all items, pricing details, images, and availability status.",
     *     tags={"Tenant Product Bundles"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Bundle ID",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         example=1
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Bundle retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Bundle retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="bundle_name", type="string", example="Breakfast Combo"),
     *                 @OA\Property(property="bundle_sku", type="string", example="BNDL-GENR-BU8V"),
     *                 @OA\Property(property="description", type="string", example="Complete breakfast package for family"),
     *                 @OA\Property(
     *                     property="images",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="path", type="string", example="bundles/images/bundle_1_a54-extra_1766482359_0.jpg"),
     *                         @OA\Property(property="url", type="string", example="http://localhost/storage/bundles/images/bundle_1_a54-extra_1766482359_0.jpg"),
     *                         @OA\Property(property="filename", type="string", example="bundle_1_a54-extra_1766482359_0.jpg")
     *                     )
     *                 ),
     *                 @OA\Property(property="image_count", type="integer", example=1),
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
     *                 @OA\Property(
     *                     property="tax_rate",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="tax_name", type="string", example="VAT"),
     *                     @OA\Property(property="rate", type="string", example="16.00"),
     *                     @OA\Property(property="effective_from", type="string", format="date", example="2025-12-01"),
     *                     @OA\Property(property="effective_until", type="string", nullable=true, example=null),
     *                     @OA\Property(property="is_active", type="boolean", example=true),
     *                     @OA\Property(property="is_default", type="boolean", example=true),
     *                     @OA\Property(property="is_currently_effective", type="boolean", example=true),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-19T09:10:47.000000Z")
     *                 ),
     *                 @OA\Property(property="bundle_price", type="string", example="450.00"),
     *                 @OA\Property(property="calculated_individual_price", type="string", example="45999.00"),
     *                 @OA\Property(property="discount_amount", type="string", example="45549.00"),
     *                 @OA\Property(property="savings_percentage", type="number", example=99.02),
     *                 @OA\Property(property="formatted_bundle_price", type="string", example="KES 450.00"),
     *                 @OA\Property(property="formatted_discount", type="string", example="KES 45,549.00"),
     *                 @OA\Property(property="is_available_online", type="boolean", example=true),
     *                 @OA\Property(property="online_price", type="string", example="420.00"),
     *                 @OA\Property(property="formatted_online_price", type="string", example="KES 420.00"),
     *                 @OA\Property(property="online_description", type="string", example="Order now and save KES 30!"),
     *                 @OA\Property(
     *                     property="items",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="bundle_id", type="integer", example=1),
     *                         @OA\Property(property="product_id", type="integer", example=1),
     *                         @OA\Property(
     *                             property="product",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="name", type="string", example="Samsung Galaxy A54 5G 128GB"),
     *                             @OA\Property(property="sku", type="string", example="ELEC-SAMS-VTFM")
     *                         ),
     *                         @OA\Property(property="product_variant_id", type="integer", nullable=true, example=null),
     *                         @OA\Property(property="display_name", type="string", example="Samsung Galaxy A54 5G 128GB"),
     *                         @OA\Property(property="sku", type="string", example="ELEC-SAMS-VTFM"),
     *                         @OA\Property(property="is_using_variant", type="boolean", example=false),
     *                         @OA\Property(
     *                             property="uom",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="code", type="string", example="pcs"),
     *                             @OA\Property(property="name", type="string", example="Piece"),
     *                             @OA\Property(property="display_name", type="string", example="Piece (pcs)")
     *                         ),
     *                         @OA\Property(property="quantity", type="string", example="1.0000"),
     *                         @OA\Property(property="quantity_in_base_uom", type="string", example="1.0000"),
     *                         @OA\Property(property="uom_display", type="string", example="1.0000 pcs"),
     *                         @OA\Property(property="item_price", type="number", example=45999),
     *                         @OA\Property(property="total_price", type="number", example=45999),
     *                         @OA\Property(property="formatted_item_price", type="string", example="KES 45,999.00"),
     *                         @OA\Property(property="formatted_total_price", type="string", example="KES 45,999.00"),
     *                         @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-23T09:31:52.000000Z"),
     *                         @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-23T09:31:52.000000Z")
     *                     )
     *                 ),
     *                 @OA\Property(property="items_count", type="integer", example=2),
     *                 @OA\Property(property="is_active", type="boolean", example=true),
     *                 @OA\Property(property="has_minimum_items", type="boolean", example=true),
     *                 @OA\Property(property="all_items_active", type="boolean", example=false),
     *                 @OA\Property(property="is_available_for_sale", type="boolean", example=false),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-23T09:31:52.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-23T09:32:39.000000Z")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-23T09:33:41.130897Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="91fe771c-2fe0-4bd4-8d05-feeb2d49f1e0"),
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
     *         description="Bundle not found"
     *     )
     * )
     */
    public function show(int $id): JsonResponse
    {
        $bundle = $this->bundleService->getById($id);

        return ApiResponse::success(
            message: 'Bundle retrieved successfully',
            data: new ProductBundleResource($bundle)
        );
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/tenant/bundles/{id}",
     *     summary="Update bundle",
     *     description="Updates basic bundle information including name, SKU, and descriptions. All fields are optional - only provided fields will be updated.",
     *     tags={"Tenant Product Bundles"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Bundle ID",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         example=2
     *     ),
     *     @OA\RequestBody(
     *         required=false,
     *         description="Bundle fields to update (all optional)",
     *         @OA\JsonContent(
     *             @OA\Property(property="bundle_name", type="string", maxLength=255, example="Breakfast Combo"),
     *             @OA\Property(property="bundle_sku", type="string", maxLength=30, example="BNDL-GENR-BKMX"),
     *             @OA\Property(property="description", type="string", maxLength=5000, example="Complete breakfast package for family and friends"),
     *             @OA\Property(property="online_description", type="string", maxLength=5000, example="Order now and save!")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Bundle updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Bundle updated successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 description="Complete bundle object with updated information (same structure as GET /bundles/{id})"
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
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Bundle not found"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error - SKU already taken"
     *     )
     * )
     */
    public function update(UpdateProductBundleRequest $request, int $id): JsonResponse
    {
        $bundle = $this->bundleService->getById($id);
        $updatedBundle = $this->bundleService->update($bundle, $request->validated());

        return ApiResponse::success(
            message: 'Bundle updated successfully',
            data: new ProductBundleResource($updatedBundle)
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/bundles/{id}/items",
     *     summary="Add item to bundle",
     *     description="Adds a new product or product variant to an existing bundle. The bundle must maintain at least 2 items.",
     *     tags={"Tenant Product Bundles"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Bundle ID",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         example=2
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"product_id", "uom_id", "quantity"},
     *             @OA\Property(property="product_id", type="integer", example=1, description="Product ID"),
     *             @OA\Property(property="product_variant_id", type="integer", nullable=true, example=null, description="Variant ID (null for base product)"),
     *             @OA\Property(property="uom_id", type="integer", example=1, description="Unit of Measure ID"),
     *             @OA\Property(property="quantity", type="number", format="decimal", minimum=0.0001, maximum=999999.9999, example=1.0, description="Quantity")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Item added to bundle successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Item added to bundle successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 description="Complete updated bundle object with new item included"
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
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Bundle not found"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function addItem(AddBundleItemRequest $request, int $id): JsonResponse
    {
        $bundle = $this->bundleService->getById($id);
        $item = $this->bundleService->addItem($bundle, $request->validated());

        return ApiResponse::created(
            message: 'Item added to bundle successfully',
            data: new ProductBundleResource($bundle->fresh('items'))
        );
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/tenant/bundles/{bundleId}/items/{itemId}",
     *     summary="Update bundle item",
     *     description="Updates the quantity or UOM of an existing item in a bundle. All fields are optional.",
     *     tags={"Tenant Product Bundles"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="bundleId",
     *         in="path",
     *         description="Bundle ID",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         example=2
     *     ),
     *     @OA\Parameter(
     *         name="itemId",
     *         in="path",
     *         description="Bundle Item ID",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         example=1
     *     ),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="uom_id", type="integer", example=1),
     *             @OA\Property(property="quantity", type="number", format="decimal", minimum=0.0001, maximum=999999.9999, example=1)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Bundle item updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Bundle item updated successfully"),
     *             @OA\Property(property="data", type="object", description="Complete updated bundle object")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=404, description="Bundle or item not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function updateItem(UpdateBundleItemRequest $request, int $id, int $itemId): JsonResponse
    {
        $bundle = $this->bundleService->getById($id);
        $item = ProductBundleItem::where('id', $itemId)->where('bundle_id', $id)->firstOrFail();

        $this->bundleService->updateItem($item, $request->validated());

        return ApiResponse::success(
            message: 'Bundle item updated successfully',
            data: new ProductBundleResource($bundle->fresh('items'))
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/tenant/bundles/{bundleId}/items/{itemId}",
     *     summary="Remove item from bundle",
     *     description="Removes an item from a bundle. Bundle must maintain at least 2 items - returns 400 if removal would leave fewer than 2 items.",
     *     tags={"Tenant Product Bundles"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="bundleId",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         example=2
     *     ),
     *     @OA\Parameter(
     *         name="itemId",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         example=2
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Item removed from bundle successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Item removed from bundle successfully"),
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
     *     @OA\Response(
     *         response=400,
     *         description="Bundle must have at least 2 items",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Bundle must have at least 2 items")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=404, description="Bundle or item not found")
     * )
     */
    public function removeItem(int $id, int $itemId): JsonResponse
    {
        $bundle = $this->bundleService->getById($id);
        $item = ProductBundleItem::where('id', $itemId)->where('bundle_id', $id)->firstOrFail();

        try {
            $this->bundleService->removeItem($item);

            return ApiResponse::success(message: 'Item removed from bundle successfully');
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error(message: $e->getMessage(), status: 400);
        }
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/tenant/bundles/{id}/toggle-active",
     *     summary="Toggle bundle active status",
     *     description="Toggles the active/inactive status of a bundle. No request body required.",
     *     tags={"Tenant Product Bundles"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         example=2
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Bundle status toggled (activated)",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Bundle activated")
     *         )
     *     ),
     *     @OA\Response(
     *         response="200 ",
     *         description="Bundle status toggled (deactivated)",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Bundle deactivated")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=404, description="Bundle not found")
     * )
     */
    public function toggleActive(int $id): JsonResponse
    {
        $bundle = $this->bundleService->getById($id);
        $updatedBundle = $this->bundleService->toggleActive($bundle);

        return ApiResponse::success(
            message: $updatedBundle->is_active ? 'Bundle activated' : 'Bundle deactivated',
        );
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/tenant/bundles/{id}/toggle-online",
     *     summary="Toggle bundle online availability",
     *     description="Toggles whether the bundle is available on the online marketplace. No request body required.",
     *     tags={"Tenant Product Bundles"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         example=2
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Bundle made available online",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Bundle available online")
     *         )
     *     ),
     *     @OA\Response(
     *         response="200 ",
     *         description="Bundle removed from online",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Bundle removed from online")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=404, description="Bundle not found")
     * )
     */
    public function toggleOnline(int $id): JsonResponse
    {
        $bundle = $this->bundleService->getById($id);
        $updatedBundle = $this->bundleService->toggleOnline($bundle);

        return ApiResponse::success(
            message: $updatedBundle->is_available_online ? 'Bundle available online' : 'Bundle removed from online',
        );
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/tenant/bundles/{id}/pricing",
     *     summary="Update bundle pricing",
     *     description="Updates the bundle price and optional online price.",
     *     tags={"Tenant Product Bundles"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         example=2
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"bundle_price"},
     *             @OA\Property(property="bundle_price", type="number", format="decimal", minimum=0, maximum=9999999999.99, example=4800.00),
     *             @OA\Property(property="online_price", type="number", format="decimal", nullable=true, minimum=0, maximum=9999999999.99, example=4500.00)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Bundle pricing updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Bundle pricing updated successfully")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=404, description="Bundle not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function updatePricing(UpdateBundlePricingRequest $request, int $id): JsonResponse
    {
        $bundle = $this->bundleService->getById($id);
        $updatedBundle = $this->bundleService->updatePricing($bundle, $request->validated());

        return ApiResponse::success(
            message: 'Bundle pricing updated successfully',
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/bundles/{id}/images",
     *     summary="Add bundle images",
     *     description="Uploads images for a bundle. Accepts 1-5 images. Max 2MB per image. Supported formats: jpeg, jpg, png, webp.",
     *     tags={"Tenant Product Bundles"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         example=2
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"images"},
     *                 @OA\Property(
     *                     property="images",
     *                     type="array",
     *                     minItems=1,
     *                     maxItems=5,
     *                     @OA\Items(type="string", format="binary"),
     *                     description="Array of image files (min: 1, max: 5)"
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
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=404, description="Bundle not found"),
     *     @OA\Response(response=422, description="Validation error - invalid file type or size")
     * )
     */
    public function addImages(AddBundleImagesRequest $request, int $id): JsonResponse
    {
        $bundle = $this->bundleService->getById($id);
        $updatedBundle = $this->bundleService->addImages($bundle, $request->file('images'));

        return ApiResponse::success(
            message: 'Images added successfully',
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/tenant/bundles/{id}/images",
     *     summary="Remove bundle image",
     *     description="Removes a specific image from a bundle by providing the image path.",
     *     tags={"Tenant Product Bundles"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         example=2
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"image_path"},
     *             @OA\Property(
     *                 property="image_path",
     *                 type="string",
     *                 example="bundles/images/bundle_2_a54_1766480358_0.jpg",
     *                 description="Path of the image to remove"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Image removed successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Image removed successfully"),
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
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=404, description="Bundle or image not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function removeImage(Request $request, int $id): JsonResponse
    {
        $bundle = $this->bundleService->getById($id);

        $request->validate([
            'image_path' => 'required|string'
        ]);

        $updatedBundle = $this->bundleService->removeImage($bundle, $request->image_path);

        return ApiResponse::success(
            message: 'Image removed successfully',
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/bundles/{id}/breakdown",
     *     summary="Get bundle price breakdown",
     *     description="Retrieves a detailed breakdown of bundle items with individual prices, total, bundle price, and savings calculation.",
     *     tags={"Tenant Product Bundles"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         example=2
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Bundle breakdown retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Bundle breakdown retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="items",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="Samsung Galaxy A54 5G 128GB"),
     *                         @OA\Property(property="sku", type="string", example="ELEC-SAMS-VTFM"),
     *                         @OA\Property(property="quantity", type="string", example="1.0000"),
     *                         @OA\Property(property="uom", type="string", example="pcs"),
     *                         @OA\Property(property="unit_price", type="number", example=45999),
     *                         @OA\Property(property="total_price", type="number", example=45999),
     *                         @OA\Property(property="formatted_unit_price", type="string", example="KES 45,999.00"),
     *                         @OA\Property(property="formatted_total_price", type="string", example="KES 45,999.00")
     *                     )
     *                 ),
     *                 @OA\Property(property="individual_total", type="number", example=45999),
     *                 @OA\Property(property="bundle_price", type="string", example="4800.00"),
     *                 @OA\Property(property="savings", type="number", example=41199),
     *                 @OA\Property(property="savings_percentage", type="number", example=89.56),
     *                 @OA\Property(property="formatted_individual_total", type="string", example="KES 45,999.00"),
     *                 @OA\Property(property="formatted_bundle_price", type="string", example="KES 4,800.00"),
     *                 @OA\Property(property="formatted_savings", type="string", example="KES 41,199.00")
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
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=404, description="Bundle not found")
     * )
     */
    public function getBreakdown(int $id): JsonResponse
    {
        $bundle = $this->bundleService->getById($id);
        $breakdown = $this->bundleService->getBreakdown($bundle);

        return ApiResponse::success(
            message: 'Bundle breakdown retrieved successfully',
            data: $breakdown
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/bundles/{id}/savings",
     *     summary="Calculate bundle savings",
     *     description="Calculates and returns the savings amount and percentage for a bundle compared to individual item prices.",
     *     tags={"Tenant Product Bundles"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         example=2
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Savings calculated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Savings calculated successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="individual_total", type="string", example="45999.00"),
     *                 @OA\Property(property="bundle_price", type="string", example="4800.00"),
     *                 @OA\Property(property="savings", type="string", example="41199.00"),
     *                 @OA\Property(property="savings_percentage", type="number", example=89.56),
     *                 @OA\Property(property="formatted_savings", type="string", example="KES 41,199.00")
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
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=404, description="Bundle not found")
     * )
     */
    public function calculateSavings(int $id): JsonResponse
    {
        $bundle = $this->bundleService->getById($id);

        return ApiResponse::success(
            message: 'Savings calculated successfully',
            data: [
                'individual_total' => $bundle->calculated_individual_price,
                'bundle_price' => $bundle->bundle_price,
                'savings' => $bundle->discount_amount,
                'savings_percentage' => $bundle->savings_percentage,
                'formatted_savings' => $bundle->formatted_discount,
            ]
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/tenant/bundles/{id}",
     *     summary="Delete bundle",
     *     description="Permanently deletes a product bundle. This action cannot be undone.",
     *     tags={"Tenant Product Bundles"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Bundle ID",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         example=2
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Bundle deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Bundle deleted successfully"),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-23T09:29:25.568067Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="3fb945bd-1205-456d-b3a7-81c7c8b78c75"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Bundle not found"
     *     )
     * )
     */
    public function destroy(int $id): JsonResponse
    {
        $bundle = $this->bundleService->getById($id);

        $this->bundleService->delete($bundle);

        return ApiResponse::success(message: 'Bundle deleted successfully');
    }
}
