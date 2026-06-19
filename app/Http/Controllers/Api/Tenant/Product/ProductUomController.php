<?php

namespace App\Http\Controllers\Api\Tenant\Product;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Product\StoreProductUomRequest;
use App\Http\Requests\Tenant\Product\UpdateProductUomRequest;
use App\Http\Resources\Tenant\Product\ProductUomResource;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant\ProductUom;
use App\Services\Tenant\Product\ProductService;
use App\Services\Tenant\Product\ProductUomService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductUomController extends Controller
{
    public function __construct(
        private ProductUomService $productUomService,
        private ProductService $productService
    ) {}

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/products/{uuid}/uoms",
     *     summary="Get all product UOMs",
     *     description="Retrieves all Units of Measure (UOMs) configured for a specific product, including base UOM identification and conversion factors.",
     *     tags={"Tenant Product UOMs"},
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
     *         description="Product UOMs retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Product UOMs retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="product",
     *                     type="object",
     *                     @OA\Property(property="uuid", type="string", format="uuid", example="67b466f5-8b6d-4122-af5d-1683d1dd7a72"),
     *                     @OA\Property(property="name", type="string", example="TCL 55 4K UHD Smart LED TV"),
     *                     @OA\Property(property="sku", type="string", example="ELEC-DELL-56QT")
     *                 ),
     *                 @OA\Property(
     *                     property="uoms",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=2),
     *                         @OA\Property(property="product_id", type="integer", example=4),
     *                         @OA\Property(
     *                             property="uom",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=2),
     *                             @OA\Property(property="code", type="string", example="pair"),
     *                             @OA\Property(property="name", type="string", example="Pair"),
     *                             @OA\Property(property="type", type="string", example="count"),
     *                             @OA\Property(property="source_type", type="string", example="system"),
     *                             @OA\Property(property="source_type_label", type="string", example="System Defined"),
     *                             @OA\Property(property="is_base_unit", type="boolean", example=false),
     *                             @OA\Property(property="is_active", type="boolean", example=true),
     *                             @OA\Property(property="is_system", type="boolean", example=true),
     *                             @OA\Property(property="is_custom", type="boolean", example=false),
     *                             @OA\Property(property="description", type="string", example="Two pieces"),
     *                             @OA\Property(property="display_name", type="string", example="Pair (pair)"),
     *                             @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-19T13:40:21.000000Z"),
     *                             @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-19T16:12:20.000000Z")
     *                         ),
     *                         @OA\Property(property="uom_id", type="integer", example=2),
     *                         @OA\Property(property="is_base_uom", type="boolean", example=true),
     *                         @OA\Property(property="is_purchase_uom", type="boolean", example=true),
     *                         @OA\Property(property="is_sales_uom", type="boolean", example=true),
     *                         @OA\Property(property="is_inventory_uom", type="boolean", example=true),
     *                         @OA\Property(property="conversion_to_base", type="string", example="1.000000"),
     *                         @OA\Property(property="conversion_description", type="string", example="Base Unit"),
     *                         @OA\Property(property="display_name", type="string", example="Pair (pair)"),
     *                         @OA\Property(property="can_purchase", type="boolean", example=true),
     *                         @OA\Property(property="can_sell", type="boolean", example=true),
     *                         @OA\Property(property="can_track_inventory", type="boolean", example=true),
     *                         @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-22T13:13:04.000000Z"),
     *                         @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-22T13:13:04.000000Z")
     *                     )
     *                 ),
     *                 @OA\Property(property="total", type="integer", example=2, description="Total number of UOMs configured"),
     *                 @OA\Property(property="has_base_uom", type="boolean", example=true, description="Whether a base UOM is configured")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-22T13:14:44.792928Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="f90165ea-9ec6-4246-987c-31e24754123a"),
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
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-22T13:14:44.792928Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="f90165ea-9ec6-4246-987c-31e24754123a"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     )
     * )
     */
    public function index(string $productUuid): JsonResponse
    {
        $product = $this->productService->getByUuid($productUuid);

        $productUoms = $this->productUomService->list($product);

        return ApiResponse::success(
            message: 'Product UOMs retrieved successfully',
            data: [
                'product' => [
                    'uuid' => $product->uuid,
                    'name' => $product->name,
                    'sku' => $product->sku,
                ],
                'uoms' => ProductUomResource::collection($productUoms),
                'total' => $productUoms->count(),
                'has_base_uom' => $productUoms->where('is_base_uom', true)->isNotEmpty(),
            ]
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/products/{uuid}/uoms",
     *     summary="Add UOM to product",
     *     description="Adds a new Unit of Measure configuration to a product. Can be set as base UOM, purchase UOM, sales UOM, or inventory UOM with conversion factor.",
     *     tags={"Tenant Product UOMs"},
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
     *             required={"uom_id"},
     *             @OA\Property(property="uom_id", type="integer", example=3, description="Unit of Measure ID"),
     *             @OA\Property(property="is_base_uom", type="boolean", example=true, description="Set as base UOM (if true, conversion_to_base must be 1)"),
     *             @OA\Property(property="is_purchase_uom", type="boolean", example=true, description="Enable for purchase transactions"),
     *             @OA\Property(property="is_sales_uom", type="boolean", example=true, description="Enable for sales transactions"),
     *             @OA\Property(property="is_inventory_uom", type="boolean", example=true, description="Enable for inventory tracking"),
     *             @OA\Property(property="conversion_to_base", type="number", format="decimal", example=1, description="Conversion factor to base UOM (must be 1 for base UOM)")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Product UOM created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Product UOM created successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="product_id", type="integer", example=4),
     *                 @OA\Property(
     *                     property="uom",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=3),
     *                     @OA\Property(property="code", type="string", example="doz"),
     *                     @OA\Property(property="name", type="string", example="Dozen"),
     *                     @OA\Property(property="type", type="string", example="count"),
     *                     @OA\Property(property="source_type", type="string", example="system"),
     *                     @OA\Property(property="source_type_label", type="string", example="System Defined"),
     *                     @OA\Property(property="is_base_unit", type="boolean", example=false),
     *                     @OA\Property(property="is_active", type="boolean", example=true),
     *                     @OA\Property(property="is_system", type="boolean", example=true),
     *                     @OA\Property(property="is_custom", type="boolean", example=false),
     *                     @OA\Property(property="description", type="string", example="12 pieces"),
     *                     @OA\Property(property="display_name", type="string", example="Dozen (doz)"),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-19T13:40:21.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-19T13:40:21.000000Z")
     *                 ),
     *                 @OA\Property(property="uom_id", type="integer", example=3),
     *                 @OA\Property(property="is_base_uom", type="boolean", example=true),
     *                 @OA\Property(property="is_purchase_uom", type="boolean", example=true),
     *                 @OA\Property(property="is_sales_uom", type="boolean", example=true),
     *                 @OA\Property(property="is_inventory_uom", type="boolean", example=true),
     *                 @OA\Property(property="conversion_to_base", type="string", example="1.000000"),
     *                 @OA\Property(property="conversion_description", type="string", example="Base Unit"),
     *                 @OA\Property(property="display_name", type="string", example="Dozen (doz)"),
     *                 @OA\Property(property="can_purchase", type="boolean", example=true),
     *                 @OA\Property(property="can_sell", type="boolean", example=true),
     *                 @OA\Property(property="can_track_inventory", type="boolean", example=true),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-22T12:49:49.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-22T12:49:49.000000Z")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-22T12:49:49.238996Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="71477b45-af6c-4699-958b-374b9da02f54"),
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
     *                     property="uom_id",
     *                     type="array",
     *                     @OA\Items(type="string", example="The uom id field is required.")
     *                 ),
     *                 @OA\Property(
     *                     property="conversion_to_base",
     *                     type="array",
     *                     @OA\Items(type="string", example="Base UOM must have conversion factor of 1")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-22T12:49:49.238996Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="71477b45-af6c-4699-958b-374b9da02f54"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     )
     * )
     */
    public function store(
        StoreProductUomRequest $request,
        string $productUuid
    ): JsonResponse {
        $product = $this->productService->getByUuid($productUuid);

        $productUom = $this->productUomService->create(
            $product,
            $request->validated()
        );

        return ApiResponse::created(
            message: 'Product UOM created successfully',
            data: new ProductUomResource($productUom)
        );
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/tenant/products/{uuid}/uoms/{id}",
     *     summary="Update product UOM configuration",
     *     description="Updates an existing UOM configuration for a product. Cannot change conversion factor of base UOM (must remain 1). All fields are optional.",
     *     tags={"Tenant Product UOMs"},
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
     *         name="id",
     *         in="path",
     *         description="Product UOM ID",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         example=1
     *     ),
     *     @OA\RequestBody(
     *         required=false,
     *         description="UOM configuration fields to update (all fields optional)",
     *         @OA\JsonContent(
     *             @OA\Property(property="uom_id", type="integer", example=20, description="Change to different UOM"),
     *             @OA\Property(property="is_purchase_uom", type="boolean", example=true, description="Enable/disable for purchases"),
     *             @OA\Property(property="is_sales_uom", type="boolean", example=false, description="Enable/disable for sales"),
     *             @OA\Property(property="is_inventory_uom", type="boolean", example=true, description="Enable/disable for inventory"),
     *             @OA\Property(property="conversion_to_base", type="number", format="decimal", example=50, description="Conversion factor (cannot be changed for base UOM)")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Product UOM updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Product UOM updated successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="product_id", type="integer", example=4),
     *                 @OA\Property(
     *                     property="uom",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=3),
     *                     @OA\Property(property="code", type="string", example="doz"),
     *                     @OA\Property(property="name", type="string", example="Dozen"),
     *                     @OA\Property(property="type", type="string", example="count"),
     *                     @OA\Property(property="source_type", type="string", example="system"),
     *                     @OA\Property(property="source_type_label", type="string", example="System Defined"),
     *                     @OA\Property(property="is_base_unit", type="boolean", example=false),
     *                     @OA\Property(property="is_active", type="boolean", example=true),
     *                     @OA\Property(property="is_system", type="boolean", example=true),
     *                     @OA\Property(property="is_custom", type="boolean", example=false),
     *                     @OA\Property(property="description", type="string", example="12 pieces"),
     *                     @OA\Property(property="display_name", type="string", example="Dozen (doz)"),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-19T13:40:21.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-19T13:40:21.000000Z")
     *                 ),
     *                 @OA\Property(property="uom_id", type="integer", example=3),
     *                 @OA\Property(property="is_base_uom", type="boolean", example=true),
     *                 @OA\Property(property="is_purchase_uom", type="boolean", example=true),
     *                 @OA\Property(property="is_sales_uom", type="boolean", example=true),
     *                 @OA\Property(property="is_inventory_uom", type="boolean", example=true),
     *                 @OA\Property(property="conversion_to_base", type="string", example="1.000000"),
     *                 @OA\Property(property="conversion_description", type="string", example="Base Unit"),
     *                 @OA\Property(property="display_name", type="string", example="Dozen (doz)"),
     *                 @OA\Property(property="can_purchase", type="boolean", example=true),
     *                 @OA\Property(property="can_sell", type="boolean", example=true),
     *                 @OA\Property(property="can_track_inventory", type="boolean", example=true),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-22T12:49:49.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-22T13:12:23.000000Z")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-22T13:12:23.138674Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="f2bcf937-51dc-4582-a9c7-7dfa95f8b830"),
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
     *         description="Product or Product UOM not found"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error - Cannot change base UOM conversion factor",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="conversion_to_base",
     *                     type="array",
     *                     @OA\Items(type="string", example="Cannot change conversion factor of base UOM. It must always be 1")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-22T13:11:55.970774Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="f95c065f-30a8-4468-be3b-cb55c17979ad"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     )
     * )
     */
    public function update(
        UpdateProductUomRequest $request,
        string $productUuid,
        int $productUom
    ): JsonResponse {
        $product = $this->productService->getByUuid($productUuid);

        $productUom = ProductUom::where('id', $productUom)
            ->where('product_id', $product->id)
            ->firstOrFail();

        $updatedProductUom = $this->productUomService->update(
            $productUom,
            $request->validated()
        );

        return ApiResponse::success(
            message: 'Product UOM updated successfully',
            data: new ProductUomResource($updatedProductUom)
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/tenant/products/{uuid}/uoms/{id}",
     *     summary="Delete product UOM",
     *     description="Deletes a UOM configuration from a product. Cannot delete the base UOM - you must assign a different base UOM first before deleting the current one.",
     *     tags={"Tenant Product UOMs"},
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
     *         name="id",
     *         in="path",
     *         description="Product UOM ID",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         example=1
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Product UOM deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Product UOM deleted successfully"),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-22T13:26:10.193031Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="844c0e41-60a7-47f2-b67f-91dde3705764"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad Request - Cannot delete base UOM",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Cannot delete the base UOM. Assign a different base UOM first."),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-22T13:10:04.112936Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="297c483e-ea9c-43ae-be1b-b8ca3db36ca0"),
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
     *         description="Product or Product UOM not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Product UOM not found"),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-22T13:26:10.193031Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="844c0e41-60a7-47f2-b67f-91dde3705764"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     )
     * )
     */
    public function destroy(
        string $productUuid,
        int $productUomId
    ): JsonResponse {
        $product = $this->productService->getByUuid($productUuid);

        $productUom = ProductUom::where('id', $productUomId)
            ->where('product_id', $product->id)
            ->firstOrFail();

        try {
            $this->productUomService->delete($productUom);

            return ApiResponse::success(
                message: 'Product UOM deleted successfully'
            );
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error(
                message: $e->getMessage(),
                status: 400
            );
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/products/{uuid}/uoms/base",
     *     summary="Get product base UOM",
     *     description="Retrieves the base Unit of Measure configured for a product. The base UOM has a conversion factor of 1 and is used as the reference for all other UOMs.",
     *     tags={"Tenant Product UOMs"},
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
     *         description="Base UOM retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Base UOM retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=2),
     *                 @OA\Property(property="product_id", type="integer", example=4),
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
     *                 @OA\Property(property="is_base_uom", type="boolean", example=true),
     *                 @OA\Property(property="is_purchase_uom", type="boolean", example=true),
     *                 @OA\Property(property="is_sales_uom", type="boolean", example=true),
     *                 @OA\Property(property="is_inventory_uom", type="boolean", example=true),
     *                 @OA\Property(property="conversion_to_base", type="string", example="1.000000"),
     *                 @OA\Property(property="conversion_description", type="string", example="Base Unit"),
     *                 @OA\Property(property="display_name", type="string", example="Pair (pair)"),
     *                 @OA\Property(property="can_purchase", type="boolean", example=true),
     *                 @OA\Property(property="can_sell", type="boolean", example=true),
     *                 @OA\Property(property="can_track_inventory", type="boolean", example=true),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-22T13:13:04.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-22T13:13:04.000000Z")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-22T13:17:56.228335Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="96c1e386-361a-49dc-96ab-be3312d9c699"),
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
     *         description="Product not found or no base UOM configured",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Base UOM not found for this product"),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-22T13:17:56.228335Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="96c1e386-361a-49dc-96ab-be3312d9c699"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     )
     * )
     */
    public function base(string $productUuid): JsonResponse
    {
        $product = $this->productService->getByUuid($productUuid);

        $baseUom = $this->productUomService->getBaseUom($product);

        if (!$baseUom) {
            return ApiResponse::notFound(
                message: 'No base UOM configured for this product'
            );
        }

        return ApiResponse::success(
            message: 'Base UOM retrieved successfully',
            data: new ProductUomResource($baseUom)
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/products/{uuid}/uoms/purchase",
     *     summary="Get product purchase UOMs",
     *     description="Retrieves all Units of Measure that are enabled for purchase transactions for a specific product. These UOMs can be used when creating purchase orders.",
     *     tags={"Tenant Product UOMs"},
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
     *         description="Purchase UOMs retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Purchase UOMs retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=2),
     *                     @OA\Property(property="product_id", type="integer", example=4),
     *                     @OA\Property(
     *                         property="uom",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=2),
     *                         @OA\Property(property="code", type="string", example="pair"),
     *                         @OA\Property(property="name", type="string", example="Pair"),
     *                         @OA\Property(property="type", type="string", example="count"),
     *                         @OA\Property(property="source_type", type="string", example="system"),
     *                         @OA\Property(property="source_type_label", type="string", example="System Defined"),
     *                         @OA\Property(property="is_base_unit", type="boolean", example=false),
     *                         @OA\Property(property="is_active", type="boolean", example=true),
     *                         @OA\Property(property="is_system", type="boolean", example=true),
     *                         @OA\Property(property="is_custom", type="boolean", example=false),
     *                         @OA\Property(property="description", type="string", example="Two pieces"),
     *                         @OA\Property(property="display_name", type="string", example="Pair (pair)"),
     *                         @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-19T13:40:21.000000Z"),
     *                         @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-19T16:12:20.000000Z")
     *                     ),
     *                     @OA\Property(property="uom_id", type="integer", example=2),
     *                     @OA\Property(property="is_base_uom", type="boolean", example=true),
     *                     @OA\Property(property="is_purchase_uom", type="boolean", example=true),
     *                     @OA\Property(property="is_sales_uom", type="boolean", example=true),
     *                     @OA\Property(property="is_inventory_uom", type="boolean", example=true),
     *                     @OA\Property(property="conversion_to_base", type="string", example="1.000000"),
     *                     @OA\Property(property="conversion_description", type="string", example="Base Unit"),
     *                     @OA\Property(property="display_name", type="string", example="Pair (pair)"),
     *                     @OA\Property(property="can_purchase", type="boolean", example=true),
     *                     @OA\Property(property="can_sell", type="boolean", example=true),
     *                     @OA\Property(property="can_track_inventory", type="boolean", example=true),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-22T13:13:04.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-22T13:13:04.000000Z")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-22T13:19:14.883223Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="6a2481d6-93fc-4a8c-b5bb-0843bdec1989"),
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
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-22T13:19:14.883223Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="6a2481d6-93fc-4a8c-b5bb-0843bdec1989"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     )
     * )
     */
    public function purchase(string $productUuid): JsonResponse
    {
        $product = $this->productService->getByUuid($productUuid);

        $purchaseUoms = $this->productUomService->getPurchaseUoms($product);

        return ApiResponse::success(
            message: 'Purchase UOMs retrieved successfully',
            data: ProductUomResource::collection($purchaseUoms)
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/products/{uuid}/uoms/sales",
     *     summary="Get product sales UOMs",
     *     description="Retrieves all Units of Measure that are enabled for sales transactions for a specific product. These UOMs can be used when creating sales orders and invoices.",
     *     tags={"Tenant Product UOMs"},
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
     *         description="Sales UOMs retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Sales UOMs retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=2),
     *                     @OA\Property(property="product_id", type="integer", example=4),
     *                     @OA\Property(
     *                         property="uom",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=2),
     *                         @OA\Property(property="code", type="string", example="pair"),
     *                         @OA\Property(property="name", type="string", example="Pair"),
     *                         @OA\Property(property="type", type="string", example="count"),
     *                         @OA\Property(property="source_type", type="string", example="system"),
     *                         @OA\Property(property="source_type_label", type="string", example="System Defined"),
     *                         @OA\Property(property="is_base_unit", type="boolean", example=false),
     *                         @OA\Property(property="is_active", type="boolean", example=true),
     *                         @OA\Property(property="is_system", type="boolean", example=true),
     *                         @OA\Property(property="is_custom", type="boolean", example=false),
     *                         @OA\Property(property="description", type="string", example="Two pieces"),
     *                         @OA\Property(property="display_name", type="string", example="Pair (pair)"),
     *                         @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-19T13:40:21.000000Z"),
     *                         @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-19T16:12:20.000000Z")
     *                     ),
     *                     @OA\Property(property="uom_id", type="integer", example=2),
     *                     @OA\Property(property="is_base_uom", type="boolean", example=true),
     *                     @OA\Property(property="is_purchase_uom", type="boolean", example=true),
     *                     @OA\Property(property="is_sales_uom", type="boolean", example=true),
     *                     @OA\Property(property="is_inventory_uom", type="boolean", example=true),
     *                     @OA\Property(property="conversion_to_base", type="string", example="1.000000"),
     *                     @OA\Property(property="conversion_description", type="string", example="Base Unit"),
     *                     @OA\Property(property="display_name", type="string", example="Pair (pair)"),
     *                     @OA\Property(property="can_purchase", type="boolean", example=true),
     *                     @OA\Property(property="can_sell", type="boolean", example=true),
     *                     @OA\Property(property="can_track_inventory", type="boolean", example=true),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-22T13:13:04.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-22T13:13:04.000000Z")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-22T13:22:42.836084Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="e14eaa12-8f37-4e9c-80d8-f3093ff0daad"),
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
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-22T13:22:42.836084Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="e14eaa12-8f37-4e9c-80d8-f3093ff0daad"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     )
     * )
     */
    public function sales(string $productUuid): JsonResponse
    {
        $product = $this->productService->getByUuid($productUuid);

        $salesUoms = $this->productUomService->getSalesUoms($product);

        return ApiResponse::success(
            message: 'Sales UOMs retrieved successfully',
            data: ProductUomResource::collection($salesUoms)
        );
    }
}
