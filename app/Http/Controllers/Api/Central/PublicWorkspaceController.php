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
     *     @OA\Response(response=200, description="Workspace found"),
     *     @OA\Response(response=404, description="Workspace not found"),
     *     @OA\Response(response=422, description="Validation error")
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
