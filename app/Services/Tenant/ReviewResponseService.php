<?php

namespace App\Services\Tenant;

use App\Events\Tenant\MerchantReviewResponseCreated;
use App\Models\Tenant\ProductReview;
use Illuminate\Support\Facades\DB;

class ReviewResponseService
{
    public function createResponse(ProductReview $review, string $responseText): ProductReview
    {
        if (! $review->canReceiveMerchantResponse()) {
            throw new \InvalidArgumentException('This review already has a merchant response.');
        }

        $this->validateResponseText($responseText);

        return DB::transaction(function () use ($review, $responseText) {
            $review->update([
                'merchant_response'     => $responseText,
                'merchant_responded_at' => now(),
                'response_sync_status'  => 'pending',
            ]);

            event(new MerchantReviewResponseCreated($review, 'create'));

            return $review->refresh();
        });
    }

    public function updateResponse(ProductReview $review, string $responseText): ProductReview
    {
        if (! $review->isMerchantResponseEditable()) {
            throw new \InvalidArgumentException('Merchant responses can only be edited within 24 hours.');
        }

        $this->validateResponseText($responseText);

        return DB::transaction(function () use ($review, $responseText) {
            $review->update([
                'merchant_response'    => $responseText,
                'response_sync_status' => 'pending',
            ]);

            event(new MerchantReviewResponseCreated($review, 'update'));

            return $review->refresh();
        });
    }

    protected function validateResponseText(string $text): void
    {
        $length = mb_strlen($text);

        if ($length < 10) {
            throw new \InvalidArgumentException('Response must be at least 10 characters long.');
        }

        if ($length > 1000) {
            throw new \InvalidArgumentException('Response may not exceed 1000 characters.');
        }

        if (preg_match('/https?:\/\/|www\./i', $text)) {
            throw new \InvalidArgumentException('Response may not contain URLs.');
        }

        $upperCount = mb_strlen(preg_replace('/[^A-Z]/', '', $text));
        $letterCount = mb_strlen(preg_replace('/[^A-Za-z]/', '', $text));

        if ($letterCount > 0 && ($upperCount / $letterCount) > 0.5) {
            throw new \InvalidArgumentException('Response contains excessive capital letters.');
        }
    }
}
