<?php

namespace App\Http\Controllers\Api\Tenant\Product;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Product\IndexProductBrandRequest;
use App\Http\Requests\Tenant\Product\StoreProductBrandRequest;
use App\Http\Requests\Tenant\Product\UpdateBrandLogoRequest;
use App\Http\Resources\Tenant\Product\ProductBrandResource;
use App\Http\Responses\ApiResponse;
use App\Services\Tenant\Product\ProductBrandService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductBrandController extends Controller
{
    public function __construct(
        protected ProductBrandService $brandService
    ) {}

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/brands",
     *     summary="Get all brands",
     *     description="Retrieves a list of brands with optional filtering and pagination capabilities.",
     *     tags={"Tenant Product Brands"},
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
     *         name="is_featured",
     *         in="query",
     *         description="Filter by featured status",
     *         required=false,
     *         @OA\Schema(type="boolean"),
     *         example=true
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search brands by name",
     *         required=false,
     *         @OA\Schema(type="string"),
     *         example="Samsung"
     *     ),
     *     @OA\Parameter(
     *         name="paginate",
     *         in="query",
     *         description="Enable pagination",
     *         required=false,
     *         @OA\Schema(type="boolean"),
     *         example=true
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
     *         description="Brands retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Brands retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Apple"),
     *                     @OA\Property(property="slug", type="string", example="apple"),
     *                     @OA\Property(property="description", type="string", example="American technology company"),
     *                     @OA\Property(property="logo_url", type="string", format="url", example="http://techhaven.localhost/tenancy/assets/storage/products/brands/logos/tech-haven-logo_1765886057.jpg"),
     *                     @OA\Property(property="is_active", type="boolean", example=true),
     *                     @OA\Property(property="is_featured", type="boolean", example=false),
     *                     @OA\Property(property="display_order", type="integer", example=1),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-16T11:54:17.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-16T11:54:17.000000Z")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-16T11:55:23.617857Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="832ffd6b-f741-46cf-b4cf-e491bf83ce1d"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             ),
     *             example={
     *                 "success": true,
     *                 "message": "Brands retrieved successfully",
     *                 "data": {
     *                     {
     *                         "id": 1,
     *                         "name": "Apple",
     *                         "slug": "apple",
     *                         "description": "American technology company",
     *                         "logo_url": "http://techhaven.localhost/tenancy/assets/storage/products/brands/logos/tech-haven-logo_1765886057.jpg",
     *                         "is_active": true,
     *                         "is_featured": false,
     *                         "display_order": 1,
     *                         "created_at": "2025-12-16T11:54:17.000000Z",
     *                         "updated_at": "2025-12-16T11:54:17.000000Z"
     *                     },
     *                     {
     *                         "id": 2,
     *                         "name": "Samsung",
     *                         "slug": "samsung",
     *                         "description": "China technology company",
     *                         "logo_url": "http://techhaven.localhost/tenancy/assets/storage/products/brands/logos/odometer_1765886108.jpg",
     *                         "is_active": true,
     *                         "is_featured": true,
     *                         "display_order": 2,
     *                         "created_at": "2025-12-16T11:55:08.000000Z",
     *                         "updated_at": "2025-12-16T11:55:08.000000Z"
     *                     }
     *                 },
     *                 "meta": {
     *                     "timestamp": "2025-12-16T11:55:23.617857Z",
     *                     "request_id": "832ffd6b-f741-46cf-b4cf-e491bf83ce1d",
     *                     "tenant_id": "bbab2597-e1ae-466b-a071-83033841d2ed",
     *                     "tenant_name": null
     *                 }
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     )
     * )
     */
    public function index(IndexProductBrandRequest $request): JsonResponse
    {
        try {
            $filters = $request->getFilters();
            $paginate = $request->shouldPaginate();
            $perPage = $request->getPerPage();

            $brands = $this->brandService->getAllBrands($filters, $paginate, $perPage);

            if ($paginate) {
                return ApiResponse::success(
                    'Brands retrieved successfully',
                    [
                        'data' => ProductBrandResource::collection($brands->items()),
                        'pagination' => [
                            'current_page' => $brands->currentPage(),
                            'last_page' => $brands->lastPage(),
                            'per_page' => $brands->perPage(),
                            'total' => $brands->total(),
                            'from' => $brands->firstItem(),
                            'to' => $brands->lastItem(),
                        ]
                    ]
                );
            }

            return ApiResponse::success(
                'Brands retrieved successfully',
                ProductBrandResource::collection($brands)
            );
        } catch (\Exception $e) {
            return ApiResponse::serverError(
                'Failed to retrieve brands: ' . $e->getMessage()
            );
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/brands/{id}",
     *     summary="Get brand by ID",
     *     description="Retrieves detailed information about a specific brand including associated products and product count.",
     *     tags={"Tenant Product Brands"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Brand ID",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         example=1
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Brand retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Brand retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Apple"),
     *                 @OA\Property(property="slug", type="string", example="apple"),
     *                 @OA\Property(property="description", type="string", example="American technology company"),
     *                 @OA\Property(property="logo_url", type="string", format="url", example="http://techhaven.localhost/tenancy/assets/storage/products/brands/logos/tech-haven-logo_1765886057.jpg"),
     *                 @OA\Property(property="is_active", type="boolean", example=true),
     *                 @OA\Property(property="is_featured", type="boolean", example=false),
     *                 @OA\Property(property="display_order", type="integer", example=1),
     *                 @OA\Property(
     *                     property="products",
     *                     type="array",
     *                     @OA\Items(type="object")
     *                 ),
     *                 @OA\Property(property="product_count", type="integer", example=0),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-16T11:54:17.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-16T11:54:17.000000Z")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-16T11:56:02.240711Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="97ebbece-a3f9-47ec-b40a-ff6bbdebd47f"),
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
     *         description="Brand not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Brand not found"),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-16T11:27:01.368155Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="edd47177-49df-4241-9571-56c1eed3028a"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     )
     * )
     */
    public function show(int $id): JsonResponse
    {
        try {
            $brand = $this->brandService->getBrandById($id, true);

            if (!$brand) {
                return ApiResponse::notFound('Brand not found');
            }

            return ApiResponse::success(
                'Brand retrieved successfully',
                new ProductBrandResource($brand)
            );
        } catch (\Exception $e) {
            return ApiResponse::serverError(
                'Failed to retrieve brand: ' . $e->getMessage()
            );
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/brands",
     *     summary="Create a new brand",
     *     description="Creates a new product brand with optional logo upload. Slug is auto-generated from the name if not provided.",
     *     tags={"Tenant Product Brands"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"name"},
     *                 @OA\Property(property="name", type="string", maxLength=255, description="Brand name", example="Samsung"),
     *                 @OA\Property(property="slug", type="string", maxLength=255, description="Optional brand slug. Auto-generated if not provided", example="samsung"),
     *                 @OA\Property(property="description", type="string", description="Optional brand description", example="China technology company"),
     *                 @OA\Property(property="logo", type="string", format="binary", description="Optional brand logo image file"),
     *                 @OA\Property(property="is_active", type="boolean", description="Active status (default: true)", example=true),
     *                 @OA\Property(property="is_featured", type="boolean", description="Featured status (default: false)", example=true),
     *                 @OA\Property(property="display_order", type="integer", description="Display order (default: 0)", example=2)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Brand created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Brand created successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=2),
     *                 @OA\Property(property="name", type="string", example="Samsung"),
     *                 @OA\Property(property="slug", type="string", example="samsung"),
     *                 @OA\Property(property="description", type="string", example="China technology company"),
     *                 @OA\Property(property="logo_url", type="string", format="url", example="http://techhaven.localhost/tenancy/assets/storage/products/brands/logos/odometer_1765886108.jpg"),
     *                 @OA\Property(property="is_active", type="boolean", example=true),
     *                 @OA\Property(property="is_featured", type="boolean", example=true),
     *                 @OA\Property(property="display_order", type="integer", example=2),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-16T11:55:08.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-16T11:55:08.000000Z")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-16T11:55:08.732682Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="9c06d795-dc4e-405b-9ee5-d93c1965d7da"),
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
     *     )
     * )
     */
    public function store(StoreProductBrandRequest $request): JsonResponse
    {
        try {
            $brand = $this->brandService->createBrand($request->validated());

            return ApiResponse::created(
                'Brand created successfully',
                new ProductBrandResource($brand)
            );
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), null, 400);
        } catch (\Exception $e) {
            return ApiResponse::serverError(
                'Failed to create brand: ' . $e->getMessage()
            );
        }
    }


    /**
     * @OA\Patch(
     *     path="/api/v1/tenant/brands/{id}/activate",
     *     summary="Activate brand",
     *     description="Activates a brand, making it visible and available for use.",
     *     tags={"Tenant Product Brands"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Brand ID",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         example=1
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Brand activated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Brand activated successfully"),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-16T11:59:24.195299Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="93562e21-5df4-4c59-abea-dc589fce417f"),
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
     *         description="Brand not found"
     *     )
     * )
     */
    public function activate(int $id): JsonResponse
    {
        try {
            $this->brandService->activateBrand($id);

            return ApiResponse::success('Brand activated successfully');
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), null, 400);
        } catch (\Exception $e) {
            return ApiResponse::serverError(
                'Failed to activate brand: ' . $e->getMessage()
            );
        }
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/tenant/brands/{id}/deactivate",
     *     summary="Deactivate brand",
     *     description="Deactivates a brand, making it hidden and unavailable for use.",
     *     tags={"Tenant Product Brands"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Brand ID",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         example=1
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Brand deactivated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Brand deactivated successfully"),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-16T12:00:52.770829Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="d220eafb-e726-4387-8a35-58ef8d919b45"),
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
     *         description="Brand not found"
     *     )
     * )
     */
    public function deactivate(int $id): JsonResponse
    {
        try {
            $this->brandService->deactivateBrand($id);

            return ApiResponse::success('Brand deactivated successfully');
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), null, 400);
        } catch (\Exception $e) {
            return ApiResponse::serverError(
                'Failed to deactivate brand: ' . $e->getMessage()
            );
        }
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/tenant/brands/{id}/feature",
     *     summary="Feature brand",
     *     description="Marks a brand as featured, giving it prominence in listings.",
     *     tags={"Tenant Product Brands"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Brand ID",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         example=1
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Brand featured successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Brand featured successfully"),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-16T12:02:57.993842Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="4571c918-818d-40ae-8278-b27a57c841d1"),
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
     *         description="Brand not found"
     *     )
     * )
     */
    public function feature(int $id): JsonResponse
    {
        try {
            $this->brandService->featureBrand($id);

            return ApiResponse::success('Brand featured successfully');
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), null, 400);
        } catch (\Exception $e) {
            return ApiResponse::serverError(
                'Failed to feature brand: ' . $e->getMessage()
            );
        }
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/tenant/brands/{id}/unfeatured",
     *     summary="Unfeature brand",
     *     description="Removes the featured status from a brand.",
     *     tags={"Tenant Product Brands"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Brand ID",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         example=1
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Brand unfeatured successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Brand unfeatured successfully"),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-16T12:04:35.398989Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="b07e68e4-2b47-4165-bec4-c315bfbca5f2"),
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
     *         description="Brand not found"
     *     )
     * )
     */
    public function unfeature(int $id): JsonResponse
    {
        try {
            $this->brandService->unfeatureBrand($id);

            return ApiResponse::success('Brand unfeatured successfully');
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), null, 400);
        } catch (\Exception $e) {
            return ApiResponse::serverError(
                'Failed to unfeature brand: ' . $e->getMessage()
            );
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/brands/{id}/logo",
     *     summary="Update brand logo",
     *     description="Updates or replaces the brand's logo image. The old logo will be deleted if it exists.",
     *     tags={"Tenant Product Brands"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Brand ID",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         example=1
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"logo"},
     *                 @OA\Property(
     *                     property="logo",
     *                     type="string",
     *                     format="binary",
     *                     description="Brand logo image file (JPG, PNG,JPEG, WEBP)"
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Brand logo updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Brand logo updated successfully"),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-16T12:16:45.773807Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="190ce18c-dab6-41da-8424-dc889ce99c81"),
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
     *         description="Brand not found"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error - Invalid file type or size"
     *     )
     * )
     */
    public function updateLogo(UpdateBrandLogoRequest $request, int $id): JsonResponse
    {
        try {
            $this->brandService->updateBrandLogo($id, $request->file('logo'));

            return ApiResponse::success('Brand logo updated successfully');
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), null, 400);
        } catch (\Exception $e) {
            return ApiResponse::serverError(
                'Failed to update brand logo: ' . $e->getMessage()
            );
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/tenant/brands/{id}",
     *     summary="Delete brand",
     *     description="Permanently deletes a brand. The brand must not have any associated products. Associated logo will also be deleted.",
     *     tags={"Tenant Product Brands"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Brand ID",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         example=2
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Brand deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Brand deleted successfully"),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-16T12:26:10.459431Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="13430ee4-f2c5-41fc-9e8f-0cb2cd840cc0"),
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
     *         description="Brand not found"
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Cannot delete brand with associated products"
     *     )
     * )
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $this->brandService->deleteBrand($id);

            return ApiResponse::success('Brand deleted successfully');
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), null, 400);
        } catch (\Exception $e) {
            return ApiResponse::serverError(
                'Failed to delete brand: ' . $e->getMessage()
            );
        }
    }
}
