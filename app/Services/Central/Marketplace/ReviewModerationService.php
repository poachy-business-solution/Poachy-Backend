<?php

namespace App\Services\Central\Marketplace;

use App\Enums\Central\ReviewStatus;
use App\Models\MarketplaceCustomer;
use App\Models\MerchantReview;
use App\Models\ProductReview;
use App\Models\ReviewFlag;
use App\Repositories\Central\MerchantReviewRepository;
use App\Repositories\Central\ProductReviewRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReviewModerationService
{
    public function __construct(
        protected ProductReviewRepository $productRepository,
        protected MerchantReviewRepository $merchantRepository,
    ) {}

    public function moderateProductReview(
        ProductReview $review,
        string $action,
        int $moderatorId,
        ?string $rejectionReason = null
    ): ProductReview {
        $this->validateModerationAction($action, $rejectionReason);

        if ($action === 'dismiss_flags') {
            return $this->dismissFlags($review, $moderatorId, alsoApprove: true);
        }

        $status = $this->actionToStatus($action);

        $review->update([
            'status'           => $status,
            'moderated_at'     => now(),
            'moderated_by'     => $moderatorId,
            'rejection_reason' => $action === 'reject' ? $rejectionReason : null,
        ]);

        return $review->refresh();
    }

    public function moderateMerchantReview(
        MerchantReview $review,
        string $action,
        int $moderatorId,
        ?string $rejectionReason = null
    ): MerchantReview {
        $this->validateModerationAction($action, $rejectionReason);

        if ($action === 'dismiss_flags') {
            return $this->dismissFlags($review, $moderatorId, alsoApprove: true);
        }

        $status = $this->actionToStatus($action);

        $review->update([
            'status'           => $status,
            'moderated_at'     => now(),
            'moderated_by'     => $moderatorId,
            'rejection_reason' => $action === 'reject' ? $rejectionReason : null,
        ]);

        return $review->refresh();
    }

    /**
     * Records a customer flag on a review. Does not hide the review immediately.
     */
    public function flagProductReview(ProductReview $review, MarketplaceCustomer $customer, string $reason): ReviewFlag
    {
        if ($review->customer_id === $customer->id) {
            throw new \InvalidArgumentException('You cannot flag your own review.');
        }

        return DB::connection('central')->transaction(function () use ($review, $customer, $reason) {
            return ReviewFlag::firstOrCreate(
                [
                    'customer_id'    => $customer->id,
                    'flaggable_type' => ProductReview::class,
                    'flaggable_id'   => $review->id,
                ],
                ['reason' => $reason]
            );
        });
    }

    /**
     * Records a customer flag on a merchant review. Does not hide the review immediately.
     */
    public function flagMerchantReview(MerchantReview $review, MarketplaceCustomer $customer, string $reason): ReviewFlag
    {
        if ($review->customer_id === $customer->id) {
            throw new \InvalidArgumentException('You cannot flag your own review.');
        }

        return DB::connection('central')->transaction(function () use ($review, $customer, $reason) {
            return ReviewFlag::firstOrCreate(
                [
                    'customer_id'    => $customer->id,
                    'flaggable_type' => MerchantReview::class,
                    'flaggable_id'   => $review->id,
                ],
                ['reason' => $reason]
            );
        });
    }

    public function getPendingProductReviews(array $filters = [])
    {
        return $this->productRepository->findPending($filters);
    }

    public function getPendingMerchantReviews(array $filters = [])
    {
        return $this->merchantRepository->findPending($filters);
    }

    public function getFlaggedProductReviews(array $filters = []): LengthAwarePaginator
    {
        return $this->productRepository->findFlagged($filters);
    }

    public function getFlaggedMerchantReviews(array $filters = []): LengthAwarePaginator
    {
        return $this->merchantRepository->findFlagged($filters);
    }

    /**
     * Dismiss all flags on a review and optionally approve it
     */
    public function dismissFlags(ProductReview|MerchantReview $review, int $moderatorId, bool $alsoApprove = false): ProductReview|MerchantReview
    {
        return DB::connection('central')->transaction(function () use ($review, $moderatorId, $alsoApprove) {
            // Delete all flag records
            $flagCount = $review->flags()->count();
            $review->flags()->delete();

            if ($alsoApprove && $review->status !== ReviewStatus::Approved) {
                $review->update([
                    'status'       => ReviewStatus::Approved,
                    'moderated_at' => now(),
                    'moderated_by' => $moderatorId,
                ]);
            }

            Log::info('Review flags dismissed', [
                'review_id'            => $review->id,
                'review_type'          => get_class($review),
                'flag_count_dismissed' => $flagCount,
                'also_approved'        => $alsoApprove,
            ]);

            return $review->refresh();
        });
    }

    protected function validateModerationAction(string $action, ?string $rejectionReason): void
    {
        if (! in_array($action, ['approve', 'reject', 'flag', 'dismiss_flags'], true)) {
            throw new \InvalidArgumentException("Invalid moderation action: {$action}");
        }

        if ($action === 'reject' && empty($rejectionReason)) {
            throw new \InvalidArgumentException('A rejection reason is required when rejecting a review.');
        }
    }

    protected function actionToStatus(string $action): ReviewStatus
    {
        return match ($action) {
            'approve' => ReviewStatus::Approved,
            'reject'  => ReviewStatus::Rejected,
            'flag'    => ReviewStatus::Flagged,
        };
    }
}
