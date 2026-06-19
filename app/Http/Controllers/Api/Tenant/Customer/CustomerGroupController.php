<?php

namespace App\Http\Controllers\Api\Tenant\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Customer\AddMemberToGroupRequest;
use App\Http\Requests\Tenant\Customer\BulkAddMembersRequest;
use App\Http\Requests\Tenant\Customer\StoreCustomerGroupRequest;
use App\Http\Requests\Tenant\Customer\UpdateCustomerGroupRequest;
use App\Http\Resources\Tenant\Customer\CustomerGroupResource;
use App\Http\Resources\Tenant\Customer\CustomerResource;
use App\Http\Responses\ApiResponse;
use App\Services\Tenant\Customer\CustomerGroupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerGroupController extends Controller
{
    public function __construct(
        private readonly CustomerGroupService $groupService
    ) {}

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/customer-groups",
     *     summary="List all customer groups",
     *     description="Retrieve a paginated list of customer groups with optional filtering and sorting",
     *     operationId="listCustomerGroups",
     *     tags={"Customer Groups"},
     *     security={{"sanctum": {}}},
     *     
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search by group name or description",
     *         required=false,
     *         @OA\Schema(type="string", example="VIP")
     *     ),
     *     @OA\Parameter(
     *         name="is_active",
     *         in="query",
     *         description="Filter by active status",
     *         required=false,
     *         @OA\Schema(type="boolean", example=true)
     *     ),
     *     @OA\Parameter(
     *         name="requires_approval",
     *         in="query",
     *         description="Filter by approval requirement",
     *         required=false,
     *         @OA\Schema(type="boolean", example=true)
     *     ),
     *     @OA\Parameter(
     *         name="sort_by",
     *         in="query",
     *         description="Field to sort by",
     *         required=false,
     *         @OA\Schema(
     *             type="string",
     *             enum={"created_at", "name", "discount_percentage"},
     *             default="created_at"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="sort_order",
     *         in="query",
     *         description="Sort order",
     *         required=false,
     *         @OA\Schema(
     *             type="string",
     *             enum={"asc", "desc"},
     *             default="desc"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number for pagination",
     *         required=false,
     *         @OA\Schema(type="integer", default=1, minimum=1)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of items per page",
     *         required=false,
     *         @OA\Schema(type="integer", default=15, minimum=1, maximum=100)
     *     ),
     *     
     *     @OA\Response(
     *         response=200,
     *         description="Customer groups retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Customer groups retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="data",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=2),
     *                         @OA\Property(property="name", type="string", example="Basic Members"),
     *                         @OA\Property(property="description", type="string", example="Basic customers with 3% discount", nullable=true),
     *                         @OA\Property(property="discount_percentage", type="number", format="float", example=3),
     *                         @OA\Property(property="requires_approval", type="boolean", example=true),
     *                         @OA\Property(property="is_active", type="boolean", example=true),
     *                         @OA\Property(property="members_count", type="integer", example=1, description="Number of customers in this group"),
     *                         @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-26T08:45:33.000000Z"),
     *                         @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-26T08:45:33.000000Z")
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="pagination",
     *                     type="object",
     *                     @OA\Property(property="current_page", type="integer", example=1),
     *                     @OA\Property(property="last_page", type="integer", example=1),
     *                     @OA\Property(property="per_page", type="integer", example=15),
     *                     @OA\Property(property="total", type="integer", example=2),
     *                     @OA\Property(property="from", type="integer", example=1, nullable=true),
     *                     @OA\Property(property="to", type="integer", example=2, nullable=true)
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-26T11:22:47.960710Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="c5d23963-1f11-46a6-8d06-b6f3d0ccca93"),
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
        $filters = [
            'search' => $request->input('search'),
            'is_active' => $request->input('is_active'),
            'requires_approval' => $request->input('requires_approval'),
            'sort_by' => $request->input('sort_by', 'created_at'),
            'sort_order' => $request->input('sort_order', 'desc'),
        ];

        $perPage = (int) $request->input('per_page', 15);
        $groups = $this->groupService->getPaginatedGroups($filters, $perPage);

        return ApiResponse::paginated(
            CustomerGroupResource::collection($groups),
            'Customer groups retrieved successfully'
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/customer-groups",
     *     summary="Create a new customer group",
     *     description="Create a new customer group with discount settings and approval requirements",
     *     operationId="createCustomerGroup",
     *     tags={"Customer Groups"},
     *     security={{"sanctum": {}}},
     *     
     *     @OA\RequestBody(
     *         required=true,
     *         description="Customer group details",
     *         @OA\JsonContent(
     *             required={"name"},
     *             type="object",
     *             @OA\Property(property="name", type="string", maxLength=255, example="VIP Members", description="Group name (must be unique)"),
     *             @OA\Property(property="description", type="string", maxLength=1000, example="Premium customers with 10% discount", nullable=true, description="Group description"),
     *             @OA\Property(property="discount_percentage", type="number", format="float", minimum=0, maximum=100, example=10.00, nullable=true, description="Automatic discount percentage for group members"),
     *             @OA\Property(property="requires_approval", type="boolean", example=true, nullable=true, description="Whether joining this group requires approval"),
     *             @OA\Property(property="is_active", type="boolean", example=true, nullable=true, description="Whether this group is active")
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=201,
     *         description="Customer group created successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Customer group created successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=2),
     *                 @OA\Property(property="name", type="string", example="VIP Members"),
     *                 @OA\Property(property="description", type="string", example="Premium customers with 10% discount", nullable=true),
     *                 @OA\Property(property="discount_percentage", type="number", format="float", example=10),
     *                 @OA\Property(property="requires_approval", type="boolean", example=true),
     *                 @OA\Property(property="is_active", type="boolean", example=true),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-26T08:45:33.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-26T08:45:33.000000Z")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-26T08:45:33.967053Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="dc681b94-7007-4c5d-a744-825eb25b1419"),
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
     *                     property="discount_percentage",
     *                     type="array",
     *                     @OA\Items(type="string", example="The discount percentage must be between 0 and 100.")
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
    public function store(StoreCustomerGroupRequest $request): JsonResponse
    {
        $group = $this->groupService->createGroup($request->validated());

        return ApiResponse::created(
            'Customer group created successfully',
            new CustomerGroupResource($group)
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/customer-groups/{id}",
     *     summary="Get customer group details",
     *     description="Retrieve detailed information about a specific customer group",
     *     operationId="getCustomerGroup",
     *     tags={"Customer Groups"},
     *     security={{"sanctum": {}}},
     *     
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Customer Group ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=2)
     *     ),
     *     
     *     @OA\Response(
     *         response=200,
     *         description="Customer group retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Customer group retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=2),
     *                 @OA\Property(property="name", type="string", example="Basic Members"),
     *                 @OA\Property(property="description", type="string", example="Basic customers with 3% discount", nullable=true),
     *                 @OA\Property(property="discount_percentage", type="number", format="float", example=3),
     *                 @OA\Property(property="requires_approval", type="boolean", example=true),
     *                 @OA\Property(property="is_active", type="boolean", example=true),
     *                 @OA\Property(property="members_count", type="integer", example=1, description="Number of customers in this group"),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-26T08:45:33.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-26T08:45:33.000000Z")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-26T11:27:47.555584Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="52f1efc8-6472-42ee-8802-a62c19c0ffd7"),
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
     *         description="Customer group not found",
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
    public function show(int $id): JsonResponse
    {
        $group = $this->groupService->getGroupById($id);

        if (!$group) {
            return ApiResponse::notFound('Customer group not found');
        }

        return ApiResponse::success(
            'Customer group retrieved successfully',
            new CustomerGroupResource($group)
        );
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/tenant/customer-groups/{id}",
     *     summary="Update customer group",
     *     description="Update customer group information. All fields are optional - only provide fields that need updating.",
     *     operationId="updateCustomerGroup",
     *     tags={"Customer Groups"},
     *     security={{"sanctum": {}}},
     *     
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Customer Group ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=2)
     *     ),
     *     
     *     @OA\RequestBody(
     *         required=false,
     *         description="Customer group fields to update (all optional)",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="name", type="string", maxLength=255, example="Updated Group Name", description="Group name (must be unique)"),
     *             @OA\Property(property="description", type="string", maxLength=1000, example="Updated description", nullable=true, description="Group description"),
     *             @OA\Property(property="discount_percentage", type="number", format="float", minimum=0, maximum=100, example=12.00, nullable=true, description="Automatic discount percentage for group members"),
     *             @OA\Property(property="requires_approval", type="boolean", example=false, nullable=true, description="Whether joining this group requires approval"),
     *             @OA\Property(property="is_active", type="boolean", example=true, nullable=true, description="Whether this group is active")
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=200,
     *         description="Customer group updated successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Customer group updated successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=2),
     *                 @OA\Property(property="name", type="string", example="Basic Members"),
     *                 @OA\Property(property="description", type="string", example="Updated description", nullable=true),
     *                 @OA\Property(property="discount_percentage", type="number", format="float", example=12),
     *                 @OA\Property(property="requires_approval", type="boolean", example=true),
     *                 @OA\Property(property="is_active", type="boolean", example=true),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-26T08:45:33.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-26T11:35:41.000000Z")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-26T11:35:41.248973Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="c4c70761-ef17-4d72-a8d4-0ab936377fda"),
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
     *         description="Customer group not found",
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
     *                     @OA\Items(type="string", example="The name has already been taken.")
     *                 ),
     *                 @OA\Property(
     *                     property="discount_percentage",
     *                     type="array",
     *                     @OA\Items(type="string", example="The discount percentage must be between 0 and 100.")
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
    public function update(UpdateCustomerGroupRequest $request, int $id): JsonResponse
    {
        $group = $this->groupService->getGroupById($id);

        if (!$group) {
            return ApiResponse::notFound('Customer group not found');
        }

        $updatedGroup = $this->groupService->updateGroup($group, $request->validated());

        return ApiResponse::success(
            'Customer group updated successfully',
            new CustomerGroupResource($updatedGroup)
        );
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/tenant/customer-groups/{id}/toggle",
     *     summary="Toggle customer group active status",
     *     description="Toggle customer group between active and inactive status. If group is active, it becomes inactive and vice versa.",
     *     operationId="toggleCustomerGroupStatus",
     *     tags={"Customer Groups"},
     *     security={{"sanctum": {}}},
     *     
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Customer Group ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=2)
     *     ),
     *     
     *     @OA\Response(
     *         response=200,
     *         description="Customer group status updated successfully - Active to Inactive",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Customer group status updated successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="status",
     *                     type="string",
     *                     enum={"active", "inactive"},
     *                     example="inactive",
     *                     description="New status after toggle"
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-26T11:42:30.789876Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="810b4dbd-47db-48f0-a966-61c1bdb2ffb2"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response="200-active",
     *         description="Customer group status updated successfully - Inactive to Active",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Customer group status updated successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="status",
     *                     type="string",
     *                     enum={"active", "inactive"},
     *                     example="active",
     *                     description="New status after toggle"
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-26T11:44:20.122440Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="a86aa3fa-6d3a-48ef-b798-69120ca68fa7"),
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
     *         description="Customer group not found",
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
    public function toggleStatus(int $id): JsonResponse
    {
        $group = $this->groupService->getGroupById($id);

        if (!$group) {
            return ApiResponse::notFound('Customer group not found');
        }

        $updatedGroup = $this->groupService->toggleGroupStatus($group);

        $statusText = $updatedGroup->is_active ? 'active' : 'inactive';

        return ApiResponse::success(
            'Customer group status updated successfully',
            ['status' => $statusText]
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/customer-groups/{id}/members",
     *     summary="List group members",
     *     description="Retrieve a paginated list of all customers belonging to a specific customer group",
     *     operationId="listCustomerGroupMembers",
     *     tags={"Customer Groups"},
     *     security={{"sanctum": {}}},
     *     
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Customer Group ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=2)
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number for pagination",
     *         required=false,
     *         @OA\Schema(type="integer", default=1, minimum=1)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of items per page",
     *         required=false,
     *         @OA\Schema(type="integer", default=15, minimum=1, maximum=100)
     *     ),
     *     
     *     @OA\Response(
     *         response=200,
     *         description="Group members retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Group members retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="data",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=3),
     *                         @OA\Property(property="customer_number", type="string", example="CUST-2025-000002"),
     *                         @OA\Property(property="name", type="string", example="John Doe"),
     *                         @OA\Property(property="email", type="string", format="email", example="john.doe@example.com", nullable=true),
     *                         @OA\Property(property="phone", type="string", example="+254712345601"),
     *                         @OA\Property(property="date_of_birth", type="string", format="date", example="1990-05-15", nullable=true),
     *                         @OA\Property(property="address", type="string", example="123 Kenyatta Avenue, Nairobi", nullable=true),
     *                         @OA\Property(
     *                             property="customer_type",
     *                             type="object",
     *                             @OA\Property(property="value", type="string", example="regular"),
     *                             @OA\Property(property="label", type="string", example="Regular Customer")
     *                         ),
     *                         @OA\Property(property="loyalty_points", type="number", format="float", example=150),
     *                         @OA\Property(property="total_lifetime_purchases", type="number", format="float", example=25000),
     *                         @OA\Property(property="total_visits", type="integer", example=12),
     *                         @OA\Property(property="credit_limit", type="number", format="float", example=10000),
     *                         @OA\Property(property="current_debt", type="number", format="float", example=0),
     *                         @OA\Property(property="available_credit", type="number", format="float", example=10000),
     *                         @OA\Property(property="is_active", type="boolean", example=true),
     *                         @OA\Property(property="registered_at", type="string", format="date-time", example="2025-12-26T12:27:13.000000Z"),
     *                         @OA\Property(
     *                             property="preferred_store",
     *                             type="object",
     *                             nullable=true,
     *                             @OA\Property(property="id", type="integer", nullable=true, example=null),
     *                             @OA\Property(property="name", type="string", nullable=true, example=null),
     *                             @OA\Property(property="code", type="string", nullable=true, example=null)
     *                         ),
     *                         @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-26T12:27:13.000000Z"),
     *                         @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-26T12:27:13.000000Z")
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="pagination",
     *                     type="object",
     *                     @OA\Property(property="current_page", type="integer", example=1),
     *                     @OA\Property(property="last_page", type="integer", example=1),
     *                     @OA\Property(property="per_page", type="integer", example=15),
     *                     @OA\Property(property="total", type="integer", example=4),
     *                     @OA\Property(property="from", type="integer", example=1, nullable=true),
     *                     @OA\Property(property="to", type="integer", example=4, nullable=true)
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-26T12:37:32.959556Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="9a90ee8f-619f-41fb-93b7-75db5166299c"),
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
     *         description="Customer group not found",
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
    public function members(Request $request, int $id): JsonResponse
    {
        $group = $this->groupService->getGroupById($id);

        if (!$group) {
            return ApiResponse::notFound('Customer group not found');
        }

        $perPage = (int) $request->input('per_page', 15);
        $members = $this->groupService->getGroupMembers($group, $perPage);

        return ApiResponse::paginated(
            CustomerResource::collection($members),
            'Group members retrieved successfully'
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/customer-groups/{id}/members",
     *     summary="Add customer to group",
     *     description="Add a single customer to a customer group",
     *     operationId="addCustomerToGroup",
     *     tags={"Customer Groups"},
     *     security={{"sanctum": {}}},
     *     
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Customer Group ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=2)
     *     ),
     *     
     *     @OA\RequestBody(
     *         required=true,
     *         description="Customer to add to group",
     *         @OA\JsonContent(
     *             required={"customer_id"},
     *             type="object",
     *             @OA\Property(property="customer_id", type="integer", example=2, description="ID of the customer to add to the group")
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=200,
     *         description="Customer added to group successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Customer added to group successfully"),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-26T08:50:35.256865Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="3fc47e65-1f41-4028-8e6a-f0f2f573fa46"),
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
     *         description="Customer group or customer not found",
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
     *         response=409,
     *         description="Customer already in group",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="A record with similar data already exists."),
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
     *                     property="customer_id",
     *                     type="array",
     *                     @OA\Items(type="string", example="The customer id field is required.")
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
    public function addMember(AddMemberToGroupRequest $request, int $id): JsonResponse
    {
        $group = $this->groupService->getGroupById($id);

        if (!$group) {
            return ApiResponse::notFound('Customer group not found');
        }

        try {
            $this->groupService->addMemberToGroup($group, $request->input('customer_id'));

            return ApiResponse::success('Customer added to group successfully');
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), null, 422);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/tenant/customer-groups/{id}/members/{customerId}",
     *     summary="Remove customer from group",
     *     description="Remove a customer from a customer group",
     *     operationId="removeCustomerFromGroup",
     *     tags={"Customer Groups"},
     *     security={{"sanctum": {}}},
     *     
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Customer Group ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=2)
     *     ),
     *     @OA\Parameter(
     *         name="customerId",
     *         in="path",
     *         description="Customer ID to remove from the group",
     *         required=true,
     *         @OA\Schema(type="integer", example=2)
     *     ),
     *     
     *     @OA\Response(
     *         response=200,
     *         description="Customer removed from group successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Customer removed from group successfully"),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-26T11:57:46.539339Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="4f280e3f-cdfd-4a81-ad55-235b8ddd0feb"),
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
     *         description="Customer group, customer, or membership not found",
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
    public function removeMember(int $id, int $customerId): JsonResponse
    {
        $group = $this->groupService->getGroupById($id);

        if (!$group) {
            return ApiResponse::notFound('Customer group not found');
        }

        $removed = $this->groupService->removeMemberFromGroup($group, $customerId);

        if (!$removed) {
            return ApiResponse::notFound('Customer not found in this group');
        }

        return ApiResponse::success('Customer removed from group successfully');
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/customer-groups/{id}/members/bulk",
     *     summary="Bulk add customers to group",
     *     description="Add multiple customers to a customer group in a single operation. Returns detailed results about which customers were added, skipped, or failed.",
     *     operationId="bulkAddCustomersToGroup",
     *     tags={"Customer Groups"},
     *     security={{"sanctum": {}}},
     *     
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Customer Group ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=2)
     *     ),
     *     
     *     @OA\RequestBody(
     *         required=true,
     *         description="Array of customer IDs to add to group",
     *         @OA\JsonContent(
     *             required={"customer_ids"},
     *             type="object",
     *             @OA\Property(
     *                 property="customer_ids",
     *                 type="array",
     *                 @OA\Items(type="integer"),
     *                 example={3, 4, 5, 6},
     *                 description="Array of customer IDs to add to the group",
     *                 minItems=1
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=200,
     *         description="Bulk operation completed",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Bulk operation completed"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="added", type="integer", example=4, description="Number of customers successfully added"),
     *                 @OA\Property(property="skipped", type="integer", example=0, description="Number of customers skipped (already in group)"),
     *                 @OA\Property(property="failed", type="integer", example=0, description="Number of customers that failed to add"),
     *                 @OA\Property(
     *                     property="details",
     *                     type="object",
     *                     @OA\Property(
     *                         property="added",
     *                         type="array",
     *                         @OA\Items(type="integer"),
     *                         example={3, 4, 5, 6},
     *                         description="IDs of customers successfully added"
     *                     ),
     *                     @OA\Property(
     *                         property="skipped",
     *                         type="array",
     *                         @OA\Items(type="integer"),
     *                         example={},
     *                         description="IDs of customers skipped (already in group)"
     *                     ),
     *                     @OA\Property(
     *                         property="failed",
     *                         type="array",
     *                         @OA\Items(type="integer"),
     *                         example={},
     *                         description="IDs of customers that failed to add"
     *                     )
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-26T12:34:11.380854Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="c953cc84-badf-47a1-9744-c4e5d4419e3c"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response="200-partial",
     *         description="Bulk operation completed with some skipped/failed",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Bulk operation completed"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="added", type="integer", example=2),
     *                 @OA\Property(property="skipped", type="integer", example=1),
     *                 @OA\Property(property="failed", type="integer", example=1),
     *                 @OA\Property(
     *                     property="details",
     *                     type="object",
     *                     @OA\Property(
     *                         property="added",
     *                         type="array",
     *                         @OA\Items(type="integer"),
     *                         example={3, 4},
     *                         description="IDs of customers successfully added"
     *                     ),
     *                     @OA\Property(
     *                         property="skipped",
     *                         type="array",
     *                         @OA\Items(type="integer"),
     *                         example={5},
     *                         description="IDs of customers skipped (already in group)"
     *                     ),
     *                     @OA\Property(
     *                         property="failed",
     *                         type="array",
     *                         @OA\Items(type="integer"),
     *                         example={99},
     *                         description="IDs of customers that failed to add (not found)"
     *                     )
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
     *         description="Customer group not found",
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
     *                     property="customer_ids",
     *                     type="array",
     *                     @OA\Items(type="string", example="The customer ids field is required.")
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
    public function bulkAddMembers(BulkAddMembersRequest $request, int $id): JsonResponse
    {
        $group = $this->groupService->getGroupById($id);

        if (!$group) {
            return ApiResponse::notFound('Customer group not found');
        }

        $results = $this->groupService->bulkAddMembers(
            $group,
            $request->input('customer_ids')
        );

        return ApiResponse::success(
            'Bulk operation completed',
            [
                'added' => count($results['added']),
                'skipped' => count($results['skipped']),
                'failed' => count($results['failed']),
                'details' => $results,
            ]
        );
    }
}
