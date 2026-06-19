<?php

namespace App\Http\Controllers\Api\Tenant\Tax;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Tax\IndexTaxRateRequest;
use App\Http\Requests\Tenant\Tax\StoreTaxRateRequest;
use App\Http\Requests\Tenant\Tax\UpdateTaxRateEffectiveUntilRequest;
use App\Http\Resources\Tenant\Tax\TaxRateResource;
use App\Http\Responses\ApiResponse;
use App\Services\Tenant\Tax\TaxRateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaxRateController extends Controller
{
    public function __construct(
        protected TaxRateService $taxRateService
    ) {}

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/tax-rates",
     *     summary="Get all tax rates",
     *     description="Retrieves a list of tax rates with optional filtering and pagination capabilities.",
     *     tags={"Tenant Tax Rates"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="is_active",
     *         in="query",
     *         description="Filter by active status",
     *         required=false,
     *         @OA\Schema(type="boolean"),
     *         example=true
     *     ),
     *     @OA\Parameter(
     *         name="paginate",
     *         in="query",
     *         description="Enable pagination",
     *         required=false,
     *         @OA\Schema(type="boolean"),
     *         example=false
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Items per page (1-100)",
     *         required=false,
     *         @OA\Schema(type="integer", minimum=1, maximum=100),
     *         example=15
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Tax rates retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Tax rates retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="tax_name", type="string", example="VAT"),
     *                     @OA\Property(property="rate", type="string", example="16.00"),
     *                     @OA\Property(property="effective_from", type="string", format="date", example="2025-12-17"),
     *                     @OA\Property(property="effective_until", type="string", format="date", nullable=true, example=null),
     *                     @OA\Property(property="is_active", type="boolean", example=true),
     *                     @OA\Property(property="is_default", type="boolean", example=true),
     *                     @OA\Property(property="is_currently_effective", type="boolean", example=false),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-16T14:08:27.000000Z")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-16T14:08:33.622349Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="a661fa5d-7e39-4778-96bc-c25ee53496a5"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     )
     * )
     */
    public function index(IndexTaxRateRequest $request): JsonResponse
    {
        try {
            $filters = $request->getFilters();
            $paginate = $request->shouldPaginate();
            $perPage = $request->getPerPage();

            $taxRates = $this->taxRateService->getAllTaxRates($filters, $paginate, $perPage);

            if ($paginate) {
                return ApiResponse::success(
                    'Tax rates retrieved successfully',
                    [
                        'data' => TaxRateResource::collection($taxRates->items()),
                        'pagination' => [
                            'current_page' => $taxRates->currentPage(),
                            'last_page' => $taxRates->lastPage(),
                            'per_page' => $taxRates->perPage(),
                            'total' => $taxRates->total(),
                            'from' => $taxRates->firstItem(),
                            'to' => $taxRates->lastItem(),
                        ]
                    ]
                );
            }

            return ApiResponse::success(
                'Tax rates retrieved successfully',
                TaxRateResource::collection($taxRates)
            );
        } catch (\Exception $e) {
            return ApiResponse::serverError(
                'Failed to retrieve tax rates: ' . $e->getMessage()
            );
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/tax-rates",
     *     summary="Create a new tax rate",
     *     description="Creates a new tax rate. Cannot create a duplicate tax rate for the same name and effective date. If is_default is true, any existing default tax rate will be unset.",
     *     tags={"Tenant Tax Rates"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"tax_name", "rate", "effective_from"},
     *             @OA\Property(property="tax_name", type="string", maxLength=100, description="Tax name (e.g., VAT, GST, Sales Tax)", example="VAT"),
     *             @OA\Property(property="rate", type="number", format="float", minimum=0, maximum=100, description="Tax rate percentage (0-100)", example=16.00),
     *             @OA\Property(property="effective_from", type="string", format="date", description="Date when this tax rate becomes effective (YYYY-MM-DD)", example="2025-12-17"),
     *             @OA\Property(property="effective_until", type="string", format="date", nullable=true, description="Optional end date for this tax rate (YYYY-MM-DD)", example=null),
     *             @OA\Property(property="is_active", type="boolean", description="Whether the tax rate is active (default: true)", example=true),
     *             @OA\Property(property="is_default", type="boolean", description="Whether this is the default tax rate (default: false)", example=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Tax rate created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Tax rate created successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="tax_name", type="string", example="VAT"),
     *                 @OA\Property(property="rate", type="string", example="16.00"),
     *                 @OA\Property(property="effective_from", type="string", format="date", example="2025-12-17"),
     *                 @OA\Property(property="effective_until", type="string", format="date", nullable=true, example=null),
     *                 @OA\Property(property="is_active", type="boolean", example=true),
     *                 @OA\Property(property="is_default", type="boolean", example=true),
     *                 @OA\Property(property="is_currently_effective", type="boolean", example=false),
     *                 @OA\Property(property="created_at", type="string", format="date-time", nullable=true, example=null)
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-16T14:08:27.433666Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="bf7f6296-f5cf-4add-9a9e-a2627ba0d518"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Failed to create - Duplicate tax rate",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to create tax rate: A tax rate VAT already exists for the date 2025-12-17."),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-16T14:17:50.679521Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="7e7c389d-6c4d-4aae-ad7a-6a26c5de0ea4"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     )
     * )
     */
    public function store(StoreTaxRateRequest $request): JsonResponse
    {
        try {
            $taxRate = $this->taxRateService->createTaxRate($request->validated());

            return ApiResponse::created(
                'Tax rate created successfully',
                new TaxRateResource($taxRate)
            );
        } catch (\Exception $e) {
            return ApiResponse::serverError(
                'Failed to create tax rate: ' . $e->getMessage()
            );
        }
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/tenant/tax-rates/{id}/toggle-active",
     *     summary="Toggle tax rate active status",
     *     description="Toggles the active status of a tax rate. If currently active, it will be deactivated and vice versa.",
     *     tags={"Tenant Tax Rates"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Tax Rate ID",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         example=1
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Tax rate status toggled successfully",
     *         @OA\JsonContent(
     *             oneOf={
     *                 @OA\Schema(
     *                     @OA\Property(property="success", type="boolean", example=true),
     *                     @OA\Property(property="message", type="string", example="Tax rate deactivated successfully"),
     *                     @OA\Property(
     *                         property="meta",
     *                         type="object",
     *                         @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-16T14:21:32.577226Z"),
     *                         @OA\Property(property="request_id", type="string", format="uuid", example="a5a5ee76-0bb9-4539-9487-17af76c77064"),
     *                         @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                         @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *                     )
     *                 ),
     *                 @OA\Schema(
     *                     @OA\Property(property="success", type="boolean", example=true),
     *                     @OA\Property(property="message", type="string", example="Tax rate activated successfully"),
     *                     @OA\Property(
     *                         property="meta",
     *                         type="object",
     *                         @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-16T14:21:59.708304Z"),
     *                         @OA\Property(property="request_id", type="string", format="uuid", example="22e3e3cd-a78e-4474-9ac8-88fa98115f89"),
     *                         @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                         @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *                     )
     *                 )
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Tax rate not found"
     *     )
     * )
     */
    public function toggleActive(int $id): JsonResponse
    {
        try {
            $result = $this->taxRateService->toggleActiveStatus($id);

            return ApiResponse::success($result['message']);
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), null, 400);
        } catch (\Exception $e) {
            return ApiResponse::serverError(
                'Failed to toggle tax rate status: ' . $e->getMessage()
            );
        }
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/tenant/tax-rates/{id}/toggle-default",
     *     summary="Toggle tax rate default status",
     *     description="Toggles the default status of a tax rate. Cannot remove default status if this is the only default tax rate. When setting a new default, any existing default will be automatically unset.",
     *     tags={"Tenant Tax Rates"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Tax Rate ID",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         example=1
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Tax rate default status toggled successfully"
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Cannot remove default status",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Cannot remove default status. At least one tax rate must be default"),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-16T14:24:05.199361Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="ef6c5d63-a611-43b6-8568-bdacc119051e"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Tax rate not found"
     *     )
     * )
     */
    public function toggleDefault(int $id): JsonResponse
    {
        try {
            $result = $this->taxRateService->toggleDefaultStatus($id);

            return ApiResponse::success($result['message']);
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), null, 400);
        } catch (\Exception $e) {
            return ApiResponse::serverError(
                'Failed to toggle default status: ' . $e->getMessage()
            );
        }
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/tenant/tax-rates/{id}/effective-until",
     *     summary="Update tax rate effective until date",
     *     description="Updates the effective_until date for a tax rate. This defines when the tax rate expires.",
     *     tags={"Tenant Tax Rates"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Tax Rate ID",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         example=1
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"effective_until"},
     *             @OA\Property(
     *                 property="effective_until",
     *                 type="string",
     *                 format="date",
     *                 description="Date when this tax rate expires (YYYY-MM-DD). Must be after effective_from date.",
     *                 example="2026-01-31"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Effective until date updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Effective until date updated successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="tax_name", type="string", example="VAT"),
     *                 @OA\Property(property="rate", type="string", example="16.00"),
     *                 @OA\Property(property="effective_from", type="string", format="date", example="2025-12-17"),
     *                 @OA\Property(property="effective_until", type="string", format="date", example="2026-01-31"),
     *                 @OA\Property(property="is_active", type="boolean", example=true),
     *                 @OA\Property(property="is_default", type="boolean", example=true),
     *                 @OA\Property(property="is_currently_effective", type="boolean", example=false),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-16T14:08:27.000000Z")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-16T14:26:33.559678Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="b960d082-7780-4f66-82ff-6b7a1b5328ad"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Tax rate not found"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error - Invalid date or date before effective_from"
     *     )
     * )
     */
    public function updateEffectiveUntil(UpdateTaxRateEffectiveUntilRequest $request, int $id): JsonResponse
    {
        try {
            $taxRate = $this->taxRateService->updateEffectiveUntil(
                $id,
                $request->input('effective_until')
            );

            return ApiResponse::success(
                'Effective until date updated successfully',
                new TaxRateResource($taxRate)
            );
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), null, 400);
        } catch (\Exception $e) {
            return ApiResponse::serverError(
                'Failed to update effective until date: ' . $e->getMessage()
            );
        }
    }
}
