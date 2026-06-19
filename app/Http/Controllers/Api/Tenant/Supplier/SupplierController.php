<?php

namespace App\Http\Controllers\Api\Tenant\Supplier;

use App\Enums\Tenant\PaymentTerms;
use App\Enums\Tenant\SupplierType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Supplier\IndexSupplierRequest;
use App\Http\Requests\Tenant\Supplier\StoreSupplierPersonalDetailsRequest;
use App\Http\Requests\Tenant\Supplier\UpdateSupplierFinancialDetailsRequest;
use App\Http\Requests\Tenant\Supplier\UpdateSupplierPersonalDetailsRequest;
use App\Http\Resources\Tenant\Supplier\SupplierResource;
use App\Http\Responses\ApiResponse;
use App\Services\Tenant\Supplier\SupplierService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function __construct(
        protected SupplierService $supplierService
    ) {}

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/suppliers",
     *     summary="Get all suppliers",
     *     description="Retrieves a list of suppliers with optional filtering, searching, and pagination capabilities.",
     *     tags={"Tenant Suppliers"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="supplier_type",
     *         in="query",
     *         description="Filter by supplier type",
     *         required=false,
     *         @OA\Schema(type="string", enum={"manufacturer", "distributor", "wholesaler", "retailer"}),
     *         example="wholesaler"
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
     *         name="search",
     *         in="query",
     *         description="Search suppliers by name, contact person, email, or phone",
     *         required=false,
     *         @OA\Schema(type="string"),
     *         example="TechPro"
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
     *         example=10
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Suppliers retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Suppliers retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="TechPro Manufacturing Ltd"),
     *                     @OA\Property(property="supplier_type", type="string", example="distributor"),
     *                     @OA\Property(property="supplier_type_display", type="string", example="Distributor"),
     *                     @OA\Property(property="supplier_type_description", type="string", example="Distributes products from manufacturers to retailers"),
     *                     @OA\Property(property="contact_person", type="string", example="John Doe Jr."),
     *                     @OA\Property(property="email", type="string", example="johnjr@techpro.com"),
     *                     @OA\Property(property="phone", type="string", example="+254712345679"),
     *                     @OA\Property(property="address", type="string", example="789 New Industrial Park, Nairobi, Kenya"),
     *                     @OA\Property(property="registration_number", type="string", example="PVT-2023-001234"),
     *                     @OA\Property(property="credit_limit", type="string", example="1000000.00"),
     *                     @OA\Property(property="outstanding_balance", type="string", example="0.00"),
     *                     @OA\Property(property="payment_terms", type="string", example="net_30"),
     *                     @OA\Property(property="payment_terms_display", type="string", example="Net 30 Days"),
     *                     @OA\Property(property="payment_terms_description", type="string", example="Payment due within 30 days of invoice date"),
     *                     @OA\Property(property="payment_terms_days", type="integer", example=30),
     *                     @OA\Property(
     *                         property="bank_account_details",
     *                         type="object",
     *                         @OA\Property(property="bank", type="string", example="Equity Bank Kenya"),
     *                         @OA\Property(property="branch", type="string", example="Industrial Area Branch"),
     *                         @OA\Property(property="account_name", type="string", example="TechPro Manufacturing Ltd"),
     *                         @OA\Property(property="account_number", type="string", example="0123456789")
     *                     ),
     *                     @OA\Property(property="rating", type="string", example="0.00"),
     *                     @OA\Property(property="total_orders", type="integer", example=0),
     *                     @OA\Property(property="is_active", type="boolean", example=true),
     *                     @OA\Property(property="notes", type="string", example="Expanded operations to include distribution services"),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-19T11:55:47.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-19T12:04:39.000000Z")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-19T12:13:17.262690Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="68328e34-03a3-48a6-b2fd-32ec0d92aa0a"),
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
    public function index(IndexSupplierRequest $request): JsonResponse
    {
        try {
            $filters = $request->getFilters();
            $paginate = $request->shouldPaginate();
            $perPage = $request->getPerPage();

            $suppliers = $this->supplierService->getAllSuppliers($filters, $paginate, $perPage);

            if ($paginate) {
                return ApiResponse::success(
                    'Suppliers retrieved successfully',
                    [
                        'data' => SupplierResource::collection($suppliers->items()),
                        'pagination' => [
                            'current_page' => $suppliers->currentPage(),
                            'last_page' => $suppliers->lastPage(),
                            'per_page' => $suppliers->perPage(),
                            'total' => $suppliers->total(),
                            'from' => $suppliers->firstItem(),
                            'to' => $suppliers->lastItem(),
                        ]
                    ]
                );
            }

            return ApiResponse::success(
                'Suppliers retrieved successfully',
                SupplierResource::collection($suppliers)
            );
        } catch (\Exception $e) {
            return ApiResponse::serverError(
                'Failed to retrieve suppliers: ' . $e->getMessage()
            );
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/suppliers/{id}",
     *     summary="Get supplier by ID",
     *     description="Retrieves detailed information about a specific supplier including products and product count.",
     *     tags={"Tenant Suppliers"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Supplier ID",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         example=1
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Supplier retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Supplier retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="TechPro Manufacturing Ltd"),
     *                 @OA\Property(property="supplier_type", type="string", example="distributor"),
     *                 @OA\Property(property="supplier_type_display", type="string", example="Distributor"),
     *                 @OA\Property(property="supplier_type_description", type="string", example="Distributes products from manufacturers to retailers"),
     *                 @OA\Property(property="contact_person", type="string", example="John Doe Jr."),
     *                 @OA\Property(property="email", type="string", example="johnjr@techpro.com"),
     *                 @OA\Property(property="phone", type="string", example="+254712345679"),
     *                 @OA\Property(property="address", type="string", example="789 New Industrial Park, Nairobi, Kenya"),
     *                 @OA\Property(property="registration_number", type="string", example="PVT-2023-001234"),
     *                 @OA\Property(property="credit_limit", type="string", example="1000000.00"),
     *                 @OA\Property(property="outstanding_balance", type="string", example="0.00"),
     *                 @OA\Property(property="payment_terms", type="string", example="net_30"),
     *                 @OA\Property(property="payment_terms_display", type="string", example="Net 30 Days"),
     *                 @OA\Property(property="payment_terms_description", type="string", example="Payment due within 30 days of invoice date"),
     *                 @OA\Property(property="payment_terms_days", type="integer", example=30),
     *                 @OA\Property(
     *                     property="bank_account_details",
     *                     type="object",
     *                     @OA\Property(property="bank", type="string", example="Equity Bank Kenya"),
     *                     @OA\Property(property="branch", type="string", example="Industrial Area Branch"),
     *                     @OA\Property(property="account_name", type="string", example="TechPro Manufacturing Ltd"),
     *                     @OA\Property(property="account_number", type="string", example="0123456789")
     *                 ),
     *                 @OA\Property(property="rating", type="string", example="0.00"),
     *                 @OA\Property(property="total_orders", type="integer", example=0),
     *                 @OA\Property(property="is_active", type="boolean", example=true),
     *                 @OA\Property(property="notes", type="string", example="Expanded operations to include distribution services"),
     *                 @OA\Property(
     *                     property="products",
     *                     type="array",
     *                     @OA\Items(type="object")
     *                 ),
     *                 @OA\Property(property="product_count", type="integer", example=0),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-19T11:55:47.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-19T12:04:39.000000Z")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-19T12:10:59.736938Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="9aefc593-6d8d-4ca4-840d-60ed2a290e47"),
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
     *         description="Supplier not found"
     *     )
     * )
     */
    public function show(int $id): JsonResponse
    {
        try {
            $supplier = $this->supplierService->getSupplierById($id, true);

            if (!$supplier) {
                return ApiResponse::notFound('Supplier not found');
            }

            return ApiResponse::success(
                'Supplier retrieved successfully',
                new SupplierResource($supplier)
            );
        } catch (\Exception $e) {
            return ApiResponse::serverError(
                'Failed to retrieve supplier: ' . $e->getMessage()
            );
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/suppliers",
     *     summary="Create a new supplier",
     *     description="Creates a new supplier with contact and business information. Default payment terms is 'cod' and credit limit is 0.00.",
     *     tags={"Tenant Suppliers"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "supplier_type"},
     *             @OA\Property(property="name", type="string", maxLength=255, description="Supplier name", example="TechPro Manufacturing Ltd"),
     *             @OA\Property(property="supplier_type", type="string", enum={"manufacturer", "distributor", "wholesaler", "retailer"}, description="Supplier type", example="manufacturer"),
     *             @OA\Property(property="contact_person", type="string", maxLength=255, description="Contact person name", example="Mike Doe"),
     *             @OA\Property(property="email", type="string", format="email", maxLength=255, description="Email address", example="mike.doe@techpro.com"),
     *             @OA\Property(property="phone", type="string", maxLength=20, description="Phone number (Kenyan format)", example="+254712345678"),
     *             @OA\Property(property="address", type="string", description="Physical address", example="123 Industrial Area, Nairobi, Kenya"),
     *             @OA\Property(property="registration_number", type="string", maxLength=100, description="Business registration number", example="PVT-2023-001234"),
     *             @OA\Property(property="notes", type="string", description="Additional notes", example="Specializes in electronic components and hardware manufacturing"),
     *             @OA\Property(property="is_active", type="boolean", description="Active status (default: true)", example=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Supplier created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Supplier created successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="TechPro Manufacturing Ltd"),
     *                 @OA\Property(property="supplier_type", type="string", example="manufacturer"),
     *                 @OA\Property(property="supplier_type_display", type="string", example="Manufacturer"),
     *                 @OA\Property(property="supplier_type_description", type="string", example="Produces or manufactures goods directly"),
     *                 @OA\Property(property="contact_person", type="string", example="Mike Doe"),
     *                 @OA\Property(property="email", type="string", example="mike.doe@techpro.com"),
     *                 @OA\Property(property="phone", type="string", example="+254712345678"),
     *                 @OA\Property(property="address", type="string", example="123 Industrial Area, Nairobi, Kenya"),
     *                 @OA\Property(property="registration_number", type="string", example="PVT-2023-001234"),
     *                 @OA\Property(property="credit_limit", type="string", example="0.00"),
     *                 @OA\Property(property="outstanding_balance", type="string", example="0.00"),
     *                 @OA\Property(property="payment_terms", type="string", example="cod"),
     *                 @OA\Property(property="payment_terms_display", type="string", example="Cash on Delivery"),
     *                 @OA\Property(property="payment_terms_description", type="string", example="Payment due upon delivery of goods"),
     *                 @OA\Property(property="payment_terms_days", type="integer", example=0),
     *                 @OA\Property(property="bank_account_details", type="object", nullable=true, example=null),
     *                 @OA\Property(property="rating", type="string", example="0.00"),
     *                 @OA\Property(property="total_orders", type="integer", example=0),
     *                 @OA\Property(property="is_active", type="boolean", example=true),
     *                 @OA\Property(property="notes", type="string", example="Specializes in electronic components and hardware manufacturing"),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-19T11:55:47.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-19T11:55:47.000000Z")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-19T11:55:47.994820Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="13278c12-743d-4e06-b678-df8adf51a7ed"),
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
     *                 @OA\Property(
     *                     property="supplier_type",
     *                     type="array",
     *                     @OA\Items(type="string", example="The selected supplier type is invalid.")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-19T11:58:04.631375Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="3cdbc869-64f9-4cc6-9670-a0e4d365f957"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     )
     * )
     */
    public function store(StoreSupplierPersonalDetailsRequest $request): JsonResponse
    {
        try {
            $supplier = $this->supplierService->createSupplierPersonalDetails($request->validated());

            return ApiResponse::created(
                'Supplier created successfully',
                new SupplierResource($supplier)
            );
        } catch (\Exception $e) {
            return ApiResponse::serverError(
                'Failed to create supplier: ' . $e->getMessage()
            );
        }
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/tenant/suppliers/{id}/personal-details",
     *     summary="Update supplier personal details",
     *     description="Updates supplier contact and address information. Only provided fields will be updated.",
     *     tags={"Tenant Suppliers"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Supplier ID",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         example=1
     *     ),
     *     @OA\RequestBody(
     *         required=false,
     *         description="Personal details to update (all fields are optional)",
     *         @OA\JsonContent(
     *             @OA\Property(property="contact_person", type="string", maxLength=255, description="Contact person name", example="John Doe Jr."),
     *             @OA\Property(property="email", type="string", format="email", maxLength=255, description="Email address", example="johnjr@techpro.com"),
     *             @OA\Property(property="phone", type="string", maxLength=20, description="Phone number", example="+254712345679"),
     *             @OA\Property(property="address", type="string", description="Physical address", example="789 New Industrial Park, Nairobi, Kenya")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Supplier personal details updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Supplier personal details updated successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="TechPro Manufacturing Ltd"),
     *                 @OA\Property(property="supplier_type", type="string", example="manufacturer"),
     *                 @OA\Property(property="supplier_type_display", type="string", example="Manufacturer"),
     *                 @OA\Property(property="supplier_type_description", type="string", example="Produces or manufactures goods directly"),
     *                 @OA\Property(property="contact_person", type="string", example="John Doe Jr."),
     *                 @OA\Property(property="email", type="string", example="johnjr@techpro.com"),
     *                 @OA\Property(property="phone", type="string", example="+254712345679"),
     *                 @OA\Property(property="address", type="string", example="789 New Industrial Park, Nairobi, Kenya"),
     *                 @OA\Property(property="registration_number", type="string", example="PVT-2023-001234"),
     *                 @OA\Property(property="credit_limit", type="string", example="0.00"),
     *                 @OA\Property(property="outstanding_balance", type="string", example="0.00"),
     *                 @OA\Property(property="payment_terms", type="string", example="cod"),
     *                 @OA\Property(property="payment_terms_display", type="string", example="Cash on Delivery"),
     *                 @OA\Property(property="payment_terms_description", type="string", example="Payment due upon delivery of goods"),
     *                 @OA\Property(property="payment_terms_days", type="integer", example=0),
     *                 @OA\Property(property="bank_account_details", type="object", nullable=true, example=null),
     *                 @OA\Property(property="rating", type="string", example="0.00"),
     *                 @OA\Property(property="total_orders", type="integer", example=0),
     *                 @OA\Property(property="is_active", type="boolean", example=true),
     *                 @OA\Property(property="notes", type="string", example="Specializes in electronic components and hardware manufacturing"),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-19T11:55:47.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-19T12:01:50.000000Z")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-19T12:01:50.057904Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="26610d12-7ef5-47bc-aad7-ddbac8768ec9"),
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
     *         description="Supplier not found"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function updatePersonalDetails(UpdateSupplierPersonalDetailsRequest $request, int $id): JsonResponse
    {
        try {
            $supplier = $this->supplierService->updateSupplierPersonalDetails($id, $request->validated());

            return ApiResponse::success(
                'Supplier personal details updated successfully',
                new SupplierResource($supplier)
            );
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), null, 400);
        } catch (\Exception $e) {
            return ApiResponse::serverError(
                'Failed to update supplier personal details: ' . $e->getMessage()
            );
        }
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/tenant/suppliers/{id}/financial-details",
     *     summary="Update supplier financial details",
     *     description="Updates supplier credit limit, payment terms, and bank account details. Only provided fields will be updated.",
     *     tags={"Tenant Suppliers"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Supplier ID",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         example=1
     *     ),
     *     @OA\RequestBody(
     *         required=false,
     *         description="Financial details to update (all fields are optional)",
     *         @OA\JsonContent(
     *             @OA\Property(property="credit_limit", type="number", format="float", minimum=0, description="Credit limit (cannot be negative)", example=1000000.00),
     *             @OA\Property(property="payment_terms", type="string", enum={"cod", "net_7", "net_15", "net_30", "net_60", "net_90"}, description="Payment terms", example="net_30"),
     *             @OA\Property(
     *                 property="bank_account_details",
     *                 type="object",
     *                 description="Bank account information",
     *                 @OA\Property(property="bank", type="string", description="Bank name", example="Equity Bank Kenya"),
     *                 @OA\Property(property="account_name", type="string", description="Account holder name", example="TechPro Manufacturing Ltd"),
     *                 @OA\Property(property="account_number", type="string", description="Account number", example="0123456789"),
     *                 @OA\Property(property="branch", type="string", description="Branch name", example="Industrial Area Branch")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Supplier financial details updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Supplier financial details updated successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="TechPro Manufacturing Ltd"),
     *                 @OA\Property(property="supplier_type", type="string", example="distributor"),
     *                 @OA\Property(property="supplier_type_display", type="string", example="Distributor"),
     *                 @OA\Property(property="supplier_type_description", type="string", example="Distributes products from manufacturers to retailers"),
     *                 @OA\Property(property="contact_person", type="string", example="John Doe Jr."),
     *                 @OA\Property(property="email", type="string", example="johnjr@techpro.com"),
     *                 @OA\Property(property="phone", type="string", example="+254712345679"),
     *                 @OA\Property(property="address", type="string", example="789 New Industrial Park, Nairobi, Kenya"),
     *                 @OA\Property(property="registration_number", type="string", example="PVT-2023-001234"),
     *                 @OA\Property(property="credit_limit", type="string", example="1000000.00"),
     *                 @OA\Property(property="outstanding_balance", type="string", example="0.00"),
     *                 @OA\Property(property="payment_terms", type="string", example="net_30"),
     *                 @OA\Property(property="payment_terms_display", type="string", example="Net 30 Days"),
     *                 @OA\Property(property="payment_terms_description", type="string", example="Payment due within 30 days of invoice date"),
     *                 @OA\Property(property="payment_terms_days", type="integer", example=30),
     *                 @OA\Property(
     *                     property="bank_account_details",
     *                     type="object",
     *                     @OA\Property(property="bank", type="string", example="Equity Bank Kenya"),
     *                     @OA\Property(property="branch", type="string", example="Industrial Area Branch"),
     *                     @OA\Property(property="account_name", type="string", example="TechPro Manufacturing Ltd"),
     *                     @OA\Property(property="account_number", type="string", example="0123456789")
     *                 ),
     *                 @OA\Property(property="rating", type="string", example="0.00"),
     *                 @OA\Property(property="total_orders", type="integer", example=0),
     *                 @OA\Property(property="is_active", type="boolean", example=true),
     *                 @OA\Property(property="notes", type="string", example="Expanded operations to include distribution services"),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-19T11:55:47.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-19T12:04:39.000000Z")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-19T12:04:39.444972Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="5dd967d5-5ca5-417d-afd8-ee7a337b749b"),
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
     *         description="Supplier not found"
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
     *                 oneOf={
     *                     @OA\Schema(
     *                         @OA\Property(
     *                             property="payment_terms",
     *                             type="array",
     *                             @OA\Items(type="string", example="The selected payment terms is invalid.")
     *                         )
     *                     ),
     *                     @OA\Schema(
     *                         @OA\Property(
     *                             property="credit_limit",
     *                             type="array",
     *                             @OA\Items(type="string", example="Credit limit cannot be negative")
     *                         )
     *                     )
     *                 }
     *             )
     *         )
     *     )
     * )
     */
    public function updateFinancialDetails(UpdateSupplierFinancialDetailsRequest $request, int $id): JsonResponse
    {
        try {
            $supplier = $this->supplierService->updateSupplierFinancialDetails($id, $request->validated());

            return ApiResponse::success(
                'Supplier financial details updated successfully',
                new SupplierResource($supplier)
            );
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), null, 400);
        } catch (\Exception $e) {
            return ApiResponse::serverError(
                'Failed to update supplier financial details: ' . $e->getMessage()
            );
        }
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/tenant/suppliers/{id}/toggle-active",
     *     summary="Toggle supplier active status",
     *     description="Toggles the active status of a supplier. If currently active, it will be deactivated and vice versa.",
     *     tags={"Tenant Suppliers"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Supplier ID",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         example=2
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Supplier status toggled successfully",
     *         @OA\JsonContent(
     *             oneOf={
     *                 @OA\Schema(
     *                     @OA\Property(property="success", type="boolean", example=true),
     *                     @OA\Property(property="message", type="string", example="Supplier deactivated successfully"),
     *                     @OA\Property(
     *                         property="meta",
     *                         type="object",
     *                         @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-19T12:08:36.156061Z"),
     *                         @OA\Property(property="request_id", type="string", format="uuid", example="a5cdda12-1c6d-4407-be46-744054144abf"),
     *                         @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                         @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *                     )
     *                 ),
     *                 @OA\Schema(
     *                     @OA\Property(property="success", type="boolean", example=true),
     *                     @OA\Property(property="message", type="string", example="Supplier activated successfully")
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
     *         description="Supplier not found"
     *     )
     * )
     */
    public function toggleActive(int $id): JsonResponse
    {
        try {
            $result = $this->supplierService->toggleActiveStatus($id);

            return ApiResponse::success($result['message']);
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), null, 400);
        } catch (\Exception $e) {
            return ApiResponse::serverError(
                'Failed to toggle supplier status: ' . $e->getMessage()
            );
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/suppliers/supplier-options",
     *     summary="Get supplier options",
     *     description="Retrieves available options for supplier types and payment terms. Useful for populating form dropdowns.",
     *     tags={"Tenant Suppliers"},
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Supplier options retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="supplier_types",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="value", type="string", example="manufacturer"),
     *                         @OA\Property(property="label", type="string", example="Manufacturer"),
     *                         @OA\Property(property="description", type="string", example="Produces or manufactures goods directly")
     *                     ),
     *                     example={
     *                         {
     *                             "value": "manufacturer",
     *                             "label": "Manufacturer",
     *                             "description": "Produces or manufactures goods directly"
     *                         },
     *                         {
     *                             "value": "distributor",
     *                             "label": "Distributor",
     *                             "description": "Distributes products from manufacturers to retailers"
     *                         },
     *                         {
     *                             "value": "wholesaler",
     *                             "label": "Wholesaler",
     *                             "description": "Sells products in bulk to retailers or other businesses"
     *                         },
     *                         {
     *                             "value": "retailer",
     *                             "label": "Retailer",
     *                             "description": "Sells products directly to consumers"
     *                         }
     *                     }
     *                 ),
     *                 @OA\Property(
     *                     property="payment_terms",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="value", type="string", example="cod"),
     *                         @OA\Property(property="label", type="string", example="Cash on Delivery"),
     *                         @OA\Property(property="description", type="string", example="Payment due upon delivery of goods"),
     *                         @OA\Property(property="days", type="integer", example=0)
     *                     ),
     *                     example={
     *                         {
     *                             "value": "cod",
     *                             "label": "Cash on Delivery",
     *                             "description": "Payment due upon delivery of goods",
     *                             "days": 0
     *                         },
     *                         {
     *                             "value": "net_7",
     *                             "label": "Net 7 Days",
     *                             "description": "Payment due within 7 days of invoice date",
     *                             "days": 7
     *                         },
     *                         {
     *                             "value": "net_15",
     *                             "label": "Net 15 Days",
     *                             "description": "Payment due within 15 days of invoice date",
     *                             "days": 15
     *                         },
     *                         {
     *                             "value": "net_30",
     *                             "label": "Net 30 Days",
     *                             "description": "Payment due within 30 days of invoice date",
     *                             "days": 30
     *                         },
     *                         {
     *                             "value": "net_60",
     *                             "label": "Net 60 Days",
     *                             "description": "Payment due within 60 days of invoice date",
     *                             "days": 60
     *                         },
     *                         {
     *                             "value": "net_90",
     *                             "label": "Net 90 Days",
     *                             "description": "Payment due within 90 days of invoice date",
     *                             "days": 90
     *                         }
     *                     }
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     )
     * )
     */
    public function supplierOptions(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'supplier_types' => SupplierType::options(),
                'payment_terms' => PaymentTerms::options(),
            ],
        ]);
    }
}
