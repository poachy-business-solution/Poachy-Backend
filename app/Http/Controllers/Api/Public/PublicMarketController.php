<?php

namespace App\Http\Controllers\Api\Public;

use App\Helpers\BusinessHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Public\MarketListingRequest;
use App\Http\Resources\Public\MarketOfferResource;
use App\Http\Resources\Public\MarketProductCardResource;
use App\Http\Resources\Public\MarketShopCardResource;
use App\Http\Responses\ApiResponse;
use App\Models\BusinessDetail;
use App\Models\MarketplaceCategory;
use App\Services\Central\Marketplace\AdvertService;
use App\Services\Central\Marketplace\PublicMarketService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/** The public marketplace read API behind poachy.com/market. */
class PublicMarketController extends Controller
{
    public function __construct(private readonly PublicMarketService $market) {}

    /**
     * @OA\Get(
     *     path="/api/v1/public/market/home",
     *     summary="Marketplace home: categories, featured shops, latest listings",
     *     operationId="publicMarketHome",
     *     tags={"Public - Market"},
     *
     *     @OA\Response(response=200, description="categories[] (slug, name, icon, products_count), featured_shops[], latest[] (product cards), sponsored[] (empty until adverts)")
     * )
     */
    public function home(): JsonResponse
    {
        $home = $this->market->home();
        BusinessHelper::warmCache($home['latest']->pluck('tenant_id')->unique()->all());

        return ApiResponse::success('Marketplace home', [
            'categories' => $home['categories']->map(fn (MarketplaceCategory $c) => [
                'slug' => $c->slug, 'name' => $c->name, 'icon' => $c->icon, 'products_count' => (int) $c->products_count,
            ])->values(),
            'featured_shops' => MarketShopCardResource::collection($home['featured_shops']),
            'latest' => MarketProductCardResource::collection($home['latest']),
            'sponsored' => MarketProductCardResource::collection($home['sponsored']),
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/public/market/categories/{slug}",
     *     summary="A category page: products of verified shops with filters",
     *     operationId="publicMarketCategory",
     *     tags={"Public - Market"},
     *
     *     @OA\Parameter(name="slug", in="path", required=true, @OA\Schema(type="string", example="groceries")),
     *     @OA\Parameter(name="min_price", in="query", @OA\Schema(type="number")),
     *     @OA\Parameter(name="max_price", in="query", @OA\Schema(type="number")),
     *     @OA\Parameter(name="in_stock", in="query", @OA\Schema(type="boolean")),
     *     @OA\Parameter(name="county", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="sort", in="query", @OA\Schema(type="string", enum={"newest","price_asc","price_desc","popular"})),
     *     @OA\Parameter(name="page", in="query", @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=200, description="category, children[], products[] + pagination, sponsored[]"),
     *     @OA\Response(response=404, description="No such category")
     * )
     */
    public function category(string $slug, MarketListingRequest $request): JsonResponse
    {
        $category = MarketplaceCategory::on('central')->where('is_active', true)->where('slug', $slug)->first();

        if (! $category) {
            return ApiResponse::notFound('Category not found.');
        }

        $page = $this->market->category($category, $request->validated());
        $sponsored = $this->market->sponsoredFor($category, null, $request->validated());
        $children = MarketplaceCategory::on('central')->where('is_active', true)->where('parent_id', $category->id)->orderBy('display_order')->get(['slug', 'name']);

        return $this->listing('Category', $page, $sponsored, [
            'category' => ['slug' => $category->slug, 'name' => $category->name, 'description' => $category->description, 'icon' => $category->icon,
                'parent' => $category->parent_id ? MarketplaceCategory::on('central')->whereKey($category->parent_id)->first(['slug', 'name']) : null],
            'children' => $children,
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/public/market/search",
     *     summary="Search products and shops",
     *     operationId="publicMarketSearch",
     *     tags={"Public - Market"},
     *
     *     @OA\Parameter(name="q", in="query", required=true, @OA\Schema(type="string", example="mango")),
     *     @OA\Parameter(name="sort", in="query", @OA\Schema(type="string", enum={"newest","price_asc","price_desc","popular"})),
     *     @OA\Parameter(name="page", in="query", @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=200, description="query, products[] + pagination, shops[], sponsored[] (max 2 per page)"),
     *     @OA\Response(response=422, description="Query shorter than 2 characters")
     * )
     */
    public function search(MarketListingRequest $request): JsonResponse
    {
        $request->validate(['q' => ['required', 'string', 'min:2', 'max:100']]);
        $term = (string) $request->validated('q');
        $page = $this->market->search($term, $request->validated());
        $sponsored = $this->market->sponsoredFor(null, $term, $request->validated());

        return $this->listing('Search results', $page, $sponsored, [
            'query' => $term,
            'shops' => MarketShopCardResource::collection($this->market->shopsMatching($term)),
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/public/market/products/{slug}",
     *     summary="A product page with every verified shop's offer of the same item",
     *     operationId="publicMarketProduct",
     *     tags={"Public - Market"},
     *
     *     @OA\Parameter(name="slug", in="path", required=true, @OA\Schema(type="string", example="mango-juice-1l")),
     *
     *     @OA\Response(response=200, description="name, description, slug, item_slug, barcode, category, brand, images[], from_price, offers[] (price, stock, delivery, shop{slug,name,area,rating,is_verified,whatsapp_link,phone,return_policy,operating_hours}), related[]"),
     *     @OA\Response(response=404, description="No such product, or its shop is not listed")
     * )
     */
    public function product(string $slug): JsonResponse
    {
        $page = $this->market->product($slug);

        if (! $page) {
            return ApiResponse::notFound('Product not found.');
        }

        $product = $page['product'];
        $offers = $page['offers'];
        $tenantIds = $offers->pluck('tenant_id')->unique()->values()->all();
        $shops = BusinessDetail::on('central')->listed()->whereIn('tenant_id', $tenantIds)->get()->keyBy('tenant_id')->all();
        $delivering = $this->market->deliveringTenants($tenantIds);
        $images = $offers->flatMap(fn ($o) => [$o->primary_image, ...($o->secondary_images ?? [])])->filter()->unique()->values();
        BusinessHelper::warmCache($page['related']->pluck('tenant_id')->unique()->all());

        return ApiResponse::success('Product', [
            'slug' => $product->slug,
            'item_slug' => $page['item']?->slug,
            'name' => $page['name'],
            'description' => $page['description'],
            'barcode' => $page['item']?->barcode ?? $product->barcode,
            'category' => $product->marketplaceCategory ? ['slug' => $product->marketplaceCategory->slug, 'name' => $product->marketplaceCategory->name] : null,
            'brand' => $product->marketplaceBrand?->name ?? $product->tenant_brand_name,
            'uom' => $product->base_uom_name,
            'images' => $images,
            'from_price' => (float) $offers->min('online_price'),
            'offers_count' => $offers->count(),
            'offers' => $offers->map(fn ($o) => new MarketOfferResource($o, $shops, $delivering))->values(),
            'related' => MarketProductCardResource::collection($page['related']),
        ]);
    }

    /** @param array<string, mixed> $extra */
    /**
     * @OA\Post(
     *     path="/api/v1/public/market/adverts/{id}/click",
     *     summary="Count a click on a Sponsored slot",
     *     operationId="publicMarketAdvertClick",
     *     tags={"Public - Marketplace"},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=204, description="Counted (or ignored for an advert that is not running)")
     * )
     */
    public function advertClick(int $id): Response
    {
        app(AdvertService::class)->recordClick($id);

        return response()->noContent();
    }

    private function listing(string $message, LengthAwarePaginator $page, Collection $sponsored, array $extra): JsonResponse
    {
        BusinessHelper::warmCache(collect($page->items())->pluck('tenant_id')->unique()->all());

        return ApiResponse::success($message, $extra + [
            'products' => MarketProductCardResource::collection($page->items()),
            'pagination' => ['page' => $page->currentPage(), 'per_page' => $page->perPage(), 'total' => $page->total(), 'last_page' => $page->lastPage()],
            'sponsored' => MarketProductCardResource::collection($sponsored),
        ]);
    }
}
