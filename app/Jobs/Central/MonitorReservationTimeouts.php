<?php

namespace App\Jobs\Central;

use App\Models\MarketplaceOrder;
use App\Services\Central\Marketplace\MarketplaceOrderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class MonitorReservationTimeouts implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;

    public int $tries = 3;

    public function handle(MarketplaceOrderService $orderService): void
    {
        $expiredOrders = MarketplaceOrder::on('central')
            ->expiredReservations()
            ->get();

        foreach ($expiredOrders as $order) {
            $orderService->handleReservationExpiry($order);
        }

        if ($expiredOrders->isNotEmpty()) {
            Log::info('Expired reservations processed', ['count' => $expiredOrders->count()]);
        }
    }
}
