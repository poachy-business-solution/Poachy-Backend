<?php

namespace App\Services\Central\Marketplace;

use App\Models\BusinessDetail;
use App\Models\MarketplaceCategory;
use App\Models\MarketplaceItem;
use App\Models\MarketplaceProduct;
use App\Models\TenantDeliveryZone;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Read model for the public marketplace (poachy.com/market). Only active listings of
 * verified, active shops ever leave here. Sponsored slots stay empty until adverts (item 42).
 */
class PublicMarketService
{
    public const SORTS = ['newest', 'price_asc', 'price_desc', 'popular'];

    public const MAX_PER_PAGE = 48;

    /** @return array{categories: Collection, featured_shops: Collection, latest: Collection, sponsored: array} */
    public function home(): array
    {
        // Listings sit in sub-categories; the home tiles are the top level, so roll the counts up.
        $counts = $this->listings()->selectRaw('marketplace_category_id, count(*) as c')->groupBy('marketplace_category_id')->pluck('c', 'marketplace_category_id');
        $parents = MarketplaceCategory::on('central')->whereNotNull('parent_id')->pluck('parent_id', 'id');
        $rolled = [];
        foreach ($counts as $categoryId => $count) {
            $top = $parents[$categoryId] ?? $categoryId;
            $rolled[$top] = ($rolled[$top] ?? 0) + (int) $count;
        }

        $categories = MarketplaceCategory::on('central')->where('is_active', true)->whereNull('parent_id')
            ->orderBy('display_order')->orderBy('name')->get()
            ->each(fn (MarketplaceCategory $c) => $c->setAttribute('products_count', (int) ($rolled[$c->id] ?? 0)));

        $tenantIds = $this->listings()->distinct()->pluck('tenant_id');
        $featured = BusinessDetail::on('central')->listed()->whereIn('tenant_id', $tenantIds)
            ->orderByDesc('rating')->orderByDesc('rating_count')->orderBy('business_name')->limit(8)->get();

        $latest = $this->listings()->with('item:id,slug')->latest('id')->limit(12)->get();

        return ['categories' => $categories, 'featured_shops' => $featured, 'latest' => $latest, 'sponsored' => []];
    }

    /** @param array<string, mixed> $filters */
    public function category(MarketplaceCategory $category, array $filters): LengthAwarePaginator
    {
        $ids = MarketplaceCategory::on('central')->where('parent_id', $category->id)->pluck('id')->push($category->id);

        return $this->paginate($this->listings()->whereIn('marketplace_category_id', $ids), $filters);
    }

    /** @param array<string, mixed> $filters */
    public function search(string $term, array $filters): LengthAwarePaginator
    {
        $term = trim($term);
        $query = $this->listings()->where(function (Builder $q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
                ->orWhere('tenant_brand_name', 'like', "%{$term}%")
                ->orWhere('tenant_category_name', 'like', "%{$term}%")
                ->orWhere('barcode', $term);
        });

        return $this->paginate($query, $filters);
    }

    public function shopsMatching(string $term, int $limit = 6): Collection
    {
        $term = trim($term);

        return BusinessDetail::on('central')->listed()
            ->whereIn('tenant_id', $this->listings()->distinct()->pluck('tenant_id'))
            ->where(fn ($q) => $q->where('business_name', 'like', "%{$term}%")->orWhere('city', 'like', "%{$term}%"))
            ->orderByDesc('rating')->limit($limit)->get();
    }

    /**
     * A product page: the listing behind the slug (or the item behind an item slug) plus
     * every listed shop's offer of the same item, cheapest first.
     *
     * @return array{name: string, description: ?string, item: ?MarketplaceItem, product: MarketplaceProduct, offers: Collection, related: Collection}|null
     */
    public function product(string $slug): ?array
    {
        $product = $this->listings()->with('item')->where('slug', $slug)->first();
        $item = $product?->item;

        if (! $product) {
            $item = MarketplaceItem::on('central')->where('slug', $slug)->first();
            $product = $item ? $this->listings()->where('marketplace_item_id', $item->id)->orderBy('online_price')->first() : null;
        }

        if (! $product) {
            return null;
        }

        $offers = $item
            ? $this->listings()->where('marketplace_item_id', $item->id)->orderBy('online_price')->orderBy('id')->get()
            : new Collection([$product]);

        $related = $this->listings()->where('marketplace_category_id', $product->marketplace_category_id)
            ->whereNotIn('id', $offers->pluck('id'))->inRandomOrder()->limit(8)->get();

        return [
            'name' => $item?->name ?? $product->name,
            'description' => $item?->description ?? ($product->online_description ?: $product->description),
            'item' => $item,
            'product' => $product,
            'offers' => $offers,
            'related' => $related,
        ];
    }

    /**
     * One shop's listings for its page (poachy.com/s/{shop}).
     *
     * @param  array<string, mixed>  $filters
     */
    public function shopProducts(BusinessDetail $shop, array $filters): LengthAwarePaginator
    {
        return $this->paginate($this->listings()->where('tenant_id', $shop->tenant_id), $filters);
    }

    /** Shops with at least one delivery zone can deliver; the rest are pickup only. */
    public function deliveringTenants(array $tenantIds): array
    {
        return TenantDeliveryZone::on('central')->whereIn('tenant_id', $tenantIds)->distinct()->pluck('tenant_id')->all();
    }

    private function listings(): Builder
    {
        return MarketplaceProduct::on('central')->active()->fromListedShops();
    }

    /** @param array<string, mixed> $filters */
    private function paginate(Builder $query, array $filters): LengthAwarePaginator
    {
        if (isset($filters['min_price'])) {
            $query->where('online_price', '>=', (float) $filters['min_price']);
        }
        if (isset($filters['max_price'])) {
            $query->where('online_price', '<=', (float) $filters['max_price']);
        }
        if (! empty($filters['in_stock'])) {
            $query->where('stock_status', '!=', 'out_of_stock');
        }
        if (! empty($filters['county'])) {
            $tenantIds = BusinessDetail::on('central')->listed()->where('county', 'like', $filters['county'])->pluck('tenant_id');
            $query->whereIn('tenant_id', $tenantIds);
        }

        match ($filters['sort'] ?? 'newest') {
            'price_asc' => $query->orderBy('online_price')->orderBy('id'),
            'price_desc' => $query->orderByDesc('online_price')->orderBy('id'),
            'popular' => $query->orderByDesc('order_count')->orderByDesc('view_count')->orderBy('id'),
            default => $query->orderByDesc('id'),
        };

        return $query->with('item:id,slug')->paginate(min((int) ($filters['per_page'] ?? 24), self::MAX_PER_PAGE))->withQueryString();
    }
}
