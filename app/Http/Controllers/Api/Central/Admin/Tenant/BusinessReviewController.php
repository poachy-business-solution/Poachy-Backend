<?php

namespace App\Http\Controllers\Api\Central\Admin\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Business\ReviewBusinessDetailsRequest;
use App\Http\Resources\Central\Admin\Tenant\BusinessDetailResource;
use App\Http\Responses\ApiResponse;
use App\Services\Tenant\Business\BusinessDetailsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BusinessReviewController extends Controller
{
    public function __construct(
        private readonly BusinessDetailsService $businessDetailsService
    ) {}

    /**
     * @OA\Get(
     *     path="/api/v1/central/business-details/pending",
     *     summary="Get pending business details",
     *     description="Admin retrieves all business details awaiting approval",
     *     tags={"Business Review"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Items per page",
     *         @OA\Schema(type="integer", default=15, maximum=100)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Pending business details retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Pending business details retrieved successfully")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Forbidden - Admin only")
     * )
     */
    public function pending(Request $request): JsonResponse
    {
        $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = min((int) $request->get('per_page', 15), 100);
        $businessDetails = $this->businessDetailsService->getPending($perPage);

        return ApiResponse::paginated(
            BusinessDetailResource::collection($businessDetails),
            'Pending business details retrieved successfully'
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/central/business-details",
     *     summary="Get all business details",
     *     description="Admin retrieves all business details with optional filters",
     *     tags={"Business Review"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter by status",
     *         @OA\Schema(type="string", enum={"pending", "active", "inactive", "suspended"})
     *     ),
     *     @OA\Parameter(
     *         name="business_type_id",
     *         in="query",
     *         description="Filter by business type",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="is_verified",
     *         in="query",
     *         description="Filter by verification status",
     *         @OA\Schema(type="boolean")
     *     ),
     *     @OA\Parameter(
     *         name="city",
     *         in="query",
     *         description="Filter by city",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Items per page",
     *         @OA\Schema(type="integer", default=15, maximum=100)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Business details retrieved successfully"
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Forbidden - Admin only")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status' => ['sometimes', 'in:pending,active,inactive,suspended'],
            'business_type_id' => ['sometimes', 'integer', 'exists:central.business_types,id'],
            'is_verified' => ['sometimes', 'boolean'],
            'city' => ['sometimes', 'string'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = min((int) $request->get('per_page', 15), 100);
        $filters = $request->only(['status', 'business_type_id', 'is_verified', 'city']);

        $businessDetails = $this->businessDetailsService->getAllBusinessDetails($filters, $perPage);

        return ApiResponse::paginated(
            BusinessDetailResource::collection($businessDetails),
            'Business details retrieved successfully'
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/central/business-details/{id}/approve",
     *     summary="Approve business details",
     *     description="Admin approves a business details submission",
     *     tags={"Business Review"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Business detail ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="notes", type="string", example="Approved after review")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Business details approved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Business details approved successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/BusinessDetailResource")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Forbidden - Admin only"),
     *     @OA\Response(response=404, description="Business details not found")
     * )
     */
    public function approve(int $id, ReviewBusinessDetailsRequest $request): JsonResponse
    {
        $businessDetail = $this->businessDetailsService->approve(
            $id,
            $request->validated('notes')
        );

        return ApiResponse::success(
            'Business details approved successfully',
            new BusinessDetailResource($businessDetail)
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/central/business-details/{id}/reject",
     *     summary="Reject business details",
     *     description="Admin rejects a business details submission",
     *     tags={"Business Review"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Business detail ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="notes", type="string", example="Missing required documents")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Business details rejected successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Business details rejected successfully")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Forbidden - Admin only"),
     *     @OA\Response(response=404, description="Business details not found")
     * )
     */
    public function reject(int $id, ReviewBusinessDetailsRequest $request): JsonResponse
    {
        $this->businessDetailsService->reject(
            $id,
            $request->validated('notes')
        );

        return ApiResponse::success('Business details rejected successfully');
    }
}
