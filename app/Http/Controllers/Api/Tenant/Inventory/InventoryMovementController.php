<?php

namespace App\Http\Controllers\Api\Tenant\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Inventory\CreateAdjustmentRequest;
use App\Http\Requests\Tenant\Inventory\CreateDamageRequest;
use App\Http\Requests\Tenant\Inventory\GetMovementsRequest;
use App\Http\Resources\Tenant\Inventory\InventoryMovementResource;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant\InventoryMovement;
use App\Services\Tenant\Inventory\InventoryMovementService;
use App\Services\Tenant\Inventory\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryMovementController extends Controller
{
    public function __construct(
        private InventoryMovementService $movementService,
        private InventoryService $inventoryService
    ) {}

    public function index(GetMovementsRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $movements = $this->inventoryService->getInventoryMovements($validated);

        return ApiResponse::paginated(
            InventoryMovementResource::collection($movements),
            'Inventory movements retrieved successfully'
        );
    }

    public function show(int $id): JsonResponse
    {
        $movement = InventoryMovement::with([
            'product.baseUom',
            'productVariant',
            'store',
            'uom',
            'createdByUser',
        ])->findOrFail($id);

        return ApiResponse::success(
            'Movement record retrieved successfully',
            new InventoryMovementResource($movement)
        );
    }

    public function createAdjustment(CreateAdjustmentRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $movement = $this->movementService->recordAdjustment($validated);

            return ApiResponse::created(
                'Inventory adjustment recorded successfully',
                new InventoryMovementResource($movement)
            );
        } catch (\Exception $e) {
            return ApiResponse::error(
                'Failed to record adjustment: ' . $e->getMessage(),
                null,
                500
            );
        }
    }

    public function createDamage(CreateDamageRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $movement = $this->movementService->recordDamage($validated);

            return ApiResponse::created(
                'Damaged goods recorded successfully',
                new InventoryMovementResource($movement)
            );
        } catch (\Exception $e) {
            return ApiResponse::error(
                'Failed to record damage: ' . $e->getMessage(),
                null,
                500
            );
        }
    }
}
