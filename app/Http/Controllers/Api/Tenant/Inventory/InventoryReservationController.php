<?php

namespace App\Http\Controllers\Api\Tenant\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Inventory\GetReservationsRequest;
use App\Http\Resources\Tenant\Inventory\InventoryReservationResource;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant\InventoryReservation;
use App\Services\Tenant\Inventory\InventoryService;
use Illuminate\Http\JsonResponse;

class InventoryReservationController extends Controller
{
    public function __construct(
        private InventoryService $inventoryService
    ) {}

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/inventory-reservations",
     *     summary="List inventory reservations",
     *     description="Retrieve a paginated list of inventory reservations with optional filtering by store, product, status, and date range. Defaults to the last 7 days when no date range is provided.",
     *     operationId="listInventoryReservations",
     *     tags={"Tenant - Inventory Reservations"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="store_id",
     *         in="query",
     *         description="Filter by specific store ID",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="product_id",
     *         in="query",
     *         description="Filter by specific product ID",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter by reservation status",
     *         required=false,
     *         @OA\Schema(
     *             type="string",
     *             enum={"active", "fulfilled", "cancelled", "expired"},
     *             example="active"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="from_date",
     *         in="query",
     *         description="Filter reservations created from this date (inclusive, format: Y-m-d). Defaults to 7 days ago.",
     *         required=false,
     *         @OA\Schema(type="string", format="date", example="2026-02-01")
     *     ),
     *     @OA\Parameter(
     *         name="to_date",
     *         in="query",
     *         description="Filter reservations created up to this date (inclusive, format: Y-m-d, must be after or equal to from_date). Defaults to today.",
     *         required=false,
     *         @OA\Schema(type="string", format="date", example="2026-02-26")
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of items per page",
     *         required=false,
     *         @OA\Schema(type="integer", minimum=1, maximum=100, default=20, example=20)
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number",
     *         required=false,
     *         @OA\Schema(type="integer", minimum=1, default=1, example=1)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Inventory reservations retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Inventory reservations retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="data",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(
     *                             property="inventory",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(
     *                                 property="store",
     *                                 type="object",
     *                                 @OA\Property(property="id", type="integer", example=1),
     *                                 @OA\Property(property="name", type="string", example="Branch Store - Mombasa"),
     *                                 @OA\Property(property="code", type="string", example="STR-2025-74622")
     *                             ),
     *                             @OA\Property(
     *                                 property="product",
     *                                 type="object",
     *                                 @OA\Property(property="id", type="integer", example=1),
     *                                 @OA\Property(property="name", type="string", example="Samsung Galaxy A54 5G"),
     *                                 @OA\Property(property="sku", type="string", example="ELEC-SAMS-VTFM")
     *                             ),
     *                             @OA\Property(
     *                                 property="base_uom",
     *                                 type="object",
     *                                 @OA\Property(property="id", type="integer", example=1),
     *                                 @OA\Property(property="code", type="string", example="pcs"),
     *                                 @OA\Property(property="name", type="string", example="Piece")
     *                             )
     *                         ),
     *                         @OA\Property(property="quantity_reserved", type="number", format="float", example=5),
     *                         @OA\Property(
     *                             property="status",
     *                             type="object",
     *                             @OA\Property(property="value", type="string", enum={"active", "fulfilled", "cancelled", "expired"}, example="active"),
     *                             @OA\Property(property="label", type="string", example="Active")
     *                         ),
     *                         @OA\Property(property="reserved_until", type="string", format="date-time", example="2026-02-27T10:00:00.000000Z"),
     *                         @OA\Property(property="is_expired", type="boolean", example=false),
     *                         @OA\Property(property="is_active", type="boolean", example=true),
     *                         @OA\Property(property="can_be_cancelled", type="boolean", example=true),
     *                         @OA\Property(property="remaining_minutes", type="integer", nullable=true, example=480),
     *                         @OA\Property(property="created_at", type="string", format="date-time", example="2026-02-26T08:00:00.000000Z"),
     *                         @OA\Property(property="updated_at", type="string", format="date-time", example="2026-02-26T08:00:00.000000Z")
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="pagination",
     *                     type="object",
     *                     @OA\Property(property="current_page", type="integer", example=1),
     *                     @OA\Property(property="last_page", type="integer", example=1),
     *                     @OA\Property(property="per_page", type="integer", example=20),
     *                     @OA\Property(property="total", type="integer", example=10),
     *                     @OA\Property(property="from", type="integer", example=1),
     *                     @OA\Property(property="to", type="integer", example=10)
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", nullable=true),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true)
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(property="meta", type="object")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthenticated."),
     *             @OA\Property(property="meta", type="object")
     *         )
     *     )
     * )
     */
    public function index(GetReservationsRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $reservations = $this->inventoryService->getInventoryReservations($validated);

        return ApiResponse::paginated(
            InventoryReservationResource::collection($reservations),
            'Inventory reservations retrieved successfully'
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/inventory-reservations/{id}",
     *     summary="Get single inventory reservation",
     *     description="Retrieve detailed information about a specific inventory reservation by its ID.",
     *     operationId="getInventoryReservation",
     *     tags={"Tenant - Inventory Reservations"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of the inventory reservation",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Reservation retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Reservation retrieved successfully"),
     *             @OA\Property(property="data", type="object"),
     *             @OA\Property(property="meta", type="object")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Reservation not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Resource not found"),
     *             @OA\Property(property="meta", type="object")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthenticated."),
     *             @OA\Property(property="meta", type="object")
     *         )
     *     )
     * )
     */
    public function show(int $id): JsonResponse
    {
        $reservation = InventoryReservation::withDetails()
            ->with('cancelledBy:id,name')
            ->findOrFail($id);

        return ApiResponse::success(
            'Reservation retrieved successfully',
            new InventoryReservationResource($reservation)
        );
    }
}
