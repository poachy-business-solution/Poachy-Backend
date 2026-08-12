<?php

namespace App\Http\Controllers\Api\Tenant\Product;

use App\Exceptions\Tenant\ProductBarcodeLookupConflictException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Product\ImportProductBarcodesRequest;
use App\Http\Requests\Tenant\Product\LookupProductBarcodeRequest;
use App\Http\Requests\Tenant\Product\ReviewProductBarcodeSuggestionRequest;
use App\Http\Requests\Tenant\Product\StoreProductBarcodeRequest;
use App\Http\Requests\Tenant\Product\StoreProductBarcodeSuggestionRequest;
use App\Http\Requests\Tenant\Product\WorkflowProductBarcodeRequest;
use App\Http\Resources\Tenant\Product\ProductBarcodeResource;
use App\Http\Resources\Tenant\Product\ProductBarcodeSuggestionResource;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant\Product;
use App\Models\Tenant\ProductBarcode;
use App\Models\Tenant\ProductBarcodeSuggestion;
use App\Models\Tenant\ProductUom;
use App\Models\Tenant\ProductVariant;
use App\Services\Tenant\Product\ProductBarcodeService;
use App\Services\Tenant\Product\ProductBarcodeSuggestionService;
use Illuminate\Http\JsonResponse;

/**
 * @OA\Schema(
 *     schema="ProductBarcodeStoreRequest",
 *     required={"barcode"},
 *
 *     @OA\Property(property="barcode", type="string", maxLength=50, example="6161234567890"),
 *     @OA\Property(property="barcode_type", type="string", enum={"EAN-13","EAN-8","UPC-A","UPC-E","CODE-128","CODE-39","QR","INTERNAL","SCALE"}, example="EAN-13"),
 *     @OA\Property(property="is_primary", type="boolean", example=true),
 *     @OA\Property(property="is_active", type="boolean", example=true),
 *     @OA\Property(property="supplier_id", type="integer", nullable=true, example=null),
 *     @OA\Property(property="region", type="string", nullable=true, example="KE"),
 *     @OA\Property(property="store_id", type="integer", nullable=true, example=1),
 *     @OA\Property(property="valid_from", type="string", format="date", nullable=true, example="2026-01-01"),
 *     @OA\Property(property="valid_until", type="string", format="date", nullable=true, example="2026-12-31"),
 *     @OA\Property(property="source", type="string", enum={"manufacturer","manual","generated","supplier","imported","scale"}, example="manual"),
 *     @OA\Property(property="metadata", type="object", nullable=true),
 *     @OA\Property(property="notes", type="string", nullable=true, example="Manufacturer pack barcode")
 * )
 *
 * @OA\Schema(
 *     schema="ProductBarcodeWorkflowRequest",
 *     required={"target_type", "barcode"},
 *
 *     @OA\Property(property="target_type", type="string", enum={"product","variant","product_uom"}, example="product"),
 *     @OA\Property(property="product_uuid", type="string", format="uuid", nullable=true, example="67b466f5-8b6d-4122-af5d-1683d1dd7a72"),
 *     @OA\Property(property="variant_id", type="integer", nullable=true, example=12),
 *     @OA\Property(property="product_uom_id", type="integer", nullable=true, example=8),
 *     @OA\Property(property="barcode", type="string", maxLength=50, example="6161234567890"),
 *     @OA\Property(property="barcode_type", type="string", enum={"EAN-13","EAN-8","UPC-A","UPC-E","CODE-128","CODE-39","QR","INTERNAL","SCALE"}, example="EAN-13"),
 *     @OA\Property(property="is_primary", type="boolean", example=true),
 *     @OA\Property(property="supplier_id", type="integer", nullable=true, example=null),
 *     @OA\Property(property="store_id", type="integer", nullable=true, example=1),
 *     @OA\Property(property="region", type="string", nullable=true, example="KE"),
 *     @OA\Property(property="metadata", type="object", nullable=true),
 *     @OA\Property(property="notes", type="string", nullable=true, example="Captured during POS setup")
 * )
 */
class ProductBarcodeController extends Controller
{
    public function __construct(
        private ProductBarcodeService $barcodeService,
        private ProductBarcodeSuggestionService $suggestionService
    ) {}

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/product-barcodes/lookup",
     *     summary="Lookup a sellable item by barcode",
     *     description="Resolves an active, currently-valid barcode to a product, product variant, or product UOM. Exact static barcode matches are attempted first. If none is found, configured weighing-scale/price-embedded barcode formats are parsed and the extracted PLU is resolved as a SCALE barcode. When store_id is supplied, a matching store-specific barcode takes precedence over a global barcode and the response includes store price/availability context.",
     *     tags={"Tenant Product Barcodes"},
     *     security={{"sanctum": {}}},
     *
     *     @OA\Parameter(
     *         name="barcode",
     *         in="query",
     *         required=true,
     *         description="Barcode value scanned by POS/mobile. Leading zeroes are preserved; the backend trims surrounding whitespace only.",
     *
     *         @OA\Schema(type="string", maxLength=50),
     *         example="6161234567890"
     *     ),
     *
     *     @OA\Parameter(
     *         name="store_id",
     *         in="query",
     *         required=false,
     *         description="Optional store context used to prefer store-specific barcode mappings and return store availability/price.",
     *
     *         @OA\Schema(type="integer"),
     *         example=1
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Barcode resolved successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Barcode resolved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="barcode",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=10),
     *                     @OA\Property(property="barcode", type="string", example="6161234567890"),
     *                     @OA\Property(property="barcode_type", type="string", example="EAN-13"),
     *                     @OA\Property(property="is_primary", type="boolean", example=true),
     *                     @OA\Property(property="is_active", type="boolean", example=true),
     *                     @OA\Property(property="store_id", type="integer", nullable=true, example=1),
     *                     @OA\Property(property="source", type="string", example="manufacturer"),
     *                     @OA\Property(
     *                         property="barcodeable",
     *                         type="object",
     *                         @OA\Property(property="type", type="string", enum={"product", "variant", "product_uom"}, example="product"),
     *                         @OA\Property(property="id", type="integer", example=4),
     *                         @OA\Property(property="uuid", type="string", nullable=true, example="67b466f5-8b6d-4122-af5d-1683d1dd7a72"),
     *                         @OA\Property(property="name", type="string", nullable=true, example="Maize Flour 2kg"),
     *                         @OA\Property(property="sku", type="string", nullable=true, example="GROC-MAIZ-2KG")
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="store_context",
     *                     type="object",
     *                     nullable=true,
     *                     @OA\Property(property="store_id", type="integer", example=1),
     *                     @OA\Property(property="is_available", type="boolean", example=true),
     *                     @OA\Property(property="store_selling_price", type="string", nullable=true, example="155.00"),
     *                     @OA\Property(property="min_stock_level", type="integer", nullable=true, example=5)
     *                 ),
     *                 @OA\Property(
     *                     property="sale_line",
     *                     type="object",
     *                     description="POS-ready sale line defaults derived from the resolved barcode target.",
     *                     @OA\Property(property="target_type", type="string", enum={"product","variant","product_uom"}, example="product_uom"),
     *                     @OA\Property(property="product_id", type="integer", example=4),
     *                     @OA\Property(property="variant_id", type="integer", nullable=true, example=null),
     *                     @OA\Property(property="product_uom_id", type="integer", nullable=true, example=9),
     *                     @OA\Property(property="uom_id", type="integer", example=2),
     *                     @OA\Property(property="uom_code", type="string", example="ctn"),
     *                     @OA\Property(property="quantity", type="number", format="float", example=1),
     *                     @OA\Property(property="quantity_in_base_uom", type="number", format="float", example=12),
     *                     @OA\Property(
     *                         property="sale_item_payload",
     *                         type="object",
     *                         @OA\Property(property="product_id", type="integer", example=4),
     *                         @OA\Property(property="variant_id", type="integer", nullable=true, example=null),
     *                         @OA\Property(property="bundle_id", type="integer", nullable=true, example=null),
     *                         @OA\Property(property="uom_id", type="integer", example=2),
     *                         @OA\Property(property="quantity", type="number", format="float", example=1)
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="scale_barcode",
     *                     type="object",
     *                     nullable=true,
     *                     description="Present only when the scanned value was parsed using a configured scale barcode format.",
     *                     @OA\Property(property="format_id", type="integer", example=1),
     *                     @OA\Property(property="format_name", type="string", example="EAN13 weight scale"),
     *                     @OA\Property(property="raw_barcode", type="string", example="2101234007501"),
     *                     @OA\Property(property="plu", type="string", example="01234"),
     *                     @OA\Property(property="value_type", type="string", enum={"weight","price","quantity"}, example="weight"),
     *                     @OA\Property(property="raw_value", type="string", example="00750"),
     *                     @OA\Property(property="value", type="number", format="float", example=0.75),
     *                     @OA\Property(property="decimal_places", type="integer", example=3),
     *                     @OA\Property(property="checksum", type="string", nullable=true, example="ean13"),
     *                     @OA\Property(property="store_id", type="integer", nullable=true, example=null)
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=404, description="Barcode not found, inactive, expired, or attached to an inactive sellable item"),
     *     @OA\Response(response=409, description="Multiple active barcode mappings matched this lookup"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function lookup(LookupProductBarcodeRequest $request): JsonResponse
    {
        try {
            $lookup = $this->barcodeService->lookupResult(
                $request->validated('barcode'),
                $request->validated('store_id')
            );
        } catch (ProductBarcodeLookupConflictException $exception) {
            return ApiResponse::conflict($exception->getMessage());
        }

        $barcode = $lookup['barcode'];
        $scaleBarcode = $lookup['scale_barcode'];

        return ApiResponse::success('Barcode resolved successfully', [
            'barcode' => new ProductBarcodeResource($barcode),
            'store_context' => $this->barcodeService->storeContext($barcode, $request->validated('store_id')),
            'sale_line' => $this->barcodeService->saleLine($barcode, $scaleBarcode, $request->validated('store_id')),
            'scale_barcode' => $scaleBarcode,
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/product-barcodes/suggestions",
     *     summary="Submit unknown barcode attachment suggestion",
     *     description="Allows any authenticated POS/mobile user to submit an unknown scanned barcode as a pending suggestion against an existing product, variant, or product UOM. The suggestion does not affect live barcode lookup or offline catalog sync until a manage-products user approves it.",
     *     tags={"Tenant Product Barcodes"},
     *     security={{"sanctum": {}}},
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/ProductBarcodeWorkflowRequest")),
     *
     *     @OA\Response(response=201, description="Barcode suggestion submitted successfully"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=404, description="Target product, variant, or product UOM not found"),
     *     @OA\Response(response=409, description="A pending suggestion already exists for this barcode context"),
     *     @OA\Response(response=422, description="Validation error or active barcode already exists")
     * )
     */
    public function suggest(StoreProductBarcodeSuggestionRequest $request): JsonResponse
    {
        $suggestion = $this->suggestionService->suggest(
            $request->validated(),
            $request->user()->id
        );

        return ApiResponse::created('Barcode suggestion submitted successfully', [
            'suggestion' => new ProductBarcodeSuggestionResource($suggestion),
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/product-barcodes/suggestions/pending",
     *     summary="List pending barcode suggestions",
     *     description="Returns unknown barcode attachment suggestions waiting for manager/admin review. Requires manage-products permission.",
     *     tags={"Tenant Product Barcodes"},
     *     security={{"sanctum": {}}},
     *
     *     @OA\Response(response=200, description="Pending barcode suggestions retrieved successfully"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function pendingSuggestions(): JsonResponse
    {
        return ApiResponse::success('Pending barcode suggestions retrieved successfully', [
            'suggestions' => ProductBarcodeSuggestionResource::collection($this->suggestionService->pending()),
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/product-barcodes/suggestions/{suggestion}/approve",
     *     summary="Approve barcode suggestion",
     *     description="Approves a pending unknown-barcode suggestion and creates an active product barcode mapping. Requires manage-products permission.",
     *     tags={"Tenant Product Barcodes"},
     *     security={{"sanctum": {}}},
     *
     *     @OA\Parameter(name="suggestion", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\RequestBody(required=false, @OA\JsonContent(
     *
     *         @OA\Property(property="is_primary", type="boolean", nullable=true, example=false),
     *         @OA\Property(property="notes", type="string", nullable=true, example="Approved after manager verified package")
     *     )),
     *
     *     @OA\Response(response=200, description="Barcode suggestion approved successfully"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=404, description="Suggestion not found"),
     *     @OA\Response(response=422, description="Suggestion is not pending or active barcode already exists")
     * )
     */
    public function approveSuggestion(ReviewProductBarcodeSuggestionRequest $request, ProductBarcodeSuggestion $suggestion): JsonResponse
    {
        $result = $this->suggestionService->approve(
            $suggestion,
            $request->validated(),
            $request->user()->id
        );

        return ApiResponse::success('Barcode suggestion approved successfully', [
            'suggestion' => new ProductBarcodeSuggestionResource($result['suggestion']),
            'barcode' => new ProductBarcodeResource($result['barcode']),
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/product-barcodes/suggestions/{suggestion}/reject",
     *     summary="Reject barcode suggestion",
     *     description="Rejects a pending unknown-barcode suggestion without creating a live barcode mapping. Requires manage-products permission.",
     *     tags={"Tenant Product Barcodes"},
     *     security={{"sanctum": {}}},
     *
     *     @OA\Parameter(name="suggestion", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"rejection_reason"},
     *
     *         @OA\Property(property="rejection_reason", type="string", example="Barcode belongs to a different product")
     *     )),
     *
     *     @OA\Response(response=200, description="Barcode suggestion rejected successfully"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=404, description="Suggestion not found"),
     *     @OA\Response(response=422, description="Suggestion is not pending or rejection reason is missing")
     * )
     */
    public function rejectSuggestion(ReviewProductBarcodeSuggestionRequest $request, ProductBarcodeSuggestion $suggestion): JsonResponse
    {
        $suggestion = $this->suggestionService->reject(
            $suggestion,
            $request->validated(),
            $request->user()->id
        );

        return ApiResponse::success('Barcode suggestion rejected successfully', [
            'suggestion' => new ProductBarcodeSuggestionResource($suggestion),
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/product-barcodes/manual",
     *     summary="Create a manually entered barcode",
     *     description="Creates a barcode from an admin-entered value and records metadata.workflow=manual_entry. Requires manage-products permission.",
     *     tags={"Tenant Product Barcodes"},
     *     security={{"sanctum": {}}},
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/ProductBarcodeWorkflowRequest")),
     *
     *     @OA\Response(response=201, description="Manual barcode created successfully"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=404, description="Target product, variant, or product UOM not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function manual(WorkflowProductBarcodeRequest $request): JsonResponse
    {
        $barcode = $this->barcodeService->createManual($request->validated());

        return ApiResponse::created('Manual barcode created successfully', [
            'barcode' => new ProductBarcodeResource($barcode),
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/product-barcodes/scanned",
     *     summary="Attach a scanned barcode",
     *     description="Creates a barcode mapping from a cashier/mobile scan and records metadata.workflow=scanned_attachment. This supports the unknown barcode -> attach to existing product flow.",
     *     tags={"Tenant Product Barcodes"},
     *     security={{"sanctum": {}}},
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/ProductBarcodeWorkflowRequest")),
     *
     *     @OA\Response(response=201, description="Scanned barcode attached successfully"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=404, description="Target product, variant, or product UOM not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function scanned(WorkflowProductBarcodeRequest $request): JsonResponse
    {
        $barcode = $this->barcodeService->createScanned($request->validated());

        return ApiResponse::created('Scanned barcode attached successfully', [
            'barcode' => new ProductBarcodeResource($barcode),
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/product-barcodes/generated",
     *     summary="Generate and attach an internal barcode",
     *     description="Generates a unique INTERNAL barcode value, attaches it to the selected target, and records source=generated with metadata.workflow=backend_generation.",
     *     tags={"Tenant Product Barcodes"},
     *     security={{"sanctum": {}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"target_type"},
     *
     *             @OA\Property(property="target_type", type="string", enum={"product","variant","product_uom"}, example="product"),
     *             @OA\Property(property="product_uuid", type="string", format="uuid", nullable=true, example="67b466f5-8b6d-4122-af5d-1683d1dd7a72"),
     *             @OA\Property(property="variant_id", type="integer", nullable=true, example=12),
     *             @OA\Property(property="product_uom_id", type="integer", nullable=true, example=8),
     *             @OA\Property(property="is_primary", type="boolean", example=true),
     *             @OA\Property(property="store_id", type="integer", nullable=true, example=null),
     *             @OA\Property(property="metadata", type="object", nullable=true),
     *             @OA\Property(property="notes", type="string", nullable=true, example="Internal shelf label")
     *         )
     *     ),
     *
     *     @OA\Response(response=201, description="Internal barcode generated successfully"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=404, description="Target product, variant, or product UOM not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function generated(WorkflowProductBarcodeRequest $request): JsonResponse
    {
        $barcode = $this->barcodeService->generate($request->validated());

        return ApiResponse::created('Internal barcode generated successfully', [
            'barcode' => new ProductBarcodeResource($barcode),
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/product-barcodes/imported",
     *     summary="Import barcode mappings in batch",
     *     description="Accepts parsed import rows and creates barcode mappings with source=imported and metadata.workflow=structured_import. CSV parsing can happen outside this endpoint and submit rows here.",
     *     tags={"Tenant Product Barcodes"},
     *     security={{"sanctum": {}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"rows"},
     *
     *             @OA\Property(
     *                 property="rows",
     *                 type="array",
     *
     *                 @OA\Items(ref="#/components/schemas/ProductBarcodeWorkflowRequest")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=201, description="Barcode import completed"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function imported(ImportProductBarcodesRequest $request): JsonResponse
    {
        $result = $this->barcodeService->importBatch($request->validated('rows'));

        return ApiResponse::created('Barcode import completed', [
            'created_count' => $result['created']->count(),
            'error_count' => count($result['errors']),
            'barcodes' => ProductBarcodeResource::collection($result['created']),
            'errors' => $result['errors'],
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/product-barcodes/supplier",
     *     summary="Attach a supplier-provided barcode",
     *     description="Creates a supplier-provided barcode mapping and requires supplier_id. Records source=supplier and metadata.workflow=supplier_catalog.",
     *     tags={"Tenant Product Barcodes"},
     *     security={{"sanctum": {}}},
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/ProductBarcodeWorkflowRequest")),
     *
     *     @OA\Response(response=201, description="Supplier barcode created successfully"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=404, description="Target product, variant, product UOM, or supplier not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function supplier(WorkflowProductBarcodeRequest $request): JsonResponse
    {
        $barcode = $this->barcodeService->createSupplier($request->validated());

        return ApiResponse::created('Supplier barcode created successfully', [
            'barcode' => new ProductBarcodeResource($barcode),
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/product-barcodes/scale",
     *     summary="Register a scale barcode",
     *     description="Registers a weighing-scale barcode mapping with source=scale and barcode_type=SCALE. This does not parse embedded weight or price yet; parsing needs the scale barcode format.",
     *     tags={"Tenant Product Barcodes"},
     *     security={{"sanctum": {}}},
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/ProductBarcodeWorkflowRequest")),
     *
     *     @OA\Response(response=201, description="Scale barcode registered successfully"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=404, description="Target product, variant, or product UOM not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function scale(WorkflowProductBarcodeRequest $request): JsonResponse
    {
        $barcode = $this->barcodeService->createScale($request->validated());

        return ApiResponse::created('Scale barcode registered successfully', [
            'barcode' => new ProductBarcodeResource($barcode),
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/products/{uuid}/barcodes",
     *     summary="List product barcodes",
     *     description="Returns all barcode mappings attached directly to a product, including inactive and future/expired mappings for administration.",
     *     tags={"Tenant Product Barcodes"},
     *     security={{"sanctum": {}}},
     *
     *     @OA\Parameter(name="uuid", in="path", required=true, description="Product UUID", @OA\Schema(type="string", format="uuid")),
     *
     *     @OA\Response(response=200, description="Product barcodes retrieved successfully"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=404, description="Product not found")
     * )
     */
    public function productIndex(string $uuid): JsonResponse
    {
        $product = Product::where('uuid', $uuid)->firstOrFail();

        return ApiResponse::success('Product barcodes retrieved successfully', [
            'barcodes' => ProductBarcodeResource::collection($this->barcodeService->listFor($product)),
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/products/{uuid}/barcodes",
     *     summary="Attach a barcode to a product",
     *     description="Creates a barcode mapping for a product. Requires manage-products permission. If is_primary=true, other barcodes on the same product are marked non-primary.",
     *     tags={"Tenant Product Barcodes"},
     *     security={{"sanctum": {}}},
     *
     *     @OA\Parameter(name="uuid", in="path", required=true, description="Product UUID", @OA\Schema(type="string", format="uuid")),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"barcode"},
     *
     *             @OA\Property(property="barcode", type="string", maxLength=50, example="6161234567890"),
     *             @OA\Property(property="barcode_type", type="string", enum={"EAN-13","EAN-8","UPC-A","UPC-E","CODE-128","CODE-39","QR","INTERNAL","SCALE"}, example="EAN-13"),
     *             @OA\Property(property="is_primary", type="boolean", example=true),
     *             @OA\Property(property="is_active", type="boolean", example=true),
     *             @OA\Property(property="supplier_id", type="integer", nullable=true, example=null),
     *             @OA\Property(property="region", type="string", nullable=true, example="KE"),
     *             @OA\Property(property="store_id", type="integer", nullable=true, example=1),
     *             @OA\Property(property="valid_from", type="string", format="date", nullable=true, example="2026-01-01"),
     *             @OA\Property(property="valid_until", type="string", format="date", nullable=true, example="2026-12-31"),
     *             @OA\Property(property="source", type="string", enum={"manufacturer","manual","generated","supplier","imported","scale"}, example="manual"),
     *             @OA\Property(property="metadata", type="object", nullable=true),
     *             @OA\Property(property="notes", type="string", nullable=true, example="Manufacturer pack barcode")
     *         )
     *     ),
     *
     *     @OA\Response(response=201, description="Product barcode created successfully"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=404, description="Product not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function productStore(StoreProductBarcodeRequest $request, string $uuid): JsonResponse
    {
        $product = Product::where('uuid', $uuid)->firstOrFail();
        $barcode = $this->barcodeService->createFor($product, $request->validated());

        return ApiResponse::created('Product barcode created successfully', [
            'barcode' => new ProductBarcodeResource($barcode),
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/variants/{id}/barcodes",
     *     summary="List product variant barcodes",
     *     description="Returns all barcode mappings attached directly to a product variant.",
     *     tags={"Tenant Product Barcodes"},
     *     security={{"sanctum": {}}},
     *
     *     @OA\Parameter(name="id", in="path", required=true, description="Product variant ID", @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=200, description="Variant barcodes retrieved successfully"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=404, description="Variant not found")
     * )
     */
    public function variantIndex(int $id): JsonResponse
    {
        $variant = ProductVariant::findOrFail($id);

        return ApiResponse::success('Variant barcodes retrieved successfully', [
            'barcodes' => ProductBarcodeResource::collection($this->barcodeService->listFor($variant)),
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/variants/{id}/barcodes",
     *     summary="Attach a barcode to a product variant",
     *     description="Creates a barcode mapping for a variant. Requires manage-products permission. Use this when each sellable variant has its own manufacturer or internal barcode.",
     *     tags={"Tenant Product Barcodes"},
     *     security={{"sanctum": {}}},
     *
     *     @OA\Parameter(name="id", in="path", required=true, description="Product variant ID", @OA\Schema(type="integer")),
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/ProductBarcodeStoreRequest")),
     *
     *     @OA\Response(response=201, description="Variant barcode created successfully"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=404, description="Variant not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function variantStore(StoreProductBarcodeRequest $request, int $id): JsonResponse
    {
        $variant = ProductVariant::findOrFail($id);
        $barcode = $this->barcodeService->createFor($variant, $request->validated());

        return ApiResponse::created('Variant barcode created successfully', [
            'barcode' => new ProductBarcodeResource($barcode),
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/products/{uuid}/uoms/{productUom}/barcodes",
     *     summary="List product UOM barcodes",
     *     description="Returns barcode mappings attached to a configured product UOM/package size.",
     *     tags={"Tenant Product Barcodes"},
     *     security={{"sanctum": {}}},
     *
     *     @OA\Parameter(name="uuid", in="path", required=true, description="Product UUID", @OA\Schema(type="string", format="uuid")),
     *     @OA\Parameter(name="productUom", in="path", required=true, description="Product UOM ID", @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=200, description="Product UOM barcodes retrieved successfully"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=404, description="Product or product UOM not found")
     * )
     */
    public function productUomIndex(string $uuid, int $productUom): JsonResponse
    {
        $entity = $this->productUomForProduct($uuid, $productUom);

        return ApiResponse::success('Product UOM barcodes retrieved successfully', [
            'barcodes' => ProductBarcodeResource::collection($this->barcodeService->listFor($entity)),
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/products/{uuid}/uoms/{productUom}/barcodes",
     *     summary="Attach a barcode to a product UOM",
     *     description="Creates a barcode mapping for a package/sellable UOM. Requires manage-products permission.",
     *     tags={"Tenant Product Barcodes"},
     *     security={{"sanctum": {}}},
     *
     *     @OA\Parameter(name="uuid", in="path", required=true, description="Product UUID", @OA\Schema(type="string", format="uuid")),
     *     @OA\Parameter(name="productUom", in="path", required=true, description="Product UOM ID", @OA\Schema(type="integer")),
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/ProductBarcodeStoreRequest")),
     *
     *     @OA\Response(response=201, description="Product UOM barcode created successfully"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=404, description="Product or product UOM not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function productUomStore(StoreProductBarcodeRequest $request, string $uuid, int $productUom): JsonResponse
    {
        $entity = $this->productUomForProduct($uuid, $productUom);
        $barcode = $this->barcodeService->createFor($entity, $request->validated());

        return ApiResponse::created('Product UOM barcode created successfully', [
            'barcode' => new ProductBarcodeResource($barcode),
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/tenant/product-barcodes/{barcode}",
     *     summary="Delete a product barcode",
     *     description="Soft-deletes a barcode mapping. Requires manage-products permission.",
     *     tags={"Tenant Product Barcodes"},
     *     security={{"sanctum": {}}},
     *
     *     @OA\Parameter(name="barcode", in="path", required=true, description="Product barcode row ID", @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=200, description="Product barcode deleted successfully"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=404, description="Barcode not found")
     * )
     */
    public function destroy(ProductBarcode $barcode): JsonResponse
    {
        $this->barcodeService->delete($barcode);

        return ApiResponse::success('Product barcode deleted successfully');
    }

    private function productUomForProduct(string $uuid, int $productUom): ProductUom
    {
        $product = Product::where('uuid', $uuid)->firstOrFail();

        return ProductUom::where('product_id', $product->id)->findOrFail($productUom);
    }
}
