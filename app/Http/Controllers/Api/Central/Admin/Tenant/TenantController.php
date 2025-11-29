<?php

namespace App\Http\Controllers\Api\Central\Admin\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Tenant\AddDomainRequest;
use App\Http\Requests\Central\Tenant\CreateTenantRequest;
use App\Http\Requests\Central\Tenant\UpdateDomainRequest;
use App\Http\Resources\Central\Admin\Tenant\DomainResource;
use App\Http\Resources\Central\Admin\Tenant\TenantResource;
use App\Http\Responses\ApiResponse;
use App\Services\Central\Admin\Tenant\TenantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantController extends Controller
{
    public function __construct(
        private readonly TenantService $tenantService
    ) {}

    /**
     * Create a new tenant.
     *
     * @OA\Post(
     *     path="/api/v1/central/tenants",
     *     summary="Create New Tenant",
     *     description="Create a new tenant with domain(s). This will create the tenant database and assign domain(s).",
     *     operationId="createTenant",
     *     tags={"Tenant Management"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Tenant creation data",
     *         @OA\JsonContent(
     *             required={"domain"},
     *             @OA\Property(
     *                 property="domain",
     *                 type="string",
     *                 example="merchant1.poachy.com",
     *                 description="Primary domain for the tenant"
     *             ),
     *             @OA\Property(
     *                 property="additional_domains",
     *                 type="array",
     *                 @OA\Items(type="string", example="merchant1.example.com"),
     *                 description="Optional additional domains"
     *             ),
     *             @OA\Property(
     *                 property="tenant_name",
     *                 type="string",
     *                 example="Merchant Store 1",
     *                 description="Optional tenant identifier name"
     *             ),
     *             @OA\Property(
     *                 property="notes",
     *                 type="string",
     *                 example="Premium merchant account",
     *                 description="Optional notes about the tenant"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Tenant created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Tenant created successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 ref="#/components/schemas/TenantResource"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - Admin role required",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="This action is unauthorized.")
     *         )
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
     *                     property="domain",
     *                     type="array",
     *                     @OA\Items(type="string", example="This domain is already assigned to another tenant.")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function store(CreateTenantRequest $request): JsonResponse
    {
        $tenant = $this->tenantService->createTenant($request->validated());

        return ApiResponse::created(
            'Tenant created successfully',
            new TenantResource($tenant)
        );
    }

    /**
     * Get all tenants.
     *
     * @OA\Get(
     *     path="/api/v1/central/tenants",
     *     summary="List All Tenants",
     *     description="Get paginated list of all tenants with their domains and business details.",
     *     operationId="listTenants",
     *     tags={"Tenant Management"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Items per page",
     *         required=false,
     *         @OA\Schema(type="integer", example=15, maximum=100)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Tenants retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Tenants retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="data",
     *                     type="array",
     *                     @OA\Items(ref="#/components/schemas/TenantResource")
     *                 ),
     *                 @OA\Property(
     *                     property="pagination",
     *                     type="object",
     *                     @OA\Property(property="current_page", type="integer", example=1),
     *                     @OA\Property(property="last_page", type="integer", example=5),
     *                     @OA\Property(property="per_page", type="integer", example=15),
     *                     @OA\Property(property="total", type="integer", example=75)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->get('per_page', 15), 100);
        $tenants = $this->tenantService->getAllTenants($perPage);

        return ApiResponse::paginated(
            TenantResource::collection($tenants),
            'Tenants retrieved successfully'
        );
    }

    /**
     * Get specific tenant.
     *
     * @OA\Get(
     *     path="/api/v1/central/tenants/{tenant_id}",
     *     summary="Get Tenant Details",
     *     description="Get detailed information about a specific tenant including domains and business details.",
     *     operationId="getTenant",
     *     tags={"Tenant Management"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="tenant_id",
     *         in="path",
     *         description="Tenant UUID",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Tenant details retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Tenant retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 ref="#/components/schemas/TenantResource"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Tenant not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Resource not found")
     *         )
     *     )
     * )
     */
    public function show(string $tenantId): JsonResponse
    {
        $tenant = $this->tenantService->getTenant($tenantId);

        return ApiResponse::success(
            'Tenant retrieved successfully',
            new TenantResource($tenant)
        );
    }

    /**
     * Search tenants.
     *
     * @OA\Get(
     *     path="/api/v1/central/tenants/search",
     *     summary="Search Tenants",
     *     description="Search tenants by domain name or business name.",
     *     operationId="searchTenants",
     *     tags={"Tenant Management"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="q",
     *         in="query",
     *         description="Search query",
     *         required=true,
     *         @OA\Schema(type="string", example="merchant")
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Items per page",
     *         required=false,
     *         @OA\Schema(type="integer", example=15)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Search results retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Search results retrieved successfully")
     *         )
     *     )
     * )
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'q' => ['required', 'string', 'min:2'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = min((int) $request->get('per_page', 15), 100);
        $tenants = $this->tenantService->searchTenants($request->q, $perPage);

        return ApiResponse::paginated(
            TenantResource::collection($tenants),
            'Search results retrieved successfully'
        );
    }

    /**
     * Add domain to tenant.
     *
     * @OA\Post(
     *     path="/api/v1/central/tenants/{tenant_id}/domains",
     *     summary="Add Domain to Tenant",
     *     description="Add an additional domain to an existing tenant.",
     *     operationId="addDomainToTenant",
     *     tags={"Tenant Management"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="tenant_id",
     *         in="path",
     *         description="Tenant UUID",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"domain"},
     *             @OA\Property(
     *                 property="domain",
     *                 type="string",
     *                 example="merchant1-new.poachy.com"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Domain added successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Domain added successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 ref="#/components/schemas/DomainResource"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Tenant not found"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error - Domain already exists"
     *     )
     * )
     */
    public function addDomain(string $tenantId, AddDomainRequest $request): JsonResponse
    {
        $domain = $this->tenantService->addDomain(
            $tenantId,
            $request->validated('domain')
        );

        return ApiResponse::created(
            'Domain added successfully',
            new DomainResource($domain)
        );
    }

    /**
     * Update domain.
     *
     * @OA\Put(
     *     path="/api/v1/central/domains/{domain_id}",
     *     summary="Update Domain",
     *     description="Update an existing domain name.",
     *     operationId="updateDomain",
     *     tags={"Tenant Management"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="domain_id",
     *         in="path",
     *         description="Domain ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"domain"},
     *             @OA\Property(
     *                 property="domain",
     *                 type="string",
     *                 example="merchant1-updated.poachy.com"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Domain updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Domain updated successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 ref="#/components/schemas/DomainResource"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Domain not found"
     *     )
     * )
     */
    public function updateDomain(int $domainId, UpdateDomainRequest $request): JsonResponse
    {
        $domain = $this->tenantService->updateDomain(
            $domainId,
            $request->validated('domain')
        );

        return ApiResponse::success(
            'Domain updated successfully',
            new DomainResource($domain)
        );
    }

    /**
     * Delete domain.
     *
     * @OA\Delete(
     *     path="/api/v1/central/domains/{domain_id}",
     *     summary="Delete Domain",
     *     description="Delete a domain. Cannot delete the last domain of a tenant.",
     *     operationId="deleteDomain",
     *     tags={"Tenant Management"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="domain_id",
     *         in="path",
     *         description="Domain ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Domain deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Domain deleted successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Cannot delete last domain",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Cannot delete the last domain. A tenant must have at least one domain.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Domain not found"
     *     )
     * )
     */
    public function deleteDomain(int $domainId): JsonResponse
    {
        $this->tenantService->deleteDomain($domainId);

        return ApiResponse::success('Domain deleted successfully');
    }

    /**
     * Update tenant metadata.
     *
     * @OA\Patch(
     *     path="/api/v1/central/tenants/{tenant_id}/metadata",
     *     summary="Update Tenant Metadata",
     *     description="Update tenant metadata (name, notes, etc.).",
     *     operationId="updateTenantMetadata",
     *     tags={"Tenant Management"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="tenant_id",
     *         in="path",
     *         description="Tenant UUID",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="tenant_name", type="string", example="Updated Merchant Name"),
     *             @OA\Property(property="notes", type="string", example="Updated notes")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Tenant metadata updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Tenant metadata updated successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 ref="#/components/schemas/TenantResource"
     *             )
     *         )
     *     )
     * )
     */
    public function updateMetadata(string $tenantId, Request $request): JsonResponse
    {
        $request->validate([
            'tenant_name' => ['sometimes', 'string', 'max:255'],
            'notes' => ['sometimes', 'string', 'max:1000'],
        ]);

        $tenant = $this->tenantService->updateTenantMetadata(
            $tenantId,
            $request->only(['tenant_name', 'notes'])
        );

        return ApiResponse::success(
            'Tenant metadata updated successfully',
            new TenantResource($tenant)
        );
    }

    /**
     * Delete tenant.
     *
     * @OA\Delete(
     *     path="/api/v1/central/tenants/{tenant_id}",
     *     summary="Delete Tenant",
     *     description="Delete a tenant and all associated data including database. This action is irreversible.",
     *     operationId="deleteTenant",
     *     tags={"Tenant Management"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="tenant_id",
     *         in="path",
     *         description="Tenant UUID",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Tenant deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Tenant deleted successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Tenant not found"
     *     )
     * )
     */
    public function destroy(string $tenantId): JsonResponse
    {
        $this->tenantService->deleteTenant($tenantId);

        return ApiResponse::success('Tenant deleted successfully');
    }
}
