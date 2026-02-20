<?php

namespace App\Repositories\Central;

use App\Enums\Central\ReviewStatus;
use App\Models\ProductReview;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ProductReviewRepository
{
    public function findApprovedByProduct(int $productId, array $filters = []): LengthAwarePaginator
    {
        $query = ProductReview::query()
            ->approved()
            ->byProduct($productId)
            ->with(['customer.user:id,name']);

        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortDir = match ($sortBy) {
            'rating'       => 'desc',
            'helpful_count' => 'desc',
            default        => 'desc',
        };

        if (in_array($sortBy, ['rating', 'helpful_count', 'created_at'], true)) {
            $query->orderBy($sortBy, $sortDir);
        } else {
            $query->latest();
        }

        $perPage = min((int) ($filters['per_page'] ?? 15), 50);

        return $query->paginate($perPage);
    }

    public function existsForCustomerProductOrder(int $customerId, int $productId, ?int $orderId): bool
    {
        return ProductReview::query()
            ->where('customer_id', $customerId)
            ->where('marketplace_product_id', $productId)
            ->where('order_id', $orderId)
            ->exists();
    }

    public function findById(int $id): ?ProductReview
    {
        return ProductReview::find($id);
    }

    public function findApprovedById(int $id): ?ProductReview
    {
        return ProductReview::approved()->find($id);
    }

    public function findOwnedByCustomer(int $reviewId, int $customerId): ?ProductReview
    {
        return ProductReview::where('id', $reviewId)
            ->where('customer_id', $customerId)
            ->first();
    }

    /**
     * @return LengthAwarePaginator<ProductReview>
     */
    public function findPending(array $filters = []): LengthAwarePaginator
    {
        $perPage = min((int) ($filters['per_page'] ?? 20), 50);

        return ProductReview::query()
            ->whereIn('status', [ReviewStatus::Pending, ReviewStatus::Flagged])
            ->with(['customer.user:id,name', 'product:id,name,slug'])
            ->oldest()
            ->paginate($perPage);
    }

    /**
     * Get reviews that have customer flags (ReviewFlag records)
     * Ordered by flag count descending (most flagged first)
     *
     * @return LengthAwarePaginator<ProductReview>
     */
    public function findFlagged(array $filters = []): LengthAwarePaginator
    {
        $perPage = min((int) ($filters['per_page'] ?? 20), 50);

        return ProductReview::query()
            ->has('flags') // Only reviews with flag records
            ->withCount('flags') // Add flags_count attribute
            ->with([
                'flags.customer.user:id,name',
                'customer.user:id,name',
                'product:id,name,slug',
            ])
            ->orderByDesc('flags_count')
            ->oldest()
            ->paginate($perPage);
    }
}
