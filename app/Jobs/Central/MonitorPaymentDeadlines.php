<?php

namespace App\Jobs\Central;

use App\Enums\Central\MarketplacePaymentStatus;
use App\Enums\Central\ReservationStatus;
use App\Models\MarketplaceOrder;
use App\Services\Central\Marketplace\MarketplacePaymentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class MonitorPaymentDeadlines implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;

    public int $tries = 3;

    public function handle(MarketplacePaymentService $paymentService): void
    {
        $overdueOrders = MarketplaceOrder::on('central')
            ->where('reservation_status', ReservationStatus::Confirmed)
            ->where('payment_deadline_at', '<', now())
            ->whereDoesntHave('payments', function ($query) {
                $query->where('payment_status', MarketplacePaymentStatus::Completed);
            })
            ->whereNot('order_status', 'cancelled')
            ->get();

        foreach ($overdueOrders as $order) {
            $paymentService->handlePaymentTimeout($order);
        }

        if ($overdueOrders->isNotEmpty()) {
            Log::info('Payment deadline timeouts processed', ['count' => $overdueOrders->count()]);
        }
    }
}
