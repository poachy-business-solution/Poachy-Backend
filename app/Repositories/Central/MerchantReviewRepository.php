<?php

namespace App\Repositories\Central;

use App\Enums\Central\ReviewStatus;
use App\Models\MerchantReview;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class MerchantReviewRepository
{
    public function findApprovedByTenant(string $tenantId, array $filters = []): LengthAwarePaginator
    {
        $query = MerchantReview::query()
            ->approved()
            ->byTenant($tenantId)
            ->with(['customer.user:id,name']);

        $sortBy = $filters['sort_by'] ?? 'created_at';
        if (in_array($sortBy, ['overall_rating', 'helpful_count', 'created_at'], true)) {
            $query->orderBy($sortBy, 'desc');
        } else {
            $query->latest();
        }

        $perPage = min((int) ($filters['per_page'] ?? 15), 50);

        return $query->paginate($perPage);
    }

    public function existsForOrder(int $orderId): bool
    {
        return MerchantReview::where('order_id', $orderId)->exists();
    }

    public function findByOrder(int $orderId): ?MerchantReview
    {
        return MerchantReview::where('order_id', $orderId)->first();
    }

    public function findById(int $id): ?MerchantReview
    {
        return MerchantReview::find($id);
    }

    /**
     * @return LengthAwarePaginator<MerchantReview>
     */
    public function findPending(array $filters = []): LengthAwarePaginator
    {
        $perPage = min((int) ($filters['per_page'] ?? 20), 50);

        return MerchantReview::query()
            ->whereIn('status', [ReviewStatus::Pending, ReviewStatus::Flagged])
            ->with(['customer.user:id,name'])
            ->oldest()
            ->paginate($perPage);
    }

    /**
     * Get reviews that have customer flags (ReviewFlag records)
     * Ordered by flag count descending (most flagged first)
     *
     * @return LengthAwarePaginator<MerchantReview>
     */
    public function findFlagged(array $filters = []): LengthAwarePaginator
    {
        $perPage = min((int) ($filters['per_page'] ?? 20), 50);

        return MerchantReview::query()
            ->has('flags') // Only reviews with flag records
            ->withCount('flags') // Add flags_count attribute
            ->with([
                'flags.customer.user:id,name',
                'customer.user:id,name',
            ])
            ->orderByDesc('flags_count')
            ->oldest()
            ->paginate($perPage);
    }
}
