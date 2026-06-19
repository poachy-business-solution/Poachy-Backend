<?php

namespace App\Services\Central\Marketplace;

use App\Enums\Central\OrderStatus;
use App\Enums\Central\ReviewStatus;
use App\Models\MarketplaceCustomer;
use App\Models\MarketplaceOrder;
use App\Models\ProductReview;
use App\Repositories\Central\ProductReviewRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;

class ProductReviewService
{
    public function __construct(
        protected ProductReviewRepository $repository,
        protected ReviewContentChecker $contentChecker,
    ) {}

    public function storeReview(MarketplaceCustomer $customer, int $productId, array $data, array $imageFiles = []): ProductReview
    {
        $this->enforceRateLimit($customer->id);

        $orderId = $data['order_id'] ?? null;
        $isVerifiedPurchase = false;

        if ($orderId) {
            $order = $this->resolveAndValidateOrder($customer, $orderId, $productId);
            $isVerifiedPurchase = true;
        }

        if ($this->repository->existsForCustomerProductOrder($customer->id, $productId, $orderId)) {
            throw new \InvalidArgumentException(
                'You have already submitted a review for this product on this order.'
            );
        }

        $reviewText = $data['review_text'];
        $initialStatus = $this->contentChecker->determineInitialStatus($reviewText);

        $imagePaths = $this->uploadImages($imageFiles, $productId);

        return DB::connection('central')->transaction(function () use ($customer, $productId, $orderId, $data, $isVerifiedPurchase, $initialStatus, $imagePaths) {
            return ProductReview::create([
                'marketplace_product_id' => $productId,
                'customer_id'            => $customer->id,
                'order_id'               => $orderId,
                'rating'                 => $data['rating'],
                'title'                  => $data['title'] ?? null,
                'review_text'            => $data['review_text'],
                'review_images'          => $imagePaths ?: null,
                'is_verified_purchase'   => $isVerifiedPurchase,
                'status'                 => $initialStatus,
            ]);
        });
    }

    public function getApprovedReviewsForProduct(int $productId, array $filters = []): LengthAwarePaginator
    {
        return $this->repository->findApprovedByProduct($productId, $filters);
    }

    public function addMerchantResponse(ProductReview $review, string $tenantId, string $responseText): ProductReview
    {
        if ($review->product->tenant_id !== $tenantId) {
            throw new \InvalidArgumentException('You are not authorised to respond to this review.');
        }

        if (! $review->canReceiveMerchantResponse()) {
            throw new \InvalidArgumentException(
                'This review is not in a state that allows a merchant response.'
            );
        }

        $status = $this->contentChecker->determineInitialStatus($responseText);
        if ($status === ReviewStatus::Flagged) {
            throw new \InvalidArgumentException(
                'Your response contains content that is not allowed (e.g., URLs or excessive caps).'
            );
        }

        $review->update([
            'merchant_response'     => $responseText,
            'merchant_responded_at' => now(),
        ]);

        return $review->refresh();
    }

    public function updateMerchantResponse(ProductReview $review, string $tenantId, string $responseText): ProductReview
    {
        if ($review->product->tenant_id !== $tenantId) {
            throw new \InvalidArgumentException('You are not authorised to update this response.');
        }

        if (is_null($review->merchant_response)) {
            throw new \InvalidArgumentException('No existing merchant response to update.');
        }

        if (! $review->isMerchantResponseEditable()) {
            throw new \InvalidArgumentException(
                'Merchant responses can only be edited within 24 hours of posting.'
            );
        }

        $status = $this->contentChecker->determineInitialStatus($responseText);
        if ($status === ReviewStatus::Flagged) {
            throw new \InvalidArgumentException(
                'Your response contains content that is not allowed (e.g., URLs or excessive caps).'
            );
        }

        $review->update(['merchant_response' => $responseText]);

        return $review->refresh();
    }

    /**
     * Enforce a rate limit of 5 product reviews per customer per hour.
     */
    protected function enforceRateLimit(int $customerId): void
    {
        $key = "review:product:{$customerId}";

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);

            throw new \RuntimeException(
                "You have submitted too many reviews. Please try again in {$seconds} seconds.",
                429
            );
        }

        RateLimiter::hit($key, 3600);
    }

    /**
     * Validates that the order is eligible for a verified product review.
     */
    protected function resolveAndValidateOrder(MarketplaceCustomer $customer, int $orderId, int $productId): MarketplaceOrder
    {
        $order = MarketplaceOrder::on('central')
            ->where('id', $orderId)
            ->where('customer_id', $customer->id)
            ->with('items:id,order_id,marketplace_product_id')
            ->first();

        if (! $order) {
            throw new \InvalidArgumentException('The specified order does not belong to your account.');
        }

        if ($order->order_status !== OrderStatus::Completed) {
            throw new \InvalidArgumentException(
                'Reviews can only be submitted for completed orders.'
            );
        }

        $hasProduct = $order->items->contains('marketplace_product_id', $productId);
        if (! $hasProduct) {
            throw new \InvalidArgumentException('This product was not part of the specified order.');
        }

        if ($order->updated_at->diffInDays(now()) > 30) {
            throw new \RuntimeException(
                'The 30-day review window for this order has expired.',
                422
            );
        }

        return $order;
    }

    /**
     * Stores uploaded review images and returns their paths.
     *
     * @param  array<UploadedFile>  $files
     * @return array<string>
     */
    protected function uploadImages(array $files, int $productId): array
    {
        $paths = [];

        foreach ($files as $file) {
            $path = $file->store("reviews/products/{$productId}", 'public');
            if ($path) {
                $paths[] = $path;
            }
        }

        return $paths;
    }
}
