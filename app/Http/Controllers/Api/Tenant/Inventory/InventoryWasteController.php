<?php

namespace App\Http\Controllers\Api\Tenant\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Inventory\Alerts\ApproveWasteRequest;
use App\Http\Requests\Tenant\Inventory\Alerts\RecordWasteRequest;
use App\Http\Requests\Tenant\Inventory\Alerts\RejectWasteRequest;
use App\Http\Requests\Tenant\Inventory\Alerts\UpdateWasteRequest;
use App\Http\Resources\Tenant\Inventory\InventoryWasteResource;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant\InventoryWaste;
use App\Services\Tenant\Inventory\InventoryWasteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryWasteController extends Controller
{
    public function __construct(
        private InventoryWasteService $inventoryWasteService
    ) {}

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/inventory-waste",
     *     tags={"Inventory Waste"},
     *     summary="List inventory waste records",
     *     description="Retrieve a paginated list of inventory waste records with optional filters",
     *     operationId="listInventoryWaste",
     *     @OA\Parameter(
     *         name="store_id",
     *         in="query",
     *         required=false,
     *         description="Filter by store ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="product_id",
     *         in="query",
     *         required=false,
     *         description="Filter by product ID",
     *         @OA\Schema(type="integer", example=4)
     *     ),
     *     @OA\Parameter(
     *         name="waste_type",
     *         in="query",
     *         required=false,
     *         description="Filter by waste type",
     *         @OA\Schema(type="string", example="damaged")
     *     ),
     *     @OA\Parameter(
     *         name="approval_status",
     *         in="query",
     *         required=false,
     *         description="Filter by approval status",
     *         @OA\Schema(type="string", enum={"pending", "approved", "rejected"}, example="pending")
     *     ),
     *     @OA\Parameter(
     *         name="from_date",
     *         in="query",
     *         required=false,
     *         description="Filter waste records from this date",
     *         @OA\Schema(type="string", format="date", example="2026-01-01")
     *     ),
     *     @OA\Parameter(
     *         name="to_date",
     *         in="query",
     *         required=false,
     *         description="Filter waste records up to this date",
     *         @OA\Schema(type="string", format="date", example="2026-01-31")
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         required=false,
     *         description="Number of items per page",
     *         @OA\Schema(type="integer", default=20, example=20)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Waste records retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Waste records retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="waste_records",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="waste_type", type="string", example="damaged"),
     *                         @OA\Property(property="waste_type_label", type="string", example="Damaged"),
     *                         @OA\Property(property="quantity_wasted", type="string", example="2.0000"),
     *                         @OA\Property(property="cost_per_base_uom", type="string", example="1500.00"),
     *                         @OA\Property(property="total_loss", type="string", example="3000.00"),
     *                         @OA\Property(property="waste_date", type="string", format="date", example="2026-01-12"),
     *                         @OA\Property(property="reason", type="string", example="Damaged during transit"),
     *                         @OA\Property(property="approval_status", type="string", example="pending"),
     *                         @OA\Property(property="approval_status_label", type="string", example="Pending Approval"),
     *                         @OA\Property(property="approved_at", type="string", nullable=true, example=null),
     *                         @OA\Property(property="is_pending", type="boolean", example=true),
     *                         @OA\Property(property="is_approved", type="boolean", example=false),
     *                         @OA\Property(property="is_rejected", type="boolean", example=false),
     *                         @OA\Property(property="can_be_approved", type="boolean", example=true),
     *                         @OA\Property(property="can_be_rejected", type="boolean", example=true),
     *                         @OA\Property(property="age_in_days", type="integer", example=0),
     *                         @OA\Property(property="created_at", type="string", format="date-time", example="2026-01-12T18:28:22.000000Z"),
     *                         @OA\Property(property="updated_at", type="string", format="date-time", example="2026-01-12T18:28:22.000000Z"),
     *                         @OA\Property(
     *                             property="store",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="name", type="string", example="Branch Store - Mombasa"),
     *                             @OA\Property(property="code", type="string", example="STR-2025-74622")
     *                         ),
     *                         @OA\Property(
     *                             property="product",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=4),
     *                             @OA\Property(property="name", type="string", example="TCL 55 4K UHD Smart LED TV"),
     *                             @OA\Property(property="sku", type="string", example="ELEC-DELL-56QT"),
     *                             @OA\Property(property="base_uom", type="string", example="pair"),
     *                             @OA\Property(property="primary_image", type="string", example="products/images/primary_a54_1766346778.jpg")
     *                         ),
     *                         @OA\Property(
     *                             property="batch",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=2),
     *                             @OA\Property(property="batch_number", type="string", example="BATCH-202512-0002"),
     *                             @OA\Property(property="expiry_date", type="string", nullable=true, example=null)
     *                         ),
     *                         @OA\Property(
     *                             property="reported_by",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="name", type="string", example="John Doe"),
     *                             @OA\Property(property="email", type="string", example="john@techhaven.com")
     *                         )
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="pagination",
     *                     type="object",
     *                     @OA\Property(property="current_page", type="integer", example=1),
     *                     @OA\Property(property="last_page", type="integer", example=1),
     *                     @OA\Property(property="per_page", type="integer", example=20),
     *                     @OA\Property(property="total", type="integer", example=1),
     *                     @OA\Property(property="from", type="integer", example=1),
     *                     @OA\Property(property="to", type="integer", example=1)
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-01-12T18:28:41.891761Z"),
     *                 @OA\Property(property="request_id", type="string", example="563194a5-3721-48e8-a756-0e172f0eb386"),
     *                 @OA\Property(property="tenant_id", type="string", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only([
            'store_id',
            'product_id',
            'waste_type',
            'approval_status',
            'from_date',
            'to_date',
            'per_page',
        ]);

        $wasteRecords = $this->inventoryWasteService->getWasteRecords($filters);

        return ApiResponse::success(
            'Waste records retrieved successfully',
            [
                'waste_records' => InventoryWasteResource::collection($wasteRecords->items()),
                'pagination' => [
                    'current_page' => $wasteRecords->currentPage(),
                    'last_page' => $wasteRecords->lastPage(),
                    'per_page' => $wasteRecords->perPage(),
                    'total' => $wasteRecords->total(),
                    'from' => $wasteRecords->firstItem(),
                    'to' => $wasteRecords->lastItem(),
                ],
            ]
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/inventory-waste",
     *     tags={"Inventory Waste"},
     *     summary="Record inventory waste",
     *     description="Create a new inventory waste record. The record will be in pending status awaiting approval.",
     *     operationId="createInventoryWaste",
     *     @OA\RequestBody(
     *         required=true,
     *         description="Waste record details",
     *         @OA\JsonContent(
     *             required={"store_id", "product_id", "waste_type", "quantity_wasted"},
     *             @OA\Property(property="store_id", type="integer", example=1),
     *             @OA\Property(property="product_id", type="integer", example=10),
     *             @OA\Property(property="batch_id", type="integer", example=5),
     *             @OA\Property(
     *                 property="waste_type",
     *                 type="string",
     *                 enum={"expired", "damaged", "stolen", "lost", "quality_issue", "other"},
     *                 example="damaged"
     *             ),
     *             @OA\Property(property="quantity_wasted", type="number", format="float", example=10.5),
     *             @OA\Property(property="waste_date", type="string", format="date", example="2025-01-10"),
     *             @OA\Property(property="reason", type="string", example="Product expired and removed from shelf")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Waste recorded successfully. Pending approval.",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Waste recorded successfully. Pending approval."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="waste_type", type="string", example="damaged"),
     *                 @OA\Property(property="waste_type_label", type="string", example="Damaged"),
     *                 @OA\Property(property="quantity_wasted", type="string", example="2.0000"),
     *                 @OA\Property(property="cost_per_base_uom", type="string", example="1500.00"),
     *                 @OA\Property(property="total_loss", type="string", example="3000.00"),
     *                 @OA\Property(property="waste_date", type="string", format="date", example="2026-01-12"),
     *                 @OA\Property(property="reason", type="string", example="Damaged during transit"),
     *                 @OA\Property(property="approval_status", type="string", example="pending"),
     *                 @OA\Property(property="approval_status_label", type="string", example="Pending Approval"),
     *                 @OA\Property(property="approved_at", type="string", nullable=true, example=null),
     *                 @OA\Property(property="is_pending", type="boolean", example=true),
     *                 @OA\Property(property="is_approved", type="boolean", example=false),
     *                 @OA\Property(property="is_rejected", type="boolean", example=false),
     *                 @OA\Property(property="can_be_approved", type="boolean", example=true),
     *                 @OA\Property(property="can_be_rejected", type="boolean", example=true),
     *                 @OA\Property(property="age_in_days", type="integer", example=0),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2026-01-12T18:28:22.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2026-01-12T18:28:22.000000Z"),
     *                 @OA\Property(
     *                     property="store",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Branch Store - Mombasa"),
     *                     @OA\Property(property="code", type="string", example="STR-2025-74622")
     *                 ),
     *                 @OA\Property(
     *                     property="product",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=4),
     *                     @OA\Property(property="name", type="string", example="TCL 55 4K UHD Smart LED TV"),
     *                     @OA\Property(property="sku", type="string", example="ELEC-DELL-56QT"),
     *                     @OA\Property(property="base_uom", type="string", example="pair"),
     *                     @OA\Property(property="primary_image", type="string", example="products/images/primary_a54_1766346778.jpg")
     *                 ),
     *                 @OA\Property(
     *                     property="batch",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=2),
     *                     @OA\Property(property="batch_number", type="string", example="BATCH-202512-0002"),
     *                     @OA\Property(property="expiry_date", type="string", nullable=true, example=null)
     *                 ),
     *                 @OA\Property(
     *                     property="reported_by",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="John Doe"),
     *                     @OA\Property(property="email", type="string", example="john@techhaven.com")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-01-12T18:28:22.674884Z"),
     *                 @OA\Property(property="request_id", type="string", example="ff5b0369-d3ab-473d-8dc1-3924aea44573"),
     *                 @OA\Property(property="tenant_id", type="string", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     )
     * )
     */
    public function store(RecordWasteRequest $request): JsonResponse
    {
        $waste = $this->inventoryWasteService->recordWaste($request->validated());

        return ApiResponse::created(
            'Waste recorded successfully. Pending approval.',
            new InventoryWasteResource($waste)
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/inventory-waste/{id}",
     *     tags={"Inventory Waste"},
     *     summary="Get waste record details",
     *     description="Retrieve detailed information about a specific inventory waste record",
     *     operationId="getInventoryWasteDetails",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Waste record ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Waste record retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Waste record retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="waste_type", type="string", example="damaged"),
     *                 @OA\Property(property="waste_type_label", type="string", example="Damaged"),
     *                 @OA\Property(property="quantity_wasted", type="string", example="2.0000"),
     *                 @OA\Property(property="cost_per_base_uom", type="string", example="1500.00"),
     *                 @OA\Property(property="total_loss", type="string", example="3000.00"),
     *                 @OA\Property(property="waste_date", type="string", format="date", example="2026-01-12"),
     *                 @OA\Property(property="reason", type="string", example="Damaged during transit"),
     *                 @OA\Property(property="approval_status", type="string", example="pending"),
     *                 @OA\Property(property="approval_status_label", type="string", example="Pending Approval"),
     *                 @OA\Property(property="approved_at", type="string", nullable=true, example=null),
     *                 @OA\Property(property="is_pending", type="boolean", example=true),
     *                 @OA\Property(property="is_approved", type="boolean", example=false),
     *                 @OA\Property(property="is_rejected", type="boolean", example=false),
     *                 @OA\Property(property="can_be_approved", type="boolean", example=true),
     *                 @OA\Property(property="can_be_rejected", type="boolean", example=true),
     *                 @OA\Property(property="age_in_days", type="integer", example=0),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2026-01-12T18:28:22.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2026-01-12T18:28:22.000000Z"),
     *                 @OA\Property(
     *                     property="store",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Branch Store - Mombasa"),
     *                     @OA\Property(property="code", type="string", example="STR-2025-74622")
     *                 ),
     *                 @OA\Property(
     *                     property="product",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=4),
     *                     @OA\Property(property="name", type="string", example="TCL 55 4K UHD Smart LED TV"),
     *                     @OA\Property(property="sku", type="string", example="ELEC-DELL-56QT"),
     *                     @OA\Property(property="base_uom", type="string", example="pair"),
     *                     @OA\Property(property="primary_image", type="string", example="products/images/primary_a54_1766346778.jpg")
     *                 ),
     *                 @OA\Property(
     *                     property="batch",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=2),
     *                     @OA\Property(property="batch_number", type="string", example="BATCH-202512-0002"),
     *                     @OA\Property(property="expiry_date", type="string", nullable=true, example=null)
     *                 ),
     *                 @OA\Property(
     *                     property="reported_by",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="John Doe"),
     *                     @OA\Property(property="email", type="string", example="john@techhaven.com")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-01-12T19:06:28.096604Z"),
     *                 @OA\Property(property="request_id", type="string", example="b721ae01-2ae0-4daa-a33f-be25570e6ba6"),
     *                 @OA\Property(property="tenant_id", type="string", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     )
     * )
     */
    public function show(int $id): JsonResponse
    {
        $waste = InventoryWaste::withDetails()->findOrFail($id);

        return ApiResponse::success(
            'Waste record retrieved successfully',
            new InventoryWasteResource($waste)
        );
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/tenant/inventory-waste/{id}",
     *     tags={"Inventory Waste"},
     *     summary="Update waste record (Only Pending)",
     *     description="Update an inventory waste record. Only pending records can be updated.",
     *     operationId="updateInventoryWaste",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Waste record ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=false,
     *         description="Fields to update",
     *         @OA\JsonContent(
     *             @OA\Property(property="waste_type", type="string", example="damaged"),
     *             @OA\Property(property="quantity_wasted", type="number", format="float", example=10),
     *             @OA\Property(property="waste_date", type="string", format="date", example="2026-01-12"),
     *             @OA\Property(property="reason", type="string", example="Updated reason for waste")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Waste record updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Waste record updated successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="waste_type", type="string", example="damaged"),
     *                 @OA\Property(property="waste_type_label", type="string", example="Damaged"),
     *                 @OA\Property(property="quantity_wasted", type="string", example="10.0000"),
     *                 @OA\Property(property="cost_per_base_uom", type="string", example="1500.00"),
     *                 @OA\Property(property="total_loss", type="string", example="15000.00"),
     *                 @OA\Property(property="waste_date", type="string", format="date", example="2026-01-12"),
     *                 @OA\Property(property="reason", type="string", example="Damaged during transit"),
     *                 @OA\Property(property="approval_status", type="string", example="pending"),
     *                 @OA\Property(property="approval_status_label", type="string", example="Pending Approval"),
     *                 @OA\Property(property="approved_at", type="string", nullable=true, example=null),
     *                 @OA\Property(property="is_pending", type="boolean", example=true),
     *                 @OA\Property(property="is_approved", type="boolean", example=false),
     *                 @OA\Property(property="is_rejected", type="boolean", example=false),
     *                 @OA\Property(property="can_be_approved", type="boolean", example=true),
     *                 @OA\Property(property="can_be_rejected", type="boolean", example=true),
     *                 @OA\Property(property="age_in_days", type="integer", example=0),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2026-01-12T18:28:22.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2026-01-12T19:22:18.000000Z"),
     *                 @OA\Property(
     *                     property="store",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Branch Store - Mombasa"),
     *                     @OA\Property(property="code", type="string", example="STR-2025-74622")
     *                 ),
     *                 @OA\Property(
     *                     property="product",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=4),
     *                     @OA\Property(property="name", type="string", example="TCL 55 4K UHD Smart LED TV"),
     *                     @OA\Property(property="sku", type="string", example="ELEC-DELL-56QT"),
     *                     @OA\Property(property="base_uom", type="string", example="pair"),
     *                     @OA\Property(property="primary_image", type="string", example="products/images/primary_a54_1766346778.jpg")
     *                 ),
     *                 @OA\Property(
     *                     property="batch",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=2),
     *                     @OA\Property(property="batch_number", type="string", example="BATCH-202512-0002"),
     *                     @OA\Property(property="expiry_date", type="string", nullable=true, example=null)
     *                 ),
     *                 @OA\Property(
     *                     property="reported_by",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="John Doe"),
     *                     @OA\Property(property="email", type="string", example="john@techhaven.com")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-01-12T19:22:18.799576Z"),
     *                 @OA\Property(property="request_id", type="string", example="9c39c059-2ac3-4266-a519-d5c04fbf7066"),
     *                 @OA\Property(property="tenant_id", type="string", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     )
     * )
     */
    public function update(int $id, UpdateWasteRequest $request): JsonResponse
    {
        $waste = $this->inventoryWasteService->updateWaste($id, $request->validated());

        return ApiResponse::success(
            'Waste record updated successfully',
            new InventoryWasteResource($waste)
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/inventory-waste/{id}/approve",
     *     tags={"Inventory Waste"},
     *     summary="Approve waste record",
     *     description="Approve an inventory waste record. This will update the inventory accordingly.",
     *     operationId="approveInventoryWaste",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Waste record ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=false,
     *         description="Optional approval notes",
     *         @OA\JsonContent(
     *             @OA\Property(property="notes", type="string", example="Approved - verified damaged goods")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Waste approved successfully. Inventory has been updated.",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Waste approved successfully. Inventory has been updated."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="waste_type", type="string", example="damaged"),
     *                 @OA\Property(property="waste_type_label", type="string", example="Damaged"),
     *                 @OA\Property(property="quantity_wasted", type="string", example="2.0000"),
     *                 @OA\Property(property="cost_per_base_uom", type="string", example="1500.00"),
     *                 @OA\Property(property="total_loss", type="string", example="3000.00"),
     *                 @OA\Property(property="waste_date", type="string", format="date", example="2026-01-12"),
     *                 @OA\Property(property="reason", type="string", example="Damaged during transit"),
     *                 @OA\Property(property="approval_status", type="string", example="approved"),
     *                 @OA\Property(property="approval_status_label", type="string", example="Approved"),
     *                 @OA\Property(property="approved_at", type="string", format="date-time", example="2026-01-12T19:27:14.000000Z"),
     *                 @OA\Property(property="is_pending", type="boolean", example=false),
     *                 @OA\Property(property="is_approved", type="boolean", example=true),
     *                 @OA\Property(property="is_rejected", type="boolean", example=false),
     *                 @OA\Property(property="can_be_approved", type="boolean", example=false),
     *                 @OA\Property(property="can_be_rejected", type="boolean", example=false),
     *                 @OA\Property(property="age_in_days", type="integer", example=0),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2026-01-12T18:28:22.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2026-01-12T19:27:14.000000Z"),
     *                 @OA\Property(
     *                     property="store",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Branch Store - Mombasa"),
     *                     @OA\Property(property="code", type="string", example="STR-2025-74622")
     *                 ),
     *                 @OA\Property(
     *                     property="product",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=4),
     *                     @OA\Property(property="name", type="string", example="TCL 55 4K UHD Smart LED TV"),
     *                     @OA\Property(property="sku", type="string", example="ELEC-DELL-56QT"),
     *                     @OA\Property(property="base_uom", type="string", example="pair"),
     *                     @OA\Property(property="primary_image", type="string", example="products/images/primary_a54_1766346778.jpg")
     *                 ),
     *                 @OA\Property(
     *                     property="batch",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=2),
     *                     @OA\Property(property="batch_number", type="string", example="BATCH-202512-0002"),
     *                     @OA\Property(property="expiry_date", type="string", nullable=true, example=null)
     *                 ),
     *                 @OA\Property(
     *                     property="reported_by",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="John Doe"),
     *                     @OA\Property(property="email", type="string", example="john@techhaven.com")
     *                 ),
     *                 @OA\Property(
     *                     property="approved_by",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="John Doe")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-01-12T19:27:14.485382Z"),
     *                 @OA\Property(property="request_id", type="string", example="74bd98c2-7d61-4a70-a333-dcc77221f140"),
     *                 @OA\Property(property="tenant_id", type="string", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     )
     * )
     */
    public function approve(int $id, ApproveWasteRequest $request): JsonResponse
    {
        $waste = $this->inventoryWasteService->approveWaste($id, $request->user()->id);

        return ApiResponse::success(
            'Waste approved successfully. Inventory has been updated.',
            new InventoryWasteResource($waste)
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/inventory-waste/{id}/reject",
     *     tags={"Inventory Waste"},
     *     summary="Reject waste record",
     *     description="Reject an inventory waste record with a required reason",
     *     operationId="rejectInventoryWaste",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Waste record ID",
     *         @OA\Schema(type="integer", example=2)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Rejection reason",
     *         @OA\JsonContent(
     *             required={"reason"},
     *             @OA\Property(property="reason", type="string", example="Quantity discrepancy - requires re-verification")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Waste rejected successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Waste rejected successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=2),
     *                 @OA\Property(property="waste_type", type="string", example="damaged"),
     *                 @OA\Property(property="waste_type_label", type="string", example="Damaged"),
     *                 @OA\Property(property="quantity_wasted", type="string", example="2.0000"),
     *                 @OA\Property(property="cost_per_base_uom", type="string", example="1500.00"),
     *                 @OA\Property(property="total_loss", type="string", example="3000.00"),
     *                 @OA\Property(property="waste_date", type="string", format="date", example="2026-01-12"),
     *                 @OA\Property(property="reason", type="string", example="Quantity discrepancy"),
     *                 @OA\Property(property="approval_status", type="string", example="rejected"),
     *                 @OA\Property(property="approval_status_label", type="string", example="Rejected"),
     *                 @OA\Property(property="approved_at", type="string", format="date-time", example="2026-01-12T20:00:47.000000Z"),
     *                 @OA\Property(property="is_pending", type="boolean", example=false),
     *                 @OA\Property(property="is_approved", type="boolean", example=false),
     *                 @OA\Property(property="is_rejected", type="boolean", example=true),
     *                 @OA\Property(property="can_be_approved", type="boolean", example=false),
     *                 @OA\Property(property="can_be_rejected", type="boolean", example=false),
     *                 @OA\Property(property="age_in_days", type="integer", example=0),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2026-01-12T19:47:46.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2026-01-12T20:00:47.000000Z"),
     *                 @OA\Property(
     *                     property="store",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Branch Store - Mombasa"),
     *                     @OA\Property(property="code", type="string", example="STR-2025-74622")
     *                 ),
     *                 @OA\Property(
     *                     property="product",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=4),
     *                     @OA\Property(property="name", type="string", example="TCL 55 4K UHD Smart LED TV"),
     *                     @OA\Property(property="sku", type="string", example="ELEC-DELL-56QT"),
     *                     @OA\Property(property="base_uom", type="string", example="pair"),
     *                     @OA\Property(property="primary_image", type="string", example="products/images/primary_a54_1766346778.jpg")
     *                 ),
     *                 @OA\Property(
     *                     property="batch",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=2),
     *                     @OA\Property(property="batch_number", type="string", example="BATCH-202512-0002"),
     *                     @OA\Property(property="expiry_date", type="string", nullable=true, example=null)
     *                 ),
     *                 @OA\Property(
     *                     property="reported_by",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="John Doe"),
     *                     @OA\Property(property="email", type="string", example="john@techhaven.com")
     *                 ),
     *                 @OA\Property(
     *                     property="approved_by",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="John Doe")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-01-12T20:00:47.116387Z"),
     *                 @OA\Property(property="request_id", type="string", example="30bb71fd-b802-4e8e-bea9-b52080448b3b"),
     *                 @OA\Property(property="tenant_id", type="string", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     )
     * )
     */
    public function reject(int $id, RejectWasteRequest $request): JsonResponse
    {
        $waste = $this->inventoryWasteService->rejectWaste(
            $id,
            $request->user()->id,
            $request->input('reason')
        );

        return ApiResponse::success(
            'Waste rejected successfully',
            new InventoryWasteResource($waste)
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/tenant/inventory-waste/{id}",
     *     tags={"Inventory Waste"},
     *     summary="Delete waste record (Soft deletes - Pending only)",
     *     description="Delete an inventory waste record. Only pending records can be deleted (soft delete).",
     *     operationId="deleteInventoryWaste",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Waste record ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Waste record deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Waste record deleted successfully"),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-01-12T20:26:04.364339Z"),
     *                 @OA\Property(property="request_id", type="string", example="148bd230-8d95-4f52-b9f4-08e9ac90fd6e"),
     *                 @OA\Property(property="tenant_id", type="string", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Only pending waste records can be deleted",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Only pending waste records can be deleted"),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-01-12T20:25:24.044945Z"),
     *                 @OA\Property(property="request_id", type="string", example="cebe9380-e6f4-4f6e-ad8a-3e4070a503b6"),
     *                 @OA\Property(property="tenant_id", type="string", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     )
     * )
     */
    public function destroy(int $id): JsonResponse
    {
        $waste = InventoryWaste::findOrFail($id);

        if (!$waste->is_pending) {
            return ApiResponse::error(
                'Only pending waste records can be deleted',
                null,
                400
            );
        }

        $waste->delete();

        return ApiResponse::success('Waste record deleted successfully');
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/stores/{storeId}/inventory-waste/summary",
     *     tags={"Inventory Waste"},
     *     summary="Get waste summary for a store",
     *     description="Retrieve summary statistics of inventory waste for a specific store with optional date range",
     *     operationId="getStoreWasteSummary",
     *     @OA\Parameter(
     *         name="storeId",
     *         in="path",
     *         required=true,
     *         description="Store ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="from_date",
     *         in="query",
     *         required=false,
     *         description="Filter waste records from this date",
     *         @OA\Schema(type="string", format="date", example="2026-01-01")
     *     ),
     *     @OA\Parameter(
     *         name="to_date",
     *         in="query",
     *         required=false,
     *         description="Filter waste records up to this date",
     *         @OA\Schema(type="string", format="date", example="2026-01-31")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Waste summary retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Waste summary retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="total_waste_records", type="integer", example=2),
     *                 @OA\Property(property="pending_approvals", type="integer", example=0),
     *                 @OA\Property(property="approved_count", type="integer", example=1),
     *                 @OA\Property(property="rejected_count", type="integer", example=1),
     *                 @OA\Property(property="total_financial_loss", type="string", example="3000.00"),
     *                 @OA\Property(property="total_quantity_wasted", type="string", example="2.0000"),
     *                 @OA\Property(
     *                     property="waste_by_type",
     *                     type="object",
     *                     @OA\Property(
     *                         property="damaged",
     *                         type="object",
     *                         @OA\Property(property="count", type="integer", example=1),
     *                         @OA\Property(property="total_loss", type="string", example="3000.00")
     *                     )
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-01-12T20:23:44.873485Z"),
     *                 @OA\Property(property="request_id", type="string", example="9a1a7b70-4e53-4705-9b9c-5896c0337b99"),
     *                 @OA\Property(property="tenant_id", type="string", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     )
     * )
     */
    public function summary(int $storeId, Request $request): JsonResponse
    {
        $summary = $this->inventoryWasteService->getStoreSummary(
            $storeId,
            $request->input('from_date'),
            $request->input('to_date')
        );

        return ApiResponse::success(
            'Waste summary retrieved successfully',
            $summary
        );
    }
}
