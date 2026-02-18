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

class AbandonCartJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;

    public int $tries = 3;

    private const INACTIVE_MINUTES = 60;

    public function handle(): void
    {
        $count = ShoppingCart::on('central')
            ->where('status', CartStatus::Active)
            ->where('updated_at', '<', now()->subMinutes(self::INACTIVE_MINUTES))
            ->update(['status' => CartStatus::Abandoned]);

        if ($count > 0) {
            Log::info('Carts marked as abandoned', ['count' => $count]);
        }
    }
}
