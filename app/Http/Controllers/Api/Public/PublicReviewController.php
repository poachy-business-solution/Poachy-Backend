<?php

namespace App\Http\Controllers\Api\Public;

use App\Helpers\BusinessHelper;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\BusinessDetail;
use App\Models\MerchantReview;
use App\Models\ProductReview;
use App\Services\Central\Marketplace\PublicMarketService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Approved, verified-buyer reviews for the product and shop pages. Reviewers show as first name +
 * initial; nothing else about them leaves the API.
 */
class PublicReviewController extends Controller
{
    private const PER_PAGE = 10;

    /**
     * @OA\Get(
     *     path="/api/v1/public/market/products/{slug}/reviews",
     *     summary="Approved reviews of a product (every verified shop's listing of the same item)",
     *     operationId="publicMarketProductReviews",
     *     tags={"Public - Market"},
     *
     *     @OA\Parameter(name="slug", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Parameter(name="page", in="query", @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=200, description="summary{average,count,stars{1..5}}, reviews[] (rating, title, text, name, verified, shop, shop_response, created_at) + pagination"),
     *     @OA\Response(response=404, description="No such product")
     * )
     */
    public function product(string $slug, Request $request, PublicMarketService $market): JsonResponse
    {
        $page = $market->product($slug);
        if (! $page) {
            return ApiResponse::notFound('Product not found.');
        }
        $ids = $page['offers']->pluck('id')->all();
        $query = fn (): Builder => ProductReview::query()->approved()->whereIn('marketplace_product_id', $ids);
        $reviews = $query()->with(['customer.user:id,name', 'product:id,tenant_id'])->latest('id')->paginate(self::PER_PAGE);
        BusinessHelper::warmCache($reviews->getCollection()->pluck('product.tenant_id')->unique()->all());

        return $this->page('Product reviews', $query(), 'rating', $reviews, fn (ProductReview $r) => [
            'rating' => (float) $r->rating,
            'title' => $r->title,
            'text' => $r->review_text,
            'name' => self::displayName($r->customer?->user?->name),
            'verified' => (bool) $r->is_verified_purchase,
            'shop' => BusinessHelper::getBusinessName($r->product?->tenant_id),
            'shop_response' => $r->merchant_response,
            'created_at' => $r->created_at?->toIso8601String(),
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/public/shops/{slug}/reviews",
     *     summary="Approved reviews of a shop",
     *     operationId="publicShopReviews",
     *     tags={"Public - Shops"},
     *
     *     @OA\Parameter(name="slug", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Parameter(name="page", in="query", @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=200, description="summary{average,count,stars{1..5}}, reviews[] (rating, text, name, verified, shop_response, created_at) + pagination"),
     *     @OA\Response(response=404, description="No such listed shop")
     * )
     */
    public function shop(string $slug): JsonResponse
    {
        $tenantId = BusinessDetail::on('central')->listed()->where('slug', $slug)->value('tenant_id');
        if (! $tenantId) {
            return ApiResponse::notFound('Shop not found.');
        }
        $query = fn (): Builder => MerchantReview::query()->approved()->where('tenant_id', $tenantId);
        $reviews = $query()->with('customer.user:id,name')->latest('id')->paginate(self::PER_PAGE);

        return $this->page('Shop reviews', $query(), 'overall_rating', $reviews, fn (MerchantReview $r) => [
            'rating' => (float) $r->overall_rating,
            'text' => $r->review_text,
            'name' => self::displayName($r->customer?->user?->name),
            'verified' => true,
            'shop_response' => $r->merchant_response,
            'created_at' => $r->created_at?->toIso8601String(),
        ]);
    }

    /** "Wanjiru Kamau" → "Wanjiru K." */
    public static function displayName(?string $name): string
    {
        $parts = preg_split('/\s+/', trim((string) $name)) ?: [];
        $first = $parts[0] ?? '';
        if ($first === '') {
            return 'Buyer';
        }
        $last = count($parts) > 1 ? ' '.mb_strtoupper(mb_substr((string) end($parts), 0, 1)).'.' : '';

        return mb_substr($first, 0, 30).$last;
    }

    private function page(string $message, Builder $all, string $column, LengthAwarePaginator $reviews, callable $map): JsonResponse
    {
        $stars = (clone $all)->selectRaw("FLOOR({$column}) as star, COUNT(*) as n")->groupBy('star')->pluck('n', 'star');
        $count = (int) $stars->sum();

        return ApiResponse::success($message, [
            'summary' => [
                'average' => $count ? round((float) (clone $all)->avg($column), 1) : null,
                'count' => $count,
                'stars' => collect(range(5, 1))->mapWithKeys(fn (int $s) => [$s => (int) ($stars[$s] ?? 0)]),
            ],
            'reviews' => $reviews->getCollection()->map($map)->values(),
            'pagination' => ['page' => $reviews->currentPage(), 'per_page' => $reviews->perPage(), 'total' => $reviews->total(), 'last_page' => $reviews->lastPage()],
        ]);
    }
}
