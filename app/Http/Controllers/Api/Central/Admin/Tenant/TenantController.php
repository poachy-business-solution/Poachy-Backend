<?php

namespace App\Http\Controllers\Api\Central\Admin\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Tenant\AddDomainRequest;
use App\Http\Requests\Central\Tenant\CreateTenantRequest;
use App\Http\Requests\Central\Tenant\CreateTenantUserRequest;
use App\Http\Requests\Central\Tenant\StartTrialPeriodRequest;
use App\Http\Requests\Central\Tenant\UpdateDomainRequest;
use App\Http\Resources\Central\Admin\Tenant\DomainResource;
use App\Http\Resources\Central\Admin\Tenant\TenantResource;
use App\Http\Resources\Central\Tenant\BusinessSubscriptionResource;
use App\Http\Responses\ApiResponse;
use App\Services\Central\Admin\Tenant\TenantService;
use App\Services\Central\Admin\Tenant\TenantUserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantController extends Controller
{
    public function __construct(
        private readonly TenantService $tenantService,
        private readonly TenantUserService $tenantUserService
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

    /**
     * @OA\Post(
     *     path="/api/v1/central/tenants/{tenantId}/users",
     *     summary="Create initial tenant user",
     *     description="Admin creates the first user for a tenant (stored in tenant's database)",
     *     tags={"Tenant Management"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="tenantId",
     *         in="path",
     *         required=true,
     *         description="Tenant UUID",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "email", "phone"},
     *             @OA\Property(property="name", type="string", example="John Doe"),
     *             @OA\Property(property="email", type="string", format="email", example="john@merchant.com"),
     *             @OA\Property(property="phone", type="string", example="+254712345678"),
     *             @OA\Property(property="send_credentials", type="boolean", example=true, description="Send credentials via email")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Tenant user created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Tenant user created successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="John Doe"),
     *                 @OA\Property(property="email", type="string", example="john@merchant.com"),
     *                 @OA\Property(property="credentials_sent", type="boolean", example=true)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Forbidden - Admin only"),
     *     @OA\Response(response=404, description="Tenant not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function createTenantUser(CreateTenantUserRequest $request, string $tenantId)
    {
        $user = $this->tenantUserService->createTenantUser(
            $tenantId,
            $request->validated()
        );

        return ApiResponse::created(
            'Tenant user created successfully',
            [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'credentials_sent' => $request->input('send_credentials', true),
            ]
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/central/tenants/{tenant_id}/trial-period",
     *     summary="Start trial period for tenant",
     *     description="Initiates a trial period for a specific tenant. The tenant must not have an existing active trial period. This creates a subscription with trial status.",
     *     tags={"Subscription Plans"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="tenant_id",
     *         in="path",
     *         description="The UUID of the tenant to start trial for",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid"),
     *         example="bbab2597-e1ae-466b-a071-83033841d2ed"
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Trial period end date",
     *         @OA\JsonContent(
     *             required={"trial_ends_at"},
     *             @OA\Property(
     *                 property="trial_ends_at",
     *                 type="string",
     *                 format="date",
     *                 description="The date when the trial period should end (YYYY-MM-DD format). Must be a future date.",
     *                 example="2026-01-31"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Trial period started successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Trial period started successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="subscription_id", type="integer", example=1, description="The ID of the created subscription"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed", description="The tenant's UUID"),
     *                 @OA\Property(property="plan", type="string", example="Free", description="The subscription plan name"),
     *                 @OA\Property(property="status", type="string", example="trial", description="The subscription status"),
     *                 @OA\Property(property="is_trial", type="boolean", example=true, description="Indicates if this is a trial subscription"),
     *                 @OA\Property(property="start_date", type="string", format="date", example="2025-12-14", description="The trial start date"),
     *                 @OA\Property(property="trial_ends_at", type="string", format="date", example="2026-01-31", description="The trial end date")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-14T14:56:41.329429Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="cd8684f5-42fa-4436-bcaf-5a4159731908"),
     *                 @OA\Property(property="tenant_id", type="string", nullable=true, example=null),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             ),
     *             example={
     *                 "success": true,
     *                 "message": "Trial period started successfully",
     *                 "data": {
     *                     "subscription_id": 1,
     *                     "tenant_id": "bbab2597-e1ae-466b-a071-83033841d2ed",
     *                     "plan": "Free",
     *                     "status": "trial",
     *                     "is_trial": true,
     *                     "start_date": "2025-12-14",
     *                     "trial_ends_at": "2026-01-31"
     *                 },
     *                 "meta": {
     *                     "timestamp": "2025-12-14T14:56:41.329429Z",
     *                     "request_id": "cd8684f5-42fa-4436-bcaf-5a4159731908",
     *                     "tenant_id": null,
     *                     "tenant_name": null
     *                 }
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad request - Trial period cannot be started",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to start trial period"),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="error",
     *                     type="string",
     *                     example="Tenant already has an active trial period."
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-14T14:57:35.756555Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="02f0df0d-5da8-49d0-8bab-1257294b12b8"),
     *                 @OA\Property(property="tenant_id", type="string", nullable=true, example=null),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             ),
     *             example={
     *                 "success": false,
     *                 "message": "Failed to start trial period",
     *                 "errors": {
     *                     "error": "Tenant already has an active trial period."
     *                 },
     *                 "meta": {
     *                     "timestamp": "2025-12-14T14:57:35.756555Z",
     *                     "request_id": "02f0df0d-5da8-49d0-8bab-1257294b12b8",
     *                     "tenant_id": null,
     *                     "tenant_name": null
     *                 }
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized - Authentication required"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Tenant not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Tenant not found"),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_id", type="string", nullable=true),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true)
     *             )
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
     *                     property="trial_ends_at",
     *                     type="array",
     *                     @OA\Items(type="string", example="The trial ends at field is required.")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_id", type="string", nullable=true),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true)
     *             )
     *         )
     *     )
     * )
     */
    public function startTrialPeriod(string $tenantId, StartTrialPeriodRequest $request): JsonResponse
    {
        try {
            $subscription = $this->tenantService->startTrialPeriod(
                $tenantId,
                $request->validated('trial_ends_at')
            );

            return ApiResponse::success(
                message: 'Trial period started successfully',
                data: [
                    'subscription_id' => $subscription->id,
                    'tenant_id' => $subscription->tenant_id,
                    'plan' => $subscription->plan->name,
                    'status' => $subscription->status,
                    'is_trial' => $subscription->is_trial,
                    'start_date' => $subscription->start_date->toDateString(),
                    'trial_ends_at' => $subscription->trial_ends_at->toDateString(),
                ]
            );
        } catch (\Exception $e) {
            return ApiResponse::error(
                message: 'Failed to start trial period',
                errors: ['error' => $e->getMessage()],
                status: 400
            );
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/central/tenants/{tenant_id}/subscriptions",
     *     summary="Get tenant subscriptions",
     *     description="Retrieves all subscription records for a specific tenant, including active, trial, expired, and cancelled subscriptions. Returns detailed information about each subscription period, payment details, and status.",
     *     tags={"Subscription Plans"},
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Tenant subscriptions retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Tenant subscriptions retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1, description="Subscription record ID"),
     *                     @OA\Property(
     *                         property="subscription",
     *                         type="object",
     *                         description="Subscription plan information",
     *                         @OA\Property(property="plan_id", type="integer", example=1, description="The ID of the subscription plan"),
     *                         @OA\Property(property="plan_name", type="string", example="Free", description="The name of the subscription plan"),
     *                         @OA\Property(property="plan_slug", type="string", example="free", description="The slug of the subscription plan")
     *                     ),
     *                     @OA\Property(
     *                         property="period",
     *                         type="object",
     *                         description="Subscription period details",
     *                         @OA\Property(property="start_date", type="string", format="date", example="2025-12-14", description="Subscription start date"),
     *                         @OA\Property(property="end_date", type="string", format="date", nullable=true, example=null, description="Subscription end date (null for lifetime or ongoing subscriptions)"),
     *                         @OA\Property(property="duration_days", type="integer", nullable=true, example=null, description="Duration of subscription in days (null for lifetime)")
     *                     ),
     *                     @OA\Property(
     *                         property="payment",
     *                         type="object",
     *                         description="Payment information",
     *                         @OA\Property(property="amount_paid", type="number", format="float", example=0, description="Amount paid for this subscription"),
     *                         @OA\Property(property="currency", type="string", example="KES", description="Currency code"),
     *                         @OA\Property(property="payment_method", type="string", nullable=true, example=null, description="Payment method used (e.g., mpesa, card, bank_transfer)"),
     *                         @OA\Property(property="payment_reference", type="string", nullable=true, example=null, description="Payment transaction reference"),
     *                         @OA\Property(property="payment_date", type="string", format="date-time", nullable=true, example=null, description="Date when payment was made")
     *                     ),
     *                     @OA\Property(
     *                         property="status",
     *                         type="object",
     *                         description="Subscription status information",
     *                         @OA\Property(property="current_status", type="string", example="trial", description="Current subscription status", enum={"active", "trial", "expired", "cancelled"}),
     *                         @OA\Property(property="is_active", type="boolean", example=false, description="Whether the subscription is currently active"),
     *                         @OA\Property(property="is_expired", type="boolean", example=false, description="Whether the subscription has expired"),
     *                         @OA\Property(property="auto_renew", type="boolean", example=false, description="Whether auto-renewal is enabled")
     *                     ),
     *                     @OA\Property(
     *                         property="trial",
     *                         type="object",
     *                         description="Trial period information",
     *                         @OA\Property(property="is_trial", type="boolean", example=true, description="Whether this is a trial subscription"),
     *                         @OA\Property(property="trial_ends_at", type="string", format="date", nullable=true, example="2026-01-31", description="Trial end date"),
     *                         @OA\Property(property="is_in_trial", type="boolean", example=true, description="Whether currently in trial period"),
     *                         @OA\Property(property="trial_days_remaining", type="integer", nullable=true, example=47, description="Days remaining in trial period")
     *                     ),
     *                     @OA\Property(
     *                         property="cancellation",
     *                         type="object",
     *                         description="Cancellation details",
     *                         @OA\Property(property="cancelled_at", type="string", format="date-time", nullable=true, example=null, description="Date when subscription was cancelled"),
     *                         @OA\Property(property="cancellation_reason", type="string", nullable=true, example=null, description="Reason for cancellation"),
     *                         @OA\Property(property="is_cancelled", type="boolean", example=false, description="Whether the subscription has been cancelled")
     *                     ),
     *                     @OA\Property(
     *                         property="metadata",
     *                         type="object",
     *                         description="Record metadata",
     *                         @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-14T15:24:50.000000Z", description="Record creation timestamp"),
     *                         @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-14T15:24:50.000000Z", description="Record last update timestamp")
     *                     )
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-14T16:08:12.415262Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="50dbb46f-cf80-4aa6-9553-1b4ce72f6da7"),
     *                 @OA\Property(property="tenant_id", type="string", nullable=true, example=null),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             ),
     *             example={
     *                 "success": true,
     *                 "message": "Tenant subscriptions retrieved successfully",
     *                 "data": {
     *                     {
     *                         "id": 1,
     *                         "subscription": {
     *                             "plan_id": 1,
     *                             "plan_name": "Free",
     *                             "plan_slug": "free"
     *                         },
     *                         "period": {
     *                             "start_date": "2025-12-14",
     *                             "end_date": null,
     *                             "duration_days": null
     *                         },
     *                         "payment": {
     *                             "amount_paid": 0,
     *                             "currency": "KES",
     *                             "payment_method": null,
     *                             "payment_reference": null,
     *                             "payment_date": null
     *                         },
     *                         "status": {
     *                             "current_status": "trial",
     *                             "is_active": false,
     *                             "is_expired": false,
     *                             "auto_renew": false
     *                         },
     *                         "trial": {
     *                             "is_trial": true,
     *                             "trial_ends_at": "2026-01-31",
     *                             "is_in_trial": true,
     *                             "trial_days_remaining": 47
     *                         },
     *                         "cancellation": {
     *                             "cancelled_at": null,
     *                             "cancellation_reason": null,
     *                             "is_cancelled": false
     *                         },
     *                         "metadata": {
     *                             "created_at": "2025-12-14T15:24:50.000000Z",
     *                             "updated_at": "2025-12-14T15:24:50.000000Z"
     *                         }
     *                     },
     *                     {
     *                         "id": 2,
     *                         "subscription": {
     *                             "plan_id": 2,
     *                             "plan_name": "Basic",
     *                             "plan_slug": "basic"
     *                         },
     *                         "period": {
     *                             "start_date": "2025-11-01",
     *                             "end_date": "2025-12-01",
     *                             "duration_days": 30
     *                         },
     *                         "payment": {
     *                             "amount_paid": 2500,
     *                             "currency": "KES",
     *                             "payment_method": "mpesa",
     *                             "payment_reference": "RK12345678",
     *                             "payment_date": "2025-11-01T10:30:00.000000Z"
     *                         },
     *                         "status": {
     *                             "current_status": "expired",
     *                             "is_active": false,
     *                             "is_expired": true,
     *                             "auto_renew": true
     *                         },
     *                         "trial": {
     *                             "is_trial": false,
     *                             "trial_ends_at": null,
     *                             "is_in_trial": false,
     *                             "trial_days_remaining": null
     *                         },
     *                         "cancellation": {
     *                             "cancelled_at": null,
     *                             "cancellation_reason": null,
     *                             "is_cancelled": false
     *                         },
     *                         "metadata": {
     *                             "created_at": "2025-11-01T10:30:00.000000Z",
     *                             "updated_at": "2025-12-01T10:30:00.000000Z"
     *                         }
     *                     }
     *                 },
     *                 "meta": {
     *                     "timestamp": "2025-12-14T16:08:12.415262Z",
     *                     "request_id": "50dbb46f-cf80-4aa6-9553-1b4ce72f6da7",
     *                     "tenant_id": null,
     *                     "tenant_name": null
     *                 }
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized - Authentication required"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Tenant not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Tenant not found"),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_id", type="string", nullable=true),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true)
     *             )
     *         )
     *     )
     * )
     */
    public function subscriptions(string $tenantId): JsonResponse
    {
        try {
            $subscriptions = $this->tenantService->getTenantSubscriptions($tenantId);

            return ApiResponse::success(
                message: 'Tenant subscriptions retrieved successfully',
                data: BusinessSubscriptionResource::collection($subscriptions)
            );
        } catch (\Exception $e) {
            return ApiResponse::error(
                message: 'Failed to retrieve tenant subscriptions',
                errors: ['error' => $e->getMessage()],
                status: 400
            );
        }
    }
}
