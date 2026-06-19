<?php

namespace App\Http\Controllers\Api\Tenant\Business;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\Tenant\Business\BusinessHelperService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BusinessHelperController extends Controller
{
    public function __construct(
        private readonly BusinessHelperService $businessHelperService
    ) {}

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/business-types",
     *     summary="Get business types with categories",
     *     description="Get all active business types with their categories for business details form",
     *     tags={"Tenant Business Details"},
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Business types retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Business types retrieved successfully"),
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Retail & Consumer Goods"),
     *                     @OA\Property(property="slug", type="string", example="retail-consumer-goods"),
     *                     @OA\Property(property="description", type="string"),
     *                     @OA\Property(property="categories", type="array",
     *                         @OA\Items(
     *                             type="object",
     *                             @OA\Property(property="id", type="integer"),
     *                             @OA\Property(property="name", type="string"),
     *                             @OA\Property(property="slug", type="string")
     *                         )
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function index(): JsonResponse
    {
        $businessTypes = $this->businessHelperService->getBusinessTypesWithCategories();

        return ApiResponse::success(
            'Business types retrieved successfully',
            $businessTypes
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/business-types/{typeId}/categories",
     *     summary="Get categories for business type",
     *     description="Get all active categories for a specific business type",
     *     tags={"Tenant Business Details"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="typeId",
     *         in="path",
     *         required=true,
     *         description="Business Type ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Categories retrieved successfully"
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function categories(int $typeId): JsonResponse
    {
        $categories = $this->businessHelperService->getCategoriesForType($typeId);

        return ApiResponse::success(
            'Categories retrieved successfully',
            $categories
        );
    }
}
