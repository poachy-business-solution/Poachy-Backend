<?php

namespace App\Jobs\Central;

use App\Models\MarketplaceProduct;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UpdateProductAggregateRatingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [30, 60, 120];

    public function __construct(public readonly int $marketplaceProductId) {}

    public function handle(): void
    {
        $aggregate = DB::connection('central')
            ->table('product_reviews')
            ->where('marketplace_product_id', $this->marketplaceProductId)
            ->where('status', 'approved')
            ->whereNull('deleted_at')
            ->selectRaw('AVG(rating) as average_rating, COUNT(*) as review_count')
            ->first();

        $product = MarketplaceProduct::on('central')->find($this->marketplaceProductId);

        if (! $product) {
            Log::warning('UpdateProductAggregateRatingJob: product not found', [
                'marketplace_product_id' => $this->marketplaceProductId,
            ]);

            return;
        }

        $product->update([
            'average_rating' => $aggregate->review_count > 0
                ? round((float) $aggregate->average_rating, 2)
                : null,
            'rating_count' => (int) $aggregate->review_count,
        ]);

        Log::info('UpdateProductAggregateRatingJob: updated product rating aggregate', [
            'marketplace_product_id' => $this->marketplaceProductId,
            'average_rating'         => $product->average_rating,
            'rating_count'           => $product->rating_count,
        ]);
    }
}
