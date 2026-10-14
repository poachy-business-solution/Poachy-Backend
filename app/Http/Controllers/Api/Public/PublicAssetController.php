<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Support\TenantPublicAssets;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Serves a shop's public images (product photos, logo) from the central host. */
class PublicAssetController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/public/assets/{tenant}/{path}",
     *     summary="A shop's public image (product photo, logo)",
     *     operationId="publicAssetShow",
     *     tags={"Public - Shops"},
     *
     *     @OA\Parameter(name="tenant", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *     @OA\Parameter(name="path", in="path", required=true, @OA\Schema(type="string", example="products/images/primary_product_1.png")),
     *
     *     @OA\Response(response=200, description="The image, cacheable for a day"),
     *     @OA\Response(response=404, description="No such shop or file, or not an image")
     * )
     */
    public function show(string $tenant, string $path): BinaryFileResponse
    {
        $path = TenantPublicAssets::normalize($path);

        abort_unless(TenantPublicAssets::isSafeImagePath($path), 404);

        $shop = Tenant::find($tenant);
        abort_unless($shop, 404);

        $file = $shop->run(fn () => Storage::disk('public')->exists($path) ? Storage::disk('public')->path($path) : null);
        abort_unless($file && is_file($file), 404);

        return response()->file($file, ['Cache-Control' => 'public, max-age=86400']);
    }
}
