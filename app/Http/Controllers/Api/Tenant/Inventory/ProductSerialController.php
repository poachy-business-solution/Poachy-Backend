<?php

namespace App\Http\Controllers\Api\Tenant\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Inventory\Serial\GetSerialsRequest;
use App\Http\Resources\Tenant\Inventory\ProductSerialResource;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant\ProductSerial;
use App\Services\Tenant\Inventory\ProductSerialService;
use Illuminate\Http\JsonResponse;

class ProductSerialController extends Controller
{
    public function __construct(
        private ProductSerialService $serialService
    ) {}

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/serials",
     *     summary="List product serials",
     *     description="Retrieve individually-serialized inventory units (e.g. IMEIs) with filtering by store, product, variant, and availability.",
     *     operationId="listProductSerials",
     *     tags={"Tenant - Product Serials"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="store_id", in="query", required=true, @OA\Schema(type="integer", example=1)),
     *     @OA\Parameter(name="product_id", in="query", required=false, @OA\Schema(type="integer", example=4)),
     *     @OA\Parameter(name="variant_id", in="query", required=false, @OA\Schema(type="integer", example=2)),
     *     @OA\Parameter(name="only_available", in="query", required=false, @OA\Schema(type="boolean", example=true)),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Serials retrieved successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Serials retrieved successfully"),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object", @OA\Property(property="serial_number", type="string", example="IMEI-359012345678901")))
     *         )
     *     ),
     *
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function index(GetSerialsRequest $request): JsonResponse
    {
        $validated = $request->validated();

        if (isset($validated['product_id'])) {
            $serials = $this->serialService->getSerialsForProduct(
                storeId: $validated['store_id'],
                productId: $validated['product_id'],
                variantId: $validated['variant_id'] ?? null,
                onlyAvailable: $validated['only_available'] ?? false
            );

            return ApiResponse::success(
                'Serials retrieved successfully',
                ProductSerialResource::collection($serials)
            );
        }

        // No product_id filter — paginated, since this can be a store's
        // entire serial history.
        $query = ProductSerial::where('store_id', $validated['store_id'])
            ->with(['product', 'productVariant', 'supplier', 'purchaseOrder']);

        if ($validated['only_available'] ?? false) {
            $query->available();
        }

        $serials = $query->fifoOrder()->paginate($validated['per_page'] ?? 20);

        return ApiResponse::paginated(
            ProductSerialResource::collection($serials),
            'Serials retrieved successfully'
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/serials/{id}",
     *     summary="Get single product serial",
     *     description="Retrieve detailed information about a specific serialized inventory unit by ID.",
     *     operationId="getProductSerial",
     *     tags={"Tenant - Product Serials"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=1)),
     *
     *     @OA\Response(response=200, description="Serial retrieved successfully"),
     *     @OA\Response(response=404, description="Serial not found"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function show(int $id): JsonResponse
    {
        $serial = ProductSerial::with([
            'store',
            'product.baseUom',
            'productVariant',
            'purchaseOrder',
            'supplier',
        ])->findOrFail($id);

        return ApiResponse::success(
            'Serial retrieved successfully',
            new ProductSerialResource($serial)
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/serials/lookup/{serialNumber}",
     *     summary="Look up a serial by its real-world serial number",
     *     description="Warranty/support lookup — find which sale or store a specific serialized unit belongs to by its manufacturer serial number/IMEI.",
     *     operationId="lookupProductSerial",
     *     tags={"Tenant - Product Serials"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="serialNumber", in="path", required=true, @OA\Schema(type="string", example="IMEI-359012345678901")),
     *
     *     @OA\Response(response=200, description="Serial found"),
     *     @OA\Response(response=404, description="No serial matches this number"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function lookup(string $serialNumber): JsonResponse
    {
        $serial = $this->serialService->findBySerialNumber($serialNumber);

        if (! $serial) {
            return ApiResponse::error('No serial found matching that number', null, 404);
        }

        return ApiResponse::success(
            'Serial retrieved successfully',
            new ProductSerialResource($serial)
        );
    }
}
