<?php

namespace App\Events\Tenant;

use App\DataTransferObjects\Sync\ReviewResponseSyncDTO;
use App\Models\Tenant\ProductReview;
use Illuminate\Foundation\Events\Dispatchable;

class MerchantReviewResponseCreated
{
    use Dispatchable;

    public readonly ReviewResponseSyncDTO $responseDTO;
    public readonly string $action;
    public readonly int $priority;

    public function __construct(ProductReview $review, string $action = 'create', int $priority = 2)
    {
        $this->responseDTO = ReviewResponseSyncDTO::fromReview($review);
        $this->action = $action;
        $this->priority = $priority;
    }
}
