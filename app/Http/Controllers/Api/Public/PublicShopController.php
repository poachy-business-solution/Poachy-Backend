<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\Public\PublicShopResource;
use App\Http\Responses\ApiResponse;
use App\Models\BusinessDetail;
use App\Models\MarketplaceProduct;
use Illuminate\Http\JsonResponse;

/** Public shop pages for the marketplace: only verified, active shops are reachable. */
class PublicShopController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/public/shops/{slug}",
     *     summary="A verified shop's public profile",
     *     operationId="publicShopShow",
     *     tags={"Public - Shops"},
     *
     *     @OA\Parameter(name="slug", in="path", required=true, @OA\Schema(type="string", example="mama-njeri-shop")),
     *
     *     @OA\Response(response=200, description="slug, name, description, category, logo_url, banner_url, area{city,county}, operating_hours, location{latitude,longitude}, directions_url, phone, whatsapp_link, return_window_days, return_policy, rating, rating_count, is_verified, online_products, tenant_id"),
     *     @OA\Response(response=404, description="No such shop, or the shop is not verified or not active")
     * )
     */
    public function show(string $slug): JsonResponse
    {
        $detail = BusinessDetail::on('central')
            ->with('businessCategory:id,name')
            ->listed()
            ->where('slug', $slug)
            ->first();

        if (! $detail) {
            return ApiResponse::notFound('Shop not found.');
        }

        $detail->online_products_count = MarketplaceProduct::on('central')->active()->where('tenant_id', $detail->tenant_id)->count();

        return ApiResponse::success('Shop found', new PublicShopResource($detail));
    }
}
