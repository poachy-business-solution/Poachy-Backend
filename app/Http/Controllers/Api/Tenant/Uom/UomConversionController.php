<?php

namespace App\Http\Controllers\Api\Tenant\Uom;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Uom\StoreUomConversionRequest;
use App\Http\Requests\Tenant\Uom\UpdateUomConversionRequest;
use App\Http\Resources\Tenant\Uom\UomConversionResource;
use App\Http\Responses\ApiResponse;
use App\Services\Tenant\Uom\UnitOfMeasureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UomConversionController extends Controller
{
    public function __construct(
        protected UnitOfMeasureService $uomService
    ) {}

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/uom-conversions",
     *     summary="Create UOM conversion",
     *     description="Create a conversion between two units of measure of the same type",
     *     operationId="createUomConversion",
     *     tags={"Tenant UoM & UoM Conversions"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"from_uom_id", "to_uom_id", "conversion_factor"},
     *             @OA\Property(property="from_uom_id", type="integer", example=50, description="Source unit ID"),
     *             @OA\Property(property="to_uom_id", type="integer", example=1, description="Target unit ID (must be different from source)"),
     *             @OA\Property(property="conversion_factor", type="number", format="float", example=6, description="Conversion factor (> 0, max 6 decimal places)")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Conversion created successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Conversion created successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=100),
     *                 @OA\Property(
     *                     property="from_uom",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=50),
     *                     @OA\Property(property="code", type="string", example="bundle"),
     *                     @OA\Property(property="name", type="string", example="Bundle"),
     *                     @OA\Property(property="type", type="string", example="count"),
     *                     @OA\Property(property="source_type", type="string", example="custom"),
     *                     @OA\Property(property="source_type_label", type="string", example="Custom"),
     *                     @OA\Property(property="is_base_unit", type="boolean", example=false),
     *                     @OA\Property(property="is_active", type="boolean", example=true),
     *                     @OA\Property(property="is_system", type="boolean", example=false),
     *                     @OA\Property(property="is_custom", type="boolean", example=true),
     *                     @OA\Property(property="description", type="string", nullable=true),
     *                     @OA\Property(property="display_name", type="string", example="Bundle (bundle)"),
     *                     @OA\Property(property="created_at", type="string", format="date-time"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time")
     *                 ),
     *                 @OA\Property(
     *                     property="to_uom",
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
     *                     @OA\Property(property="description", type="string", nullable=true),
     *                     @OA\Property(property="display_name", type="string", example="Piece (pcs)"),
     *                     @OA\Property(property="created_at", type="string", format="date-time"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time")
     *                 ),
     *                 @OA\Property(property="conversion_factor", type="number", format="float", example=6),
     *                 @OA\Property(property="reverse_factor", type="number", format="float", example=0.166667),
     *                 @OA\Property(property="description", type="string", example="1 bundle = 6 pcs"),
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
     *                     property="conversion",
     *                     type="array",
     *                     @OA\Items(type="string", example="A conversion between these units already exists.")
     *                 ),
     *                 @OA\Property(
     *                     property="from_uom_id",
     *                     type="array",
     *                     @OA\Items(type="string", example="Source and target units must be different.")
     *                 ),
     *                 @OA\Property(
     *                     property="conversion_factor",
     *                     type="array",
     *                     @OA\Items(type="string", example="Conversion factor must be greater than zero.")
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
     *             @OA\Property(property="message", type="string", example="Cannot create conversion between different types: weight and volume"),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(property="error", type="string", example="Cannot create conversion between different types: weight and volume")
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
    public function store(StoreUomConversionRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();

            $conversion = $this->uomService->createUomConversion($data);

            return ApiResponse::created(
                'Conversion created successfully',
                new UomConversionResource($conversion)
            );
        } catch (\Exception $e) {
            return ApiResponse::serverError(
                $e->getMessage(),
                config('app.debug') ? ['error' => $e->getMessage()] : null
            );
        }
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/tenant/uom-conversions/{id}",
     *     summary="Update UOM conversion",
     *     description="Update the conversion factor of an existing conversion",
     *     operationId="updateUomConversion",
     *     tags={"Tenant UoM & UoM Conversions"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Conversion ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=100)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"conversion_factor"},
     *             @OA\Property(property="conversion_factor", type="number", format="float", example=7.5, description="New conversion factor (> 0, max 6 decimal places)")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Conversion updated successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Conversion updated successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=100),
     *                 @OA\Property(
     *                     property="from_uom",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=50),
     *                     @OA\Property(property="code", type="string", example="bundle"),
     *                     @OA\Property(property="name", type="string", example="Bundle"),
     *                     @OA\Property(property="type", type="string", example="count"),
     *                     @OA\Property(property="source_type", type="string", example="custom"),
     *                     @OA\Property(property="source_type_label", type="string", example="Custom"),
     *                     @OA\Property(property="is_base_unit", type="boolean", example=false),
     *                     @OA\Property(property="is_active", type="boolean", example=true),
     *                     @OA\Property(property="is_system", type="boolean", example=false),
     *                     @OA\Property(property="is_custom", type="boolean", example=true),
     *                     @OA\Property(property="description", type="string", nullable=true),
     *                     @OA\Property(property="display_name", type="string", example="Bundle (bundle)"),
     *                     @OA\Property(property="created_at", type="string", format="date-time"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time")
     *                 ),
     *                 @OA\Property(
     *                     property="to_uom",
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
     *                     @OA\Property(property="description", type="string", nullable=true),
     *                     @OA\Property(property="display_name", type="string", example="Piece (pcs)"),
     *                     @OA\Property(property="created_at", type="string", format="date-time"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time")
     *                 ),
     *                 @OA\Property(property="conversion_factor", type="number", format="float", example=7.5),
     *                 @OA\Property(property="reverse_factor", type="number", format="float", example=0.133333),
     *                 @OA\Property(property="description", type="string", example="1 bundle = 7.5 pcs"),
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
     *         response=404,
     *         description="Conversion not found",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Conversion not found"),
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
     *                     property="conversion_factor",
     *                     type="array",
     *                     @OA\Items(type="string", example="Conversion factor must be greater than zero.")
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
     *             @OA\Property(property="message", type="string", example="Failed to update conversion"),
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
    public function update(UpdateUomConversionRequest $request, int $id): JsonResponse
    {
        try {
            $data = $request->validated();

            $conversion = $this->uomService->updateUomConversion($id, $data);

            return ApiResponse::success(
                'Conversion updated successfully',
                new UomConversionResource($conversion)
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ApiResponse::notFound('Conversion not found');
        } catch (\Exception $e) {
            return ApiResponse::serverError(
                $e->getMessage(),
                config('app.debug') ? ['error' => $e->getMessage()] : null
            );
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/tenant/uom-conversions/{id}",
     *     summary="Delete UOM conversion",
     *     description="Remove a conversion between two units of measure",
     *     operationId="deleteUomConversion",
     *     tags={"Tenant UoM & UoM Conversions"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Conversion ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=100)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Conversion deleted successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Conversion deleted successfully"),
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
     *         description="Conversion not found",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Conversion not found"),
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
     *             @OA\Property(property="message", type="string", example="Failed to delete conversion"),
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
    public function destroy(int $id): JsonResponse
    {
        try {
            $this->uomService->deleteUomConversion($id);

            return ApiResponse::success('Conversion deleted successfully');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ApiResponse::notFound('Conversion not found');
        } catch (\Exception $e) {
            return ApiResponse::serverError(
                'Failed to delete conversion',
                config('app.debug') ? ['error' => $e->getMessage()] : null
            );
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/uom-conversions/convert",
     *     summary="Convert quantity between units",
     *     description="Calculate the converted quantity between two units of measure (utility endpoint)",
     *     operationId="convertQuantity",
     *     tags={"Tenant UoM & UoM Conversions"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"quantity", "from_uom_id", "to_uom_id"},
     *             @OA\Property(property="quantity", type="number", format="float", example=5, description="Amount to convert (must be > 0)"),
     *             @OA\Property(property="from_uom_id", type="integer", example=50, description="Source unit ID"),
     *             @OA\Property(property="to_uom_id", type="integer", example=1, description="Target unit ID")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Conversion calculated successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Conversion calculated successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="original_quantity", type="number", format="float", example=5),
     *                 @OA\Property(property="converted_quantity", type="number", format="float", example=30),
     *                 @OA\Property(
     *                     property="from_uom",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=50),
     *                     @OA\Property(property="code", type="string", example="bundle"),
     *                     @OA\Property(property="name", type="string", example="Bundle"),
     *                     @OA\Property(property="type", type="string", example="count"),
     *                     @OA\Property(property="source_type", type="string", example="custom"),
     *                     @OA\Property(property="source_type_label", type="string", example="Custom"),
     *                     @OA\Property(property="is_base_unit", type="boolean", example=false),
     *                     @OA\Property(property="is_active", type="boolean", example=true),
     *                     @OA\Property(property="is_system", type="boolean", example=false),
     *                     @OA\Property(property="is_custom", type="boolean", example=true),
     *                     @OA\Property(property="description", type="string", nullable=true),
     *                     @OA\Property(property="display_name", type="string", example="Bundle (bundle)"),
     *                     @OA\Property(property="created_at", type="string", format="date-time"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time")
     *                 ),
     *                 @OA\Property(
     *                     property="to_uom",
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
     *                     @OA\Property(property="description", type="string", nullable=true),
     *                     @OA\Property(property="display_name", type="string", example="Piece (pcs)"),
     *                     @OA\Property(property="created_at", type="string", format="date-time"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time")
     *                 ),
     *                 @OA\Property(property="conversion_factor", type="number", format="float", example=6, nullable=true)
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
     *                     property="quantity",
     *                     type="array",
     *                     @OA\Items(type="string", example="The quantity field is required.")
     *                 ),
     *                 @OA\Property(
     *                     property="from_uom_id",
     *                     type="array",
     *                     @OA\Items(type="string", example="The selected from uom id is invalid.")
     *                 ),
     *                 @OA\Property(
     *                     property="to_uom_id",
     *                     type="array",
     *                     @OA\Items(type="string", example="The selected to uom id is invalid.")
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
     *             @OA\Property(property="message", type="string", example="Cannot convert between different UOM types: weight and volume"),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(property="error", type="string", example="Cannot convert between different UOM types: weight and volume")
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
    public function convert(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'quantity' => ['required', 'numeric', 'gt:0'],
            'from_uom_id' => ['required', 'integer', 'exists:units_of_measure,id'],
            'to_uom_id' => ['required', 'integer', 'exists:units_of_measure,id'],
        ]);

        try {
            $result = $this->uomService->convert(
                $validated['quantity'],
                $validated['from_uom_id'],
                $validated['to_uom_id']
            );

            return ApiResponse::success(
                'Conversion calculated successfully',
                $result
            );
        } catch (\Exception $e) {
            return ApiResponse::serverError(
                $e->getMessage(),
                config('app.debug') ? ['error' => $e->getMessage()] : null
            );
        }
    }
}
