<?php

namespace App\Events\Central;

use App\Models\ProductReview;
use Illuminate\Foundation\Events\Dispatchable;

class ProductReviewApproved
{
    use Dispatchable;

    public function __construct(public readonly ProductReview $review) {}
}
