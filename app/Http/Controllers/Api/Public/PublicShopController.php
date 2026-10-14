<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Public\MarketListingRequest;
use App\Http\Resources\Public\MarketProductCardResource;
use App\Http\Resources\Public\PublicShopResource;
use App\Http\Responses\ApiResponse;
use App\Models\BusinessDetail;
use App\Models\MarketplaceProduct;
use App\Services\Central\Marketplace\PublicMarketService;
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

    /**
     * @OA\Get(
     *     path="/api/v1/public/shops/{slug}/products",
     *     summary="A verified shop's online products (its page on the web)",
     *     operationId="publicShopProducts",
     *     tags={"Public - Shops"},
     *
     *     @OA\Parameter(name="slug", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Parameter(name="sort", in="query", @OA\Schema(type="string", enum={"newest","price_asc","price_desc","popular"})),
     *     @OA\Parameter(name="in_stock", in="query", @OA\Schema(type="boolean")),
     *     @OA\Parameter(name="page", in="query", @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=200, description="shop{slug,name}, products[] (cards) + pagination"),
     *     @OA\Response(response=404, description="No such shop, or not verified / not active")
     * )
     */
    public function products(string $slug, MarketListingRequest $request, PublicMarketService $market): JsonResponse
    {
        $detail = BusinessDetail::on('central')->listed()->where('slug', $slug)->first(['tenant_id', 'slug', 'business_name']);

        if (! $detail) {
            return ApiResponse::notFound('Shop not found.');
        }

        $page = $market->shopProducts($detail, $request->validated());

        return ApiResponse::success('Shop products', [
            'shop' => ['slug' => $detail->slug, 'name' => $detail->business_name],
            'products' => MarketProductCardResource::collection($page->items()),
            'pagination' => ['page' => $page->currentPage(), 'per_page' => $page->perPage(), 'total' => $page->total(), 'last_page' => $page->lastPage()],
        ]);
    }
}
