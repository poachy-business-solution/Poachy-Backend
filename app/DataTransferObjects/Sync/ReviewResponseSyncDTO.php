<?php

namespace App\DataTransferObjects\Sync;

use App\Models\Tenant\ProductReview;

class ReviewResponseSyncDTO
{
    public function __construct(
        public readonly string $tenantId,
        public readonly int $centralReviewId,
        public readonly string $responseText,
        public readonly array $metadata,
    ) {}

    public static function fromReview(ProductReview $review): self
    {
        return new self(
            tenantId: tenant()->id,
            centralReviewId: $review->central_review_id,
            responseText: $review->merchant_response,
            metadata: [
                'local_review_id' => $review->id,
                'timestamp'       => now()->toISOString(),
            ],
        );
    }

    public function toArray(): array
    {
        return [
            'tenant_id'     => $this->tenantId,
            'review_id'     => $this->centralReviewId,
            'response_text' => $this->responseText,
            'metadata'      => $this->metadata,
        ];
    }

    public function generateIdempotencyKey(string $action = 'create'): string
    {
        return md5($this->tenantId . 'review_response' . $this->centralReviewId . $action . hash('sha256', $this->responseText));
    }
}
