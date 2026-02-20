<?php

namespace App\Services\Central\Marketplace;

use App\Enums\Central\ReviewStatus;
use App\Enums\Central\ReviewVoteType;
use App\Models\MarketplaceCustomer;
use App\Models\MerchantReview;
use App\Models\ProductReview;
use App\Models\ReviewVote;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ReviewVoteService
{
    public function vote(
        MarketplaceCustomer $customer,
        ReviewVoteType $voteType,
        string $reviewType,
        int $reviewId
    ): ReviewVote {
        $review = $this->resolveReview($reviewType, $reviewId);

        if ($review->status !== ReviewStatus::Approved) {
            throw new \InvalidArgumentException('Votes can only be cast on approved reviews.');
        }

        if ($review->customer_id === $customer->id) {
            throw new \InvalidArgumentException('You cannot vote on your own review.');
        }

        return DB::connection('central')->transaction(function () use ($customer, $voteType, $review) {
            $reviewableType = get_class($review);

            ReviewVote::updateOrCreate(
                [
                    'customer_id'    => $customer->id,
                    'voteable_type'  => $reviewableType,
                    'voteable_id'    => $review->id,
                ],
                ['vote_type' => $voteType->value]
            );

            $this->recalculateVoteCounts($review, $reviewableType);

            return ReviewVote::where([
                'customer_id'   => $customer->id,
                'voteable_type' => $reviewableType,
                'voteable_id'   => $review->id,
            ])->first();
        });
    }

    public function removeVote(MarketplaceCustomer $customer, string $reviewType, int $reviewId): void
    {
        $review = $this->resolveReview($reviewType, $reviewId);
        $reviewableType = get_class($review);

        DB::connection('central')->transaction(function () use ($customer, $review, $reviewableType) {
            ReviewVote::where([
                'customer_id'   => $customer->id,
                'voteable_type' => $reviewableType,
                'voteable_id'   => $review->id,
            ])->delete();

            $this->recalculateVoteCounts($review, $reviewableType);
        });
    }

    protected function resolveReview(string $reviewType, int $reviewId): Model
    {
        return match ($reviewType) {
            'product'  => ProductReview::findOrFail($reviewId),
            'merchant' => MerchantReview::findOrFail($reviewId),
            default    => throw new \InvalidArgumentException("Invalid review type: {$reviewType}"),
        };
    }

    /**
     * Recalculates helpful/not_helpful counts from a fresh DB query to avoid race conditions.
     */
    protected function recalculateVoteCounts(Model $review, string $reviewableType): void
    {
        $counts = DB::connection('central')
            ->table('review_votes')
            ->where('voteable_type', $reviewableType)
            ->where('voteable_id', $review->id)
            ->selectRaw("
                SUM(CASE WHEN vote_type = 'helpful' THEN 1 ELSE 0 END) as helpful_count,
                SUM(CASE WHEN vote_type = 'not_helpful' THEN 1 ELSE 0 END) as not_helpful_count
            ")
            ->first();

        $review->update([
            'helpful_count'     => (int) ($counts->helpful_count ?? 0),
            'not_helpful_count' => (int) ($counts->not_helpful_count ?? 0),
        ]);
    }
}
