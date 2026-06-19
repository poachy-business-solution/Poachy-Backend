<?php

namespace App\Observers\Central;

use App\Enums\Central\ReviewStatus;
use App\Jobs\Central\UpdateTenantAggregateRatingJob;
use App\Models\MerchantReview;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class MerchantReviewObserver
{
    public function created(MerchantReview $review): void
    {
        $this->clearCache($review);

        if ($review->status === ReviewStatus::Approved) {
            $this->dispatchAggregateJob($review);
        }

        Log::info('MerchantReview created', [
            'review_id' => $review->id,
            'tenant_id' => $review->tenant_id,
            'status'    => $review->status->value,
        ]);
    }

    public function updated(MerchantReview $review): void
    {
        $this->clearCache($review);

        if ($review->wasChanged('status')) {
            $previousStatus = $review->getOriginal('status');
            $wasApproved = $previousStatus === ReviewStatus::Approved->value;
            $isNowApproved = $review->status === ReviewStatus::Approved;

            if ($wasApproved || $isNowApproved) {
                $this->dispatchAggregateJob($review);
            }
        }

        Log::info('MerchantReview updated', [
            'review_id' => $review->id,
            'tenant_id' => $review->tenant_id,
            'changes'   => $review->getChanges(),
        ]);
    }

    public function deleted(MerchantReview $review): void
    {
        $this->clearCache($review);

        // Only recalculate aggregate if the deleted review was previously approved
        if ($review->status === ReviewStatus::Approved) {
            $this->dispatchAggregateJob($review);
        }

        Log::info('MerchantReview soft-deleted', [
            'review_id'    => $review->id,
            'tenant_id'    => $review->tenant_id,
            'was_approved' => $review->status === ReviewStatus::Approved,
        ]);
    }

    public function restored(MerchantReview $review): void
    {
        $this->clearCache($review);

        if ($review->status === ReviewStatus::Approved) {
            $this->dispatchAggregateJob($review);
        }

        Log::info('MerchantReview restored', [
            'review_id' => $review->id,
            'tenant_id' => $review->tenant_id,
        ]);
    }

    protected function dispatchAggregateJob(MerchantReview $review): void
    {
        UpdateTenantAggregateRatingJob::dispatch($review->tenant_id)
            ->onQueue('sync-normal')
            ->afterCommit();
    }

    protected function clearCache(MerchantReview $review): void
    {
        try {
            Cache::tags(['central', 'reviews', "tenant-{$review->tenant_id}"])->flush();
        } catch (\Exception $e) {
            Log::error('MerchantReviewObserver: failed to clear cache', [
                'review_id' => $review->id,
                'error'     => $e->getMessage(),
            ]);
        }
    }
}
