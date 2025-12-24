<?php

namespace App\Http\Controllers\Api\Tenant\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Inventory\CheckAvailabilityRequest;
use App\Http\Requests\Tenant\Inventory\GetInventoryRequest;
use App\Http\Resources\Tenant\Inventory\InventoryResource;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant\Inventory;
use App\Services\Tenant\Inventory\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    public function __construct(
        private InventoryService $inventoryService
    ) {}

    public function index(GetInventoryRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $inventory = $this->inventoryService->getInventoryForStore(
            storeId: $validated['store_id'],
            filters: $validated
        );

        return ApiResponse::paginated(
            InventoryResource::collection($inventory),
            'Inventory retrieved successfully'
        );
    }

    public function show(int $id): JsonResponse
    {
        $inventory = Inventory::with([
            'product.baseUom',
            'product.category',
            'product.brand',
            'productVariant',
            'store',
        ])->findOrFail($id);

        return ApiResponse::success(
            'Inventory record retrieved successfully',
            new InventoryResource($inventory)
        );
    }

    public function getProductInventory(int $productId): JsonResponse
    {
        $storeId = request()->query('store_id');
        $variantId = request()->query('variant_id');

        $inventory = $this->inventoryService->getInventoryForProduct(
            $productId,
            $storeId,
            $variantId
        );

        // If single record (specific store), return as object
        if ($inventory instanceof Inventory) {
            return ApiResponse::success(
                'Product inventory retrieved successfully',
                new InventoryResource($inventory)
            );
        }

        // If collection (multiple stores), return as array
        return ApiResponse::success(
            'Product inventory retrieved successfully',
            InventoryResource::collection($inventory)
        );
    }

    public function checkAvailability(CheckAvailabilityRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $result = $this->inventoryService->checkAvailability(
            productId: $validated['product_id'],
            quantity: $validated['quantity'],
            storeId: $validated['store_id'],
            uomId: $validated['uom_id'],
            variantId: $validated['variant_id'] ?? null
        );

        return ApiResponse::success(
            $result['message'] ?? 'Stock availability checked',
            $result
        );
    }

    public function getLowStock(): JsonResponse
    {
        $storeId = request()->query('store_id');
        $threshold = request()->query('threshold');

        if (!$storeId) {
            return ApiResponse::error('Store ID is required', null, 422);
        }

        $lowStock = $this->inventoryService->getLowStockProducts(
            (int) $storeId,
            $threshold ? (float) $threshold : null
        );

        return ApiResponse::success(
            'Low stock products retrieved successfully',
            InventoryResource::collection($lowStock)
        );
    }

    public function getOutOfStock(): JsonResponse
    {
        $storeId = request()->query('store_id');

        if (!$storeId) {
            return ApiResponse::error('Store ID is required', null, 422);
        }

        $outOfStock = $this->inventoryService->getOutOfStockProducts((int) $storeId);

        return ApiResponse::success(
            'Out of stock products retrieved successfully',
            InventoryResource::collection($outOfStock)
        );
    }

    public function getInventoryValue(): JsonResponse
    {
        $storeId = request()->query('store_id');
        $productId = request()->query('product_id');

        if (!$storeId) {
            return ApiResponse::error('Store ID is required', null, 422);
        }

        $value = $this->inventoryService->getInventoryValue(
            (int) $storeId,
            $productId ? (int) $productId : null
        );

        return ApiResponse::success(
            'Inventory value calculated successfully',
            $value
        );
    }

    public function getSummary(): JsonResponse
    {
        $storeId = request()->query('store_id');

        if (!$storeId) {
            return ApiResponse::error('Store ID is required', null, 422);
        }

        $summary = $this->inventoryService->getInventorySummary((int) $storeId);

        return ApiResponse::success(
            'Inventory summary retrieved successfully',
            $summary
        );
    }
}
