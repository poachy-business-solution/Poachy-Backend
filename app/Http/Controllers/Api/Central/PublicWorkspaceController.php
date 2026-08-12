<?php

namespace App\Http\Controllers\Api\Central;

use App\Http\Controllers\Controller;
use App\Http\Resources\Central\PublicWorkspaceResource;
use App\Http\Responses\ApiResponse;
use App\Services\Central\Admin\Tenant\TenantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicWorkspaceController extends Controller
{
    public function __construct(
        private readonly TenantService $tenantService
    ) {}

    /**
     * @OA\Get(
     *     path="/api/v1/central/workspaces/lookup",
     *     summary="Find workspace",
     *     description="Resolve a tenant workspace before login by exact tenant id, domain, subdomain slug, or business email.",
     *     operationId="lookupWorkspace",
     *     tags={"Central - Public Workspace"},
     *
     *     @OA\Parameter(
     *         name="q",
     *         in="query",
     *         description="Tenant id, full domain, subdomain slug, or business email",
     *         required=true,
     *
     *         @OA\Schema(type="string", minLength=2, maxLength=255),
     *         example="demo"
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Workspace found",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Workspace found successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="workspace", type="object",
     *                     @OA\Property(property="tenant_id", type="string", example="demo"),
     *                     @OA\Property(property="name", type="string", example="Demo Store Ltd"),
     *                     @OA\Property(property="domain", type="string", nullable=true, example="demo.poachy.test"),
     *                     @OA\Property(property="login_url", type="string", format="url", nullable=true, example="https://demo.poachy.test/login"),
     *                     @OA\Property(property="business", type="object",
     *                         @OA\Property(property="status", type="string", nullable=true, example="active"),
     *                         @OA\Property(property="city", type="string", nullable=true, example="Nairobi"),
     *                         @OA\Property(property="county", type="string", nullable=true, example="Nairobi"),
     *                         @OA\Property(property="is_verified", type="boolean", example=true),
     *                         @OA\Property(property="logo_url", type="string", format="url", nullable=true, example="https://api.poachy.test/storage/business/logos/demo.png")
     *                     )
     *                 )
     *             ),
     *             @OA\Property(property="meta", type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-08-12T10:20:30.000000Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="1ad5c0a7-0890-4a7d-a614-22a27b2c4782"),
     *                 @OA\Property(property="tenant_id", type="string", nullable=true, example=null),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Workspace not found",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Workspace not found.")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(property="errors", type="object",
     *                 @OA\Property(property="q", type="array", @OA\Items(type="string", example="The q field is required."))
     *             )
     *         )
     *     )
     * )
     */
    public function lookup(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:255'],
        ]);

        $tenant = $this->tenantService->findPublicWorkspace($validated['q']);

        if (! $tenant) {
            return ApiResponse::notFound('Workspace not found.');
        }

        return ApiResponse::success(
            'Workspace found successfully',
            ['workspace' => new PublicWorkspaceResource($tenant)]
        );
    }
}
