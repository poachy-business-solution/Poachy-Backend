<?php

namespace App\Http\Controllers\Api\Tenant\Expenses;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Expense\StoreExpenseCategoryRequest;
use App\Http\Requests\Tenant\Expense\UpdateExpenseCategoryRequest;
use App\Http\Resources\Tenant\Expense\ExpenseCategoryCollection;
use App\Http\Resources\Tenant\Expense\ExpenseCategoryResource;
use App\Http\Responses\ApiResponse;
use App\Services\Tenant\Expenses\ExpenseCategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExpenseCategoryController extends Controller
{
    public function __construct(
        protected ExpenseCategoryService $service
    ) {}

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/expense-categories",
     *     summary="List all expense categories",
     *     description="Retrieve a flat list of all expense categories with optional filtering for active categories only. Returns hierarchical metadata (level, full_path, parent) but in a flat list structure.",
     *     operationId="listExpenseCategories",
     *     tags={"Expense Management"},
     *     security={{"sanctum": {}}},
     *     
     *     @OA\Parameter(
     *         name="active_only",
     *         in="query",
     *         description="Filter for active categories only",
     *         required=false,
     *         @OA\Schema(type="boolean", example=true)
     *     ),
     *     
     *     @OA\Response(
     *         response=200,
     *         description="Expense categories retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Expense categories retrieved successfully."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="data",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="Rent"),
     *                         @OA\Property(property="code", type="string", example="RENT", description="Auto-generated from name if not provided"),
     *                         @OA\Property(property="description", type="string", nullable=true, example="Monthly store or office rent payments"),
     *                         @OA\Property(property="parent_id", type="integer", nullable=true, example=null, description="Parent category ID for hierarchical structure"),
     *                         @OA\Property(property="is_recurring_eligible", type="boolean", example=true, description="Can be used for recurring expenses"),
     *                         @OA\Property(property="requires_receipt", type="boolean", example=true, description="Receipt attachment required for expenses in this category"),
     *                         @OA\Property(property="requires_approval", type="boolean", example=true, description="Expenses require manager approval"),
     *                         @OA\Property(property="is_active", type="boolean", example=true),
     *                         @OA\Property(property="display_order", type="integer", example=10, description="Sort order for display"),
     *                         @OA\Property(property="full_path", type="string", example="Rent", description="Full hierarchical path (e.g., 'Utilities > Electricity')"),
     *                         @OA\Property(property="level", type="integer", example=0, description="Depth in hierarchy (0 = root)"),
     *                         @OA\Property(property="has_children", type="boolean", example=false, description="Has subcategories"),
     *                         @OA\Property(property="has_expenses", type="boolean", example=false, description="Has expenses recorded"),
     *                         @OA\Property(property="is_deletable", type="boolean", example=true, description="Can be deleted (false if has children or expenses)"),
     *                         @OA\Property(
     *                             property="parent",
     *                             type="object",
     *                             nullable=true,
     *                             description="Parent category object (null for root categories)",
     *                             @OA\Property(property="id", type="integer", example=2),
     *                             @OA\Property(property="name", type="string", example="Utilities"),
     *                             @OA\Property(property="code", type="string", example="UTILITIES"),
     *                             @OA\Property(property="full_path", type="string", example="Utilities")
     *                         ),
     *                         @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-29T07:19:11.000000Z"),
     *                         @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-29T07:19:11.000000Z")
     *                     )
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-29T07:27:41.761944Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="9eca143e-bb22-43f8-95a9-02c31f80b247"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     
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
     *                 @OA\Property(property="request_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", nullable=true),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true)
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="An unexpected error occurred."),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", nullable=true),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true)
     *             )
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $activeOnly = $request->boolean('active_only', false);
            $categories = $this->service->getAllCategories($activeOnly);

            return ApiResponse::success(
                'Expense categories retrieved successfully.',
                new ExpenseCategoryCollection($categories)
            );
        } catch (\Exception $e) {
            return ApiResponse::error(
                'Failed to retrieve expense categories.',
                ['error' => $e->getMessage()],
                500
            );
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/expense-categories/tree",
     *     summary="Get expense categories tree structure",
     *     description="Retrieve expense categories in a hierarchical tree structure with nested children. Useful for displaying category dropdowns and navigation menus.",
     *     operationId="getExpenseCategoriesTree",
     *     tags={"Expense Management"},
     *     security={{"sanctum": {}}},
     *     
     *     @OA\Parameter(
     *         name="active_only",
     *         in="query",
     *         description="Filter for active categories only",
     *         required=false,
     *         @OA\Schema(type="boolean", example=true)
     *     ),
     *     
     *     @OA\Response(
     *         response=200,
     *         description="Category tree retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Category tree retrieved successfully."),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 description="Root categories with nested children",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=2),
     *                     @OA\Property(property="name", type="string", example="Utilities"),
     *                     @OA\Property(property="code", type="string", example="UTILITIES"),
     *                     @OA\Property(property="description", type="string", nullable=true, example="Electricity, water, internet, etc."),
     *                     @OA\Property(property="parent_id", type="integer", nullable=true, example=null),
     *                     @OA\Property(property="is_recurring_eligible", type="boolean", example=true),
     *                     @OA\Property(property="requires_receipt", type="boolean", example=true),
     *                     @OA\Property(property="requires_approval", type="boolean", example=false),
     *                     @OA\Property(property="is_active", type="boolean", example=true),
     *                     @OA\Property(property="display_order", type="integer", example=20),
     *                     @OA\Property(property="full_path", type="string", example="Utilities"),
     *                     @OA\Property(property="level", type="integer", example=0),
     *                     @OA\Property(property="has_children", type="boolean", example=true),
     *                     @OA\Property(property="has_expenses", type="boolean", example=false),
     *                     @OA\Property(property="is_deletable", type="boolean", example=false),
     *                     @OA\Property(property="parent", type="object", nullable=true, example=null),
     *                     @OA\Property(
     *                         property="children",
     *                         type="array",
     *                         description="Nested child categories",
     *                         @OA\Items(
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=3),
     *                             @OA\Property(property="name", type="string", example="Electricity"),
     *                             @OA\Property(property="code", type="string", example="ELECTRICITY"),
     *                             @OA\Property(property="description", type="string", nullable=true, example="Monthly electricity bill"),
     *                             @OA\Property(property="parent_id", type="integer", example=2),
     *                             @OA\Property(property="is_recurring_eligible", type="boolean", example=true),
     *                             @OA\Property(property="requires_receipt", type="boolean", example=true),
     *                             @OA\Property(property="requires_approval", type="boolean", example=false),
     *                             @OA\Property(property="is_active", type="boolean", example=true),
     *                             @OA\Property(property="display_order", type="integer", example=21),
     *                             @OA\Property(property="full_path", type="string", example="Utilities > Electricity"),
     *                             @OA\Property(property="level", type="integer", example=1),
     *                             @OA\Property(property="has_children", type="boolean", example=false),
     *                             @OA\Property(property="has_expenses", type="boolean", example=false),
     *                             @OA\Property(property="is_deletable", type="boolean", example=true),
     *                             @OA\Property(
     *                                 property="parent",
     *                                 type="object",
     *                                 description="Parent category reference",
     *                                 @OA\Property(property="id", type="integer", example=2),
     *                                 @OA\Property(property="name", type="string", example="Utilities"),
     *                                 @OA\Property(property="code", type="string", example="UTILITIES")
     *                             ),
     *                             @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-29T07:22:08.000000Z"),
     *                             @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-29T07:22:08.000000Z")
     *                         )
     *                     ),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-29T07:20:22.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-29T07:20:22.000000Z")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-29T07:28:47.696042Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="33f51ec7-5682-438c-8be8-8563c6fe0aef"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     
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
     *                 @OA\Property(property="request_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", nullable=true),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true)
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="An unexpected error occurred."),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", nullable=true),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true)
     *             )
     *         )
     *     )
     * )
     */
    public function tree(Request $request): JsonResponse
    {
        try {
            $activeOnly = $request->boolean('active_only', false);
            $tree = $this->service->getCategoryTree($activeOnly);

            return ApiResponse::success(
                'Category tree retrieved successfully.',
                ExpenseCategoryResource::collection($tree)
            );
        } catch (\Exception $e) {
            return ApiResponse::error(
                'Failed to retrieve category tree.',
                ['error' => $e->getMessage()],
                500
            );
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/expense-categories",
     *     summary="Create expense category",
     *     description="Create a new expense category. Categories can be nested by providing a parent_id. The code is auto-generated from name if not provided.",
     *     operationId="createExpenseCategory",
     *     tags={"Expense Management"},
     *     security={{"sanctum": {}}},
     *     
     *     @OA\RequestBody(
     *         required=true,
     *         description="Expense category details",
     *         @OA\JsonContent(
     *             required={"name"},
     *             type="object",
     *             @OA\Property(property="name", type="string", maxLength=255, example="Utilities", description="Category name (required)"),
     *             @OA\Property(property="code", type="string", maxLength=50, nullable=true, example="UTILITIES", description="Unique code (auto-generated from name if not provided, alphanumeric and dash/underscore only)"),
     *             @OA\Property(property="description", type="string", maxLength=1000, nullable=true, example="Electricity, water, internet, etc.", description="Category description"),
     *             @OA\Property(property="parent_id", type="integer", nullable=true, example=null, description="Parent category ID for hierarchical structure"),
     *             @OA\Property(property="is_recurring_eligible", type="boolean", example=true, description="Allow recurring expenses in this category"),
     *             @OA\Property(property="requires_receipt", type="boolean", example=true, description="Require receipt attachment for expenses"),
     *             @OA\Property(property="requires_approval", type="boolean", example=false, description="Require manager approval for expenses"),
     *             @OA\Property(property="is_active", type="boolean", example=true, description="Category active status"),
     *             @OA\Property(property="display_order", type="integer", minimum=0, example=20, description="Sort order for display")
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=201,
     *         description="Expense category created successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Expense category created successfully."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=2),
     *                 @OA\Property(property="name", type="string", example="Utilities"),
     *                 @OA\Property(property="code", type="string", example="UTILITIES"),
     *                 @OA\Property(property="description", type="string", nullable=true, example="Electricity, water, internet, etc."),
     *                 @OA\Property(property="parent_id", type="integer", nullable=true, example=null),
     *                 @OA\Property(property="is_recurring_eligible", type="boolean", example=true),
     *                 @OA\Property(property="requires_receipt", type="boolean", example=true),
     *                 @OA\Property(property="requires_approval", type="boolean", example=false),
     *                 @OA\Property(property="is_active", type="boolean", example=true),
     *                 @OA\Property(property="display_order", type="integer", example=20),
     *                 @OA\Property(property="full_path", type="string", example="Utilities"),
     *                 @OA\Property(property="level", type="integer", example=0),
     *                 @OA\Property(property="has_children", type="boolean", example=false),
     *                 @OA\Property(property="has_expenses", type="boolean", example=false),
     *                 @OA\Property(property="is_deletable", type="boolean", example=true),
     *                 @OA\Property(property="parent", type="object", nullable=true, example=null),
     *                 @OA\Property(property="children", type="array", @OA\Items(type="object")),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-29T07:20:22.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-29T07:20:22.000000Z")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-29T07:20:22.747100Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="458b3965-1938-4443-b382-d955234bd78a"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     
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
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="name",
     *                     type="array",
     *                     @OA\Items(type="string", example="The name field is required.")
     *                 ),
     *                 @OA\Property(
     *                     property="code",
     *                     type="array",
     *                     @OA\Items(type="string", example="The code has already been taken.")
     *                 ),
     *                 @OA\Property(
     *                     property="parent_id",
     *                     type="array",
     *                     @OA\Items(type="string", example="The selected parent id is invalid.")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true)
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="An unexpected error occurred."),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", nullable=true),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true)
     *             )
     *         )
     *     )
     * )
     */
    public function store(StoreExpenseCategoryRequest $request): JsonResponse
    {
        try {
            $category = $this->service->createCategory($request->validated());

            return ApiResponse::created(
                'Expense category created successfully.',
                new ExpenseCategoryResource($category)
            );
        } catch (\Exception $e) {
            return ApiResponse::error(
                'Failed to create expense category.',
                ['error' => $e->getMessage()],
                500
            );
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/expense-categories/{id}",
     *     summary="Get expense category details",
     *     description="Retrieve detailed information about a specific expense category including parent and children relationships",
     *     operationId="getExpenseCategory",
     *     tags={"Expense Management"},
     *     security={{"sanctum": {}}},
     *     
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Expense category ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     
     *     @OA\Response(
     *         response=200,
     *         description="Expense category retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Expense category retrieved successfully."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Rent"),
     *                 @OA\Property(property="code", type="string", example="RENT"),
     *                 @OA\Property(property="description", type="string", nullable=true, example="Monthly store or office rent payments"),
     *                 @OA\Property(property="parent_id", type="integer", nullable=true, example=null),
     *                 @OA\Property(property="is_recurring_eligible", type="boolean", example=true),
     *                 @OA\Property(property="requires_receipt", type="boolean", example=true),
     *                 @OA\Property(property="requires_approval", type="boolean", example=true),
     *                 @OA\Property(property="is_active", type="boolean", example=true),
     *                 @OA\Property(property="display_order", type="integer", example=10),
     *                 @OA\Property(property="full_path", type="string", example="Rent"),
     *                 @OA\Property(property="level", type="integer", example=0),
     *                 @OA\Property(property="has_children", type="boolean", example=false),
     *                 @OA\Property(property="has_expenses", type="boolean", example=false),
     *                 @OA\Property(property="is_deletable", type="boolean", example=true),
     *                 @OA\Property(property="parent", type="object", nullable=true, example=null),
     *                 @OA\Property(property="children", type="array", @OA\Items(type="object")),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time")
     *             ),
     *             @OA\Property(property="meta", type="object")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Category not found"),
     *     @OA\Response(response=500, description="Internal server error")
     * )
     */
    public function show(int $id): JsonResponse
    {
        try {
            $category = $this->service->getCategoryById($id);

            if (!$category) {
                return ApiResponse::notFound('Expense category not found.');
            }

            return ApiResponse::success(
                'Expense category retrieved successfully.',
                new ExpenseCategoryResource($category)
            );
        } catch (\Exception $e) {
            return ApiResponse::error(
                'Failed to retrieve expense category.',
                ['error' => $e->getMessage()],
                500
            );
        }
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/tenant/expense-categories/{id}",
     *     summary="Update expense category",
     *     description="Update expense category details. All fields are optional. Cannot set self as parent.",
     *     operationId="updateExpenseCategory",
     *     tags={"Expense Management"},
     *     security={{"sanctum": {}}},
     *     
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Expense category ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="name", type="string", maxLength=255, example="Rent UPDATED"),
     *             @OA\Property(property="code", type="string", maxLength=50, example="RENT_NEW"),
     *             @OA\Property(property="description", type="string", maxLength=1000, nullable=true),
     *             @OA\Property(property="parent_id", type="integer", nullable=true),
     *             @OA\Property(property="is_recurring_eligible", type="boolean"),
     *             @OA\Property(property="requires_receipt", type="boolean"),
     *             @OA\Property(property="requires_approval", type="boolean"),
     *             @OA\Property(property="is_active", type="boolean"),
     *             @OA\Property(property="display_order", type="integer", minimum=0)
     *         )
     *     ),
     *     
     *     @OA\Response(response=200, description="Category updated successfully"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Category not found"),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=500, description="Internal server error")
     * )
     */
    public function update(UpdateExpenseCategoryRequest $request, int $id): JsonResponse
    {
        try {
            $category = $this->service->updateCategory($id, $request->validated());

            return ApiResponse::success(
                'Expense category updated successfully.',
                new ExpenseCategoryResource($category)
            );
        } catch (\Exception $e) {
            return ApiResponse::error(
                'Failed to update expense category.',
                ['error' => $e->getMessage()],
                500
            );
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/tenant/expense-categories/{id}",
     *     summary="Delete expense category",
     *     description="Delete an expense category. Cannot delete if category has children or associated expenses.",
     *     operationId="deleteExpenseCategory",
     *     tags={"Expense Management"},
     *     security={{"sanctum": {}}},
     *     
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Expense category ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     
     *     @OA\Response(
     *         response=200,
     *         description="Expense category deleted successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Expense category deleted successfully."),
     *             @OA\Property(property="meta", type="object")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Category not found"),
     *     @OA\Response(response=409, description="Cannot delete - has children or expenses"),
     *     @OA\Response(response=500, description="Internal server error")
     * )
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $this->service->deleteCategory($id);

            return ApiResponse::success('Expense category deleted successfully.');
        } catch (\Exception $e) {
            return ApiResponse::error(
                'Failed to delete expense category.',
                ['error' => $e->getMessage()],
                500
            );
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/expense-categories/{id}/children",
     *     summary="Get category children",
     *     description="Retrieve all direct children (subcategories) of a specific expense category. Returns only immediate children, not nested descendants.",
     *     operationId="getExpenseCategoryChildren",
     *     tags={"Expense Management"},
     *     security={{"sanctum": {}}},
     *     
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Parent category ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=2)
     *     ),
     *     @OA\Parameter(
     *         name="active_only",
     *         in="query",
     *         description="Filter for active categories only",
     *         required=false,
     *         @OA\Schema(type="boolean", example=true)
     *     ),
     *     
     *     @OA\Response(
     *         response=200,
     *         description="Category children retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Category children retrieved successfully."),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 description="Array of child categories",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=3),
     *                     @OA\Property(property="name", type="string", example="Electricity"),
     *                     @OA\Property(property="code", type="string", example="ELECTRICITY"),
     *                     @OA\Property(property="description", type="string", nullable=true, example="Monthly electricity bill"),
     *                     @OA\Property(property="parent_id", type="integer", example=2),
     *                     @OA\Property(property="is_recurring_eligible", type="boolean", example=true),
     *                     @OA\Property(property="requires_receipt", type="boolean", example=true),
     *                     @OA\Property(property="requires_approval", type="boolean", example=false),
     *                     @OA\Property(property="is_active", type="boolean", example=true),
     *                     @OA\Property(property="display_order", type="integer", example=21),
     *                     @OA\Property(property="full_path", type="string", example="Utilities > Electricity"),
     *                     @OA\Property(property="level", type="integer", example=1),
     *                     @OA\Property(property="has_children", type="boolean", example=false),
     *                     @OA\Property(property="has_expenses", type="boolean", example=false),
     *                     @OA\Property(property="is_deletable", type="boolean", example=true),
     *                     @OA\Property(
     *                         property="parent",
     *                         type="object",
     *                         description="Parent category reference",
     *                         @OA\Property(property="id", type="integer", example=2),
     *                         @OA\Property(property="name", type="string", example="Utilities"),
     *                         @OA\Property(property="code", type="string", example="UTILITIES"),
     *                         @OA\Property(property="description", type="string", nullable=true, example="Electricity, water, internet, etc."),
     *                         @OA\Property(property="parent_id", type="integer", nullable=true, example=null),
     *                         @OA\Property(property="is_recurring_eligible", type="boolean", example=true),
     *                         @OA\Property(property="requires_receipt", type="boolean", example=true),
     *                         @OA\Property(property="requires_approval", type="boolean", example=false),
     *                         @OA\Property(property="is_active", type="boolean", example=true),
     *                         @OA\Property(property="display_order", type="integer", example=20),
     *                         @OA\Property(property="full_path", type="string", example="Utilities"),
     *                         @OA\Property(property="level", type="integer", example=0),
     *                         @OA\Property(property="has_children", type="boolean", example=true),
     *                         @OA\Property(property="has_expenses", type="boolean", example=false),
     *                         @OA\Property(property="is_deletable", type="boolean", example=false),
     *                         @OA\Property(property="parent", type="object", nullable=true, example=null),
     *                         @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-29T07:20:22.000000Z"),
     *                         @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-29T07:20:22.000000Z")
     *                     ),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-29T07:22:08.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-29T07:22:08.000000Z")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-29T07:52:31.733741Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="59c81808-89b7-4abe-97df-c4d49f926f49"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     
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
     *                 @OA\Property(property="request_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", nullable=true),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true)
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=404,
     *         description="Category not found",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="The requested resource was not found."),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true)
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="An unexpected error occurred."),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", nullable=true),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true)
     *             )
     *         )
     *     )
     * )
     */
    public function children(Request $request, int $id): JsonResponse
    {
        try {
            $activeOnly = $request->boolean('active_only', false);
            $children = $this->service->getCategoryChildren($id, $activeOnly);

            return ApiResponse::success(
                'Category children retrieved successfully.',
                ExpenseCategoryResource::collection($children)
            );
        } catch (\Exception $e) {
            return ApiResponse::error(
                'Failed to retrieve category children.',
                ['error' => $e->getMessage()],
                500
            );
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/expense-categories/{id}/toggle-active",
     *     summary="Toggle category active status",
     *     description="Toggle the is_active status of an expense category between true and false",
     *     operationId="toggleExpenseCategoryStatus",
     *     tags={"Expense Management"},
     *     security={{"sanctum": {}}},
     *     
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Expense category ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=2)
     *     ),
     *     
     *     @OA\Response(
     *         response=200,
     *         description="Category status updated successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Category status updated successfully."),
     *             @OA\Property(property="meta", type="object")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Category not found"),
     *     @OA\Response(response=500, description="Internal server error")
     * )
     */
    public function toggleActive(int $id): JsonResponse
    {
        try {
            $category = $this->service->toggleActiveStatus($id);

            return ApiResponse::success(
                'Category status updated successfully.',
                // new ExpenseCategoryResource($category)
            );
        } catch (\Exception $e) {
            return ApiResponse::error(
                'Failed to update category status.',
                ['error' => $e->getMessage()],
                500
            );
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/expense-categories/recurring-eligible",
     *     summary="Get recurring-eligible categories",
     *     description="Retrieve all expense categories that are marked as recurring-eligible (is_recurring_eligible = true). Useful for populating dropdowns when creating recurring expenses.",
     *     operationId="getRecurringEligibleCategories",
     *     tags={"Expense Management"},
     *     security={{"sanctum": {}}},
     *     
     *     @OA\Response(
     *         response=200,
     *         description="Recurring-eligible categories retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Recurring-eligible categories retrieved successfully."),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 description="Array of categories with is_recurring_eligible = true",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=6),
     *                     @OA\Property(property="name", type="string", example="Rent"),
     *                     @OA\Property(property="code", type="string", example="RENT"),
     *                     @OA\Property(property="description", type="string", example="Monthly store or office rent payments"),
     *                     @OA\Property(property="parent_id", type="integer", nullable=true, example=null),
     *                     @OA\Property(property="is_recurring_eligible", type="boolean", example=true),
     *                     @OA\Property(property="requires_receipt", type="boolean", example=true),
     *                     @OA\Property(property="requires_approval", type="boolean", example=true),
     *                     @OA\Property(property="is_active", type="boolean", example=true),
     *                     @OA\Property(property="display_order", type="integer", example=10),
     *                     @OA\Property(property="full_path", type="string", example="Rent"),
     *                     @OA\Property(property="level", type="integer", example=0),
     *                     @OA\Property(property="has_children", type="boolean", example=false),
     *                     @OA\Property(property="has_expenses", type="boolean", example=false),
     *                     @OA\Property(property="is_deletable", type="boolean", example=true),
     *                     @OA\Property(
     *                         property="parent",
     *                         type="object",
     *                         nullable=true,
     *                         @OA\Property(property="id", type="integer", example=2),
     *                         @OA\Property(property="name", type="string", example="Utilities"),
     *                         @OA\Property(property="code", type="string", example="UTILITIES"),
     *                         @OA\Property(property="description", type="string", example="Electricity, water, internet, etc."),
     *                         @OA\Property(property="parent_id", type="integer", nullable=true, example=null),
     *                         @OA\Property(property="is_recurring_eligible", type="boolean", example=true),
     *                         @OA\Property(property="requires_receipt", type="boolean", example=true),
     *                         @OA\Property(property="requires_approval", type="boolean", example=false),
     *                         @OA\Property(property="is_active", type="boolean", example=false),
     *                         @OA\Property(property="display_order", type="integer", example=20),
     *                         @OA\Property(property="full_path", type="string", example="Utilities"),
     *                         @OA\Property(property="level", type="integer", example=0),
     *                         @OA\Property(property="has_children", type="boolean", example=true),
     *                         @OA\Property(property="has_expenses", type="boolean", example=false),
     *                         @OA\Property(property="is_deletable", type="boolean", example=false),
     *                         @OA\Property(property="parent", type="object", nullable=true, example=null),
     *                         @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-29T07:20:22.000000Z"),
     *                         @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-29T07:55:39.000000Z")
     *                     ),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-29T07:49:04.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-29T07:49:04.000000Z")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-29T08:02:04.183950Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="e7df9752-cbc2-4460-a09f-d72dc81e015d"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=500, description="Internal server error")
     * )
     */
    public function recurringEligible(Request $request): JsonResponse
    {
        try {
            $activeOnly = $request->boolean('active_only', true);
            $categories = $this->service->getRecurringEligibleCategories($activeOnly);

            return ApiResponse::success(
                'Recurring-eligible categories retrieved successfully.',
                ExpenseCategoryResource::collection($categories)
            );
        } catch (\Exception $e) {
            return ApiResponse::error(
                'Failed to retrieve recurring-eligible categories.',
                ['error' => $e->getMessage()],
                500
            );
        }
    }
}
