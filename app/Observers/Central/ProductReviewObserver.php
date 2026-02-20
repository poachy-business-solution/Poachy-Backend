<?php

namespace App\Observers\Central;

use App\Enums\Central\ReviewStatus;
use App\Events\Central\ProductReviewApproved;
use App\Jobs\Central\UpdateProductAggregateRatingJob;
use App\Models\ProductReview;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ProductReviewObserver
{
    public function created(ProductReview $review): void
    {
        $this->clearCache($review);

        if ($review->status === ReviewStatus::Approved) {
            $this->dispatchAggregateJob($review);
        }

        Log::info('ProductReview created', [
            'review_id'              => $review->id,
            'marketplace_product_id' => $review->marketplace_product_id,
            'status'                 => $review->status->value,
        ]);
    }

    public function updated(ProductReview $review): void
    {
        $this->clearCache($review);

        if ($review->wasChanged('status')) {
            $previousStatus = $review->getOriginal('status');
            $wasApproved = $previousStatus === ReviewStatus::Approved->value;
            $isNowApproved = $review->status === ReviewStatus::Approved;

            if ($wasApproved || $isNowApproved) {
                $this->dispatchAggregateJob($review);
            }

            // Dispatch event when review is newly approved (sync to tenant)
            if (! $wasApproved && $isNowApproved) {
                ProductReviewApproved::dispatch($review);
            }
        }

        Log::info('ProductReview updated', [
            'review_id'              => $review->id,
            'marketplace_product_id' => $review->marketplace_product_id,
            'changes'                => $review->getChanges(),
        ]);
    }

    public function deleted(ProductReview $review): void
    {
        $this->clearCache($review);

        // Only recalculate aggregate if the deleted review was previously approved
        if ($review->status === ReviewStatus::Approved) {
            $this->dispatchAggregateJob($review);
        }

        Log::info('ProductReview soft-deleted', [
            'review_id'              => $review->id,
            'marketplace_product_id' => $review->marketplace_product_id,
            'was_approved'           => $review->status === ReviewStatus::Approved,
        ]);
    }

    public function restored(ProductReview $review): void
    {
        $this->clearCache($review);

        if ($review->status === ReviewStatus::Approved) {
            $this->dispatchAggregateJob($review);
        }

        Log::info('ProductReview restored', [
            'review_id'              => $review->id,
            'marketplace_product_id' => $review->marketplace_product_id,
        ]);
    }

    protected function dispatchAggregateJob(ProductReview $review): void
    {
        UpdateProductAggregateRatingJob::dispatch($review->marketplace_product_id)
            ->onQueue('sync-normal')
            ->afterCommit();
    }

    protected function clearCache(ProductReview $review): void
    {
        try {
            Cache::tags(['central', 'reviews', "product-{$review->marketplace_product_id}"])->flush();
        } catch (\Exception $e) {
            Log::error('ProductReviewObserver: failed to clear cache', [
                'review_id' => $review->id,
                'error'     => $e->getMessage(),
            ]);
        }
    }
}
