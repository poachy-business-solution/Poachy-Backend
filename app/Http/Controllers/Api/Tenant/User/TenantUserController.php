<?php

namespace App\Http\Controllers\Api\Tenant\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\User\AssignRoleRequest;
use App\Http\Requests\Tenant\User\CreateUserRequest;
use App\Http\Requests\Tenant\User\UpdateUserRequest;
use App\Http\Requests\Tenant\User\UpdateUserStoresRequest;
use App\Http\Resources\Tenant\Auth\TenantUserResource;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant\User;
use App\Services\Central\Admin\Tenant\TenantUserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantUserController extends Controller
{
    public function __construct(
        private readonly TenantUserService $userService
    ) {}

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/users",
     *     summary="Get all tenant users",
     *     description="Get all users in this tenant (owner/manager only)",
     *     tags={"Tenant User Management"},
     *     security={{"sanctum": {}}},
     *
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Items per page",
     *
     *         @OA\Schema(type="integer", default=15, maximum=100)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Users retrieved successfully"
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Forbidden - Owner/Manager only")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = min((int) $request->get('per_page', 15), 100);
        $users = $this->userService->getAllUsers($perPage);

        return ApiResponse::paginated(
            TenantUserResource::collection($users),
            'Users retrieved successfully'
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/users",
     *     summary="Create new user",
     *     description="Create new user with auto-generated password sent via email (owner/manager only)",
     *     tags={"Tenant User Management"},
     *     security={{"sanctum": {}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"name", "email", "role"},
     *
     *             @OA\Property(property="name", type="string", example="Jane Doe"),
     *             @OA\Property(property="email", type="string", format="email", example="jane@merchant.com"),
     *             @OA\Property(property="phone", type="string", example="+254723456789"),
     *             @OA\Property(property="role", type="string", enum={"owner", "manager", "cashier"}, example="cashier"),
     *             @OA\Property(property="is_active", type="boolean", example=true)
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="User created successfully. Credentials sent to email."
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Forbidden - Owner/Manager only"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(CreateUserRequest $request): JsonResponse
    {
        $user = $this->userService->createUser($request->validated());

        return ApiResponse::created(
            'User created successfully. Login credentials have been sent to their email.',
            new TenantUserResource($user)
        );
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/tenant/users/{userId}",
     *     summary="Update user",
     *     description="Update user details (owner/manager only)",
     *     tags={"Tenant User Management"},
     *     security={{"sanctum": {}}},
     *
     *     @OA\Parameter(
     *         name="userId",
     *         in="path",
     *         required=true,
     *         description="User ID",
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\RequestBody(
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="name", type="string", example="Jane Updated"),
     *             @OA\Property(property="email", type="string", example="jane.updated@merchant.com"),
     *             @OA\Property(property="phone", type="string", example="+254723456789"),
     *             @OA\Property(property="is_active", type="boolean", example=false)
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="User updated successfully"
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=404, description="User not found")
     * )
     */
    public function update(int $userId, UpdateUserRequest $request): JsonResponse
    {
        $user = $this->userService->updateUser($userId, $request->validated());

        return ApiResponse::success(
            'User updated successfully',
            new TenantUserResource($user)
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/users/{userId}/assign-role",
     *     summary="Assign role to user",
     *     description="Assign or change user role (owner only)",
     *     tags={"Tenant User Management"},
     *     security={{"sanctum": {}}},
     *
     *     @OA\Parameter(
     *         name="userId",
     *         in="path",
     *         required=true,
     *         description="User ID",
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"role"},
     *
     *             @OA\Property(property="role", type="string", enum={"owner", "manager", "cashier"}, example="manager")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Role assigned successfully"
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Forbidden - Owner only"),
     *     @OA\Response(response=404, description="User not found")
     * )
     */
    public function assignRole(int $userId, AssignRoleRequest $request): JsonResponse
    {
        $user = $this->userService->assignRole($userId, $request->validated('role'));

        return ApiResponse::success(
            'Role assigned successfully',
            new TenantUserResource($user)
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/tenant/users/{userId}",
     *     summary="Delete user",
     *     description="Delete user (owner only, cannot delete yourself or last owner)",
     *     tags={"Tenant User Management"},
     *     security={{"sanctum": {}}},
     *
     *     @OA\Parameter(
     *         name="userId",
     *         in="path",
     *         required=true,
     *         description="User ID",
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="User deleted successfully"
     *     ),
     *     @OA\Response(response=400, description="Cannot delete yourself or last owner"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Forbidden - Owner only"),
     *     @OA\Response(response=404, description="User not found")
     * )
     */
    public function destroy(int $userId, Request $request): JsonResponse
    {
        if (! $request->user()->canManageStaff(User::query()->find($userId))) {
            return ApiResponse::forbidden('Only the owner can remove owners and managers.');
        }

        $this->userService->deleteUser($userId);

        return ApiResponse::success('User deleted successfully');
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/roles",
     *     summary="Get available roles",
     *     description="Get all roles with their permissions",
     *     tags={"Tenant User Management"},
     *     security={{"sanctum": {}}},
     *
     *     @OA\Response(
     *         response=200,
     *         description="Roles retrieved successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Roles retrieved successfully"),
     *             @OA\Property(property="data", type="array",
     *
     *                 @OA\Items(
     *                     type="object",
     *
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="owner"),
     *                     @OA\Property(property="permissions_count", type="integer", example=35),
     *                     @OA\Property(property="permissions", type="array", @OA\Items(type="string"))
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function roles(): JsonResponse
    {
        $roles = $this->userService->getRoles();

        return ApiResponse::success(
            'Roles retrieved successfully',
            $roles
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/users/{userId}/stores",
     *     summary="Branches a staff member works at",
     *     description="assigned: branches explicitly assigned; workable: where they may actually sell, open a till or record money (every branch for owners/managers; the main branch for staff with no assignment).",
     *     operationId="getUserStores",
     *     tags={"Tenant Users"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="userId", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=200, description="Branch assignment"),
     *     @OA\Response(response=404, description="User not found")
     * )
     */
    public function stores(int $userId): JsonResponse
    {
        $user = User::query()->findOrFail($userId);

        return ApiResponse::success('User branches', $this->storesPayload($user));
    }

    /**
     * @OA\Put(
     *     path="/api/v1/tenant/users/{userId}/stores",
     *     summary="Set the branches a staff member works at (owner/manager)",
     *     description="Replaces the assignment. An empty list sends a staff member back to the main branch only. Owners and managers always work at every branch.",
     *     operationId="updateUserStores",
     *     tags={"Tenant Users"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="userId", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(required={"store_ids"}, @OA\Property(property="store_ids", type="array", @OA\Items(type="integer")))),
     *
     *     @OA\Response(response=200, description="Saved"),
     *     @OA\Response(response=422, description="Unknown or closed branch")
     * )
     */
    public function updateStores(int $userId, UpdateUserStoresRequest $request): JsonResponse
    {
        $user = User::query()->findOrFail($userId);
        if (! $request->user()->canManageStaff($user)) {
            return ApiResponse::forbidden('Only the owner can change the branches of owners and managers.');
        }
        $user->stores()->sync($request->validated('store_ids'));

        return ApiResponse::success('Branches saved', $this->storesPayload($user->fresh()));
    }

    /**
     * @return array{user_id: int, assigned_store_ids: array<int, int>, workable_store_ids: array<int, int>, works_at_every_branch: bool}
     */
    protected function storesPayload(User $user): array
    {
        return [
            'user_id' => $user->id,
            'assigned_store_ids' => $user->stores()->orderBy('stores.id')->pluck('stores.id')->map(fn ($id) => (int) $id)->all(),
            'workable_store_ids' => $user->workableStoreIds(),
            'works_at_every_branch' => $user->worksAtEveryBranch(),
        ];
    }
}
