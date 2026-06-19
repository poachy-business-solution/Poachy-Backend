<?php

namespace App\Jobs\Central\Analytics;

use App\Models\ProductPageView;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class UpdateProductViewConversionJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $sessionId,
        public int $productId,
        public string $action, // 'added_to_cart' | 'added_to_wishlist'
    ) {
        $this->onQueue('sync-low');
    }

    public function handle(): void
    {
        ProductPageView::on('central')
            ->where('session_id', $this->sessionId)
            ->where('marketplace_product_id', $this->productId)
            ->orderByDesc('viewed_at')
            ->first()
            ?->update([$this->action => true]);
    }
}
