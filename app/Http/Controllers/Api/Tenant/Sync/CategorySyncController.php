<?php

namespace App\Http\Controllers\Api\Tenant\Sync;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant\ProductCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategorySyncController extends Controller
{
    /**
     * Return this tenant's active product categories for central to diff against
     * its marketplace category mappings.
     */
    public function index(Request $request): JsonResponse
    {
        if ($request->bearerToken() !== config('services.tenant_api.token')) {
            return ApiResponse::error('Unauthorized', status: 401);
        }

        $categories = ProductCategory::query()
            ->active()
            ->get(['id', 'name', 'slug']);

        return ApiResponse::success('Categories retrieved successfully', $categories);
    }
}
