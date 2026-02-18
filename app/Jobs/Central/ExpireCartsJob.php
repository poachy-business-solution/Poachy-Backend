<?php

namespace App\Jobs\Central;

use App\Enums\Central\CartStatus;
use App\Models\ShoppingCart;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ExpireCartsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;

    public int $tries = 3;

    private const ABANDONED_DAYS = 7;

    public function handle(): void
    {
        $count = ShoppingCart::on('central')
            ->where('status', CartStatus::Abandoned)
            ->where('updated_at', '<', now()->subDays(self::ABANDONED_DAYS))
            ->update(['status' => CartStatus::Expired]);

        if ($count > 0) {
            Log::info('Abandoned carts expired', ['count' => $count]);
        }
    }
}
