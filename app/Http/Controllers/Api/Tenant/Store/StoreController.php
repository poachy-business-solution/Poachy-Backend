<?php

namespace App\Http\Controllers\Api\Tenant\Store;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Store\AssignManagerRequest;
use App\Http\Requests\Tenant\Store\StoreRequest;
use App\Http\Requests\Tenant\Store\UpdateStoreDetailsRequest;
use App\Http\Resources\Tenant\Store\StoreCollection;
use App\Http\Resources\Tenant\Store\StoreResource;
use App\Http\Responses\ApiResponse;
use App\Services\Tenant\Store\StoreService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class StoreController extends Controller
{
    public function __construct(
        protected readonly StoreService $storeService
    ) {}

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/stores",
     *     summary="Get all stores",
     *     description="Retrieves a paginated list of stores with optional filtering and sorting capabilities.",
     *     tags={"Tenant Store Management"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search by name, code, city, or region",
     *         required=false,
     *         @OA\Schema(type="string"),
     *         example="Nairobi"
     *     ),
     *     @OA\Parameter(
     *         name="is_active",
     *         in="query",
     *         description="Filter by active status",
     *         required=false,
     *         @OA\Schema(type="boolean"),
     *         example=true
     *     ),
     *     @OA\Parameter(
     *         name="is_main_store",
     *         in="query",
     *         description="Filter by main store flag",
     *         required=false,
     *         @OA\Schema(type="boolean"),
     *         example=false
     *     ),
     *     @OA\Parameter(
     *         name="city",
     *         in="query",
     *         description="Filter by city",
     *         required=false,
     *         @OA\Schema(type="string"),
     *         example="Nairobi"
     *     ),
     *     @OA\Parameter(
     *         name="region",
     *         in="query",
     *         description="Filter by region",
     *         required=false,
     *         @OA\Schema(type="string"),
     *         example="Central"
     *     ),
     *     @OA\Parameter(
     *         name="manager_id",
     *         in="query",
     *         description="Filter by manager ID",
     *         required=false,
     *         @OA\Schema(type="integer"),
     *         example=5
     *     ),
     *     @OA\Parameter(
     *         name="sort_by",
     *         in="query",
     *         description="Field to sort by",
     *         required=false,
     *         @OA\Schema(type="string"),
     *         example="name"
     *     ),
     *     @OA\Parameter(
     *         name="sort_order",
     *         in="query",
     *         description="Sort order (asc/desc)",
     *         required=false,
     *         @OA\Schema(type="string", enum={"asc", "desc"}),
     *         example="asc"
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Items per page (1-100)",
     *         required=false,
     *         @OA\Schema(type="integer", minimum=1, maximum=100),
     *         example=20
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Stores retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Stores retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="data",
     *                     type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="code", type="string", example="STR-2025-74622"),
     *                         @OA\Property(property="name", type="string", example="Branch Store - Mombasa"),
     *                         @OA\Property(property="description", type="string", example="Mombasa branch location"),
     *                         @OA\Property(property="address", type="string", example="Moi Avenue, Mombasa"),
     *                         @OA\Property(property="city", type="string", example="Mombasa"),
     *                         @OA\Property(property="region", type="string", example="Coast"),
     *                         @OA\Property(property="phone", type="string", example="+254723456789"),
     *                         @OA\Property(property="email", type="string", example="mombasa@store.com"),
     *                         @OA\Property(property="is_main_store", type="boolean", example=true),
     *                         @OA\Property(property="is_active", type="boolean", example=true),
     *                         @OA\Property(property="status_label", type="string", example="Active"),
     *                         @OA\Property(property="store_type_label", type="string", example="Main Store"),
     *                         @OA\Property(
     *                             property="manager",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=6),
     *                             @OA\Property(property="name", type="string", example="Jane Doe"),
     *                             @OA\Property(property="email", type="string", example="jane@techhaven.com"),
     *                             @OA\Property(property="phone", type="string", nullable=true, example=null)
     *                         ),
     *                         @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-14T19:48:13.000000Z"),
     *                         @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-14T19:48:13.000000Z")
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="pagination",
     *                     type="object",
     *                     @OA\Property(property="total", type="integer", example=1),
     *                     @OA\Property(property="count", type="integer", example=1),
     *                     @OA\Property(property="per_page", type="integer", example=15),
     *                     @OA\Property(property="current_page", type="integer", example=1),
     *                     @OA\Property(property="last_page", type="integer", example=1),
     *                     @OA\Property(property="from", type="integer", example=1),
     *                     @OA\Property(property="to", type="integer", example=1)
     *                 ),
     *                 @OA\Property(
     *                     property="links",
     *                     type="object",
     *                     @OA\Property(property="first", type="array", @OA\Items(type="string")),
     *                     @OA\Property(property="last", type="array", @OA\Items(type="string")),
     *                     @OA\Property(property="prev", type="array", @OA\Items(type="string", nullable=true)),
     *                     @OA\Property(property="next", type="array", @OA\Items(type="string", nullable=true))
     *                 ),
     *                 @OA\Property(
     *                     property="meta",
     *                     type="object",
     *                     @OA\Property(property="current_page", type="integer"),
     *                     @OA\Property(property="from", type="integer"),
     *                     @OA\Property(property="last_page", type="integer"),
     *                     @OA\Property(property="path", type="string"),
     *                     @OA\Property(property="per_page", type="integer"),
     *                     @OA\Property(property="to", type="integer"),
     *                     @OA\Property(property="total", type="integer")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-15T06:22:56.658593Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="c014d802-30b1-4657-85a5-dba9ceeb9008"),
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
    public function index(Request $request): JsonResponse
    {
        try {
            // Validate per_page parameter
            $perPage = min((int) $request->get('per_page', 15), 100);

            // Build filters array
            $filters = [
                'search' => $request->get('search'),
                'is_active' => $request->get('is_active'),
                'is_main_store' => $request->get('is_main_store'),
                'city' => $request->get('city'),
                'region' => $request->get('region'),
                'manager_id' => $request->get('manager_id'),
                'sort_by' => $request->get('sort_by', 'created_at'),
                'sort_order' => $request->get('sort_order', 'desc'),
            ];

            // Get paginated stores
            $stores = $this->storeService->getStores($filters, $perPage);

            // Transform to resource collection
            $collection = new StoreCollection($stores);

            return ApiResponse::success(
                'Stores retrieved successfully',
                $collection->response()->getData(true)
            );
        } catch (\Exception $e) {
            Log::error('Failed to fetch stores', [
                'tenant_id' => tenant()->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiResponse::serverError(
                'Failed to retrieve stores. Please try again.'
            );
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/stores",
     *     summary="Create a new store",
     *     description="Creates a new store with the provided details. The store will be automatically assigned a unique code.",
     *     tags={"Tenant Store Management"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "address"},
     *             @OA\Property(property="name", type="string", maxLength=255, description="Store name", example="Branch Store - Mombasa"),
     *             @OA\Property(property="description", type="string", maxLength=1000, description="Store description", example="Mombasa branch location"),
     *             @OA\Property(property="address", type="string", maxLength=500, description="Store address", example="Moi Avenue, Mombasa"),
     *             @OA\Property(property="city", type="string", maxLength=100, description="City name", example="Mombasa"),
     *             @OA\Property(property="region", type="string", maxLength=100, description="Region name", example="Coast"),
     *             @OA\Property(property="phone", type="string", description="Phone number (Kenyan format)", example="+254723456789"),
     *             @OA\Property(property="email", type="string", format="email", maxLength=255, description="Email address", example="mombasa@store.com"),
     *             @OA\Property(property="is_main_store", type="boolean", description="Whether this is the main store", example=false),
     *             @OA\Property(property="is_active", type="boolean", description="Whether the store is active", example=true),
     *             @OA\Property(property="manager_id", type="integer", description="Manager user ID (must have manager/owner role)", example=6)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Store created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Stores retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="data",
     *                     type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="id", type="integer", example=2),
     *                         @OA\Property(property="code", type="string", example="STR-2025-08954"),
     *                         @OA\Property(property="name", type="string", example="Branch Store - Nairobi"),
     *                         @OA\Property(property="description", type="string", example="Nairobi branch location"),
     *                         @OA\Property(property="address", type="string", example="Moi Avenue, Nairobi"),
     *                         @OA\Property(property="city", type="string", example="Nairobi"),
     *                         @OA\Property(property="region", type="string", example="Nairobi"),
     *                         @OA\Property(property="phone", type="string", example="+254700000000"),
     *                         @OA\Property(property="email", type="string", example="info.nairobi@store.com"),
     *                         @OA\Property(property="is_main_store", type="boolean", example=false),
     *                         @OA\Property(property="is_active", type="boolean", example=true),
     *                         @OA\Property(property="status_label", type="string", example="Active"),
     *                         @OA\Property(property="store_type_label", type="string", example="Branch"),
     *                         @OA\Property(
     *                             property="manager",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="name", type="string", example="John Doe"),
     *                             @OA\Property(property="email", type="string", example="john@techhaven.com"),
     *                             @OA\Property(property="phone", type="string", nullable=true, example=null)
     *                         ),
     *                         @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-15T18:08:51.000000Z"),
     *                         @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-15T18:08:51.000000Z")
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="pagination",
     *                     type="object",
     *                     @OA\Property(property="total", type="integer", example=2),
     *                     @OA\Property(property="count", type="integer", example=2),
     *                     @OA\Property(property="per_page", type="integer", example=15),
     *                     @OA\Property(property="current_page", type="integer", example=1),
     *                     @OA\Property(property="last_page", type="integer", example=1),
     *                     @OA\Property(property="from", type="integer", example=1),
     *                     @OA\Property(property="to", type="integer", example=2)
     *                 ),
     *                 @OA\Property(
     *                     property="links",
     *                     type="object",
     *                     @OA\Property(property="first", type="array", @OA\Items(type="string")),
     *                     @OA\Property(property="last", type="array", @OA\Items(type="string")),
     *                     @OA\Property(property="prev", type="array", @OA\Items(type="string", nullable=true)),
     *                     @OA\Property(property="next", type="array", @OA\Items(type="string", nullable=true))
     *                 ),
     *                 @OA\Property(
     *                     property="meta",
     *                     type="object",
     *                     @OA\Property(property="current_page", type="integer"),
     *                     @OA\Property(property="from", type="integer"),
     *                     @OA\Property(property="last_page", type="integer"),
     *                     @OA\Property(property="path", type="string"),
     *                     @OA\Property(property="per_page", type="integer"),
     *                     @OA\Property(property="to", type="integer"),
     *                     @OA\Property(property="total", type="integer")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-15T18:09:03.166622Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="0451ad6e-a779-4b20-9930-b830a1e97268"),
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
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(property="name", type="array", @OA\Items(type="string", example="Store name is required.")),
     *                 @OA\Property(property="address", type="array", @OA\Items(type="string", example="Store address is required.")),
     *                 @OA\Property(property="phone", type="array", @OA\Items(type="string", example="Phone number must be a valid Kenyan phone number."))
     *             )
     *         )
     *     )
     * )
     */
    public function store(StoreRequest $request): JsonResponse
    {
        try {
            $store = $this->storeService->createStore($request->validated());

            $resource = new StoreResource($store);

            return ApiResponse::created(
                'Store created successfully',
                $resource->response()->getData(true)
            );
        } catch (\Exception $e) {
            Log::error('Failed to create store', [
                'tenant_id' => tenant()->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiResponse::serverError(
                'Failed to create store. Please try again.'
            );
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/stores/{id}",
     *     summary="Get store by ID",
     *     description="Retrieves detailed information about a specific store including manager, creator, and updater information.",
     *     tags={"Tenant Store Management"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Store ID",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         example=1
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Store retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Store retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="data",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="code", type="string", example="STR-2025-74622"),
     *                     @OA\Property(property="name", type="string", example="Branch Store - Mombasa"),
     *                     @OA\Property(property="description", type="string", example="Mombasa branch location"),
     *                     @OA\Property(property="address", type="string", example="Moi Avenue, Mombasa"),
     *                     @OA\Property(property="city", type="string", example="Mombasa"),
     *                     @OA\Property(property="region", type="string", example="Coast"),
     *                     @OA\Property(property="phone", type="string", example="+254723456789"),
     *                     @OA\Property(property="email", type="string", example="mombasa@store.com"),
     *                     @OA\Property(property="is_main_store", type="boolean", example=true),
     *                     @OA\Property(property="is_active", type="boolean", example=true),
     *                     @OA\Property(property="status_label", type="string", example="Active"),
     *                     @OA\Property(property="store_type_label", type="string", example="Main Store"),
     *                     @OA\Property(
     *                         property="manager",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=6),
     *                         @OA\Property(property="name", type="string", example="Jane Doe"),
     *                         @OA\Property(property="email", type="string", example="jane@techhaven.com"),
     *                         @OA\Property(property="phone", type="string", example="+254712345678")
     *                     ),
     *                     @OA\Property(
     *                         property="creator",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="John Doe")
     *                     ),
     *                     @OA\Property(
     *                         property="updater",
     *                         type="object",
     *                         nullable=true,
     *                         example=null
     *                     ),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-14T19:48:13.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-14T19:48:13.000000Z")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-15T17:52:25.512790Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="3fad300b-f516-4f33-901e-cd832c59425f"),
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
     *         description="Store not found"
     *     )
     * )
     */
    public function show(int $id): JsonResponse
    {
        try {
            $store = $this->storeService->getStoreById($id);

            if (!$store) {
                return ApiResponse::notFound('Store not found');
            }

            $resource = new StoreResource($store);

            return ApiResponse::success(
                'Store retrieved successfully',
                $resource->response()->getData(true)
            );
        } catch (\Exception $e) {
            Log::error('Failed to fetch store details', [
                'tenant_id' => tenant()->id,
                'store_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::serverError(
                'Failed to retrieve store details. Please try again.'
            );
        }
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/tenant/stores/{id}/details",
     *     summary="Update store details",
     *     description="Update only specific store details. Only provided fields will be updated, other fields remain unchanged.",
     *     tags={"Tenant Store Management"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Store ID",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         example=1
     *     ),
     *     @OA\RequestBody(
     *         required=false,
     *         description="Store details to update (all fields are optional)",
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", maxLength=255, description="Store name", example="Updated Store Name"),
     *             @OA\Property(property="description", type="string", maxLength=1000, description="Store description", example="Updated description"),
     *             @OA\Property(property="address", type="string", maxLength=500, description="Store address", example="New Address Street"),
     *             @OA\Property(property="city", type="string", maxLength=100, description="City", example="Mombasa"),
     *             @OA\Property(property="region", type="string", maxLength=100, description="Region", example="Coast"),
     *             @OA\Property(property="phone", type="string", description="Phone number", example="+254723456789"),
     *             @OA\Property(property="email", type="string", format="email", maxLength=255, description="Email address", example="updated@store.com")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Store details updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Store details updated successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="data",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="code", type="string", example="STR-2025-74622"),
     *                     @OA\Property(property="name", type="string", example="Branch Store - Mombasa"),
     *                     @OA\Property(property="description", type="string", example="Mombasa branch location"),
     *                     @OA\Property(property="address", type="string", example="Gedi, Kwale"),
     *                     @OA\Property(property="city", type="string", example="Mombasa"),
     *                     @OA\Property(property="region", type="string", example="Coast"),
     *                     @OA\Property(property="phone", type="string", example="+254723456789"),
     *                     @OA\Property(property="email", type="string", example="info@store.com"),
     *                     @OA\Property(property="is_main_store", type="boolean", example=true),
     *                     @OA\Property(property="is_active", type="boolean", example=true),
     *                     @OA\Property(property="status_label", type="string", example="Active"),
     *                     @OA\Property(property="store_type_label", type="string", example="Main Store"),
     *                     @OA\Property(
     *                         property="manager",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=6),
     *                         @OA\Property(property="name", type="string", example="Jane Doe"),
     *                         @OA\Property(property="email", type="string", example="jane@techhaven.com"),
     *                         @OA\Property(property="phone", type="string", example="+254712345678")
     *                     ),
     *                     @OA\Property(
     *                         property="creator",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="John Doe")
     *                     ),
     *                     @OA\Property(
     *                         property="updater",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="John Doe")
     *                     ),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-14T19:48:13.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-15T17:56:09.000000Z")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-15T17:56:10.029469Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="cb536a57-4b17-45e7-bd37-046d3bacedd9"),
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
     *         description="Store not found"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function updateDetails(UpdateStoreDetailsRequest $request, int $id): JsonResponse
    {
        try {
            $store = $this->storeService->getStoreById($id);

            if (!$store) {
                return ApiResponse::notFound('Store not found');
            }

            $updatedStore = $this->storeService->updateStoreDetails($store, $request->getUpdateData());

            $resource = new StoreResource($updatedStore);

            return ApiResponse::success(
                'Store details updated successfully',
                $resource->response()->getData(true)
            );
        } catch (\Exception $e) {
            Log::error('Failed to update store details', [
                'tenant_id' => tenant()->id,
                'store_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::serverError(
                'Failed to update store. Please try again.'
            );
        }
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/tenant/stores/{id}/set-main",
     *     summary="Set store as main store",
     *     description="Sets the store as the main store. Any existing main store will be automatically unset.",
     *     tags={"Tenant Store Management"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Store ID",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         example=2
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Store set as main store successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Store set as main store successfully"),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-15T18:14:36.843324Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="64448bab-a857-4eb2-a7eb-5dec7809dd00"),
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
     *         description="Store not found"
     *     )
     * )
     */
    public function setAsMain(int $id): JsonResponse
    {
        try {
            $store = $this->storeService->getStoreById($id);

            if (!$store) {
                return ApiResponse::notFound('Store not found');
            }

            $this->storeService->setStoreAsMain($store);

            return ApiResponse::success('Store set as main store successfully');
        } catch (\Exception $e) {
            Log::error('Failed to set store as main', [
                'tenant_id' => tenant()->id,
                'store_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::serverError(
                'Failed to set store as main. Please try again.'
            );
        }
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/tenant/stores/{id}/activate",
     *     summary="Activate store",
     *     description="Activates a store, making it available for use.",
     *     tags={"Tenant Store Management"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Store ID",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         example=1
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Store activated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Store activated successfully"),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-15T18:04:53.610068Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="56667e93-fc96-4de7-879e-5d7a32f86e20"),
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
     *         description="Store not found"
     *     )
     * )
     */
    public function activate(int $id): JsonResponse
    {
        try {
            $store = $this->storeService->getStoreById($id);

            if (!$store) {
                return ApiResponse::notFound('Store not found');
            }

            $this->storeService->activateStore($store);

            return ApiResponse::success('Store activated successfully');
        } catch (\Exception $e) {
            Log::error('Failed to activate store', [
                'tenant_id' => tenant()->id,
                'store_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::serverError(
                'Failed to activate store. Please try again.'
            );
        }
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/tenant/stores/{id}/deactivate",
     *     summary="Deactivate store",
     *     description="Deactivates a store. Cannot deactivate if it's the only active store.",
     *     tags={"Tenant Store Management"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Store ID",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         example=1
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Store deactivated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Store deactivated successfully"),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-15T18:14:00.973348Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="839aeb2c-33d4-4560-a83c-4b6f0930d681"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Cannot deactivate the only active store",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Cannot deactivate the only active store. At least one store must remain active."),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-15T18:05:51.629133Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="b8f20bf6-b4d3-4812-af4b-a4140efc2e23"),
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
     *         description="Store not found"
     *     )
     * )
     */
    public function deactivate(int $id): JsonResponse
    {
        try {
            $store = $this->storeService->getStoreById($id);

            if (!$store) {
                return ApiResponse::notFound('Store not found');
            }

            $this->storeService->deactivateStore($store);

            return ApiResponse::success('Store deactivated successfully');
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), null, 400);
        } catch (\Exception $e) {
            Log::error('Failed to deactivate store', [
                'tenant_id' => tenant()->id,
                'store_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::serverError(
                'Failed to deactivate store. Please try again.'
            );
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/stores/{id}/assign-manager",
     *     summary="Assign manager to store",
     *     description="Assigns a manager to the store. The user must have manager or owner role.",
     *     tags={"Tenant Store Management"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Store ID",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         example=1
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"manager_id"},
     *             @OA\Property(
     *                 property="manager_id",
     *                 type="integer",
     *                 description="Manager user ID (must have manager or owner role)",
     *                 example=3
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Manager assigned successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Manager assigned successfully"),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-15T18:00:44.427010Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="8d87973a-b7e0-42e5-85ce-15ea2a7fad4d"),
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
     *         description="Store not found"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="manager_id",
     *                     type="array",
     *                     @OA\Items(type="string", example="The selected manager must be a user with manager or owner role.")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-15T17:59:14.010373Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="fd988fc7-d478-4b10-a508-d327404dcd00"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     )
     * )
     */
    public function assignManager(AssignManagerRequest $request, int $id): JsonResponse
    {
        try {
            $store = $this->storeService->getStoreById($id);

            if (!$store) {
                return ApiResponse::notFound('Store not found');
            }

            $this->storeService->assignManagerToStore(
                $store,
                $request->validated('manager_id')
            );

            return ApiResponse::success('Manager assigned successfully');
        } catch (\Exception $e) {
            Log::error('Failed to assign manager', [
                'tenant_id' => tenant()->id,
                'store_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::serverError(
                'Failed to assign manager. Please try again.'
            );
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/tenant/stores/{id}/remove-manager",
     *     summary="Remove manager from store",
     *     description="Removes the assigned manager from the store.",
     *     tags={"Tenant Store Management"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Store ID",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         example=1
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"manager_id"},
     *             @OA\Property(
     *                 property="manager_id",
     *                 type="integer",
     *                 description="Manager user ID to remove",
     *                 example=1
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Manager removed successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Manager removed successfully"),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-15T18:02:43.037830Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="3b7abe10-3c70-4c7b-a00f-4816c7c0d0e8"),
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
     *         description="Store not found"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function removeManager(int $id): JsonResponse
    {
        try {
            $store = $this->storeService->getStoreById($id);

            if (!$store) {
                return ApiResponse::notFound('Store not found');
            }

            $this->storeService->removeManagerFromStore($store);

            return ApiResponse::success('Manager removed successfully');
        } catch (\Exception $e) {
            Log::error('Failed to remove manager', [
                'tenant_id' => tenant()->id,
                'store_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::serverError(
                'Failed to remove manager. Please try again.'
            );
        }
    }
}
