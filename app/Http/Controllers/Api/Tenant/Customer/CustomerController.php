<?php

namespace App\Http\Controllers\Api\Tenant\Customer;

use App\Enums\Tenant\CustomerType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Customer\StoreCustomerRequest;
use App\Http\Requests\Tenant\Customer\UpdateCustomerRequest;
use App\Http\Requests\Tenant\Customer\UpgradeCustomerTypeRequest;
use App\Http\Resources\Tenant\Customer\CustomerResource;
use App\Http\Responses\ApiResponse;
use App\Services\Tenant\Customer\CustomerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function __construct(
        private readonly CustomerService $customerService
    ) {}

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/customers",
     *     summary="List all customers",
     *     description="Retrieve a paginated list of customers with optional filtering and sorting",
     *     operationId="listCustomers",
     *     tags={"Customers"},
     *     security={{"sanctum": {}}},
     *     
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search by customer name, email, phone, or customer number",
     *         required=false,
     *         @OA\Schema(type="string", example="Jane")
     *     ),
     *     @OA\Parameter(
     *         name="customer_type",
     *         in="query",
     *         description="Filter by customer type",
     *         required=false,
     *         @OA\Schema(
     *             type="string",
     *             enum={"walk_in", "regular", "vip", "wholesale"}
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="is_active",
     *         in="query",
     *         description="Filter by active status",
     *         required=false,
     *         @OA\Schema(type="boolean", example=true)
     *     ),
     *     @OA\Parameter(
     *         name="group_id",
     *         in="query",
     *         description="Filter by customer group ID",
     *         required=false,
     *         @OA\Schema(type="integer", example=2)
     *     ),
     *     @OA\Parameter(
     *         name="has_debt",
     *         in="query",
     *         description="Filter customers with outstanding debt",
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
     *             enum={"created_at", "name", "total_lifetime_purchases", "loyalty_points", "current_debt"},
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
     *         description="Customers retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Customers retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="data",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=2),
     *                         @OA\Property(property="customer_number", type="string", example="CUST-2025-000001"),
     *                         @OA\Property(property="name", type="string", example="Jane Smith"),
     *                         @OA\Property(property="email", type="string", format="email", example="jane@example.com", nullable=true),
     *                         @OA\Property(property="phone", type="string", example="+254712345679"),
     *                         @OA\Property(property="date_of_birth", type="string", format="date", example="1995-06-20", nullable=true),
     *                         @OA\Property(property="address", type="string", example="456 Oak Ave, Nairobi", nullable=true),
     *                         @OA\Property(
     *                             property="customer_type",
     *                             type="object",
     *                             @OA\Property(property="value", type="string", example="walk_in"),
     *                             @OA\Property(property="label", type="string", example="Walk-In Customer")
     *                         ),
     *                         @OA\Property(property="loyalty_points", type="number", format="float", example=0),
     *                         @OA\Property(property="total_lifetime_purchases", type="number", format="float", example=0),
     *                         @OA\Property(property="total_visits", type="integer", example=0),
     *                         @OA\Property(property="credit_limit", type="number", format="float", example=3000),
     *                         @OA\Property(property="current_debt", type="number", format="float", example=0),
     *                         @OA\Property(property="available_credit", type="number", format="float", example=3000),
     *                         @OA\Property(property="is_active", type="boolean", example=true),
     *                         @OA\Property(property="registered_at", type="string", format="date-time", example="2025-12-26T08:30:55.000000Z"),
     *                         @OA\Property(
     *                             property="preferred_store",
     *                             type="object",
     *                             nullable=true,
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="name", type="string", example="Branch Store - Mombasa"),
     *                             @OA\Property(property="code", type="string", example="STR-2025-74622")
     *                         ),
     *                         @OA\Property(
     *                             property="current_group",
     *                             type="object",
     *                             nullable=true,
     *                             @OA\Property(property="id", type="integer", example=2),
     *                             @OA\Property(property="name", type="string", example="Basic Members"),
     *                             @OA\Property(property="discount_percentage", type="number", format="float", example=3),
     *                             @OA\Property(property="joined_at", type="string", format="date-time", example="2025-12-26T08:50:35.000000Z")
     *                         ),
     *                         @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-26T08:30:55.000000Z"),
     *                         @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-26T08:30:55.000000Z")
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="pagination",
     *                     type="object",
     *                     @OA\Property(property="current_page", type="integer", example=1),
     *                     @OA\Property(property="last_page", type="integer", example=1),
     *                     @OA\Property(property="per_page", type="integer", example=15),
     *                     @OA\Property(property="total", type="integer", example=1),
     *                     @OA\Property(property="from", type="integer", example=1, nullable=true),
     *                     @OA\Property(property="to", type="integer", example=1, nullable=true)
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-26T08:52:52.683675Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="e9280002-b8d0-4611-8f78-cb808a5ed770"),
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
     *                     property="customer_type",
     *                     type="array",
     *                     @OA\Items(type="string", example="The selected customer type is invalid.")
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
    public function index(Request $request): JsonResponse
    {
        $filters = [
            'search' => $request->input('search'),
            'customer_type' => $request->input('customer_type'),
            'is_active' => $request->input('is_active'),
            'group_id' => $request->input('group_id'),
            'has_debt' => $request->input('has_debt'),
            'sort_by' => $request->input('sort_by', 'created_at'),
            'sort_order' => $request->input('sort_order', 'desc'),
        ];

        $perPage = (int) $request->input('per_page', 15);
        $customers = $this->customerService->getPaginatedCustomers($filters, $perPage);

        return ApiResponse::paginated(
            CustomerResource::collection($customers),
            'Customers retrieved successfully'
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/customers",
     *     summary="Create a new customer",
     *     description="Register a new customer in the system",
     *     operationId="createCustomer",
     *     tags={"Customers"},
     *     security={{"sanctum": {}}},
     *     
     *     @OA\RequestBody(
     *         required=true,
     *         description="Customer details",
     *         @OA\JsonContent(
     *             required={"name", "phone"},
     *             type="object",
     *             @OA\Property(property="name", type="string", maxLength=255, example="Jane Smith", description="Customer full name"),
     *             @OA\Property(property="email", type="string", format="email", maxLength=255, example="jane@example.com", nullable=true, description="Customer email address (must be unique)"),
     *             @OA\Property(property="phone", type="string", maxLength=20, example="+254712345679", description="Customer phone number (must be unique)"),
     *             @OA\Property(property="date_of_birth", type="string", format="date", example="1995-06-20", nullable=true, description="Customer date of birth (must be before today)"),
     *             @OA\Property(property="address", type="string", maxLength=500, example="456 Oak Ave, Nairobi", nullable=true, description="Customer physical address"),
     *             @OA\Property(
     *                 property="customer_type",
     *                 type="string",
     *                 enum={"walk_in", "regular", "vip", "wholesale"},
     *                 example="walk_in",
     *                 nullable=true,
     *                 description="Type of customer"
     *             ),
     *             @OA\Property(property="preferred_store_id", type="integer", example=1, nullable=true, description="ID of the customer's preferred store (must be an active store)"),
     *             @OA\Property(property="credit_limit", type="number", format="float", minimum=0, maximum=999999999.99, example=3000.00, nullable=true, description="Credit limit for the customer")
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=201,
     *         description="Customer created successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Customer created successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=2),
     *                 @OA\Property(property="customer_number", type="string", example="CUST-2025-000001"),
     *                 @OA\Property(property="name", type="string", example="Jane Smith"),
     *                 @OA\Property(property="email", type="string", format="email", example="jane@example.com", nullable=true),
     *                 @OA\Property(property="phone", type="string", example="+254712345679"),
     *                 @OA\Property(property="date_of_birth", type="string", format="date", example="1995-06-20", nullable=true),
     *                 @OA\Property(property="address", type="string", example="456 Oak Ave, Nairobi", nullable=true),
     *                 @OA\Property(
     *                     property="customer_type",
     *                     type="object",
     *                     @OA\Property(property="value", type="string", example="walk_in"),
     *                     @OA\Property(property="label", type="string", example="Walk-In Customer")
     *                 ),
     *                 @OA\Property(property="loyalty_points", type="number", format="float", example=0),
     *                 @OA\Property(property="total_lifetime_purchases", type="number", format="float", example=0),
     *                 @OA\Property(property="total_visits", type="integer", example=0),
     *                 @OA\Property(property="credit_limit", type="number", format="float", example=3000),
     *                 @OA\Property(property="current_debt", type="number", format="float", example=0),
     *                 @OA\Property(property="available_credit", type="number", format="float", example=3000),
     *                 @OA\Property(property="is_active", type="boolean", example=true),
     *                 @OA\Property(property="registered_at", type="string", format="date-time", example="2025-12-26T08:30:55.000000Z"),
     *                 @OA\Property(
     *                     property="preferred_store",
     *                     type="object",
     *                     nullable=true,
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Branch Store - Mombasa"),
     *                     @OA\Property(property="code", type="string", example="STR-2025-74622")
     *                 ),
     *                 @OA\Property(
     *                     property="current_group",
     *                     type="object",
     *                     nullable=true,
     *                     example=null
     *                 ),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-26T08:30:55.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-26T08:30:55.000000Z")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-26T08:30:55.803455Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="44b96fb7-67b0-43d9-b31b-a2825540673f"),
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
     *                     property="email",
     *                     type="array",
     *                     @OA\Items(type="string", example="The email has already been taken.")
     *                 ),
     *                 @OA\Property(
     *                     property="phone",
     *                     type="array",
     *                     @OA\Items(type="string", example="The phone field is required.")
     *                 ),
     *                 @OA\Property(
     *                     property="date_of_birth",
     *                     type="array",
     *                     @OA\Items(type="string", example="The date of birth must be a date before today.")
     *                 ),
     *                 @OA\Property(
     *                     property="customer_type",
     *                     type="array",
     *                     @OA\Items(type="string", example="The selected customer type is invalid.")
     *                 ),
     *                 @OA\Property(
     *                     property="preferred_store_id",
     *                     type="array",
     *                     @OA\Items(type="string", example="The selected preferred store id is invalid.")
     *                 ),
     *                 @OA\Property(
     *                     property="credit_limit",
     *                     type="array",
     *                     @OA\Items(type="string", example="The credit limit must be at least 0.")
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
    public function store(StoreCustomerRequest $request): JsonResponse
    {
        $customer = $this->customerService->createCustomer($request->validated());

        return ApiResponse::created(
            'Customer created successfully',
            new CustomerResource($customer)
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/customers/{id}",
     *     summary="Get customer details",
     *     description="Retrieve detailed information about a specific customer",
     *     operationId="getCustomer",
     *     tags={"Customers"},
     *     security={{"sanctum": {}}},
     *     
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Customer ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=2)
     *     ),
     *     
     *     @OA\Response(
     *         response=200,
     *         description="Customer retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Customer retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=2),
     *                 @OA\Property(property="customer_number", type="string", example="CUST-2025-000001"),
     *                 @OA\Property(property="name", type="string", example="Jane Smith"),
     *                 @OA\Property(property="email", type="string", format="email", example="jane@example.com", nullable=true),
     *                 @OA\Property(property="phone", type="string", example="+254712345679"),
     *                 @OA\Property(property="date_of_birth", type="string", format="date", example="1995-06-20", nullable=true),
     *                 @OA\Property(property="address", type="string", example="456 Oak Ave, Nairobi", nullable=true),
     *                 @OA\Property(
     *                     property="customer_type",
     *                     type="object",
     *                     @OA\Property(property="value", type="string", example="walk_in"),
     *                     @OA\Property(property="label", type="string", example="Walk-In Customer")
     *                 ),
     *                 @OA\Property(property="loyalty_points", type="number", format="float", example=0),
     *                 @OA\Property(property="total_lifetime_purchases", type="number", format="float", example=0),
     *                 @OA\Property(property="total_visits", type="integer", example=0),
     *                 @OA\Property(property="credit_limit", type="number", format="float", example=3000),
     *                 @OA\Property(property="current_debt", type="number", format="float", example=0),
     *                 @OA\Property(property="available_credit", type="number", format="float", example=3000),
     *                 @OA\Property(property="is_active", type="boolean", example=true),
     *                 @OA\Property(property="registered_at", type="string", format="date-time", example="2025-12-26T08:30:55.000000Z"),
     *                 @OA\Property(
     *                     property="preferred_store",
     *                     type="object",
     *                     nullable=true,
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Branch Store - Mombasa"),
     *                     @OA\Property(property="code", type="string", example="STR-2025-74622")
     *                 ),
     *                 @OA\Property(
     *                     property="current_group",
     *                     type="object",
     *                     nullable=true,
     *                     @OA\Property(property="id", type="integer", example=2),
     *                     @OA\Property(property="name", type="string", example="Basic Members"),
     *                     @OA\Property(property="discount_percentage", type="number", format="float", example=3),
     *                     @OA\Property(property="joined_at", type="string", format="date-time", example="2025-12-26T08:50:35.000000Z")
     *                 ),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-26T08:30:55.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-26T08:30:55.000000Z")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-26T09:06:16.712374Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="b8652744-9d8d-47dd-aee7-817a5987ce8d"),
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
     *         description="Customer not found",
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
        $customer = $this->customerService->getCustomerById($id);

        if (!$customer) {
            return ApiResponse::notFound('Customer not found');
        }

        return ApiResponse::success(
            'Customer retrieved successfully',
            new CustomerResource($customer)
        );
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/tenant/customers/{id}",
     *     summary="Update customer",
     *     description="Update customer information. All fields are optional - only provide fields that need updating.",
     *     operationId="updateCustomer",
     *     tags={"Customers"},
     *     security={{"sanctum": {}}},
     *     
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Customer ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=2)
     *     ),
     *     
     *     @OA\RequestBody(
     *         required=false,
     *         description="Customer fields to update (all optional)",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="name", type="string", maxLength=255, example="Jane Smith Updated", description="Customer full name"),
     *             @OA\Property(property="email", type="string", format="email", maxLength=255, example="john.updated@example.com", nullable=true, description="Customer email address (must be unique)"),
     *             @OA\Property(property="phone", type="string", maxLength=20, example="+254712345679", description="Customer phone number (must be unique)"),
     *             @OA\Property(property="date_of_birth", type="string", format="date", example="1995-06-20", nullable=true, description="Customer date of birth (must be before today)"),
     *             @OA\Property(property="address", type="string", maxLength=500, example="New Address 789", nullable=true, description="Customer physical address"),
     *             @OA\Property(
     *                 property="customer_type",
     *                 type="string",
     *                 enum={"walk_in", "regular", "vip", "wholesale"},
     *                 example="regular",
     *                 nullable=true,
     *                 description="Type of customer"
     *             ),
     *             @OA\Property(property="preferred_store_id", type="integer", example=1, nullable=true, description="ID of the customer's preferred store (must be an active store)"),
     *             @OA\Property(property="credit_limit", type="number", format="float", minimum=0, maximum=999999999.99, example=5000.00, nullable=true, description="Credit limit for the customer")
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=200,
     *         description="Customer updated successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Customer updated successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=2),
     *                 @OA\Property(property="customer_number", type="string", example="CUST-2025-000001"),
     *                 @OA\Property(property="name", type="string", example="Jane Smith"),
     *                 @OA\Property(property="email", type="string", format="email", example="john.updated@example.com", nullable=true),
     *                 @OA\Property(property="phone", type="string", example="+254712345679"),
     *                 @OA\Property(property="date_of_birth", type="string", format="date", example="1995-06-20", nullable=true),
     *                 @OA\Property(property="address", type="string", example="New Address 789", nullable=true),
     *                 @OA\Property(
     *                     property="customer_type",
     *                     type="object",
     *                     @OA\Property(property="value", type="string", example="walk_in"),
     *                     @OA\Property(property="label", type="string", example="Walk-In Customer")
     *                 ),
     *                 @OA\Property(property="loyalty_points", type="number", format="float", example=0),
     *                 @OA\Property(property="total_lifetime_purchases", type="number", format="float", example=0),
     *                 @OA\Property(property="total_visits", type="integer", example=0),
     *                 @OA\Property(property="credit_limit", type="number", format="float", example=3000),
     *                 @OA\Property(property="current_debt", type="number", format="float", example=0),
     *                 @OA\Property(property="available_credit", type="number", format="float", example=3000),
     *                 @OA\Property(property="is_active", type="boolean", example=true),
     *                 @OA\Property(property="registered_at", type="string", format="date-time", example="2025-12-26T08:30:55.000000Z"),
     *                 @OA\Property(
     *                     property="preferred_store",
     *                     type="object",
     *                     nullable=true,
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Branch Store - Mombasa"),
     *                     @OA\Property(property="code", type="string", example="STR-2025-74622")
     *                 ),
     *                 @OA\Property(
     *                     property="current_group",
     *                     type="object",
     *                     nullable=true,
     *                     @OA\Property(property="id", type="integer", example=2),
     *                     @OA\Property(property="name", type="string", example="Basic Members"),
     *                     @OA\Property(property="discount_percentage", type="number", format="float", example=3),
     *                     @OA\Property(property="joined_at", type="string", format="date-time", example="2025-12-26T08:50:35.000000Z")
     *                 ),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-26T08:30:55.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-26T09:21:50.000000Z")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-26T09:21:50.047583Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="4ecefd71-5b10-4d26-b614-0013a94d91e9"),
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
     *         description="Customer not found",
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
     *                     property="email",
     *                     type="array",
     *                     @OA\Items(type="string", example="The email has already been taken.")
     *                 ),
     *                 @OA\Property(
     *                     property="phone",
     *                     type="array",
     *                     @OA\Items(type="string", example="The phone has already been taken.")
     *                 ),
     *                 @OA\Property(
     *                     property="preferred_store_id",
     *                     type="array",
     *                     @OA\Items(type="string", example="The selected preferred store id is invalid.")
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
    public function update(UpdateCustomerRequest $request, int $id): JsonResponse
    {
        $customer = $this->customerService->getCustomerById($id);

        if (!$customer) {
            return ApiResponse::notFound('Customer not found');
        }

        $updatedCustomer = $this->customerService->updateCustomer($customer, $request->validated());

        return ApiResponse::success(
            'Customer updated successfully',
            new CustomerResource($updatedCustomer)
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/tenant/customers/{id}",
     *     summary="Delete customer",
     *     description="Soft delete a customer from the system. The customer can be restored later.",
     *     operationId="deleteCustomer",
     *     tags={"Customers"},
     *     security={{"sanctum": {}}},
     *     
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Customer ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=2)
     *     ),
     *     
     *     @OA\Response(
     *         response=200,
     *         description="Customer deleted successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Customer deleted successfully"),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-26T09:26:48.446153Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="b8dddd08-6767-4458-bd0c-f78d78326113"),
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
     *         description="Customer not found",
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
    public function destroy(int $id): JsonResponse
    {
        $customer = $this->customerService->getCustomerById($id);

        if (!$customer) {
            return ApiResponse::notFound('Customer not found');
        }

        $this->customerService->deleteCustomer($customer);

        return ApiResponse::success('Customer deleted successfully');
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/customers/{id}/restore",
     *     summary="Restore deleted customer",
     *     description="Restore a soft-deleted customer back to active status",
     *     operationId="restoreCustomer",
     *     tags={"Customers"},
     *     security={{"sanctum": {}}},
     *     
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Customer ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=2)
     *     ),
     *     
     *     @OA\Response(
     *         response=200,
     *         description="Customer restored successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Customer restored successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=2),
     *                 @OA\Property(property="customer_number", type="string", example="CUST-2025-000001"),
     *                 @OA\Property(property="name", type="string", example="Jane Smith"),
     *                 @OA\Property(property="email", type="string", format="email", example="john.updated@example.com", nullable=true),
     *                 @OA\Property(property="phone", type="string", example="+254712345679"),
     *                 @OA\Property(property="date_of_birth", type="string", format="date", example="1995-06-20", nullable=true),
     *                 @OA\Property(property="address", type="string", example="New Address 789", nullable=true),
     *                 @OA\Property(
     *                     property="customer_type",
     *                     type="object",
     *                     @OA\Property(property="value", type="string", example="walk_in"),
     *                     @OA\Property(property="label", type="string", example="Walk-In Customer")
     *                 ),
     *                 @OA\Property(property="loyalty_points", type="number", format="float", example=0),
     *                 @OA\Property(property="total_lifetime_purchases", type="number", format="float", example=0),
     *                 @OA\Property(property="total_visits", type="integer", example=0),
     *                 @OA\Property(property="credit_limit", type="number", format="float", example=3000),
     *                 @OA\Property(property="current_debt", type="number", format="float", example=0),
     *                 @OA\Property(property="available_credit", type="number", format="float", example=3000),
     *                 @OA\Property(property="is_active", type="boolean", example=true),
     *                 @OA\Property(property="registered_at", type="string", format="date-time", example="2025-12-26T08:30:55.000000Z"),
     *                 @OA\Property(
     *                     property="preferred_store",
     *                     type="object",
     *                     nullable=true,
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Branch Store - Mombasa"),
     *                     @OA\Property(property="code", type="string", example="STR-2025-74622")
     *                 ),
     *                 @OA\Property(
     *                     property="current_group",
     *                     type="object",
     *                     nullable=true,
     *                     @OA\Property(property="id", type="integer", example=2),
     *                     @OA\Property(property="name", type="string", example="Basic Members"),
     *                     @OA\Property(property="discount_percentage", type="number", format="float", example=3),
     *                     @OA\Property(property="joined_at", type="string", format="date-time", example="2025-12-26T08:50:35.000000Z")
     *                 ),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-26T08:30:55.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-26T09:28:20.000000Z")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-26T09:28:20.338911Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="a0cd764a-4fd2-42cb-9c1e-5c220a03a88f"),
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
     *         description="Customer not found or not deleted",
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
    public function restore(int $id): JsonResponse
    {
        $customer = $this->customerService->restoreCustomer($id);

        if (!$customer) {
            return ApiResponse::notFound('Customer not found or not deleted');
        }

        return ApiResponse::success(
            'Customer restored successfully',
            new CustomerResource($customer)
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/customers/search",
     *     summary="Search customers",
     *     description="Quick search for customers by name, email, phone, or customer number. Returns simplified customer list without pagination.",
     *     operationId="searchCustomers",
     *     tags={"Customers"},
     *     security={{"sanctum": {}}},
     *     
     *     @OA\Parameter(
     *         name="q",
     *         in="query",
     *         description="Search query - searches across name, email, phone, and customer number",
     *         required=true,
     *         @OA\Schema(type="string", example="jane", minLength=1)
     *     ),
     *     
     *     @OA\Response(
     *         response=200,
     *         description="Search results retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Search results retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=2),
     *                     @OA\Property(property="customer_number", type="string", example="CUST-2025-000001"),
     *                     @OA\Property(property="name", type="string", example="Jane Smith"),
     *                     @OA\Property(property="email", type="string", format="email", example="john.updated@example.com", nullable=true),
     *                     @OA\Property(property="phone", type="string", example="+254712345679"),
     *                     @OA\Property(property="date_of_birth", type="string", format="date", example="1995-06-20", nullable=true),
     *                     @OA\Property(property="address", type="string", example="New Address 789", nullable=true),
     *                     @OA\Property(
     *                         property="customer_type",
     *                         type="object",
     *                         @OA\Property(property="value", type="string", example="walk_in"),
     *                         @OA\Property(property="label", type="string", example="Walk-In Customer")
     *                     ),
     *                     @OA\Property(property="loyalty_points", type="number", format="float", example=0),
     *                     @OA\Property(property="total_lifetime_purchases", type="number", format="float", example=0),
     *                     @OA\Property(property="total_visits", type="integer", example=0),
     *                     @OA\Property(property="credit_limit", type="number", format="float", example=3000),
     *                     @OA\Property(property="current_debt", type="number", format="float", example=0),
     *                     @OA\Property(property="available_credit", type="number", format="float", example=3000),
     *                     @OA\Property(property="is_active", type="boolean", example=true),
     *                     @OA\Property(property="registered_at", type="string", format="date-time", example="2025-12-26T08:30:55.000000Z"),
     *                     @OA\Property(
     *                         property="preferred_store",
     *                         type="object",
     *                         nullable=true,
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="Branch Store - Mombasa"),
     *                         @OA\Property(property="code", type="string", example="STR-2025-74622")
     *                     ),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-26T08:30:55.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-26T09:28:20.000000Z")
     *                 ),
     *                 description="Array of matching customers. Returns empty array if no matches found."
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-26T09:35:22.230909Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="9f6a174a-ce65-41c3-932e-62ad85be2997"),
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
     *                     property="q",
     *                     type="array",
     *                     @OA\Items(type="string", example="The q field is required.")
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
    public function search(Request $request): JsonResponse
    {
        $query = $request->input('q', '');

        if (empty($query)) {
            return ApiResponse::validationError([
                'q' => ['Search query is required']
            ]);
        }

        $customers = $this->customerService->searchCustomers($query);

        return ApiResponse::success(
            'Search results retrieved successfully',
            CustomerResource::collection($customers)
        );
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/tenant/customers/{id}/upgrade-type",
     *     summary="Upgrade customer type",
     *     description="Upgrade customer to a higher tier type. Upgrades follow business rules: walk_in can upgrade to regular or vip (but not wholesale), regular can upgrade to vip, vip can upgrade to wholesale. Cannot downgrade customer types.",
     *     operationId="upgradeCustomerType",
     *     tags={"Customers"},
     *     security={{"sanctum": {}}},
     *     
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Customer ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=2)
     *     ),
     *     
     *     @OA\RequestBody(
     *         required=true,
     *         description="New customer type to upgrade to",
     *         @OA\JsonContent(
     *             required={"customer_type"},
     *             type="object",
     *             @OA\Property(
     *                 property="customer_type",
     *                 type="string",
     *                 enum={"regular", "vip", "wholesale"},
     *                 example="vip",
     *                 description="Target customer type (must be a valid upgrade from current type)"
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=200,
     *         description="Customer type upgraded successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Customer upgraded to vip"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="customer_type",
     *                     type="string",
     *                     enum={"walk_in", "regular", "vip", "wholesale"},
     *                     example="vip",
     *                     description="New customer type after upgrade"
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-26T11:07:45.239332Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="3168cf4a-eb08-4d04-a8cc-eb92d615e002"),
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
     *         description="Customer not found",
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
     *         description="Validation error or invalid upgrade path",
     *         @OA\JsonContent(
     *             oneOf={
     *                 @OA\Schema(
     *                     type="object",
     *                     description="Validation error - missing or invalid customer_type",
     *                     @OA\Property(property="success", type="boolean", example=false),
     *                     @OA\Property(property="message", type="string", example="The given data was invalid."),
     *                     @OA\Property(
     *                         property="errors",
     *                         type="object",
     *                         @OA\Property(
     *                             property="customer_type",
     *                             type="array",
     *                             @OA\Items(type="string", example="The customer type field is required.")
     *                         )
     *                     ),
     *                     @OA\Property(
     *                         property="meta",
     *                         type="object",
     *                         @OA\Property(property="timestamp", type="string", format="date-time"),
     *                         @OA\Property(property="request_id", type="string", format="uuid"),
     *                         @OA\Property(property="tenant_id", type="string", format="uuid"),
     *                         @OA\Property(property="tenant_name", type="string", nullable=true)
     *                     )
     *                 ),
     *                 @OA\Schema(
     *                     type="object",
     *                     description="Business logic error - invalid upgrade path",
     *                     @OA\Property(property="success", type="boolean", example=false),
     *                     @OA\Property(property="message", type="string", example="Cannot upgrade walk_in to wholesale"),
     *                     @OA\Property(
     *                         property="meta",
     *                         type="object",
     *                         @OA\Property(property="timestamp", type="string", format="date-time"),
     *                         @OA\Property(property="request_id", type="string", format="uuid"),
     *                         @OA\Property(property="tenant_id", type="string", format="uuid"),
     *                         @OA\Property(property="tenant_name", type="string", nullable=true)
     *                     )
     *                 )
     *             }
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
     * 
     * @OA\Schema(
     *     schema="CustomerTypeUpgradeRules",
     *     type="object",
     *     title="Customer Type Upgrade Rules",
     *     description="Business rules for customer type upgrades",
     *     @OA\Property(
     *         property="walk_in",
     *         type="array",
     *         description="walk_in customers can upgrade to:",
     *         @OA\Items(type="string", enum={"regular", "vip"})
     *     ),
     *     @OA\Property(
     *         property="regular",
     *         type="array",
     *         description="regular customers can upgrade to:",
     *         @OA\Items(type="string", enum={"vip"})
     *     ),
     *     @OA\Property(
     *         property="vip",
     *         type="array",
     *         description="vip customers can upgrade to:",
     *         @OA\Items(type="string", enum={"wholesale"})
     *     ),
     *     @OA\Property(
     *         property="wholesale",
     *         type="array",
     *         description="wholesale customers cannot upgrade further (highest tier)",
     *         @OA\Items(type="string")
     *     ),
     *     example={
     *         "walk_in": {"regular", "vip"},
     *         "regular": {"vip"},
     *         "vip": {"wholesale"},
     *         "wholesale": {}
     *     }
     * )
     */
    public function upgradeType(UpgradeCustomerTypeRequest $request, int $id): JsonResponse
    {
        $customer = $this->customerService->getCustomerById($id);
        if (!$customer) {
            return ApiResponse::notFound('Customer not found');
        }
        $targetType = CustomerType::from($request->input('customer_type'));
        try {
            $upgradedCustomer = $this->customerService->upgradeCustomerType($customer, $targetType);

            return ApiResponse::success(
                "Customer upgraded to {$upgradedCustomer->customer_type->value}",
                ['customer_type' => $upgradedCustomer->customer_type->value]
            );
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), null, 422);
        }
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/tenant/customers/{id}/toggle-status",
     *     summary="Toggle customer active status",
     *     description="Toggle customer between active and inactive status. If customer is active, they become inactive and vice versa.",
     *     operationId="toggleCustomerStatus",
     *     tags={"Customers"},
     *     security={{"sanctum": {}}},
     *     
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Customer ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=2)
     *     ),
     *     
     *     @OA\Response(
     *         response=200,
     *         description="Customer status updated successfully - Active to Inactive",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Customer status updated successfully"),
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
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-26T10:52:13.038368Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="bc7e2073-26c3-47ce-84cf-e2836149d1ae"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response="200-active",
     *         description="Customer status updated successfully - Inactive to Active",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Customer status updated successfully"),
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
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-26T10:51:27.151929Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="64e99c50-f5b5-4b93-a87b-0282deb612a7"),
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
     *         description="Customer not found",
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
        $customer = $this->customerService->getCustomerById($id);
        if (!$customer) {
            return ApiResponse::notFound('Customer not found');
        }
        $updatedCustomer = $this->customerService->toggleCustomerStatus($customer);

        $statusText = $updatedCustomer->is_active ? 'active' : 'inactive';

        return ApiResponse::success(
            'Customer status updated successfully',
            ['status' => $statusText]
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/customers/marketing-eligible",
     *     summary="Get marketing-eligible customers",
     *     description="Retrieve a paginated list of customers who have opted in to receive marketing communications (accepts_marketing = true). This endpoint returns customers who can be contacted for promotional and marketing purposes, including their complete profile and purchase history.",
     *     operationId="getMarketingEligibleCustomers",
     *     tags={"Customers"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of items per page",
     *         required=false,
     *         @OA\Schema(type="integer", default=50, example=50)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Marketing-eligible customers retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Marketing-eligible customers retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="data",
     *                     type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="id", type="integer", example=14),
     *                         @OA\Property(property="customer_number", type="string", example="CUST-2026-000002", description="Unique customer number"),
     *                         @OA\Property(property="name", type="string", example="Jane Smith"),
     *                         @OA\Property(property="email", type="string", nullable=true, example="janetest@example.com"),
     *                         @OA\Property(property="phone", type="string", example="+254700001111"),
     *                         @OA\Property(property="date_of_birth", type="string", format="date", nullable=true, example="1995-06-20"),
     *                         @OA\Property(property="address", type="string", nullable=true, example="456 Oak Ave, Nairobi"),
     *                         @OA\Property(
     *                             property="customer_type",
     *                             type="object",
     *                             @OA\Property(property="value", type="string", example="walk_in", description="Customer type code"),
     *                             @OA\Property(property="label", type="string", example="Walk-In Customer", description="Human-readable customer type label")
     *                         ),
     *                         @OA\Property(property="loyalty_points", type="number", format="float", example=0, description="Current loyalty points balance"),
     *                         @OA\Property(property="total_lifetime_purchases", type="number", format="float", example=0, description="Total amount spent by customer"),
     *                         @OA\Property(property="total_visits", type="integer", example=0, description="Total number of visits/purchases"),
     *                         @OA\Property(property="credit_limit", type="number", format="float", nullable=true, example=3000, description="Customer's credit limit"),
     *                         @OA\Property(property="current_debt", type="number", format="float", example=0, description="Current outstanding debt"),
     *                         @OA\Property(property="available_credit", type="number", format="float", example=3000, description="Available credit remaining"),
     *                         @OA\Property(property="is_active", type="boolean", example=true, description="Whether the customer account is active"),
     *                         @OA\Property(property="accepts_marketing", type="boolean", example=true, description="Whether customer accepts marketing communications"),
     *                         @OA\Property(property="registered_at", type="string", format="date-time", example="2026-01-11T18:35:34.000000Z", description="Customer registration date"),
     *                         @OA\Property(
     *                             property="preferred_store",
     *                             type="object",
     *                             nullable=true,
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="name", type="string", example="Branch Store - Mombasa"),
     *                             @OA\Property(property="code", type="string", example="STR-2025-74622")
     *                         ),
     *                         @OA\Property(property="created_at", type="string", format="date-time", example="2026-01-11T18:35:34.000000Z"),
     *                         @OA\Property(property="updated_at", type="string", format="date-time", example="2026-01-12T06:42:37.000000Z")
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="pagination",
     *                     type="object",
     *                     @OA\Property(property="current_page", type="integer", example=1),
     *                     @OA\Property(property="last_page", type="integer", example=1),
     *                     @OA\Property(property="per_page", type="integer", example=50),
     *                     @OA\Property(property="total", type="integer", example=1, description="Total number of marketing-eligible customers"),
     *                     @OA\Property(property="from", type="integer", example=1),
     *                     @OA\Property(property="to", type="integer", example=1)
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-01-12T06:43:19.713451Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="d8c83e2a-b687-48be-8adb-fbfc029a60aa"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - User does not have the right permissions"
     *     )
     * )*/
    public function marketingEligible(Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', 50);

        $customers = $this->customerService->getMarketingEligibleCustomers(true, $perPage);

        return ApiResponse::paginated(
            CustomerResource::collection($customers),
            'Marketing-eligible customers retrieved successfully'
        );
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/tenant/customers/{id}/toggle-marketing",
     *     summary="Toggle customer marketing preferences",
     *     description="Toggle a customer's marketing communication preferences. This endpoint switches the accepts_marketing flag between true and false. When true, the customer has opted in to receive marketing communications. When false, the customer has opted out.",
     *     operationId="toggleCustomerMarketing",
     *     tags={"Customers"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Customer ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=14)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Marketing preference toggled successfully",
     *         @OA\JsonContent(
     *             oneOf={
     *                 @OA\Schema(
     *                     description="Customer opted out of marketing",
     *                     @OA\Property(property="success", type="boolean", example=true),
     *                     @OA\Property(property="message", type="string", example="Customer has opted out of marketing communications"),
     *                     @OA\Property(
     *                         property="data",
     *                         type="object",
     *                         @OA\Property(property="accepts_marketing", type="boolean", example=false, description="Customer's new marketing preference status")
     *                     ),
     *                     @OA\Property(
     *                         property="meta",
     *                         type="object",
     *                         @OA\Property(property="timestamp", type="string", format="date-time", example="2026-01-12T06:42:08.745077Z"),
     *                         @OA\Property(property="request_id", type="string", format="uuid", example="0c08e24f-35fc-4219-bee8-6dc271885c51"),
     *                         @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                         @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *                     )
     *                 ),
     *                 @OA\Schema(
     *                     description="Customer opted in to marketing",
     *                     @OA\Property(property="success", type="boolean", example=true),
     *                     @OA\Property(property="message", type="string", example="Customer has opted in to marketing communications"),
     *                     @OA\Property(
     *                         property="data",
     *                         type="object",
     *                         @OA\Property(property="accepts_marketing", type="boolean", example=true, description="Customer's new marketing preference status")
     *                     ),
     *                     @OA\Property(
     *                         property="meta",
     *                         type="object",
     *                         @OA\Property(property="timestamp", type="string", format="date-time", example="2026-01-12T06:42:37.094399Z"),
     *                         @OA\Property(property="request_id", type="string", format="uuid", example="463234d9-c9b6-44f5-8bf3-10c54b0cce88"),
     *                         @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                         @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *                     )
     *                 )
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - User does not have the right permissions"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Customer not found"
     *     )
     * )*/
    public function toggleMarketingConsent(int $id): JsonResponse
    {
        $result = $this->customerService->toggleMarketingConsent($id);

        return ApiResponse::success(
            $result['message'],
            [
                'accepts_marketing' => $result['accepts_marketing'],
            ]
        );
    }
}
