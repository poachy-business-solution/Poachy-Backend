<?php

namespace App\Http\Controllers\Api\Tenant\Business;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\Tenant\Business\BusinessHelperService;
use App\Services\Tenant\Business\OnboardingTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BusinessHelperController extends Controller
{
    public function __construct(
        private readonly BusinessHelperService $businessHelperService,
        private readonly OnboardingTemplateService $onboardingTemplateService,
    ) {}

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/business-types",
     *     summary="Get business types with categories",
     *     description="Get all active business types with their categories for business details form",
     *     tags={"Tenant Business Details"},
     *     security={{"sanctum": {}}},
     *
     *     @OA\Response(
     *         response=200,
     *         description="Business types retrieved successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Business types retrieved successfully"),
     *             @OA\Property(property="data", type="array",
     *
     *                 @OA\Items(
     *                     type="object",
     *
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Retail & Consumer Goods"),
     *                     @OA\Property(property="slug", type="string", example="retail-consumer-goods"),
     *                     @OA\Property(property="description", type="string"),
     *                     @OA\Property(property="categories", type="array",
     *
     *                         @OA\Items(
     *                             type="object",
     *
     *                             @OA\Property(property="id", type="integer"),
     *                             @OA\Property(property="name", type="string"),
     *                             @OA\Property(property="slug", type="string")
     *                         )
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *
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
     *
     *     @OA\Parameter(
     *         name="typeId",
     *         in="path",
     *         required=true,
     *         description="Business Type ID",
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
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

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/onboarding-template",
     *     summary="Get onboarding template",
     *     description="Returns the category and unit-of-measure starter template for the current tenant or the supplied business selection.",
     *     operationId="getOnboardingTemplate",
     *     tags={"Tenant Business Details"},
     *     security={{"sanctum": {}}},
     *
     *     @OA\Parameter(
     *         name="business_type_id",
     *         in="query",
     *         required=false,
     *         description="Business type ID to preview before business details are saved",
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Parameter(
     *         name="business_category_id",
     *         in="query",
     *         required=false,
     *         description="Business category ID to preview before business details are saved",
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Onboarding template retrieved successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Onboarding template retrieved successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="template_key", type="string", example="pharmacy"),
     *                 @OA\Property(property="template_name", type="string", example="Pharmacy"),
     *                 @OA\Property(property="description", type="string", nullable=true, example="Starter setup for pharmacies with medicine categories and stock-tracking defaults."),
     *                 @OA\Property(property="source", type="string", enum={"business_category", "business_type", "default"}, example="business_category"),
     *                 @OA\Property(property="business_type", type="object", nullable=true,
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Healthcare"),
     *                     @OA\Property(property="slug", type="string", example="healthcare")
     *                 ),
     *                 @OA\Property(property="business_category", type="object", nullable=true,
     *                     @OA\Property(property="id", type="integer", example=5),
     *                     @OA\Property(property="name", type="string", example="Pharmacy"),
     *                     @OA\Property(property="slug", type="string", example="pharmacy")
     *                 ),
     *                 @OA\Property(property="categories", type="array",
     *
     *                     @OA\Items(type="object",
     *
     *                         @OA\Property(property="key", type="string", example="medicine"),
     *                         @OA\Property(property="name", type="string", example="Medicine"),
     *                         @OA\Property(property="slug", type="string", example="medicine"),
     *                         @OA\Property(property="description", type="string", nullable=true, example="Prescription and over-the-counter medicines"),
     *                         @OA\Property(property="display_order", type="integer", example=1),
     *                         @OA\Property(property="children", type="array",
     *
     *                             @OA\Items(type="object",
     *
     *                                 @OA\Property(property="name", type="string", example="Pain Relief"),
     *                                 @OA\Property(property="slug", type="string", example="pain-relief"),
     *                                 @OA\Property(property="description", type="string", nullable=true, example=null),
     *                                 @OA\Property(property="display_order", type="integer", example=1)
     *                             )
     *                         )
     *                     )
     *                 ),
     *                 @OA\Property(property="units_of_measure", type="array",
     *
     *                     @OA\Items(type="object",
     *
     *                         @OA\Property(property="code", type="string", example="pcs"),
     *                         @OA\Property(property="name", type="string", example="Piece"),
     *                         @OA\Property(property="type", type="string", example="count"),
     *                         @OA\Property(property="source_type", type="string", example="system"),
     *                         @OA\Property(property="is_base_unit", type="boolean", example=true),
     *                         @OA\Property(property="is_active", type="boolean", example=true),
     *                         @OA\Property(property="description", type="string", nullable=true, example="Single item")
     *                     )
     *                 )
     *             ),
     *             @OA\Property(property="meta", type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-08-12T10:20:30.000000Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="1ad5c0a7-0890-4a7d-a614-22a27b2c4782"),
     *                 @OA\Property(property="tenant_id", type="string", example="demo"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example="Demo Store")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=422, description="Validation error - business_type_id or business_category_id is invalid")
     * )
     */
    public function onboardingTemplate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'business_type_id' => ['sometimes', 'integer'],
            'business_category_id' => ['sometimes', 'integer'],
        ]);

        $template = array_key_exists('business_type_id', $validated)
            || array_key_exists('business_category_id', $validated)
            ? $this->onboardingTemplateService->forBusinessSelection(
                businessCategoryId: $validated['business_category_id'] ?? null,
                businessTypeId: $validated['business_type_id'] ?? null,
            )
            : $this->onboardingTemplateService->forCurrentTenant();

        return ApiResponse::success(
            'Onboarding template retrieved successfully',
            $template
        );
    }
}
