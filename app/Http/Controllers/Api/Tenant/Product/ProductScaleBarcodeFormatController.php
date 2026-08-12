<?php

namespace App\Http\Controllers\Api\Tenant\Product;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Product\StoreProductScaleBarcodeFormatRequest;
use App\Http\Requests\Tenant\Product\UpdateProductScaleBarcodeFormatRequest;
use App\Http\Resources\Tenant\Product\ProductScaleBarcodeFormatResource;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant\ProductScaleBarcodeFormat;
use App\Services\Tenant\Product\ProductScaleBarcodeFormatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Schema(
 *     schema="ProductScaleBarcodeFormat",
 *
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="EAN13 weight scale"),
 *     @OA\Property(property="prefix", type="string", example="21"),
 *     @OA\Property(property="length", type="integer", example=13),
 *     @OA\Property(property="product_code_start", type="integer", example=2, description="Zero-based start offset for the embedded PLU/product code."),
 *     @OA\Property(property="product_code_length", type="integer", example=5),
 *     @OA\Property(property="value_start", type="integer", example=7, description="Zero-based start offset for embedded weight, price, or quantity."),
 *     @OA\Property(property="value_length", type="integer", example=5),
 *     @OA\Property(property="value_type", type="string", enum={"weight","price","quantity"}, example="weight"),
 *     @OA\Property(property="decimal_places", type="integer", example=3),
 *     @OA\Property(property="checksum", type="string", nullable=true, enum={"ean13"}, example="ean13"),
 *     @OA\Property(property="store_id", type="integer", nullable=true, example=null),
 *     @OA\Property(property="is_active", type="boolean", example=true),
 *     @OA\Property(property="priority", type="integer", example=10),
 *     @OA\Property(property="metadata", type="object"),
 *     @OA\Property(property="notes", type="string", nullable=true, example="Format used by deli weighing scale"),
 *     @OA\Property(property="created_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="updated_at", type="string", format="date-time", nullable=true)
 * )
 *
 * @OA\Schema(
 *     schema="ProductScaleBarcodeFormatRequest",
 *     required={"name","prefix","length","product_code_start","product_code_length","value_start","value_length","value_type","decimal_places"},
 *
 *     @OA\Property(property="name", type="string", maxLength=100, example="EAN13 weight scale"),
 *     @OA\Property(property="prefix", type="string", maxLength=20, pattern="^[0-9]+$", example="21"),
 *     @OA\Property(property="length", type="integer", minimum=1, maximum=50, example=13),
 *     @OA\Property(property="product_code_start", type="integer", minimum=0, example=2),
 *     @OA\Property(property="product_code_length", type="integer", minimum=1, example=5),
 *     @OA\Property(property="value_start", type="integer", minimum=0, example=7),
 *     @OA\Property(property="value_length", type="integer", minimum=1, example=5),
 *     @OA\Property(property="value_type", type="string", enum={"weight","price","quantity"}, example="weight"),
 *     @OA\Property(property="decimal_places", type="integer", minimum=0, maximum=6, example=3),
 *     @OA\Property(property="checksum", type="string", nullable=true, enum={"ean13"}, example="ean13"),
 *     @OA\Property(property="store_id", type="integer", nullable=true, example=null),
 *     @OA\Property(property="is_active", type="boolean", example=true),
 *     @OA\Property(property="priority", type="integer", minimum=0, maximum=65535, example=10),
 *     @OA\Property(property="metadata", type="object", nullable=true),
 *     @OA\Property(property="notes", type="string", nullable=true, maxLength=1000, example="Mobile parses after static barcode miss and resolves extracted PLU against SCALE barcodes.")
 * )
 *
 * @OA\Schema(
 *     schema="ProductScaleBarcodeFormatUpdateRequest",
 *
 *     @OA\Property(property="name", type="string", maxLength=100, example="EAN13 weight scale"),
 *     @OA\Property(property="prefix", type="string", maxLength=20, pattern="^[0-9]+$", example="21"),
 *     @OA\Property(property="length", type="integer", minimum=1, maximum=50, example=13),
 *     @OA\Property(property="product_code_start", type="integer", minimum=0, example=2),
 *     @OA\Property(property="product_code_length", type="integer", minimum=1, example=5),
 *     @OA\Property(property="value_start", type="integer", minimum=0, example=7),
 *     @OA\Property(property="value_length", type="integer", minimum=1, example=5),
 *     @OA\Property(property="value_type", type="string", enum={"weight","price","quantity"}, example="weight"),
 *     @OA\Property(property="decimal_places", type="integer", minimum=0, maximum=6, example=3),
 *     @OA\Property(property="checksum", type="string", nullable=true, enum={"ean13"}, example="ean13"),
 *     @OA\Property(property="store_id", type="integer", nullable=true, example=null),
 *     @OA\Property(property="is_active", type="boolean", example=true),
 *     @OA\Property(property="priority", type="integer", minimum=0, maximum=65535, example=10),
 *     @OA\Property(property="metadata", type="object", nullable=true),
 *     @OA\Property(property="notes", type="string", nullable=true, maxLength=1000, example="Mobile parses after static barcode miss and resolves extracted PLU against SCALE barcodes.")
 * )
 */
class ProductScaleBarcodeFormatController extends Controller
{
    public function __construct(
        private readonly ProductScaleBarcodeFormatService $service
    ) {}

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/product-scale-barcode-formats",
     *     summary="List scale barcode formats",
     *     description="Lists configured weighing-scale/price-embedded barcode parser formats. Read access is open to authenticated tenant users because POS/mobile also receives this data through catalog sync.",
     *     tags={"Tenant Product Barcodes"},
     *     security={{"sanctum": {}}},
     *
     *     @OA\Parameter(name="store_id", in="query", required=false, description="Filter to formats for a specific store.", @OA\Schema(type="integer"), example=1),
     *     @OA\Parameter(name="is_active", in="query", required=false, description="Filter active/inactive formats.", @OA\Schema(type="boolean"), example=true),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Scale barcode formats retrieved successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Scale barcode formats retrieved successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="formats", type="array", @OA\Items(ref="#/components/schemas/ProductScaleBarcodeFormat"))
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'store_id' => ['nullable', 'integer', 'exists:stores,id'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        return ApiResponse::success('Scale barcode formats retrieved successfully', [
            'formats' => ProductScaleBarcodeFormatResource::collection($this->service->list($filters)),
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/product-scale-barcode-formats",
     *     summary="Create scale barcode format",
     *     description="Creates a configurable parser format for dynamic weighing-scale/price-embedded barcodes. Requires manage-products permission. Segments must fit within length, product-code and value segments must not overlap, and checksum=ean13 requires length=13.",
     *     tags={"Tenant Product Barcodes"},
     *     security={{"sanctum": {}}},
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/ProductScaleBarcodeFormatRequest")),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Scale barcode format created successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Scale barcode format created successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="format", ref="#/components/schemas/ProductScaleBarcodeFormat")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(StoreProductScaleBarcodeFormatRequest $request): JsonResponse
    {
        return ApiResponse::created('Scale barcode format created successfully', [
            'format' => new ProductScaleBarcodeFormatResource($this->service->create($request->validated())),
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/product-scale-barcode-formats/{scaleBarcodeFormat}",
     *     summary="Show scale barcode format",
     *     description="Returns one configured scale barcode parser format. Read access is open to authenticated tenant users.",
     *     tags={"Tenant Product Barcodes"},
     *     security={{"sanctum": {}}},
     *
     *     @OA\Parameter(name="scaleBarcodeFormat", in="path", required=true, @OA\Schema(type="integer"), example=1),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Scale barcode format retrieved successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Scale barcode format retrieved successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="format", ref="#/components/schemas/ProductScaleBarcodeFormat")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=404, description="Format not found")
     * )
     */
    public function show(ProductScaleBarcodeFormat $scaleBarcodeFormat): JsonResponse
    {
        return ApiResponse::success('Scale barcode format retrieved successfully', [
            'format' => new ProductScaleBarcodeFormatResource($scaleBarcodeFormat),
        ]);
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/tenant/product-scale-barcode-formats/{scaleBarcodeFormat}",
     *     summary="Update scale barcode format",
     *     description="Updates a scale barcode parser format. Requires manage-products permission. Partial updates are validated against the existing format so final segment windows still fit and do not overlap.",
     *     tags={"Tenant Product Barcodes"},
     *     security={{"sanctum": {}}},
     *
     *     @OA\Parameter(name="scaleBarcodeFormat", in="path", required=true, @OA\Schema(type="integer"), example=1),
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/ProductScaleBarcodeFormatUpdateRequest")),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Scale barcode format updated successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Scale barcode format updated successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="format", ref="#/components/schemas/ProductScaleBarcodeFormat")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=404, description="Format not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function update(UpdateProductScaleBarcodeFormatRequest $request, ProductScaleBarcodeFormat $scaleBarcodeFormat): JsonResponse
    {
        return ApiResponse::success('Scale barcode format updated successfully', [
            'format' => new ProductScaleBarcodeFormatResource($this->service->update($scaleBarcodeFormat, $request->validated())),
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/tenant/product-scale-barcode-formats/{scaleBarcodeFormat}",
     *     summary="Deactivate scale barcode format",
     *     description="Deactivates a scale barcode parser format instead of hard deleting it, so mobile/offline clients receive the updated inactive state through catalog sync. Requires manage-products permission.",
     *     tags={"Tenant Product Barcodes"},
     *     security={{"sanctum": {}}},
     *
     *     @OA\Parameter(name="scaleBarcodeFormat", in="path", required=true, @OA\Schema(type="integer"), example=1),
     *
     *     @OA\Response(response=200, description="Scale barcode format deactivated successfully"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=404, description="Format not found")
     * )
     */
    public function destroy(ProductScaleBarcodeFormat $scaleBarcodeFormat): JsonResponse
    {
        return ApiResponse::success('Scale barcode format deactivated successfully', [
            'format' => new ProductScaleBarcodeFormatResource($this->service->deactivate($scaleBarcodeFormat)),
        ]);
    }
}
