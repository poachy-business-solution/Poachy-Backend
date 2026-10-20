<?php

namespace App\Services\Central\Marketplace;

use App\Enums\Central\OrderStatus;
use App\Enums\Central\ReviewStatus;
use App\Models\MarketplaceCustomer;
use App\Models\MarketplaceOrder;
use App\Models\MerchantReview;
use App\Repositories\Central\MerchantReviewRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

class MerchantReviewService
{
    public function __construct(
        protected MerchantReviewRepository $repository,
        protected ReviewContentChecker $contentChecker,
    ) {}

    public function storeReview(MarketplaceCustomer $customer, int $orderId, array $data): MerchantReview
    {
        $this->enforceRateLimit($customer->id);

        $order = $this->resolveAndValidateOrder($customer, $orderId);

        if ($this->repository->existsForOrder($orderId)) {
            throw new \InvalidArgumentException(
                'A merchant review has already been submitted for this order.'
            );
        }

        $reviewText = $data['review_text'];
        $initialStatus = $this->contentChecker->determineInitialStatus($reviewText);

        return $this->create($customer, $order->tenant_id, $order->id, null, $data, $initialStatus);
    }

    /** A shop review from a reserve & collect code redeemed at the counter. */
    public function storeReviewForReservation(MarketplaceCustomer $customer, string $code, array $data): MerchantReview
    {
        $this->enforceRateLimit($customer->id);
        $reservation = ProductReviewService::redeemedReservation($customer, $code);

        if (MerchantReview::query()->where('reservation_id', $reservation->id)->exists()) {
            throw new \InvalidArgumentException('A merchant review has already been submitted for this reservation.');
        }

        return $this->create($customer, $reservation->tenant_id, null, $reservation->id, $data, $this->contentChecker->determineInitialStatus($data['review_text']));
    }

    /** @param array<string, mixed> $data */
    private function create(MarketplaceCustomer $customer, string $tenantId, ?int $orderId, ?int $reservationId, array $data, ReviewStatus $initialStatus): MerchantReview
    {
        return DB::connection('central')->transaction(function () use ($customer, $tenantId, $orderId, $reservationId, $data, $initialStatus) {
            return MerchantReview::create([
                'tenant_id' => $tenantId,
                'customer_id' => $customer->id,
                'order_id' => $orderId,
                'reservation_id' => $reservationId,
                'overall_rating' => $data['overall_rating'],
                'product_quality_rating' => $data['product_quality_rating'] ?? null,
                'delivery_rating' => $data['delivery_rating'] ?? null,
                'service_rating' => $data['service_rating'] ?? null,
                'review_text' => $data['review_text'],
                'status' => $initialStatus,
            ]);
        });
    }

    public function getApprovedReviewsForTenant(string $tenantId, array $filters = []): LengthAwarePaginator
    {
        return $this->repository->findApprovedByTenant($tenantId, $filters);
    }

    /**
     * Enforce a rate limit of 5 merchant reviews per customer per hour.
     */
    protected function enforceRateLimit(int $customerId): void
    {
        $key = "review:merchant:{$customerId}";

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);

            throw new \RuntimeException(
                "You have submitted too many reviews. Please try again in {$seconds} seconds.",
                429
            );
        }

        RateLimiter::hit($key, 3600);
    }

    protected function resolveAndValidateOrder(MarketplaceCustomer $customer, int $orderId): MarketplaceOrder
    {
        $order = MarketplaceOrder::on('central')
            ->where('id', $orderId)
            ->where('customer_id', $customer->id)
            ->first();

        if (! $order) {
            throw new \InvalidArgumentException('The specified order does not belong to your account.');
        }

        if ($order->order_status !== OrderStatus::Completed) {
            throw new \InvalidArgumentException(
                'Merchant reviews can only be submitted for completed orders.'
            );
        }

        if ($order->updated_at->diffInDays(now()) > 30) {
            throw new \RuntimeException(
                'The 30-day review window for this order has expired.',
                422
            );
        }

        return $order;
    }
}
