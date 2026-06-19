<?php

namespace App\Http\Controllers\Api\Tenant\Uom;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Uom\ListUnitOfMeasureRequest;
use App\Http\Requests\Tenant\Uom\StoreUnitOfMeasureRequest;
use App\Http\Requests\Tenant\Uom\UpdateUnitOfMeasureRequest;
use App\Http\Resources\Tenant\Uom\UnitOfMeasureDetailResource;
use App\Http\Resources\Tenant\Uom\UnitOfMeasureResource;
use App\Http\Responses\ApiResponse;
use App\Services\Tenant\Uom\UnitOfMeasureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UnitOfMeasureController extends Controller
{
    public function __construct(
        protected UnitOfMeasureService $uomService
    ) {}

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/units-of-measure",
     *     summary="List all units of measure",
     *     description="Retrieve a paginated list of units of measure with optional filters for type, source, base unit status, and active status",
     *     operationId="listUnitsOfMeasure",
     *     tags={"Tenant UoM & UoM Conversions"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="type",
     *         in="query",
     *         description="Filter by unit type",
     *         required=false,
     *         @OA\Schema(
     *             type="string",
     *             enum={"count", "weight", "volume", "length", "area", "time"}
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="source_type",
     *         in="query",
     *         description="Filter by source type",
     *         required=false,
     *         @OA\Schema(
     *             type="string",
     *             enum={"system", "custom"}
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="is_base_unit",
     *         in="query",
     *         description="Filter base units only",
     *         required=false,
     *         @OA\Schema(type="boolean")
     *     ),
     *     @OA\Parameter(
     *         name="is_active",
     *         in="query",
     *         description="Filter active units only",
     *         required=false,
     *         @OA\Schema(type="boolean")
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search by code or name",
     *         required=false,
     *         @OA\Schema(type="string", maxLength=255)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Items per page",
     *         required=false,
     *         @OA\Schema(type="integer", minimum=1, maximum=100, default=15)
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number",
     *         required=false,
     *         @OA\Schema(type="integer", minimum=1, default=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Units of measure retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Units of measure retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="data",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="code", type="string", example="kg"),
     *                         @OA\Property(property="name", type="string", example="Kilogram"),
     *                         @OA\Property(property="type", type="string", example="weight"),
     *                         @OA\Property(property="source_type", type="string", example="system"),
     *                         @OA\Property(property="source_type_label", type="string", example="System Defined"),
     *                         @OA\Property(property="is_base_unit", type="boolean", example=false),
     *                         @OA\Property(property="is_active", type="boolean", example=true),
     *                         @OA\Property(property="is_system", type="boolean", example=true),
     *                         @OA\Property(property="is_custom", type="boolean", example=false),
     *                         @OA\Property(property="description", type="string", example="1000 grams", nullable=true),
     *                         @OA\Property(property="display_name", type="string", example="Kilogram (kg)"),
     *                         @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-19T10:00:00.000000Z"),
     *                         @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-19T10:00:00.000000Z")
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="pagination",
     *                     type="object",
     *                     @OA\Property(property="current_page", type="integer", example=1),
     *                     @OA\Property(property="last_page", type="integer", example=3),
     *                     @OA\Property(property="per_page", type="integer", example=15),
     *                     @OA\Property(property="total", type="integer", example=35),
     *                     @OA\Property(property="from", type="integer", example=1),
     *                     @OA\Property(property="to", type="integer", example=15)
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-19T12:30:00.000000Z"),
     *                 @OA\Property(property="request_id", type="string", example="550e8400-e29b-41d4-a716-446655440000"),
     *                 @OA\Property(property="tenant_id", type="string", example="tenant-uuid-here"),
     *                 @OA\Property(property="tenant_name", type="string", example="Example Store")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthenticated."),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string"),
     *                 @OA\Property(property="tenant_id", type="string", nullable=true),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to retrieve units of measure"),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string"),
     *                 @OA\Property(property="tenant_id", type="string"),
     *                 @OA\Property(property="tenant_name", type="string")
     *             )
     *         )
     *     )
     * )
     */
    public function index(ListUnitOfMeasureRequest $request): JsonResponse
    {
        try {
            $filters = $request->validated();

            $uoms = $this->uomService->getList($filters);

            return ApiResponse::paginated(
                UnitOfMeasureResource::collection($uoms),
                'Units of measure retrieved successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::serverError(
                'Failed to retrieve units of measure',
                config('app.debug') ? ['error' => $e->getMessage()] : null
            );
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/units-of-measure/{id}",
     *     summary="Get specific unit of measure",
     *     description="Retrieve details of a specific unit of measure including its conversions",
     *     operationId="getUnitOfMeasure",
     *     tags={"Tenant UoM & UoM Conversions"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Unit of measure ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Unit of measure retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Unit of measure retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="code", type="string", example="kg"),
     *                 @OA\Property(property="name", type="string", example="Kilogram"),
     *                 @OA\Property(property="type", type="string", example="weight"),
     *                 @OA\Property(property="source_type", type="string", example="system"),
     *                 @OA\Property(property="source_type_label", type="string", example="System Defined"),
     *                 @OA\Property(property="is_base_unit", type="boolean", example=false),
     *                 @OA\Property(property="is_active", type="boolean", example=true),
     *                 @OA\Property(property="is_system", type="boolean", example=true),
     *                 @OA\Property(property="is_custom", type="boolean", example=false),
     *                 @OA\Property(property="description", type="string", example="1000 grams", nullable=true),
     *                 @OA\Property(property="display_name", type="string", example="Kilogram (kg)"),
     *                 @OA\Property(
     *                     property="base_unit",
     *                     type="object",
     *                     nullable=true,
     *                     @OA\Property(property="id", type="integer", example=2),
     *                     @OA\Property(property="code", type="string", example="g"),
     *                     @OA\Property(property="name", type="string", example="Gram"),
     *                     @OA\Property(property="type", type="string", example="weight"),
     *                     @OA\Property(property="source_type", type="string", example="system"),
     *                     @OA\Property(property="source_type_label", type="string", example="System Defined"),
     *                     @OA\Property(property="is_base_unit", type="boolean", example=true),
     *                     @OA\Property(property="is_active", type="boolean", example=true),
     *                     @OA\Property(property="is_system", type="boolean", example=true),
     *                     @OA\Property(property="is_custom", type="boolean", example=false),
     *                     @OA\Property(property="description", type="string", example="Base unit for weight"),
     *                     @OA\Property(property="display_name", type="string", example="Gram (g)"),
     *                     @OA\Property(property="created_at", type="string", format="date-time"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time")
     *                 ),
     *                 @OA\Property(
     *                     property="conversions_from",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=10),
     *                         @OA\Property(
     *                             property="from_uom",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="code", type="string", example="kg"),
     *                             @OA\Property(property="name", type="string", example="Kilogram"),
     *                             @OA\Property(property="type", type="string", example="weight"),
     *                             @OA\Property(property="source_type", type="string", example="system"),
     *                             @OA\Property(property="source_type_label", type="string", example="System Defined"),
     *                             @OA\Property(property="is_base_unit", type="boolean", example=false),
     *                             @OA\Property(property="is_active", type="boolean", example=true),
     *                             @OA\Property(property="is_system", type="boolean", example=true),
     *                             @OA\Property(property="is_custom", type="boolean", example=false),
     *                             @OA\Property(property="description", type="string", nullable=true),
     *                             @OA\Property(property="display_name", type="string", example="Kilogram (kg)"),
     *                             @OA\Property(property="created_at", type="string", format="date-time"),
     *                             @OA\Property(property="updated_at", type="string", format="date-time")
     *                         ),
     *                         @OA\Property(
     *                             property="to_uom",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=2),
     *                             @OA\Property(property="code", type="string", example="g"),
     *                             @OA\Property(property="name", type="string", example="Gram"),
     *                             @OA\Property(property="type", type="string", example="weight"),
     *                             @OA\Property(property="source_type", type="string", example="system"),
     *                             @OA\Property(property="source_type_label", type="string", example="System Defined"),
     *                             @OA\Property(property="is_base_unit", type="boolean", example=true),
     *                             @OA\Property(property="is_active", type="boolean", example=true),
     *                             @OA\Property(property="is_system", type="boolean", example=true),
     *                             @OA\Property(property="is_custom", type="boolean", example=false),
     *                             @OA\Property(property="description", type="string", nullable=true),
     *                             @OA\Property(property="display_name", type="string", example="Gram (g)"),
     *                             @OA\Property(property="created_at", type="string", format="date-time"),
     *                             @OA\Property(property="updated_at", type="string", format="date-time")
     *                         ),
     *                         @OA\Property(property="conversion_factor", type="number", format="float", example=1000),
     *                         @OA\Property(property="reverse_factor", type="number", format="float", example=0.001),
     *                         @OA\Property(property="description", type="string", example="1 kg = 1000 g"),
     *                         @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-19T10:00:00.000000Z"),
     *                         @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-19T10:00:00.000000Z")
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="conversions_to",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="integer"),
     *                         @OA\Property(property="from_uom", type="object"),
     *                         @OA\Property(property="to_uom", type="object"),
     *                         @OA\Property(property="conversion_factor", type="number", format="float"),
     *                         @OA\Property(property="reverse_factor", type="number", format="float"),
     *                         @OA\Property(property="description", type="string"),
     *                         @OA\Property(property="created_at", type="string", format="date-time"),
     *                         @OA\Property(property="updated_at", type="string", format="date-time")
     *                     )
     *                 ),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-19T10:00:00.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-19T10:00:00.000000Z")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-19T12:30:00.000000Z"),
     *                 @OA\Property(property="request_id", type="string", example="550e8400-e29b-41d4-a716-446655440000"),
     *                 @OA\Property(property="tenant_id", type="string", example="tenant-uuid-here"),
     *                 @OA\Property(property="tenant_name", type="string", example="Example Store")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Unit of measure not found",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unit of measure not found"),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string"),
     *                 @OA\Property(property="tenant_id", type="string"),
     *                 @OA\Property(property="tenant_name", type="string")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthenticated."),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string"),
     *                 @OA\Property(property="tenant_id", type="string", nullable=true),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to retrieve unit of measure"),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string"),
     *                 @OA\Property(property="tenant_id", type="string"),
     *                 @OA\Property(property="tenant_name", type="string")
     *             )
     *         )
     *     )
     * )
     */
    public function show(int $id): JsonResponse
    {
        try {
            $uom = $this->uomService->getById($id);

            return ApiResponse::success(
                'Unit of measure retrieved successfully',
                new UnitOfMeasureDetailResource($uom)
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ApiResponse::notFound('Unit of measure not found');
        } catch (\Exception $e) {
            return ApiResponse::serverError(
                'Failed to retrieve unit of measure',
                config('app.debug') ? ['error' => $e->getMessage()] : null
            );
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/units-of-measure",
     *     summary="Create custom unit of measure",
     *     description="Create a new custom unit of measure. Only custom units can be created (system units are pre-defined)",
     *     operationId="createUnitOfMeasure",
     *     tags={"Tenant UoM & UoM Conversions"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"code", "name", "type"},
     *             @OA\Property(property="code", type="string", maxLength=20, example="bundle", description="Unique code (lowercase, alpha-dash)"),
     *             @OA\Property(property="name", type="string", maxLength=255, example="Bundle", description="Display name"),
     *             @OA\Property(property="type", type="string", enum={"count", "weight", "volume", "length", "area", "time"}, example="count", description="Unit type"),
     *             @OA\Property(property="is_base_unit", type="boolean", example=false, description="Whether this is a base unit (default: false)"),
     *             @OA\Property(property="description", type="string", maxLength=1000, example="Custom bundle unit for packaging", nullable=true, description="Optional description")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Custom unit of measure created successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Custom unit of measure created successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=50),
     *                 @OA\Property(property="code", type="string", example="bundle"),
     *                 @OA\Property(property="name", type="string", example="Bundle"),
     *                 @OA\Property(property="type", type="string", example="count"),
     *                 @OA\Property(property="source_type", type="string", example="custom"),
     *                 @OA\Property(property="source_type_label", type="string", example="Custom"),
     *                 @OA\Property(property="is_base_unit", type="boolean", example=false),
     *                 @OA\Property(property="is_active", type="boolean", example=true),
     *                 @OA\Property(property="is_system", type="boolean", example=false),
     *                 @OA\Property(property="is_custom", type="boolean", example=true),
     *                 @OA\Property(property="description", type="string", example="Custom bundle unit for packaging", nullable=true),
     *                 @OA\Property(property="display_name", type="string", example="Bundle (bundle)"),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-19T12:30:00.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-19T12:30:00.000000Z")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-19T12:30:00.000000Z"),
     *                 @OA\Property(property="request_id", type="string", example="550e8400-e29b-41d4-a716-446655440000"),
     *                 @OA\Property(property="tenant_id", type="string", example="tenant-uuid-here"),
     *                 @OA\Property(property="tenant_name", type="string", example="Example Store")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation failed",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="code",
     *                     type="array",
     *                     @OA\Items(type="string", example="This unit code already exists in your system.")
     *                 ),
     *                 @OA\Property(
     *                     property="type",
     *                     type="array",
     *                     @OA\Items(type="string", example="Unit type must be one of: count, weight, volume, length, area, time.")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string"),
     *                 @OA\Property(property="tenant_id", type="string"),
     *                 @OA\Property(property="tenant_name", type="string")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthenticated."),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string"),
     *                 @OA\Property(property="tenant_id", type="string", nullable=true),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to create unit of measure"),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(property="error", type="string", example="A base unit already exists for type: count")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string"),
     *                 @OA\Property(property="tenant_id", type="string"),
     *                 @OA\Property(property="tenant_name", type="string")
     *             )
     *         )
     *     )
     * )
     */
    public function store(StoreUnitOfMeasureRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();

            $uom = $this->uomService->create($data);

            return ApiResponse::created(
                'Custom unit of measure created successfully',
                new UnitOfMeasureResource($uom)
            );
        } catch (\Exception $e) {
            return ApiResponse::serverError(
                'Failed to create unit of measure',
                config('app.debug') ? ['error' => $e->getMessage()] : null
            );
        }
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/tenant/units-of-measure/{id}",
     *     summary="Update custom unit of measure",
     *     description="Update an existing custom unit of measure. Note: System-defined units cannot be updated",
     *     operationId="updateUnitOfMeasure",
     *     tags={"Tenant UoM & UoM Conversions"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Unit of measure ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=50)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="code", type="string", maxLength=20, example="bundle-pack", description="Update code (must remain unique)"),
     *             @OA\Property(property="name", type="string", maxLength=255, example="Bundle Pack", description="Update name"),
     *             @OA\Property(property="type", type="string", enum={"count", "weight", "volume", "length", "area", "time"}, example="count", description="Update type"),
     *             @OA\Property(property="is_base_unit", type="boolean", example=false, description="Update base unit flag"),
     *             @OA\Property(property="description", type="string", maxLength=1000, example="Updated description for bundle", nullable=true, description="Update description")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Custom unit of measure updated successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Custom unit of measure updated successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=50),
     *                 @OA\Property(property="code", type="string", example="bundle-pack"),
     *                 @OA\Property(property="name", type="string", example="Bundle Pack"),
     *                 @OA\Property(property="type", type="string", example="count"),
     *                 @OA\Property(property="source_type", type="string", example="custom"),
     *                 @OA\Property(property="source_type_label", type="string", example="Custom"),
     *                 @OA\Property(property="is_base_unit", type="boolean", example=false),
     *                 @OA\Property(property="is_active", type="boolean", example=true),
     *                 @OA\Property(property="is_system", type="boolean", example=false),
     *                 @OA\Property(property="is_custom", type="boolean", example=true),
     *                 @OA\Property(property="description", type="string", example="Updated description for bundle", nullable=true),
     *                 @OA\Property(property="display_name", type="string", example="Bundle Pack (bundle-pack)"),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-19T12:30:00.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-19T12:35:00.000000Z")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-19T12:35:00.000000Z"),
     *                 @OA\Property(property="request_id", type="string", example="550e8400-e29b-41d4-a716-446655440000"),
     *                 @OA\Property(property="tenant_id", type="string", example="tenant-uuid-here"),
     *                 @OA\Property(property="tenant_name", type="string", example="Example Store")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Cannot update system units",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="System-defined units of measure cannot be updated."),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string"),
     *                 @OA\Property(property="tenant_id", type="string"),
     *                 @OA\Property(property="tenant_name", type="string")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Unit of measure not found",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unit of measure not found"),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string"),
     *                 @OA\Property(property="tenant_id", type="string"),
     *                 @OA\Property(property="tenant_name", type="string")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation failed",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="code",
     *                     type="array",
     *                     @OA\Items(type="string", example="This unit code already exists in your system.")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string"),
     *                 @OA\Property(property="tenant_id", type="string"),
     *                 @OA\Property(property="tenant_name", type="string")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthenticated."),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string"),
     *                 @OA\Property(property="tenant_id", type="string", nullable=true),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to update unit of measure"),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(property="error", type="string", example="A base unit already exists for type: count")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string"),
     *                 @OA\Property(property="tenant_id", type="string"),
     *                 @OA\Property(property="tenant_name", type="string")
     *             )
     *         )
     *     )
     * )
     */
    public function update(UpdateUnitOfMeasureRequest $request, int $id): JsonResponse
    {
        try {
            $data = $request->validated();

            $uom = $this->uomService->update($id, $data);

            return ApiResponse::success(
                'Custom unit of measure updated successfully',
                new UnitOfMeasureResource($uom)
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ApiResponse::notFound('Unit of measure not found');
        } catch (\Exception $e) {
            return ApiResponse::serverError(
                $e->getMessage(),
                config('app.debug') ? ['error' => $e->getMessage()] : null
            );
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/units-of-measure/{id}/conversion-options",
     *     summary="Get conversion options for a unit",
     *     description="Retrieve all compatible units of measure that can be converted to/from the specified unit (same type only)",
     *     operationId="getConversionOptions",
     *     tags={"Tenant UoM & UoM Conversions"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Unit of measure ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Conversion options retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Conversion options retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=2),
     *                     @OA\Property(property="code", type="string", example="g"),
     *                     @OA\Property(property="name", type="string", example="Gram"),
     *                     @OA\Property(property="type", type="string", example="weight"),
     *                     @OA\Property(property="source_type", type="string", example="system"),
     *                     @OA\Property(property="source_type_label", type="string", example="System Defined"),
     *                     @OA\Property(property="is_base_unit", type="boolean", example=true),
     *                     @OA\Property(property="is_active", type="boolean", example=true),
     *                     @OA\Property(property="is_system", type="boolean", example=true),
     *                     @OA\Property(property="is_custom", type="boolean", example=false),
     *                     @OA\Property(property="description", type="string", example="Base unit for weight", nullable=true),
     *                     @OA\Property(property="display_name", type="string", example="Gram (g)"),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-19T10:00:00.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-19T10:00:00.000000Z")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-19T12:30:00.000000Z"),
     *                 @OA\Property(property="request_id", type="string", example="550e8400-e29b-41d4-a716-446655440000"),
     *                 @OA\Property(property="tenant_id", type="string", example="tenant-uuid-here"),
     *                 @OA\Property(property="tenant_name", type="string", example="Example Store")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Unit of measure not found",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unit of measure not found"),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string"),
     *                 @OA\Property(property="tenant_id", type="string"),
     *                 @OA\Property(property="tenant_name", type="string")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthenticated."),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string"),
     *                 @OA\Property(property="tenant_id", type="string", nullable=true),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to retrieve conversion options"),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string"),
     *                 @OA\Property(property="tenant_id", type="string"),
     *                 @OA\Property(property="tenant_name", type="string")
     *             )
     *         )
     *     )
     * )
     */
    public function conversionOptions(int $id): JsonResponse
    {
        try {
            $options = $this->uomService->getConversionOptions($id);

            return ApiResponse::success(
                'Conversion options retrieved successfully',
                UnitOfMeasureResource::collection($options)
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ApiResponse::notFound('Unit of measure not found');
        } catch (\Exception $e) {
            return ApiResponse::serverError(
                'Failed to retrieve conversion options',
                config('app.debug') ? ['error' => $e->getMessage()] : null
            );
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/units-of-measure/{id}/set-base-unit",
     *     summary="Set unit as base unit",
     *     description="Set a unit of measure as the base unit for its type. Automatically removes base flag from any existing base unit of the same type to ensure only one base unit exists per type",
     *     operationId="setBaseUnit",
     *     tags={"Tenant UoM & UoM Conversions"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Unit of measure ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=50)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Base unit flag set successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Base unit flag set successfully"),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-19T12:30:00.000000Z"),
     *                 @OA\Property(property="request_id", type="string", example="550e8400-e29b-41d4-a716-446655440000"),
     *                 @OA\Property(property="tenant_id", type="string", example="tenant-uuid-here"),
     *                 @OA\Property(property="tenant_name", type="string", example="Example Store")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Unit is already a base unit",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="This unit is already a base unit."),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string"),
     *                 @OA\Property(property="tenant_id", type="string"),
     *                 @OA\Property(property="tenant_name", type="string")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Unit of measure not found",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unit of measure not found"),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string"),
     *                 @OA\Property(property="tenant_id", type="string"),
     *                 @OA\Property(property="tenant_name", type="string")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthenticated."),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string"),
     *                 @OA\Property(property="tenant_id", type="string", nullable=true),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to set base unit flag"),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string"),
     *                 @OA\Property(property="tenant_id", type="string"),
     *                 @OA\Property(property="tenant_name", type="string")
     *             )
     *         )
     *     )
     * )
     */
    public function setBaseUnit(int $id): JsonResponse
    {
        try {
            $this->uomService->setBaseUnit($id);

            return ApiResponse::success('Base unit flag set successfully');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ApiResponse::notFound('Unit of measure not found');
        } catch (\Exception $e) {
            return ApiResponse::serverError(
                $e->getMessage(),
                config('app.debug') ? ['error' => $e->getMessage()] : null
            );
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/tenant/units-of-measure/{id}/remove-base-unit",
     *     summary="Remove base unit flag",
     *     description="Remove the base unit flag from a unit of measure. Only applicable if the unit is currently marked as a base unit",
     *     operationId="removeBaseUnit",
     *     tags={"Tenant UoM & UoM Conversions"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Unit of measure ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=50)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Base unit flag removed successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Base unit flag removed successfully"),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-19T12:30:00.000000Z"),
     *                 @OA\Property(property="request_id", type="string", example="550e8400-e29b-41d4-a716-446655440000"),
     *                 @OA\Property(property="tenant_id", type="string", example="tenant-uuid-here"),
     *                 @OA\Property(property="tenant_name", type="string", example="Example Store")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Unit is not a base unit",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="This unit is not a base unit."),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string"),
     *                 @OA\Property(property="tenant_id", type="string"),
     *                 @OA\Property(property="tenant_name", type="string")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Unit of measure not found",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unit of measure not found"),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string"),
     *                 @OA\Property(property="tenant_id", type="string"),
     *                 @OA\Property(property="tenant_name", type="string")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthenticated."),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string"),
     *                 @OA\Property(property="tenant_id", type="string", nullable=true),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to remove base unit flag"),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string"),
     *                 @OA\Property(property="tenant_id", type="string"),
     *                 @OA\Property(property="tenant_name", type="string")
     *             )
     *         )
     *     )
     * )
     */
    public function removeBaseUnit(int $id): JsonResponse
    {
        try {
            $this->uomService->removeBaseUnit($id);

            return ApiResponse::success('Base unit flag removed successfully');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ApiResponse::notFound('Unit of measure not found');
        } catch (\Exception $e) {
            return ApiResponse::serverError(
                $e->getMessage(),
                config('app.debug') ? ['error' => $e->getMessage()] : null
            );
        }
    }
}
