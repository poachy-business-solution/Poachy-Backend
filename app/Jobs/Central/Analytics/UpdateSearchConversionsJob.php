<?php

namespace App\Jobs\Central\Analytics;

use App\Models\SearchQuery;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Queue\Queueable;

class UpdateSearchConversionsJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $sessionId,
        public Collection $cartItems,
    ) {
        $this->onQueue('sync-low');
    }

    public function handle(): void
    {
        $recentSearches = SearchQuery::on('central')
            ->where('session_id', $this->sessionId)
            ->where('searched_at', '>', now()->subHours(24))
            ->get();

        if ($recentSearches->isEmpty()) {
            return;
        }

        SearchQuery::on('central')
            ->where('session_id', $this->sessionId)
            ->where('searched_at', '>', now()->subHours(24))
            ->update([
                'converted_to_purchase' => true,
            ]);

        SearchQuery::on('central')
            ->where('session_id', $this->sessionId)
            ->where('searched_at', '>', now()->subHours(24))
            ->increment('products_added_to_cart', $this->cartItems->count());
    }
}
