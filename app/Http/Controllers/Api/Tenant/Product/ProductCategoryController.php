<?php

namespace App\Http\Controllers\Api\Tenant\Product;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Product\IndexProductCategoryRequest;
use App\Http\Requests\Tenant\Product\StoreProductCategoryRequest;
use App\Http\Requests\Tenant\Product\UpdateProductCategoryRequest;
use App\Http\Resources\Tenant\Product\ProductCategoryResource;
use App\Http\Responses\ApiResponse;
use App\Services\Tenant\Product\ProductCategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductCategoryController extends Controller
{
    public function __construct(
        protected ProductCategoryService $categoryService
    ) {}

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/categories",
     *     summary="Get all categories",
     *     description="Retrieves a list of categories with optional filtering and relationship loading. Can return paginated or non-paginated results.",
     *     tags={"Tenant Product Categories"},
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
     *         name="parent_id",
     *         in="query",
     *         description="Filter by parent category ID. Use 'null' for root categories",
     *         required=false,
     *         @OA\Schema(type="string"),
     *         example=1
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search categories by name",
     *         required=false,
     *         @OA\Schema(type="string"),
     *         example="Electronics"
     *     ),
     *     @OA\Parameter(
     *         name="with_children",
     *         in="query",
     *         description="Include child categories",
     *         required=false,
     *         @OA\Schema(type="boolean"),
     *         example=true
     *     ),
     *     @OA\Parameter(
     *         name="with_parent",
     *         in="query",
     *         description="Include parent category",
     *         required=false,
     *         @OA\Schema(type="boolean"),
     *         example=true
     *     ),
     *     @OA\Parameter(
     *         name="with_products",
     *         in="query",
     *         description="Include products in each category",
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
     *         description="Categories retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Categories retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Electronics"),
     *                     @OA\Property(property="slug", type="string", example="electronics"),
     *                     @OA\Property(property="description", type="string", example="Electronic devices and accessories"),
     *                     @OA\Property(property="parent_id", type="integer", nullable=true, example=null),
     *                     @OA\Property(property="display_order", type="integer", example=1),
     *                     @OA\Property(property="is_active", type="boolean", example=true),
     *                     @OA\Property(property="is_root", type="boolean", example=true),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-15T20:02:13.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-15T20:02:13.000000Z")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-15T20:28:59.613555Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="2bc70037-ad4b-4d57-ba4e-08c43bc6fc7e"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             ),
     *             example={
     *                 "success": true,
     *                 "message": "Categories retrieved successfully",
     *                 "data": {
     *                     {
     *                         "id": 1,
     *                         "name": "Electronics",
     *                         "slug": "electronics",
     *                         "description": "Electronic devices and accessories",
     *                         "parent_id": null,
     *                         "display_order": 1,
     *                         "is_active": true,
     *                         "is_root": true,
     *                         "created_at": "2025-12-15T20:02:13.000000Z",
     *                         "updated_at": "2025-12-15T20:02:13.000000Z"
     *                     },
     *                     {
     *                         "id": 4,
     *                         "name": "Mobile Phones",
     *                         "slug": "mobile-phones",
     *                         "description": "Smartphones and feature phones",
     *                         "parent_id": 1,
     *                         "display_order": 1,
     *                         "is_active": true,
     *                         "is_root": false,
     *                         "created_at": "2025-12-15T20:06:48.000000Z",
     *                         "updated_at": "2025-12-15T20:06:48.000000Z"
     *                     },
     *                     {
     *                         "id": 2,
     *                         "name": "Groceries",
     *                         "slug": "groceries",
     *                         "description": "Everyday food items and household consumables.",
     *                         "parent_id": null,
     *                         "display_order": 2,
     *                         "is_active": true,
     *                         "is_root": true,
     *                         "created_at": "2025-12-15T20:03:38.000000Z",
     *                         "updated_at": "2025-12-15T20:03:38.000000Z"
     *                     },
     *                     {
     *                         "id": 3,
     *                         "name": "Fashion",
     *                         "slug": "fashion",
     *                         "description": "Clothing, footwear, and wearable accessories..",
     *                         "parent_id": null,
     *                         "display_order": 3,
     *                         "is_active": true,
     *                         "is_root": true,
     *                         "created_at": "2025-12-15T20:04:45.000000Z",
     *                         "updated_at": "2025-12-15T20:04:45.000000Z"
     *                     }
     *                 },
     *                 "meta": {
     *                     "timestamp": "2025-12-15T20:28:59.613555Z",
     *                     "request_id": "2bc70037-ad4b-4d57-ba4e-08c43bc6fc7e",
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
    public function index(IndexProductCategoryRequest $request): JsonResponse
    {
        try {
            $filters = $request->getFilters();
            $paginate = $request->shouldPaginate();
            $perPage = $request->getPerPage();
            $withProducts = $request->shouldIncludeProducts();

            if ($withProducts) {
                $categories = $this->categoryService->getAllCategoriesWithProducts($filters, $paginate, $perPage);
            } else {
                $categories = $this->categoryService->getAllCategories($filters, $paginate, $perPage);
            }

            if ($paginate) {
                return ApiResponse::success(
                    'Categories retrieved successfully',
                    [
                        'data' => ProductCategoryResource::collection($categories->items()),
                        'pagination' => [
                            'current_page' => $categories->currentPage(),
                            'last_page' => $categories->lastPage(),
                            'per_page' => $categories->perPage(),
                            'total' => $categories->total(),
                            'from' => $categories->firstItem(),
                            'to' => $categories->lastItem(),
                        ]
                    ]
                );
            }

            return ApiResponse::success(
                'Categories retrieved successfully',
                ProductCategoryResource::collection($categories)
            );
        } catch (\Exception $e) {
            return ApiResponse::serverError(
                'Failed to retrieve categories: ' . $e->getMessage()
            );
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/categories",
     *     summary="Create a new category",
     *     description="Creates a new product category. Can be a root category or a subcategory by providing parent_id. Slug is auto-generated if not provided.",
     *     tags={"Tenant Product Categories"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name"},
     *             @OA\Property(property="name", type="string", maxLength=255, description="Category name", example="Fashion"),
     *             @OA\Property(property="slug", type="string", maxLength=255, description="Optional category slug. Auto-generated if not provided", example="fashion"),
     *             @OA\Property(property="description", type="string", description="Optional category description", example="Clothing, footwear, and wearable accessories.."),
     *             @OA\Property(property="parent_id", type="integer", nullable=true, description="Optional parent category ID for subcategories", example=null),
     *             @OA\Property(property="display_order", type="integer", description="Optional display order (default: 0)", example=3),
     *             @OA\Property(property="is_active", type="boolean", description="Optional active status (default: true)", example=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Category created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Category created successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=3),
     *                 @OA\Property(property="name", type="string", example="Fashion"),
     *                 @OA\Property(property="slug", type="string", example="fashion"),
     *                 @OA\Property(property="description", type="string", example="Clothing, footwear, and wearable accessories.."),
     *                 @OA\Property(property="parent_id", type="integer", nullable=true, example=null),
     *                 @OA\Property(property="display_order", type="integer", example=3),
     *                 @OA\Property(property="is_active", type="boolean", example=true),
     *                 @OA\Property(property="is_root", type="boolean", example=true),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-15T20:04:45.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-15T20:04:45.000000Z")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-15T20:04:45.392573Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="e5331aaa-f818-4c0d-ad48-facf299a0048"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             ),
     *             example={
     *                 "success": true,
     *                 "message": "Category created successfully",
     *                 "data": {
     *                     "id": 3,
     *                     "name": "Fashion",
     *                     "slug": "fashion",
     *                     "description": "Clothing, footwear, and wearable accessories..",
     *                     "parent_id": null,
     *                     "display_order": 3,
     *                     "is_active": true,
     *                     "is_root": true,
     *                     "created_at": "2025-12-15T20:04:45.000000Z",
     *                     "updated_at": "2025-12-15T20:04:45.000000Z"
     *                 },
     *                 "meta": {
     *                     "timestamp": "2025-12-15T20:04:45.392573Z",
     *                     "request_id": "e5331aaa-f818-4c0d-ad48-facf299a0048",
     *                     "tenant_id": "bbab2597-e1ae-466b-a071-83033841d2ed",
     *                     "tenant_name": null
     *                 }
     *             }
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
    public function store(StoreProductCategoryRequest $request): JsonResponse
    {
        try {
            $category = $this->categoryService->createCategory($request->validated());

            return ApiResponse::created(
                'Category created successfully',
                new ProductCategoryResource($category)
            );
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), null, 400);
        } catch (\Exception $e) {
            return ApiResponse::serverError(
                'Failed to create category: ' . $e->getMessage()
            );
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/categories/{id}",
     *     summary="Get category by ID",
     *     description="Retrieves detailed information about a specific category including its parent, children, and associated products.",
     *     tags={"Tenant Product Categories"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Category ID",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         example=1
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Category retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Category retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Electronics"),
     *                 @OA\Property(property="slug", type="string", example="electronics"),
     *                 @OA\Property(property="description", type="string", example="Electronic devices and accessories"),
     *                 @OA\Property(property="parent_id", type="integer", nullable=true, example=null),
     *                 @OA\Property(property="display_order", type="integer", example=1),
     *                 @OA\Property(property="is_active", type="boolean", example=true),
     *                 @OA\Property(
     *                     property="parent",
     *                     type="object",
     *                     nullable=true,
     *                     example=null
     *                 ),
     *                 @OA\Property(
     *                     property="children",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=4),
     *                         @OA\Property(property="name", type="string", example="Mobile Phones"),
     *                         @OA\Property(property="slug", type="string", example="mobile-phones"),
     *                         @OA\Property(property="description", type="string", example="Smartphones and feature phones"),
     *                         @OA\Property(property="parent_id", type="integer", example=1),
     *                         @OA\Property(property="display_order", type="integer", example=1),
     *                         @OA\Property(property="is_active", type="boolean", example=true),
     *                         @OA\Property(property="is_root", type="boolean", example=false),
     *                         @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-15T20:06:48.000000Z"),
     *                         @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-15T20:06:48.000000Z")
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="products",
     *                     type="array",
     *                     @OA\Items(type="object")
     *                 ),
     *                 @OA\Property(property="product_count", type="integer", example=0),
     *                 @OA\Property(property="has_children", type="boolean", example=true),
     *                 @OA\Property(property="is_root", type="boolean", example=true),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-15T20:02:13.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-15T20:02:13.000000Z")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-15T20:12:31.919229Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="8acc2e00-6d2c-4d99-9028-83273d1566c1"),
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
     *         description="Category not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Category not found")
     *         )
     *     )
     * )
     */
    public function show(int $id): JsonResponse
    {
        try {
            $category = $this->categoryService->getCategoryById($id, true);

            if (!$category) {
                return ApiResponse::notFound('Category not found');
            }

            return ApiResponse::success(
                'Category retrieved successfully',
                new ProductCategoryResource($category)
            );
        } catch (\Exception $e) {
            return ApiResponse::serverError(
                'Failed to retrieve category: ' . $e->getMessage()
            );
        }
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/tenant/categories/{id}",
     *     summary="Update category",
     *     description="Updates category details. Only provided fields will be updated, other fields remain unchanged.",
     *     tags={"Tenant Product Categories"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Category ID",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         example=1
     *     ),
     *     @OA\RequestBody(
     *         required=false,
     *         description="Category details to update (all fields are optional)",
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", maxLength=255, description="Category name", example="updated electronics"),
     *             @OA\Property(property="slug", type="string", maxLength=255, description="Category slug", example="updated-electronics"),
     *             @OA\Property(property="description", type="string", description="Category description", example="Updated description"),
     *             @OA\Property(property="parent_id", type="integer", nullable=true, description="Parent category ID", example=null),
     *             @OA\Property(property="display_order", type="integer", description="Display order", example=1),
     *             @OA\Property(property="is_active", type="boolean", description="Active status", example=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Category updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Category updated successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="updated electronics"),
     *                 @OA\Property(property="slug", type="string", example="electronics"),
     *                 @OA\Property(property="description", type="string", example="Electronic devices and accessories"),
     *                 @OA\Property(property="parent_id", type="integer", nullable=true, example=null),
     *                 @OA\Property(property="display_order", type="integer", example=1),
     *                 @OA\Property(property="is_active", type="boolean", example=true),
     *                 @OA\Property(property="is_root", type="boolean", example=true),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-15T20:02:13.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-15T20:54:01.000000Z")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-15T20:54:01.580591Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="284c2c77-538b-4370-9724-73fca6a5b1c3"),
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
     *         description="Category not found"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function update(UpdateProductCategoryRequest $request, int $id): JsonResponse
    {
        try {
            $category = $this->categoryService->updateCategory($id, $request->validated());

            return ApiResponse::success(
                'Category updated successfully',
                new ProductCategoryResource($category)
            );
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), null, 400);
        } catch (\Exception $e) {
            return ApiResponse::serverError(
                'Failed to update category: ' . $e->getMessage()
            );
        }
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/tenant/categories/{id}/activate",
     *     summary="Activate category",
     *     description="Activates a category, making it visible and available for use.",
     *     tags={"Tenant Product Categories"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Category ID",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         example=1
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Category activated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Category activated successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Electronics"),
     *                 @OA\Property(property="slug", type="string", example="electronics"),
     *                 @OA\Property(property="description", type="string", example="Electronic devices and accessories"),
     *                 @OA\Property(property="parent_id", type="integer", nullable=true, example=null),
     *                 @OA\Property(property="display_order", type="integer", example=1),
     *                 @OA\Property(property="is_active", type="boolean", example=true),
     *                 @OA\Property(property="is_root", type="boolean", example=true),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-15T20:02:13.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-15T20:02:13.000000Z")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-15T20:40:23.744177Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="42499a36-79ec-4c66-8916-6458aab57b92"),
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
     *         description="Category not found"
     *     )
     * )
     */
    public function activate(int $id): JsonResponse
    {
        try {
            $category = $this->categoryService->activateCategory($id);

            return ApiResponse::success(
                'Category activated successfully',
                new ProductCategoryResource($category)
            );
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), null, 400);
        } catch (\Exception $e) {
            return ApiResponse::serverError(
                'Failed to activate category: ' . $e->getMessage()
            );
        }
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/tenant/categories/{id}/deactivate",
     *     summary="Deactivate category",
     *     description="Deactivates a category, making it hidden and unavailable for use.",
     *     tags={"Tenant Product Categories"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Category ID",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         example=1
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Category deactivated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Category deactivated successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Electronics"),
     *                 @OA\Property(property="slug", type="string", example="electronics"),
     *                 @OA\Property(property="description", type="string", example="Electronic devices and accessories"),
     *                 @OA\Property(property="parent_id", type="integer", nullable=true, example=null),
     *                 @OA\Property(property="display_order", type="integer", example=1),
     *                 @OA\Property(property="is_active", type="boolean", example=false),
     *                 @OA\Property(property="is_root", type="boolean", example=true),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-15T20:02:13.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-15T20:45:28.000000Z")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-15T20:45:28.099680Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="8c5271cb-05c8-4f66-b7ca-5ac8b5e9338f"),
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
     *         description="Category not found"
     *     )
     * )
     */
    public function deactivate(int $id): JsonResponse
    {
        try {
            $category = $this->categoryService->deactivateCategory($id);

            return ApiResponse::success(
                'Category deactivated successfully',
                new ProductCategoryResource($category)
            );
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), null, 400);
        } catch (\Exception $e) {
            return ApiResponse::serverError(
                'Failed to deactivate category: ' . $e->getMessage()
            );
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/tenant/categories/{id}",
     *     summary="Delete category",
     *     description="Permanently deletes a category. The category must not have any child categories or associated products.",
     *     tags={"Tenant Product Categories"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Category ID",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         example=6
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Category deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Category deleted successfully"),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-15T20:57:15.272763Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="19e089f3-061d-4bbe-9730-64cf53e90532"),
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
     *         description="Category not found"
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Cannot delete category with child categories or products"
     *     )
     * )
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $this->categoryService->deleteCategory($id);

            return ApiResponse::success('Category deleted successfully');
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), null, 400);
        } catch (\Exception $e) {
            return ApiResponse::serverError(
                'Failed to delete category: ' . $e->getMessage()
            );
        }
    }
}
