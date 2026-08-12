<?php

namespace App\Http\Controllers\Api\Tenant\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Catalog\CatalogSyncRequest;
use App\Http\Responses\ApiResponse;
use App\Services\Tenant\Catalog\CatalogDeltaSyncService;
use Illuminate\Http\JsonResponse;

class CatalogSyncController extends Controller
{
    public function __construct(
        private readonly CatalogDeltaSyncService $syncService
    ) {}

    public function __invoke(CatalogSyncRequest $request): JsonResponse
    {
        return ApiResponse::success(
            'Catalog sync retrieved successfully',
            $this->syncService->sync(
                updatedSince: $request->updatedSince(),
                includeDeleted: $request->includeDeleted(),
            )
        );
    }
}
